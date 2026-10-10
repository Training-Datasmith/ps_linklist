<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== realpath(__FILE__)) {
    return;
}

$moduleRoot = dirname(__DIR__);
$workRoot = sys_get_temp_dir() . '/ps-linklist-suite-' . getmypid();
$vendorDir = $workRoot . '/composer-vendor';
$cacheDir = $workRoot . '/phpunit-cache';
$resultsDir = $workRoot . '/results';
$phpunitPhar = $workRoot . '/phpunit-10.5.66.phar';
$gnupgHome = $workRoot . '/gnupg';

const PHP_IMAGE = 'php:8.1.32-cli@sha256:d1ec09a1b2b0fadbdd0f0a29aa919d0a1c4d1d70b0ad879edd95981ebda65587';
const MYSQL_IMAGE = 'mysql:8.0.46@sha256:7dcddc01f13bab2f15cde676d44d01f61fc9f99fe7785e86196dfc07d358ae2b';
const COMPOSER_IMAGE = 'composer:2.10.3@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac';
const PHPUNIT_PHAR_URL = 'https://phar.phpunit.de/phpunit-10.5.66.phar';
const PHPUNIT_SHA256 = '42bcac97bbf9fb1aecf5a7d6a1b37123a11e7e295458978f0e268999f6d9e50f';
const PHPUNIT_GPG_FINGERPRINT = 'D8406D0D82947747293778314AA394086372C20A';

function runProcess(string $command): array
{
    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);

    return [$code, implode("\n", $output)];
}

function docker(array $args): string
{
    [$code, $output] = runProcess('docker ' . implode(' ', array_map('escapeshellarg', $args)));
    if ($code !== 0) {
        [$code, $output] = runProcess('sudo docker ' . implode(' ', array_map('escapeshellarg', $args)));
    }
    if ($code !== 0) {
        throw new RuntimeException("Docker failed:\n$output");
    }

    return $output;
}

function ensureLayout(string ...$dirs): void
{
    foreach ($dirs as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create $dir");
        }
    }
}

function writeComposerProject(string $workRoot): string
{
    $projectDir = $workRoot . '/composer-project';
    ensureLayout($projectDir);
    $composerJson = [
        'name' => 'ps-linklist/test-deps',
        'require' => [
            'doctrine/dbal' => '3.9.3',
            'doctrine/deprecations' => '1.1.4',
            'symfony/yaml' => '6.4.40',
            'symfony/translation' => '6.4.21',
        ],
        'autoload' => [
            'psr-4' => [
                'PrestaShop\\Module\\LinkList\\' => '/module/src/',
            ],
        ],
        'config' => [
            'sort-packages' => true,
        ],
    ];
    file_put_contents($projectDir . '/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    return $projectDir;
}

function fetchPHPUnitPhar(string $phpunitPhar): string
{
    if (!is_file($phpunitPhar)) {
        file_put_contents($phpunitPhar, file_get_contents(PHPUNIT_PHAR_URL));
    }
    $sha = hash_file('sha256', $phpunitPhar);
    if ($sha !== PHPUNIT_SHA256) {
        throw new RuntimeException("PHPUnit PHAR SHA-256 mismatch: got $sha");
    }

    $ascPath = $phpunitPhar . '.asc';
    if (!is_file($ascPath)) {
        file_put_contents($ascPath, file_get_contents(PHPUNIT_PHAR_URL . '.asc'));
    }

    return $ascPath;
}

function verifyPHPUnitPharSignature(string $phpunitPhar, string $ascPath, string $gnupgHome): void
{
    ensureLayout($gnupgHome);
    docker([
        'run', '--rm',
        '-e', 'GNUPGHOME=/gnupg',
        '-v', $gnupgHome . ':/gnupg',
        PHP_IMAGE,
        'bash', '-lc',
        'apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq gnupg ca-certificates >/dev/null && gpg --keyserver hkps://keyserver.ubuntu.com --recv-keys ' . PHPUNIT_GPG_FINGERPRINT,
    ]);
    $verify = docker([
        'run', '--rm',
        '-e', 'GNUPGHOME=/gnupg',
        '-v', $gnupgHome . ':/gnupg',
        '-v', $phpunitPhar . ':/phpunit.phar:ro',
        '-v', $ascPath . ':/phpunit.phar.asc:ro',
        PHP_IMAGE,
        'bash', '-lc',
        'apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq gnupg ca-certificates >/dev/null && gpg --status-fd 1 --verify /phpunit.phar.asc /phpunit.phar 2>/dev/null',
    ]);
    if (!preg_match('/\[GNUPG:\] VALIDSIG ' . PHPUNIT_GPG_FINGERPRINT . ' /', $verify)) {
        throw new RuntimeException("PHPUnit PHAR GPG verification failed:\n$verify");
    }
}

function assertPdoMysqlLoaded(): void
{
    $modules = docker([
        'run', '--rm', PHP_IMAGE,
        'bash', '-lc', 'docker-php-ext-install pdo_mysql >/dev/null && php -m',
    ]);
    if (!preg_match('/^pdo_mysql$/m', $modules)) {
        throw new RuntimeException('pdo_mysql extension not loaded in PHP image');
    }
}

function startMysql(): array
{
    $container = 'ps-linklist-mysql-' . getmypid();
    docker(['rm', '-f', $container]);
    docker([
        'run', '-d', '--name', $container,
        '-e', 'MYSQL_ROOT_PASSWORD=root',
        '-e', 'MYSQL_DATABASE=linklist_test',
        '-p', '127.0.0.1::3306',
        MYSQL_IMAGE,
    ]);
    $portLine = docker(['port', $container, '3306/tcp']);
    if (!preg_match('/127\.0\.0\.1:(\d+)/', $portLine, $m)) {
        throw new RuntimeException("Unexpected mysql port mapping: $portLine");
    }
    $deadline = time() + 120;
    while (time() < $deadline) {
        try {
            docker(['exec', $container, 'mysqladmin', 'ping', '-h', '127.0.0.1', '-uroot', '-proot', '--silent']);
            break;
        } catch (RuntimeException) {
            sleep(2);
        }
    }

    return ['container' => $container, 'port' => (int) $m[1]];
}

function runPhpUnit(
    string $moduleRoot,
    string $phpunitPhar,
    string $vendorDir,
    array $mysql,
    string $cacheDir,
    string $resultsDir,
    string $junitFile,
    array $extraArgs = []
): int {
    $env = [
        'TEST_VENDOR_AUTOLOAD=/test-vendor/autoload.php',
        'TEST_MYSQL_HOST=127.0.0.1',
        'TEST_MYSQL_PORT=' . $mysql['port'],
        'TEST_MYSQL_USER=root',
        'TEST_MYSQL_PASSWORD=root',
        'TEST_MYSQL_DATABASE=linklist_test',
        'TEST_PHP_BINARY=php',
    ];
    $phpunitArgs = array_merge(
        [
            '-c', '/module/tests/phpunit.xml.dist',
            '--cache-directory=/phpunit-cache',
            '--log-junit', '/results/' . $junitFile,
        ],
        $extraArgs
    );
    $escapedArgs = implode(' ', array_map('escapeshellarg', $phpunitArgs));
    $inner = implode(' && ', [
        'docker-php-ext-install pdo_mysql >/dev/null',
        'php -m | grep -q pdo_mysql',
        implode(' ', $env) . ' php /phpunit.phar ' . $escapedArgs,
    ]);

    $dockerArgs = [
        'run', '--rm', '--network', 'host',
        '-v', $moduleRoot . ':/module:ro',
        '-v', $phpunitPhar . ':/phpunit.phar:ro',
        '-v', $vendorDir . ':/test-vendor:ro',
        '-v', $cacheDir . ':/phpunit-cache',
        '-v', $resultsDir . ':/results',
        PHP_IMAGE,
        'bash', '-lc', $inner,
    ];
    [$code, $output] = runProcess('docker ' . implode(' ', array_map('escapeshellarg', $dockerArgs)));
    if ($code !== 0) {
        [$code, $output] = runProcess('sudo docker ' . implode(' ', array_map('escapeshellarg', $dockerArgs)));
    }
    if ($code !== 0 || preg_match('/\bWarning:/i', $output)) {
        fwrite(STDERR, $output . "\n");
        if ($code === 0) {
            $code = 1;
        }
    }

    return $code;
}

function loadOriginDevFile(string $moduleRoot, string $path): string
{
    $legacy = shell_exec('git -C ' . escapeshellarg($moduleRoot) . ' show origin/dev:' . escapeshellarg($path));
    if (!is_string($legacy) || $legacy === '') {
        throw new RuntimeException("Cannot load origin/dev version of $path");
    }

    return $legacy;
}

function exportModuleSnapshot(string $moduleRoot, string $exportDir): void
{
    if (is_dir($exportDir)) {
        runProcess('rm -rf ' . escapeshellarg($exportDir));
    }
    ensureLayout($exportDir);
    [$statusCode, $status] = runProcess('git -C ' . escapeshellarg($moduleRoot) . ' status --porcelain');
    if ($statusCode === 0 && $status === '') {
        [$code, $output] = runProcess(
            'git -C ' . escapeshellarg($moduleRoot) . ' archive HEAD | tar -xf - -C ' . escapeshellarg($exportDir)
        );
    } else {
        [$code, $output] = runProcess(
            'tar -C ' . escapeshellarg($moduleRoot) . ' --exclude=.git -cf - . | tar -xf - -C ' . escapeshellarg($exportDir)
        );
    }
    if ($code !== 0) {
        throw new RuntimeException("Module snapshot export failed:\n$output");
    }
}

function applyProveMutation(string $exportDir, string $mutationId, string $moduleRoot): void
{
    switch ($mutationId) {
        case '5.2-category':
            $path = 'src/Form/ChoiceProvider/CategoryChoiceProvider.php';
            file_put_contents($exportDir . '/' . $path, loadOriginDevFile($moduleRoot, $path));
            break;
        case '5.2-cms-category':
            $path = 'src/Form/ChoiceProvider/CMSCategoryChoiceProvider.php';
            file_put_contents($exportDir . '/' . $path, loadOriginDevFile($moduleRoot, $path));
            break;
        case '5.2-cms-page':
            $path = 'src/Form/ChoiceProvider/CMSPageChoiceProvider.php';
            file_put_contents($exportDir . '/' . $path, loadOriginDevFile($moduleRoot, $path));
            break;
        case '5.2-page':
            $path = 'src/Form/ChoiceProvider/PageChoiceProvider.php';
            file_put_contents($exportDir . '/' . $path, loadOriginDevFile($moduleRoot, $path));
            break;
        case '5.3-rollback':
            $path = $exportDir . '/src/Repository/LinkBlockRepository.php';
            $contents = file_get_contents($path);
            $contents = str_replace(
                "            if (\$this->connection->isTransactionActive()) {\n                \$this->connection->rollBack();\n            }\n\n",
                '',
                $contents
            );
            file_put_contents($path, $contents);
            break;
        case '5.3-message':
            $path = $exportDir . '/src/Repository/LinkBlockRepository.php';
            $contents = file_get_contents($path);
            $contents = str_replace(
                "throw new DatabaseException(sprintf('Could not update #%d', (int) \$position['rowId']), 0, \$e);",
                "throw new DatabaseException('Could not update #%i');",
                $contents
            );
            file_put_contents($path, $contents);
            break;
        case '5.4-boundary':
            $path = $exportDir . '/src/Presenter/LinkBlockPresenter.php';
            $contents = file_get_contents($path);
            $contents = str_replace(
                ". '(?=[/:?#]|$)' . preg_quote(\$basePath, '~')",
                ". preg_quote(\$basePath, '~')",
                $contents
            );
            file_put_contents($path, $contents);
            break;
        case '5.4-case':
            $path = $exportDir . '/src/Presenter/LinkBlockPresenter.php';
            $contents = file_get_contents($path);
            $contents = str_replace(
                "\$baseLink = rtrim(Tools::strtolower(preg_replace('#^(http)s?://#', '', \$this->link->getBaseLink())), '/');",
                "\$baseLink = rtrim(preg_replace('#^(http)s?://#', '', \$this->link->getBaseLink()), '/');",
                $contents
            );
            file_put_contents($path, $contents);
            break;
        default:
            throw new RuntimeException("Unknown prove mutation: $mutationId");
    }
}

function junitXmlAttributeValue(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function assertProveJUnitResult(string $junitFile, string $testCaseName): void
{
    if (!is_file($junitFile)) {
        throw new RuntimeException("Missing JUnit output: $junitFile");
    }
    $xml = file_get_contents($junitFile);
    if ($xml === false || $xml === '') {
        throw new RuntimeException("Invalid JUnit XML: $junitFile");
    }
    if (!preg_match('/\berrors="(\d+)"/', $xml, $errorsMatch) || (int) $errorsMatch[1] !== 0) {
        throw new RuntimeException("Proof run reported JUnit errors in $junitFile");
    }
    $encodedName = junitXmlAttributeValue($testCaseName);
    $pattern = '/<testcase\b[^>]*\bname="' . preg_quote($encodedName, '/') . '"[^>]*>.*?<\/testcase>/s';
    if (!preg_match($pattern, $xml, $testcaseMatch)) {
        throw new RuntimeException("JUnit did not contain testcase named \"$testCaseName\" in $junitFile");
    }
    if (!preg_match('/<(?:failure|error)\b/', $testcaseMatch[0])) {
        throw new RuntimeException("Expected failing testcase \"$testCaseName\" in $junitFile");
    }
}

function proveFixReverts(
    string $moduleRoot,
    string $phpunitPhar,
    string $vendorDir,
    array $mysql,
    string $cacheDir,
    string $resultsDir,
    string $exportRoot
): void {
    $cases = [
        [
            'id' => '5.2-category',
            'filter' => 'CategoryChoiceProviderShopFilterTest::testShopFilterReturnsCategoriesForMultipleRequestedShops',
            'junitTestCase' => 'testShopFilterReturnsCategoriesForMultipleRequestedShops',
        ],
        [
            'id' => '5.2-cms-category',
            'filter' => 'CmsCategoryChoiceProviderTest::testReturnsCategoriesForMultipleRequestedShops',
            'junitTestCase' => 'testReturnsCategoriesForMultipleRequestedShops',
        ],
        [
            'id' => '5.2-cms-page',
            'filter' => 'CmsPageChoiceProviderShopFilterTest::testCmsPageReturnedWhenShopIdsIncludeMultipleShops',
            'junitTestCase' => 'testCmsPageReturnedWhenShopIdsIncludeMultipleShops',
        ],
        [
            'id' => '5.2-page',
            'filter' => 'PageChoiceProviderShopFilterTest::testMetaPageResolvedWhenShopIdsIncludeMultipleShops',
            'junitTestCase' => 'testMetaPageResolvedWhenShopIdsIncludeMultipleShops',
        ],
        [
            'id' => '5.3-rollback',
            'filter' => 'LinkBlockRepositoryUpdatePositionsTest::testUpdatePositionsRollsBackOnFailure',
            'junitTestCase' => 'testUpdatePositionsRollsBackOnFailure',
        ],
        [
            'id' => '5.3-message',
            'filter' => 'LinkBlockRepositoryUpdatePositionsTest::testUpdatePositionsRollsBackOnFailure',
            'junitTestCase' => 'testUpdatePositionsRollsBackOnFailure',
        ],
        [
            'id' => '5.4-boundary',
            'filter' => 'LinkBlockPresenterExternalLinkTest::testIsExternalLink with data set "host prefix attack"',
            'junitTestCase' => 'testIsExternalLink with data set "host prefix attack"',
        ],
        [
            'id' => '5.4-case',
            'filter' => 'LinkBlockPresenterExternalLinkTest::testIsExternalLink with data set "internal when base host casing differs"',
            'junitTestCase' => 'testIsExternalLink with data set "internal when base host casing differs"',
        ],
    ];

    foreach ($cases as $case) {
        $exportDir = $exportRoot . '/' . $case['id'];
        exportModuleSnapshot($moduleRoot, $exportDir);
        applyProveMutation($exportDir, $case['id'], $moduleRoot);
        $junitFile = 'junit-prove-' . $case['id'] . '.xml';
        $code = runPhpUnit(
            $exportDir,
            $phpunitPhar,
            $vendorDir,
            $mysql,
            $cacheDir,
            $resultsDir,
            $junitFile,
            ['--filter', $case['filter']]
        );
        if ($code === 0) {
            throw new RuntimeException('Proof mutation ' . $case['id'] . ' exited 0 but must fail');
        }
        assertProveJUnitResult($resultsDir . '/' . $junitFile, $case['junitTestCase']);
        fwrite(STDOUT, 'PROVE PASS: ' . $case['id'] . ' (' . $case['filter'] . ")\n");
    }
}

$runProve = in_array('--prove', $argv ?? [], true);
$argv = array_values(array_filter($argv ?? [], static fn (string $arg): bool => $arg !== '--prove'));

ensureLayout($workRoot, $vendorDir, $cacheDir, $resultsDir, $gnupgHome);
$exportRoot = $workRoot . '/module-export';

$cacheArtifact = $moduleRoot . '/tests/${PHPUNIT_CACHE_DIR}';
if (is_dir($cacheArtifact)) {
    runProcess('sudo rm -rf ' . escapeshellarg($cacheArtifact));
}

docker(['pull', PHP_IMAGE]);
docker(['pull', MYSQL_IMAGE]);
docker(['pull', COMPOSER_IMAGE]);

assertPdoMysqlLoaded();

$mysql = startMysql();
$mysqlContainer = $mysql['container'];
register_shutdown_function(static function () use (&$mysqlContainer): void {
    if ($mysqlContainer === null) {
        return;
    }
    try {
        docker(['rm', '-f', $mysqlContainer]);
    } catch (Throwable) {
    }
    $mysqlContainer = null;
});

$projectDir = writeComposerProject($workRoot);
docker([
    'run', '--rm',
    '-v', $projectDir . ':/app',
    '-v', $vendorDir . ':/app/vendor',
    '-v', $moduleRoot . ':/module:ro',
    COMPOSER_IMAGE,
    'composer', 'install', '--no-interaction', '--no-progress', '--working-dir=/app',
]);

$auditOutput = docker([
    'run', '--rm',
    '-v', $projectDir . ':/app',
    '-v', $vendorDir . ':/app/vendor',
    COMPOSER_IMAGE,
    'composer', 'audit', '--no-interaction', '--working-dir=/app', '--format=plain', '--abandoned=ignore',
]);
if (!str_contains($auditOutput, 'No security vulnerability advisories found')) {
    throw new RuntimeException("composer audit did not report a clean result:\n$auditOutput");
}

$vendorAutoload = $vendorDir . '/autoload.php';
if (!is_file($vendorAutoload)) {
    throw new RuntimeException('External vendor autoload missing');
}

$ascPath = fetchPHPUnitPhar($phpunitPhar);
verifyPHPUnitPharSignature($phpunitPhar, $ascPath, $gnupgHome);

$defaultCode = runPhpUnit($moduleRoot, $phpunitPhar, $vendorDir, $mysql, $cacheDir, $resultsDir, 'junit-default.xml');
if ($defaultCode !== 0) {
    exit($defaultCode);
}

$shuffleCode = runPhpUnit(
    $moduleRoot,
    $phpunitPhar,
    $vendorDir,
    $mysql,
    $cacheDir,
    $resultsDir,
    'junit-shuffle.xml',
    ['--order-by=random', '--random-order-seed=20261010']
);
if ($shuffleCode !== 0) {
    exit($shuffleCode);
}

if ($runProve) {
    proveFixReverts($moduleRoot, $phpunitPhar, $vendorDir, $mysql, $cacheDir, $resultsDir, $exportRoot);
}

docker(['rm', '-f', $mysql['container']]);
$mysqlContainer = null;

$cacheArtifact = $moduleRoot . '/tests/${PHPUNIT_CACHE_DIR}';
if (is_dir($cacheArtifact)) {
    runProcess('rm -rf ' . escapeshellarg($cacheArtifact));
}

[$gitStatusCode, $gitStatus] = runProcess('git -C ' . escapeshellarg($moduleRoot) . ' status --porcelain');
if ($gitStatusCode !== 0 || $gitStatus !== '') {
    throw new RuntimeException("Repository not clean after suite:\n$gitStatus");
}

fwrite(STDOUT, "Suite completed successfully\n");
exit(0);

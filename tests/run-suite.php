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

function proveFixReverts(
    string $moduleRoot,
    string $phpunitPhar,
    string $vendorDir,
    array $mysql,
    string $cacheDir,
    string $resultsDir
): void {
    $cases = [
        [
            'paths' => [
                'src/Form/ChoiceProvider/CategoryChoiceProvider.php',
                'src/Form/ChoiceProvider/CMSCategoryChoiceProvider.php',
                'src/Form/ChoiceProvider/CMSPageChoiceProvider.php',
                'src/Form/ChoiceProvider/PageChoiceProvider.php',
            ],
            'filter' => 'CategoryChoiceProviderShopFilterTest::testShopIdsAreBoundAsIntegersNotSqlInjection',
        ],
        [
            'paths' => ['src/Repository/LinkBlockRepository.php'],
            'filter' => 'LinkBlockRepositoryUpdatePositionsTest',
        ],
        [
            'paths' => ['src/Presenter/LinkBlockPresenter.php'],
            'filter' => 'LinkBlockPresenterExternalLinkTest::testIsExternalLink with data set "internal when base host casing differs"',
        ],
    ];

    foreach ($cases as $case) {
        $backups = [];
        foreach ($case['paths'] as $path) {
            $full = $moduleRoot . '/' . $path;
            $backups[$path] = file_get_contents($full);
            $legacy = shell_exec('git -C ' . escapeshellarg($moduleRoot) . ' show origin/dev:' . escapeshellarg($path));
            if (!is_string($legacy) || $legacy === '') {
                throw new RuntimeException("Cannot load origin/dev version of $path");
            }
            file_put_contents($full, $legacy);
        }

        $code = runPhpUnit(
            $moduleRoot,
            $phpunitPhar,
            $vendorDir,
            $mysql,
            $cacheDir,
            $resultsDir,
            'junit-prove.xml',
            ['--filter', $case['filter']]
        );

        foreach ($backups as $path => $contents) {
            file_put_contents($moduleRoot . '/' . $path, $contents);
        }

        if ($code === 0) {
            throw new RuntimeException('Expected failing tests after reverting fix for ' . $case['filter']);
        }
    }
}

ensureLayout($workRoot, $vendorDir, $cacheDir, $resultsDir, $gnupgHome);

$cacheArtifact = $moduleRoot . '/tests/${PHPUNIT_CACHE_DIR}';
if (is_dir($cacheArtifact)) {
    runProcess('sudo rm -rf ' . escapeshellarg($cacheArtifact));
}

docker(['pull', PHP_IMAGE]);
docker(['pull', MYSQL_IMAGE]);
docker(['pull', COMPOSER_IMAGE]);

assertPdoMysqlLoaded();

$mysql = startMysql();

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
    docker(['rm', '-f', $mysql['container']]);
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
    docker(['rm', '-f', $mysql['container']]);
    exit($shuffleCode);
}

proveFixReverts($moduleRoot, $phpunitPhar, $vendorDir, $mysql, $cacheDir, $resultsDir);

docker(['rm', '-f', $mysql['container']]);

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

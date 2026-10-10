<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);

require $moduleRoot . '/tests/Support/stubs.php';

$vendorAutoload = getenv('TEST_VENDOR_AUTOLOAD') ?: '';
if ($vendorAutoload === '' || !is_file($vendorAutoload)) {
    fwrite(STDERR, "TEST_VENDOR_AUTOLOAD must point to external vendor/autoload.php\n");
    exit(1);
}

require $vendorAutoload;

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '9.0.0');
}

spl_autoload_register(static function (string $class) use ($moduleRoot): void {
    if (str_starts_with($class, 'PrestaShop\\Module\\LinkList\\Tests\\')) {
        $relative = str_replace('\\', '/', substr($class, strlen('PrestaShop\\Module\\LinkList\\Tests\\')));
        $path = $moduleRoot . '/tests/' . $relative . '.php';
        if (is_file($path)) {
            require $path;
        }

        return;
    }

    if (str_starts_with($class, 'PrestaShop\\PrestaShop\\')) {
        $map = [
            'PrestaShop\\PrestaShop\\Core\\Exception\\DatabaseException' => $moduleRoot . '/tests/Support/DatabaseException.php',
            'PrestaShop\\PrestaShop\\Core\\Form\\FormChoiceProviderInterface' => $moduleRoot . '/tests/Support/FormChoiceProviderInterface.php',
            'PrestaShop\\PrestaShop\\Adapter\\Shop\\Context' => $moduleRoot . '/tests/Support/PrestaShopAdapterShopContext.php',
            'PrestaShop\\PrestaShop\\Adapter\\Configuration' => $moduleRoot . '/tests/Support/PrestaShopAdapterConfiguration.php',
            'PrestaShop\\PrestaShop\\Core\\Grid\\Query\\AbstractDoctrineQueryBuilder' => $moduleRoot . '/tests/Support/AbstractDoctrineQueryBuilder.php',
            'PrestaShop\\PrestaShop\\Core\\Search\\Filters' => $moduleRoot . '/tests/Support/PrestaShopFilters.php',
            'PrestaShop\\PrestaShop\\Core\\Grid\\Search\\SearchCriteriaInterface' => $moduleRoot . '/tests/Support/SearchCriteriaInterface.php',
            'PrestaShop\\PrestaShop\\Adapter\\Domain\\AbstractObjectModelHandler' => $moduleRoot . '/tests/Support/AbstractObjectModelHandler.php',
            'PrestaShop\\PrestaShop\\Core\\Foundation\\Database\\EntityNotFoundException' => $moduleRoot . '/tests/Support/EntityNotFoundException.php',
        ];
        if (isset($map[$class]) && is_file($map[$class])) {
            require $map[$class];
        }
    }
});

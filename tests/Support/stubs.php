<?php

declare(strict_types=1);

if (!function_exists('pSQL')) {
    function pSQL(string $string, bool $htmlOK = false): string
    {
        return addslashes($string);
    }
}

class Tools
{
    public static function strtolower(string $string): string
    {
        return mb_strtolower($string, 'UTF-8');
    }
}

class Configuration
{
    /** @var array<string, mixed> */
    private static array $values = [];

    /** @var array<string, true> */
    private static array $deleted = [];

    public static function reset(): void
    {
        self::$values = [];
        self::$deleted = [];
    }

    public static function set(string $name, mixed $value): void
    {
        self::$values[$name] = $value;
        unset(self::$deleted[$name]);
    }

    public static function get(string $name, ?int $idLang = null, ?int $idShop = null)
    {
        return self::$values[$name] ?? null;
    }

    public static function deleteByName(string $name): void
    {
        self::$deleted[$name] = true;
        unset(self::$values[$name]);
    }

    public static function wasDeleted(string $name): bool
    {
        return isset(self::$deleted[$name]);
    }
}

class Link
{
    private string $baseLink;

    public function __construct(string $baseLink = 'https://shop.example.com/')
    {
        $this->baseLink = $baseLink;
    }

    public function getBaseLink(): string
    {
        return $this->baseLink;
    }
}

class Language
{
    public int $id = 1;
}

class Hook
{
    private static array $hooks = [
        'displayFooter' => 1,
    ];

    public int $id;
    public string $name = '';

    public function __construct(?int $id = null)
    {
        $this->id = (int) $id;
        foreach (self::$hooks as $name => $hookId) {
            if ($hookId === $this->id) {
                $this->name = $name;
                break;
            }
        }
    }

    public static function setHook(string $name, int $id): void
    {
        self::$hooks[$name] = $id;
    }

    public static function getIdByName(string $name): int
    {
        return self::$hooks[$name] ?? 0;
    }
}

class Meta
{
}

class Db
{
    private static ?self $instance = null;

    /** @var list<string> */
    private array $executed = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = new self();
    }

    public function getPrefix(): string
    {
        return _DB_PREFIX_;
    }

    public function execute(string $sql): bool
    {
        $this->executed[] = $sql;

        return true;
    }

    /** @return list<string> */
    public function getExecuted(): array
    {
        return $this->executed;
    }
}

class Shop
{
    public static function isFeatureActive(): bool
    {
        return false;
    }

    public static function setContext(int $context): void
    {
    }

    public const CONTEXT_ALL = 1;
}

class Context
{
}

class Module
{
    public static function getInstanceByName(string $name): ?self
    {
        return null;
    }
}

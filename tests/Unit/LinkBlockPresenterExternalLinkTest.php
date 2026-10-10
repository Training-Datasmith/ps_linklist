<?php

declare(strict_types=1);

namespace PrestaShop\Module\LinkList\Tests\Unit;

use Link;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\LinkList\Presenter\LinkBlockPresenter;

final class LinkBlockPresenterExternalLinkTest extends TestCase
{
    private LinkBlockPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new LinkBlockPresenter(new Link('https://Shop.Example.COM/'), new \Language());
    }

    /**
     * @dataProvider externalLinkCases
     */
    public function testIsExternalLink(string $url, bool $expectedExternal): void
    {
        self::assertSame($expectedExternal, $this->presenter->isExternalLink($url));
    }

    public static function externalLinkCases(): array
    {
        return [
            'internal when base host casing differs' => ['https://shop.example.com/page', false],
            'true external host' => ['https://evil.example/', true],
            'host prefix attack' => ['https://shop.example.evil/phish', true],
            'relative path' => ['/contact', false],
            'external with path' => ['https://other-shop.test/item', true],
        ];
    }
}

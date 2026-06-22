<?php

namespace Tests\Unit;

use App\Support\SsrfGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SsrfGuardTest extends TestCase
{
    private SsrfGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new SsrfGuard;
    }

    public function test_accepts_public_https_url(): void
    {
        $this->guard->validateUrl('https://example.com');

        $this->assertTrue(true);
    }

    #[DataProvider('blockedUrlsProvider')]
    public function test_blocks_unsafe_urls(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->guard->validateUrl($url);
    }

    public static function blockedUrlsProvider(): array
    {
        return [
            ['http://localhost/test'],
            ['http://127.0.0.1/test'],
            ['http://192.168.1.1/test'],
            ['ftp://example.com'],
            ['http://169.254.169.254/latest/meta-data'],
        ];
    }
}

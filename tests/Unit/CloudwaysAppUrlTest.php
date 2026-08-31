<?php

namespace Tests\Unit;

use App\Services\Cloudways\CloudwaysAppUrl;
use Tests\TestCase;

class CloudwaysAppUrlTest extends TestCase
{
    public function test_prefers_cname_over_alias_and_fqdn(): void
    {
        $url = CloudwaysAppUrl::fromApp([
            'cname' => 'www.example.com',
            'aliases' => 'alias.example.com',
            'app_fqdn' => 'wordpress-1-2.cloudwaysapps.com',
        ]);

        $this->assertSame('https://www.example.com', $url);
    }

    public function test_uses_first_alias_when_cname_is_empty(): void
    {
        $url = CloudwaysAppUrl::fromApp([
            'cname' => '',
            'aliases' => ['shop.example.com', 'www.shop.example.com'],
            'app_fqdn' => 'wordpress-1-2.cloudwaysapps.com',
        ]);

        $this->assertSame('https://shop.example.com', $url);
    }

    public function test_falls_back_to_cloudways_fqdn(): void
    {
        $url = CloudwaysAppUrl::fromApp([
            'app_fqdn' => 'wordpress-1-2.cloudwaysapps.com',
        ]);

        $this->assertSame('https://wordpress-1-2.cloudwaysapps.com', $url);
    }

    public function test_normalizes_absolute_urls_without_trailing_slash(): void
    {
        $url = CloudwaysAppUrl::fromApp([
            'cname' => 'https://www.example.com/',
        ]);

        $this->assertSame('https://www.example.com', $url);
    }

    public function test_returns_null_when_no_url_is_available(): void
    {
        $this->assertNull(CloudwaysAppUrl::fromApp(['label' => 'Senza dominio']));
    }

    public function test_detects_temporary_cloudways_urls(): void
    {
        $this->assertTrue(CloudwaysAppUrl::isTemporaryCloudwaysUrl('https://wordpress-1-2.cloudwaysapps.com'));
        $this->assertTrue(CloudwaysAppUrl::isTemporaryCloudwaysUrl('https://wordpress-1-2.cloudwaysapps.com/path'));
        $this->assertFalse(CloudwaysAppUrl::isTemporaryCloudwaysUrl('https://www.cliente.it'));
        $this->assertFalse(CloudwaysAppUrl::isTemporaryCloudwaysUrl('https://notcloudwaysapps.com'));
    }
}

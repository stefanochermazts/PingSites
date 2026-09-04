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

    public function test_reads_temporary_fqdn_url(): void
    {
        $this->assertSame(
            'https://wordpress-1633639-6599077.cloudwaysapps.com',
            CloudwaysAppUrl::temporaryUrl([
                'cname' => 'www.lanuovaenergia.com',
                'app_fqdn' => 'wordpress-1633639-6599077.cloudwaysapps.com',
            ]),
        );
        $this->assertNull(CloudwaysAppUrl::temporaryUrl([
            'app_fqdn' => 'www.lanuovaenergia.com',
        ]));
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

    public function test_is_truthy_accepts_cloudways_flag_representations(): void
    {
        $this->assertTrue(CloudwaysAppUrl::isTruthy(true));
        $this->assertTrue(CloudwaysAppUrl::isTruthy(1));
        $this->assertTrue(CloudwaysAppUrl::isTruthy(1.0));
        $this->assertTrue(CloudwaysAppUrl::isTruthy('1'));
        $this->assertTrue(CloudwaysAppUrl::isTruthy('true'));
        $this->assertTrue(CloudwaysAppUrl::isTruthy('yes'));
        $this->assertTrue(CloudwaysAppUrl::isTruthy('on'));
        $this->assertTrue(CloudwaysAppUrl::isTruthy('enabled'));

        $this->assertFalse(CloudwaysAppUrl::isTruthy(false));
        $this->assertFalse(CloudwaysAppUrl::isTruthy(0));
        $this->assertFalse(CloudwaysAppUrl::isTruthy(2));
        $this->assertFalse(CloudwaysAppUrl::isTruthy(null));
        $this->assertFalse(CloudwaysAppUrl::isTruthy(''));
        $this->assertFalse(CloudwaysAppUrl::isTruthy('0'));
        $this->assertFalse(CloudwaysAppUrl::isTruthy('false'));
        $this->assertFalse(CloudwaysAppUrl::isTruthy([]));
    }

    public function test_is_positive_count_treats_malware_file_counts_as_infected(): void
    {
        $this->assertTrue(CloudwaysAppUrl::isPositiveCount(829));
        $this->assertTrue(CloudwaysAppUrl::isPositiveCount('35'));
        $this->assertTrue(CloudwaysAppUrl::isPositiveCount(1));
        $this->assertTrue(CloudwaysAppUrl::isPositiveCount(true));
        $this->assertFalse(CloudwaysAppUrl::isPositiveCount(0));
        $this->assertFalse(CloudwaysAppUrl::isPositiveCount('0'));
        $this->assertFalse(CloudwaysAppUrl::isPositiveCount(null));
    }
}

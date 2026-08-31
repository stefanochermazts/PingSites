<?php

namespace Tests\Feature;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Services\HttpChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
    }

    public function test_treats_empty_valid_status_codes_as_default_success_codes(): void
    {
        Http::fake([
            '*' => Http::response('<html><body>ok</body></html>', 200),
        ]);

        $monitor = Monitor::query()->create([
            'name' => 'Sito',
            'url' => 'https://example.com',
            'status' => MonitorStatus::Unknown,
            'timeout' => 10,
            'check_frequency' => 10,
            'follow_redirects' => true,
            'verify_ssl' => true,
            'valid_status_codes' => [],
        ]);

        $result = app(HttpChecker::class)->check($monitor);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpCode);
    }
}

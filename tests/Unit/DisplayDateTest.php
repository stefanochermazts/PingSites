<?php

namespace Tests\Unit;

use App\Models\Check;
use App\Models\Monitor;
use App\Support\DisplayDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DisplayDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_utc_iso_string_in_app_timezone(): void
    {
        config(['app.timezone' => 'Europe/Rome']);

        $this->assertSame(
            '26/06/2026 08:57:06',
            DisplayDate::format('2026-06-26T06:57:06+00:00', 'd/m/Y H:i:s'),
        );
    }

    public function test_does_not_double_convert_model_datetime(): void
    {
        config(['app.timezone' => 'Europe/Rome']);

        $monitor = Monitor::query()->create([
            'name' => 'Test',
            'url' => 'https://example.com',
            'valid_status_codes' => [200],
        ]);

        $checkedAt = Carbon::parse('2026-06-29 10:43:03', 'Europe/Rome');

        $check = Check::query()->create([
            'monitor_id' => $monitor->id,
            'success' => true,
            'http_code' => 200,
            'response_time_ms' => 100,
            'checked_at' => $checkedAt,
        ]);

        $this->assertSame(
            '29/06/2026 10:43:03',
            DisplayDate::format(DisplayDate::isoFromModel($check, 'checked_at'), 'd/m/Y H:i:s'),
        );
    }
}

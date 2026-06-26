<?php

namespace Tests\Unit;

use App\Support\DisplayDate;
use Tests\TestCase;

class DisplayDateTest extends TestCase
{
    public function test_formats_utc_timestamp_in_app_timezone(): void
    {
        config(['app.timezone' => 'Europe/Rome']);

        $this->assertSame(
            '26/06/2026 08:57:06',
            DisplayDate::format('2026-06-26T06:57:06+00:00', 'd/m/Y H:i:s'),
        );
    }
}

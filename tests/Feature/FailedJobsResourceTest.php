<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FailedJobsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_jobs_page_requires_authentication(): void
    {
        $this->get('/admin/failed-jobs')->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_view_failed_jobs(): void
    {
        $this->seedFailedJob();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/failed-jobs')
            ->assertOk();
    }

    public function test_truncate_clears_failed_jobs_table(): void
    {
        $this->seedFailedJob();
        $this->assertDatabaseCount('failed_jobs', 1);

        DB::table('failed_jobs')->truncate();

        $this->assertDatabaseCount('failed_jobs', 0);
    }

    private function seedFailedJob(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => 'checks',
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\CheckMonitorJob',
                'data' => [
                    'command' => 'O:24:"App\\Jobs\\CheckMonitorJob":1:{s:9:"monitorId";i:2;}',
                ],
            ]),
            'exception' => 'SQLSTATE[22003]: Numeric value out of range',
            'failed_at' => now(),
        ]);
    }
}

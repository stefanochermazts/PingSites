<?php

namespace Tests\Feature;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\StatusPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncWordpressThemesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMonitorSettings();
    }

    public function test_updates_only_publimedia_monitors_from_site_html(): void
    {
        $publimedia = $this->publimediaPage();
        $default = StatusPage::query()->where('is_default', true)->firstOrFail();

        $wordpress = $this->monitor($publimedia, 'Sito WP', 'https://cliente.example');
        $plain = $this->monitor($publimedia, 'Sito Statico', 'https://statico.example');
        $other = $this->monitor($default, 'Sito Devisia', 'https://devisia.example');

        Http::fake([
            'https://cliente.example' => Http::response(
                '<meta name="generator" content="WordPress 6.7.2"><body class="wp-theme-hello-elementor"><link rel="stylesheet" href="https://cliente.example/wp-content/themes/hello-elementor/style.css"></body>',
                200,
            ),
            'https://cliente.example/wp-content/themes/hello-elementor/style.css' => Http::response(
                "/*\nTheme Name: Hello Elementor\n*/",
                200,
            ),
            'https://statico.example' => Http::response('<html><body>ok</body></html>', 200),
            'https://devisia.example' => Http::response(
                '<link rel="stylesheet" href="https://devisia.example/wp-content/themes/astra/style.css">',
                200,
            ),
        ]);

        $this->artisan('monitors:sync-themes')
            ->assertSuccessful();

        $this->assertSame('Hello Elementor', $wordpress->fresh()->wordpress_theme);
        $this->assertSame('hello-elementor', $wordpress->fresh()->wordpress_theme_slug);
        $this->assertSame('6.7.2', $wordpress->fresh()->wordpress_version);
        $this->assertNotNull($wordpress->fresh()->wordpress_theme_checked_at);
        $this->assertNull($plain->fresh()->wordpress_theme);
        $this->assertNull($plain->fresh()->wordpress_version);
        $this->assertNotNull($plain->fresh()->wordpress_theme_checked_at);
        $this->assertNull($other->fresh()->wordpress_theme);
        $this->assertNull($other->fresh()->wordpress_version);
        $this->assertNull($other->fresh()->wordpress_theme_checked_at);
    }

    public function test_keeps_previous_theme_when_site_request_fails(): void
    {
        $publimedia = $this->publimediaPage();
        $monitor = $this->monitor($publimedia, 'Sito WP', 'https://cliente.example', 'Divi', 'divi', '6.4.3');

        Http::fake([
            'https://cliente.example' => Http::response('down', 500),
        ]);

        $this->artisan('monitors:sync-themes')
            ->assertSuccessful();

        $this->assertSame('Divi', $monitor->fresh()->wordpress_theme);
        $this->assertSame('divi', $monitor->fresh()->wordpress_theme_slug);
        $this->assertSame('6.4.3', $monitor->fresh()->wordpress_version);
        $this->assertNull($monitor->fresh()->wordpress_theme_checked_at);
    }

    private function publimediaPage(): StatusPage
    {
        return StatusPage::query()->create([
            'name' => 'Publimedia',
            'title' => 'Publimedia Status',
            'slug' => 'publimedia',
            'is_default' => false,
        ]);
    }

    private function monitor(
        StatusPage $statusPage,
        string $name,
        string $url,
        ?string $theme = null,
        ?string $themeSlug = null,
        ?string $version = null,
    ): Monitor {
        return Monitor::query()->create([
            'name' => $name,
            'url' => $url,
            'status' => MonitorStatus::Online,
            'published' => true,
            'status_page_id' => $statusPage->id,
            'valid_status_codes' => [200],
            'timeout' => 10,
            'follow_redirects' => true,
            'verify_ssl' => true,
            'wordpress_theme' => $theme,
            'wordpress_theme_slug' => $themeSlug,
            'wordpress_version' => $version,
        ]);
    }
}

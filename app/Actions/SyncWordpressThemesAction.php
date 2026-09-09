<?php

namespace App\Actions;

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\Wordpress\WordpressTheme;
use App\Services\Wordpress\WordpressThemeParser;
use App\Services\Wordpress\WordpressVersionParser;
use App\Settings\MonitorSettings;
use App\Support\SsrfGuard;
use InvalidArgumentException;
use Throwable;

class SyncWordpressThemesAction
{
    public function __construct(
        private readonly WordpressThemeParser $parser,
        private readonly WordpressVersionParser $versionParser,
        private readonly SsrfGuard $ssrfGuard,
        private readonly MonitorSettings $settings,
    ) {}

    /**
     * @return array{updated: int, skipped: int, failed: int}
     */
    public function handle(): array
    {
        $result = [
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $statusPage = StatusPage::query()
            ->where('slug', StatusPage::PUBLIMEDIA_SLUG)
            ->first();

        if ($statusPage === null) {
            return $result;
        }

        $monitors = Monitor::query()
            ->where('status_page_id', $statusPage->id)
            ->get();

        foreach ($monitors as $monitor) {
            if (! filled($monitor->url)) {
                $result['skipped']++;

                continue;
            }

            $html = $this->fetch($monitor, $monitor->url);

            if ($html === null) {
                $result['failed']++;

                continue;
            }

            $theme = $this->parser->fromHtml($html);
            if ($theme !== null) {
                $theme = $this->withStylesheetName($monitor, $theme);
            }

            $monitor->wordpress_theme = $theme?->displayName();
            $monitor->wordpress_theme_slug = $theme?->slug;
            $monitor->wordpress_version = $this->detectVersion($monitor, $html, $theme !== null);
            $monitor->wordpress_theme_checked_at = now();
            $monitor->save();
            $result['updated']++;
        }

        return $result;
    }

    private function detectVersion(Monitor $monitor, string $html, bool $looksLikeWordpress): ?string
    {
        $version = $this->versionParser->fromHtml($html);

        if ($version !== null || ! $looksLikeWordpress) {
            return $version;
        }

        $feed = $this->fetch($monitor, rtrim($monitor->url, '/').'/feed/');

        return $feed !== null ? $this->versionParser->fromFeed($feed) : null;
    }

    private function withStylesheetName(Monitor $monitor, WordpressTheme $theme): WordpressTheme
    {
        $stylesheetUrl = $this->resolveUrl($monitor->url, $theme->stylesheetUrl)
            ?? rtrim($monitor->url, '/').'/wp-content/themes/'.$theme->slug.'/style.css';

        $css = $this->fetch($monitor, $stylesheetUrl);

        return $css !== null ? $theme->withName($this->parser->nameFromStylesheet($css)) : $theme;
    }

    private function fetch(Monitor $monitor, string $url): ?string
    {
        try {
            $this->ssrfGuard->validateUrl($url);
        } catch (InvalidArgumentException) {
            return null;
        }

        try {
            $response = $this->ssrfGuard->createHttpClient(
                timeout: max(1, (int) $monitor->timeout),
                followRedirects: (bool) $monitor->follow_redirects,
                verifySsl: (bool) $monitor->verify_ssl,
                userAgent: $this->settings->user_agent,
                onRedirect: function (string $uri): void {
                    $this->ssrfGuard->validateRedirectUrl($uri);
                },
            )->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            return $body !== '' ? $body : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveUrl(string $baseUrl, ?string $href): ?string
    {
        if (! is_string($href) || $href === '') {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $host = $parts['host'] ?? null;

        if (! is_string($host) || $host === '') {
            return null;
        }

        $origin = ($parts['scheme'] ?? 'https').'://'.$host;
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        return rtrim($baseUrl, '/').'/'.$href;
    }
}

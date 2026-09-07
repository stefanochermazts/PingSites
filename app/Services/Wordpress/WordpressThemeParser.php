<?php

namespace App\Services\Wordpress;

class WordpressThemeParser
{
    public function fromHtml(string $html): ?WordpressTheme
    {
        $slug = $this->slugFromBodyClass($html) ?? $this->slugFromStylesheets($html);

        if ($slug === null) {
            return null;
        }

        return new WordpressTheme(
            slug: $slug,
            stylesheetUrl: $this->stylesheetUrlForSlug($html, $slug),
        );
    }

    public function nameFromStylesheet(string $css): ?string
    {
        if (preg_match('/^\s*Theme Name:\s*(.+)$/mi', substr($css, 0, 8192), $matches) !== 1) {
            return null;
        }

        $name = trim($matches[1]);

        return $name !== '' ? $name : null;
    }

    private function slugFromBodyClass(string $html): ?string
    {
        if (preg_match('/\bwp-theme-([a-zA-Z0-9._-]+)/', $html, $matches) !== 1) {
            return null;
        }

        return $this->normalizeSlug($matches[1]);
    }

    private function slugFromStylesheets(string $html): ?string
    {
        $slugs = $this->stylesheetSlugs($html);

        if ($slugs === []) {
            return null;
        }

        foreach ($slugs as $slug) {
            if (str_ends_with(strtolower($slug), '-child')) {
                return $slug;
            }
        }

        return $slugs[0];
    }

    /**
     * @return list<string>
     */
    private function stylesheetSlugs(string $html): array
    {
        $slugs = [];

        foreach ($this->stylesheetMatches($html) as $match) {
            $slug = $this->normalizeSlug($match['slug']);
            if ($slug === null || $this->containsSlug($slugs, $slug)) {
                continue;
            }

            $slugs[] = $slug;
        }

        return $slugs;
    }

    private function stylesheetUrlForSlug(string $html, string $slug): ?string
    {
        foreach ($this->stylesheetMatches($html) as $match) {
            if (! $this->sameSlug($match['slug'], $slug)) {
                continue;
            }

            $url = html_entity_decode($match['url'], ENT_QUOTES | ENT_HTML5);
            $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: $url));

            if (str_contains($path, '/style.') && str_ends_with($path, '.css')) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @return list<array{url: string, slug: string}>
     */
    private function stylesheetMatches(string $html): array
    {
        if (preg_match_all(
            '/href=([\'"])([^\'"]*wp-content\/themes\/([a-zA-Z0-9._-]+)\/[^\'"]*)\1/i',
            $html,
            $matches,
            PREG_SET_ORDER,
        ) === false) {
            return [];
        }

        $found = [];

        foreach ($matches as $match) {
            $found[] = [
                'url' => $match[2],
                'slug' => $match[3],
            ];
        }

        return $found;
    }

    private function normalizeSlug(string $slug): ?string
    {
        $slug = trim($slug);

        if ($slug === '' || $slug === '.' || str_contains($slug, '..')) {
            return null;
        }

        return $slug;
    }

    /**
     * @param  list<string>  $slugs
     */
    private function containsSlug(array $slugs, string $slug): bool
    {
        foreach ($slugs as $existing) {
            if ($this->sameSlug($existing, $slug)) {
                return true;
            }
        }

        return false;
    }

    private function sameSlug(string $left, string $right): bool
    {
        return strcasecmp($left, $right) === 0;
    }
}

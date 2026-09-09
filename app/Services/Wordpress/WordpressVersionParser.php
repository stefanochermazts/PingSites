<?php

namespace App\Services\Wordpress;

class WordpressVersionParser
{
    public function fromHtml(string $html): ?string
    {
        return $this->fromGenerator($html) ?? $this->fromWpIncludes($html);
    }

    public function fromFeed(string $xml): ?string
    {
        if (preg_match('/wordpress\.org\/\?v=(\d+\.\d+(?:\.\d+)?)/i', $xml, $matches) !== 1) {
            return null;
        }

        return $this->normalize($matches[1]);
    }

    private function fromGenerator(string $html): ?string
    {
        if (preg_match(
            '/<meta[^>]+name=["\']generator["\'][^>]+content=["\']WordPress\s+(\d+\.\d+(?:\.\d+)?)["\']/i',
            $html,
            $matches,
        ) === 1) {
            return $this->normalize($matches[1]);
        }

        if (preg_match(
            '/content=["\']WordPress\s+(\d+\.\d+(?:\.\d+)?)["\'][^>]+name=["\']generator["\']/i',
            $html,
            $matches,
        ) === 1) {
            return $this->normalize($matches[1]);
        }

        return null;
    }

    private function fromWpIncludes(string $html): ?string
    {
        if (preg_match_all('/wp-includes\/[^\'"\s>]+[?&]ver=(\d+\.\d+(?:\.\d+)?)/i', $html, $matches) < 1) {
            return null;
        }

        $counts = array_count_values($matches[1]);
        arsort($counts);

        $version = array_key_first($counts);

        return is_string($version) ? $this->normalize($version) : null;
    }

    private function normalize(string $version): ?string
    {
        $version = trim($version);

        return $version !== '' ? $version : null;
    }
}

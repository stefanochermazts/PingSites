<?php

namespace Tests\Unit;

use App\Services\Wordpress\WordpressVersionParser;
use Tests\TestCase;

class WordpressVersionParserTest extends TestCase
{
    public function test_reads_version_from_generator_meta(): void
    {
        $html = '<meta name="generator" content="WordPress 6.7.2">';

        $this->assertSame('6.7.2', (new WordpressVersionParser)->fromHtml($html));
    }

    public function test_reads_version_from_wp_includes_assets(): void
    {
        $html = <<<'HTML'
            <link rel="stylesheet" href="https://cliente.example/wp-content/themes/divi/style.css?ver=4.25.1">
            <script src="https://cliente.example/wp-includes/js/wp-emoji-release.min.js?ver=6.6.2"></script>
            <script src="https://cliente.example/wp-includes/js/wp-embed.min.js?ver=6.6.2"></script>
            HTML;

        $this->assertSame('6.6.2', (new WordpressVersionParser)->fromHtml($html));
    }

    public function test_reads_version_from_rss_generator(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <rss>
              <channel>
                <generator>https://wordpress.org/?v=6.8.1</generator>
              </channel>
            </rss>
            XML;

        $this->assertSame('6.8.1', (new WordpressVersionParser)->fromFeed($xml));
    }

    public function test_returns_null_when_version_is_missing(): void
    {
        $parser = new WordpressVersionParser;

        $this->assertNull($parser->fromHtml('<html><body>ciao</body></html>'));
        $this->assertNull($parser->fromFeed('<rss><channel><title>Blog</title></channel></rss>'));
    }
}

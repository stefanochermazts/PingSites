<?php

namespace Tests\Unit;

use App\Services\Wordpress\WordpressThemeParser;
use Tests\TestCase;

class WordpressThemeParserTest extends TestCase
{
    public function test_reads_active_theme_from_wp_theme_body_class(): void
    {
        $html = <<<'HTML'
            <body class="home blog wp-theme-hello-elementor-child wp-child-theme">
            <link rel="stylesheet" href="https://cliente.example/wp-content/themes/hello-elementor/style.min.css">
            <link rel="stylesheet" href="https://cliente.example/wp-content/themes/hello-elementor-child/style.css?ver=1.0.0">
            </body>
            HTML;

        $theme = (new WordpressThemeParser)->fromHtml($html);

        $this->assertNotNull($theme);
        $this->assertSame('hello-elementor-child', $theme->slug);
        $this->assertSame(
            'https://cliente.example/wp-content/themes/hello-elementor-child/style.css?ver=1.0.0',
            $theme->stylesheetUrl,
        );
        $this->assertSame('Hello Elementor Child', $theme->displayName());
    }

    public function test_prefers_child_theme_when_parent_and_child_stylesheets_are_present(): void
    {
        $html = <<<'HTML'
            <link rel='stylesheet' href='https://cliente.example/wp-content/themes/generatepress/style.css'>
            <link rel='stylesheet' href='https://cliente.example/wp-content/themes/generatepress-child/style.css'>
            HTML;

        $theme = (new WordpressThemeParser)->fromHtml($html);

        $this->assertNotNull($theme);
        $this->assertSame('generatepress-child', $theme->slug);
    }

    public function test_reads_single_theme_from_stylesheet_href(): void
    {
        $html = '<link rel="stylesheet" href="https://www.cliente.it/wp-content/themes/divi/style.css?ver=4.25">';

        $theme = (new WordpressThemeParser)->fromHtml($html);

        $this->assertNotNull($theme);
        $this->assertSame('divi', $theme->slug);
        $this->assertSame('Divi', $theme->displayName());
    }

    public function test_returns_null_when_html_has_no_wordpress_theme(): void
    {
        $this->assertNull((new WordpressThemeParser)->fromHtml('<html><body>ciao</body></html>'));
    }

    public function test_reads_theme_name_from_stylesheet_header(): void
    {
        $css = <<<'CSS'
            /*
            Theme Name: Hello Elementor
            Theme URI: https://elementor.com/hello-theme/
            Description: A lightweight theme
            Version: 3.1.1
            */
            body { margin: 0; }
            CSS;

        $this->assertSame('Hello Elementor', (new WordpressThemeParser)->nameFromStylesheet($css));
    }

    public function test_ignores_stylesheet_without_theme_name(): void
    {
        $this->assertNull((new WordpressThemeParser)->nameFromStylesheet('body{margin:0}'));
    }

    public function test_keeps_theme_folder_case_and_ignores_asset_hrefs(): void
    {
        $html = <<<'HTML'
            <body class="home wp-theme-Total">
            <link rel="preload" href="https://cliente.example/wp-content/themes/Total/assets/lib/ticons/fonts/ticons.woff2" as="font">
            </body>
            HTML;

        $theme = (new WordpressThemeParser)->fromHtml($html);

        $this->assertNotNull($theme);
        $this->assertSame('Total', $theme->slug);
        $this->assertNull($theme->stylesheetUrl);
        $this->assertSame('Total', $theme->displayName());
    }
}

<?php

declare(strict_types=1);

namespace LowWidontTests;

use Low\Widont\FluxCapacitor\Conduit\StaticCache;

final class LowWidontTest extends TestCase
{
    /**
     * Load the actual plugin and give its parent realistic cached addon settings.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 3) . '/low_widont/pi.low_widont.php';
        StaticCache::put('app_settings', ['version' => '4.1.1', 'name' => 'Low Widon’t']);
    }

    /**
     * Replace only the final whitespace run and trim trailing ASCII whitespace.
     *
     * @dataProvider textCases
     * @param mixed $input
     * @param string $expected
     * @return void
     */
    public function testFormatsTheFinalWord($input, $expected): void
    {
        $plugin = new \Low_widont($input);
        $this->assertSame($expected, $plugin->return_data);
        $this->assertSame('4.1.1', $plugin->version);
        $this->assertInstanceOf('Low\Widont\FluxCapacitor\FluxCapacitor', $plugin->flux);
    }

    /**
     * Supply meaningful spacing, markup, coercion, boundary and fallback vectors.
     *
     * @return array
     */
    public function textCases(): array
    {
        return [
            'two words' => ['Hello world', 'Hello&#160;world'],
            'many words' => ['One two three four', 'One two three&#160;four'],
            'single word' => ['Solo', 'Solo'],
            'leading whitespace' => ['  Solo', '&#160;Solo'],
            'trailing whitespace' => ["Hello world \t\r\n\0\x0b", 'Hello&#160;world'],
            'tab before word' => ["Hello\tworld", 'Hello&#160;world'],
            'newline before word' => ["Hello\nworld", 'Hello&#160;world'],
            'whitespace only' => [" \t\n", ''],
            'unicode words' => ['Bonjour à tous', 'Bonjour à&#160;tous'],
            'markup' => ['Hello <strong>world</strong>', 'Hello&#160;<strong>world</strong>'],
            'existing entity' => ['Hello&#160;world', 'Hello&#160;world'],
            'nonbreaking UTF8' => ["Hello\xc2\xa0world", "Hello\xc2\xa0world"],
            'numeric string' => ['42', '42'],
            'fraction' => [2.5, '2.5'],
            'integer' => [42, '42'],
            'true' => [true, '1'],
            'empty uses tagdata' => ['', 'Template final&#160;words'],
            'null uses tagdata' => [null, 'Template final&#160;words'],
            'false uses tagdata' => [false, 'Template final&#160;words'],
            'zero uses tagdata' => [0, 'Template final&#160;words'],
            'zero string uses tagdata' => ['0', 'Template final&#160;words'],
            'empty array uses tagdata' => [[], 'Template final&#160;words'],
            'oversized string' => [str_repeat('a ', 10000) . 'last', str_repeat('a ', 9999) . 'a&#160;last'],
        ];
    }

    /**
     * Preserve the empty template result when no explicit text exists.
     *
     * @return void
     */
    public function testEmptyTemplateStaysEmpty(): void
    {
        Environment::$ee->TMPL->tagdata = '';
        $this->assertSame('', (new \Low_widont())->return_data);
    }

    /**
     * Preserve valid template string bodies through the ordinary tag-pair invocation.
     *
     * @dataProvider templateBodies
     * @param string $body
     * @param string $expected
     * @return void
     */
    public function testFormatsTemplateBodies($body, $expected): void
    {
        Environment::$ee->TMPL->tagdata = $body;
        $this->assertSame($expected, (new \Low_widont())->return_data);
    }

    /**
     * Supply zero, trimming, one-word and final-word template boundaries.
     *
     * @return array
     */
    public function templateBodies(): array
    {
        return [
            'zero body' => ['0', '0'],
            'trimmed zero body' => ["0 \t\n", '0'],
            'one word body' => ['Solo', 'Solo'],
            'final word body' => ['Template final words', 'Template final&#160;words'],
        ];
    }

    /**
     * Reject structured text through the native string function contract.
     *
     * @return void
     */
    public function testStructuredTextIsRejected(): void
    {
        $this->expectException(PHP_VERSION_ID < 80000 ? \ErrorException::class : \TypeError::class);
        $this->expectExceptionMessage('rtrim()');
        new \Low_widont(['text']);
    }
}

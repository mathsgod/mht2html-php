<?php

declare(strict_types=1);

namespace Mht2Html\Tests;

use Mht2Html\Converter;
use PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase
{
    private Converter $converter;

    protected function setUp(): void
    {
        $this->converter = new Converter();
    }

    private function requireMailparse(): void
    {
        if (!extension_loaded('mailparse')) {
            $this->markTestSkipped('ext-mailparse is not installed.');
        }
    }

    public function testConvertFileThrowsOnMissingFile(): void
    {
        $converter = new Converter();
        $this->expectException(\InvalidArgumentException::class);
        $converter->convertFile('/nonexistent/file.mht');
    }

    public function testConvertStringReturnsHtml(): void
    {
        $this->requireMailparse();
        // Minimal valid MHTML with a simple HTML body
        $boundary = 'boundary_test_001';
        $mht = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/related; boundary="' . $boundary . '"',
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset="utf-8"',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            '<html><body><p>Hello</p></body></html>',
            '--' . $boundary . '--',
        ]);

        $html = $this->converter->convertString($mht);

        $this->assertStringContainsString('Hello', $html);
    }

    public function testImagesAreEmbeddedAsDataUris(): void
    {
        $this->requireMailparse();
        // 1×1 transparent GIF
        $gifBinary = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $boundary  = 'boundary_test_002';
        $imageName = 'dot.gif';

        $mht = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/related; boundary="' . $boundary . '"',
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset="utf-8"',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            '<html><body><img src="' . $imageName . '"></body></html>',
            '',
            '--' . $boundary,
            'Content-Type: image/gif',
            'Content-Transfer-Encoding: base64',
            'Content-Location: ' . $imageName,
            '',
            base64_encode($gifBinary),
            '--' . $boundary . '--',
        ]);

        $html = $this->converter->convertString($mht);

        $this->assertStringContainsString('data:image/gif;base64,', $html);
        $this->assertStringNotContainsString('src="' . $imageName . '"', $html);
    }
}

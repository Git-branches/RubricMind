<?php

namespace Tests\Unit;

use App\Libraries\DocumentText;
use App\Libraries\DocumentTextException;
use CodeIgniter\Test\CIUnitTestCase;
use ZipArchive;

/**
 * Reading essay text from uploaded PDF and DOCX files (FR-05).
 * The files are built here, so the tests need no sample documents.
 *
 * @internal
 */
final class DocumentTextTest extends CIUnitTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function tempFile(string $contents): string
    {
        $path          = tempnam(sys_get_temp_dir(), 'rm-doc-');
        $this->files[] = $path;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * A minimal Word document; each item is one paragraph, given as its inner XML.
     */
    public static function docx(array $paragraphXml, ?string $documentXml = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rm-docx-');
        $zip  = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', $documentXml ?? (
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . implode('', array_map(static fn ($p) => "<w:p>{$p}</w:p>", $paragraphXml))
            . '</w:body></w:document>'));
        $zip->close();

        return $path;
    }

    /**
     * A one-page PDF that draws each line of text with the built-in Helvetica font.
     */
    public static function pdf(array $lines): string
    {
        $escape  = static fn (string $s) => strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $content = "BT /F1 12 Tf 72 720 Td\n";

        foreach ($lines as $i => $line) {
            $content .= ($i === 0 ? '' : "0 -16 Td\n") . '(' . $escape($line) . ") Tj\n";
        }

        $content .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($content) . " >>\nstream\n{$content}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $pdf     = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf      .= ($i + 1) . " 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    public function testReadsDocxParagraphs(): void
    {
        $path          = self::docx([
            '<w:r><w:t>School should start later.</w:t></w:r>',
            '<w:r><w:t xml:space="preserve">Teens need </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>more sleep</w:t></w:r><w:r><w:t>.</w:t></w:r>',
            '',
            '<w:r><w:delText>Deleted words.</w:delText></w:r><w:r><w:t>In conclusion, we should try it.</w:t></w:r>',
        ]);
        $this->files[] = $path;

        $text = DocumentText::extract($path, 'docx');

        $this->assertSame("School should start later.\n\nTeens need more sleep.\n\nIn conclusion, we should try it.", $text);
    }

    public function testRejectsDocxWithDoctype(): void
    {
        $path          = self::docx([], '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY a "aaaa">]><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
        $this->files[] = $path;

        $this->expectException(DocumentTextException::class);
        DocumentText::extract($path, 'docx');
    }

    public function testRejectsFilesThatAreNotWhatTheySay(): void
    {
        $fake = $this->tempFile('This is just text, not a document.');

        foreach (['docx', 'pdf'] as $extension) {
            try {
                DocumentText::extract($fake, $extension);
                $this->fail("A fake .{$extension} was accepted.");
            } catch (DocumentTextException $e) {
                $this->assertStringContainsString('not a valid', $e->getMessage());
            }
        }
    }

    public function testEmptyDocxIsRejected(): void
    {
        $path          = self::docx(['', '<w:r><w:t>   </w:t></w:r>']);
        $this->files[] = $path;

        $this->expectExceptionMessage('This Word file has no text in it.');
        DocumentText::extract($path, 'docx');
    }

    public function testReadsPdfText(): void
    {
        $path = $this->tempFile(self::pdf([
            'School should start later because students need',
            'more sleep to learn well.',
            '',
            'In conclusion (as research shows), we should try it.',
        ]));

        $text = DocumentText::extract($path, 'pdf');

        $this->assertStringContainsString('School should start later because students need more sleep to learn well.', $text);
        $this->assertStringContainsString('In conclusion (as research shows), we should try it.', $text);
    }

    public function testDamagedPdfGivesFriendlyMessage(): void
    {
        $path = $this->tempFile("%PDF-1.4\nthis is not really a pdf");

        try {
            DocumentText::extract($path, 'pdf');
            $this->fail('A damaged PDF was accepted.');
        } catch (DocumentTextException $e) {
            $this->assertStringContainsString('PDF', $e->getMessage());
        }
    }

    public function testReflowJoinsPrintedLinesIntoParagraphs(): void
    {
        $printed = "Many students are tired in the morning because their classes begin\n"
            . "very early, and research shows that teenagers need more sleep than\n"
            . "adults do.\n"
            . "3\n"
            . "Starting later would also improve attendance and help students focus\n"
            . "on difficult subjects like mathematics. It is an impor-\n"
            . "tant change.";

        $this->assertSame(
            'Many students are tired in the morning because their classes begin very early, and research shows that teenagers need more sleep than adults do.'
            . "\n\n"
            . 'Starting later would also improve attendance and help students focus on difficult subjects like mathematics. It is an important change.',
            DocumentText::reflowPdfText($printed),
        );
    }
}

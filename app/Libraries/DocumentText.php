<?php

namespace App\Libraries;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Smalot\PdfParser\Config as PdfConfig;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * Reads the essay text out of an uploaded PDF or Word (DOCX) file (FR-05).
 *
 * Only text that is stored as text can be read: scanned or photographed pages
 * have none (no OCR, see §11). Paragraphs are separated by a blank line, like a
 * typed essay, so word counts and paragraph checks work the same way.
 *
 * Every failure throws DocumentTextException with a message for the student.
 */
final class DocumentText
{
    public const EXTENSIONS = ['pdf', 'docx'];

    // Limits against "zip bombs" and very large documents.
    private const MAX_XML_BYTES       = 20 * 1024 * 1024;
    private const PDF_DECODE_MEMORY   = 64 * 1024 * 1024;
    private const MAX_TEXT_CHARACTERS = 200000;

    /**
     * @param string $extension 'pdf' or 'docx' (lowercase)
     *
     * @throws DocumentTextException
     */
    public static function extract(string $path, string $extension): string
    {
        $text = match ($extension) {
            'pdf'   => self::fromPdf($path),
            'docx'  => self::fromDocx($path),
            default => throw new DocumentTextException('Upload a PDF or Word (.docx) file.'),
        };

        $text = self::clean($text);

        if (preg_match('/[\p{L}\p{N}]/u', $text) !== 1) {
            throw new DocumentTextException($extension === 'pdf'
                ? 'No text could be read from this PDF. It may be a scanned or photographed page. Type or paste your essay instead.'
                : 'This Word file has no text in it. Check that you chose the right file.');
        }

        return mb_substr($text, 0, self::MAX_TEXT_CHARACTERS);
    }

    private static function fromDocx(string $path): string
    {
        if (self::magic($path, 4) !== "PK\x03\x04") {
            throw new DocumentTextException('This file is not a valid Word (.docx) file. Older .doc files are not supported: save it as .docx or PDF first.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new DocumentTextException('This Word file could not be opened. It may be damaged.');
        }

        try {
            $stat = $zip->statName('word/document.xml');

            if ($stat === false) {
                throw new DocumentTextException('This file is not a Word document.');
            }

            if ($stat['size'] > self::MAX_XML_BYTES) {
                throw new DocumentTextException('This Word file is too large to read.');
            }

            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        // A real document.xml never has a DOCTYPE; refusing one blocks entity tricks.
        if (! is_string($xml) || stripos($xml, '<!DOCTYPE') !== false) {
            throw new DocumentTextException('This Word file could not be read.');
        }

        $dom = new DOMDocument();

        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new DocumentTextException('This Word file could not be read. It may be damaged.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = [];

        // Body paragraphs, including those in tables. w:t is visible text; deleted
        // tracked changes (w:delText) and field codes (w:instrText) are skipped.
        foreach ($xpath->query('//w:body//w:p') as $paragraph) {
            $text = '';

            foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $paragraph) as $node) {
                /** @var DOMElement $node */
                $text .= match ($node->localName) {
                    't'     => $node->textContent,
                    'tab'   => ' ',
                    default => "\n",
                };
            }

            $paragraphs[] = $text;
        }

        return implode("\n\n", $paragraphs);
    }

    private static function fromPdf(string $path): string
    {
        if (self::magic($path, 5) !== '%PDF-') {
            throw new DocumentTextException('This file is not a valid PDF.');
        }

        $config = new PdfConfig();
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(self::PDF_DECODE_MEMORY);

        try {
            $text = (new PdfParser([], $config))->parseFile($path)->getText();
        } catch (Throwable $e) {
            throw new DocumentTextException(str_contains(strtolower($e->getMessage()), 'secured')
                ? 'This PDF is password-protected. Remove the password, or type or paste your essay instead.'
                : 'This PDF could not be read. It may be damaged. Try saving it again, or type or paste your essay instead.');
        }

        return self::reflowPdfText($text);
    }

    /**
     * PDF text comes as one line per printed line. Joins lines back into
     * paragraphs: a paragraph ends at a blank line, or at a short line that ends
     * a sentence (the last line of a paragraph rarely reaches the right margin).
     */
    public static function reflowPdfText(string $text): string
    {
        $lines = array_map(
            static fn (string $line) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line) ?? ''),
            preg_split('/\R|\f/u', $text) ?: [],
        );

        $lengths = array_filter(array_map('mb_strlen', $lines));
        sort($lengths);
        // The typical full line: the 80th-percentile length.
        $fullLine = $lengths === [] ? 0 : $lengths[(int) floor((count($lengths) - 1) * 0.8)];

        $paragraphs = [];
        $current    = '';

        foreach ($lines as $line) {
            // Blank lines end a paragraph; lone page numbers are dropped.
            if ($line === '' || preg_match('/^(?:page\s*)?\d{1,4}(?:\s*(?:of|\/)\s*\d{1,4})?$/iu', $line) === 1) {
                if ($line === '' && $current !== '') {
                    $paragraphs[] = $current;
                    $current      = '';
                }

                continue;
            }

            if ($current === '') {
                $current = $line;
            } elseif (preg_match('/\p{L}-$/u', $current) === 1 && preg_match('/^\p{Ll}/u', $line) === 1) {
                // A word split across lines with a hyphen: "impor-" + "tant".
                $current = mb_substr($current, 0, -1) . $line;
            } else {
                $current .= ' ' . $line;
            }

            $endsSentence = preg_match('/[.!?]["”’)\]]?$/u', $line) === 1;

            if ($endsSentence && mb_strlen($line) < $fullLine * 0.75) {
                $paragraphs[] = $current;
                $current      = '';
            }
        }

        if ($current !== '') {
            $paragraphs[] = $current;
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * Plain essay text: no control characters or ligatures, single spaces,
     * and paragraphs separated by exactly one blank line.
     */
    private static function clean(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1');
        }

        $text = strtr($text, ['ﬀ' => 'ff', 'ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬃ' => 'ffi', 'ﬄ' => 'ffl', "\u{00AD}" => '']);
        $text = preg_replace('/[^\P{C}\n]/u', '', str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $text)) ?? '';
        $text = preg_replace('/[ \x{00A0}\x{2000}-\x{200B}\x{3000}]+/u', ' ', $text) ?? '';
        $text = preg_replace('/ *\n */u', "\n", $text) ?? '';
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? '';

        return trim($text);
    }

    private static function magic(string $path, int $bytes): string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new DocumentTextException('The uploaded file could not be opened. Please try again.');
        }

        $start = (string) fread($handle, $bytes);
        fclose($handle);

        return $start;
    }
}

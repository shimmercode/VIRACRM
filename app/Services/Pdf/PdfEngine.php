<?php

namespace App\Services\Pdf;

use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/**
 * REQ-09.05 / REQ-07.03 — the product's one PDF engine.
 *
 * WHY mPDF
 * --------
 * The output has to be Persian, right-to-left, with correct letter joining. That
 * is not a font problem alone: Arabic script needs contextual shaping (initial,
 * medial, final, isolated forms) and a bidi pass for the Latin codes and digits
 * that sit inside Persian sentences. mPDF does both internally against the font's
 * real GSUB/GPOS tables, so Vazirmatn renders as Vazirmatn rather than as a run
 * of disconnected letters.
 *
 * THE FOUR THINGS THIS CLASS GUARANTEES
 * -------------------------------------
 *  1. Vazirmatn, embedded, from the same file the web UI uses.
 *  2. Right alignment. `directionality => 'rtl'` sets the bidi BASE direction but
 *     NOT the horizontal alignment — a paragraph still starts at the left margin
 *     unless told otherwise. That combination (correct letter joining over
 *     left-aligned text) looks close enough to pass a glance and is wrong on
 *     paper, so alignment is set explicitly in the stylesheet below.
 *  3. No filesystem writes outside one temp directory, and that directory is
 *     removed afterwards. mPDF needs a writable tempDir for its font cache; a
 *     web request that leaks temp files fills the disk one payslip at a time.
 *  4. The bytes are returned, never written to a public path. Every export is
 *     behind a permission check and a no-store response; a PDF in public/ is the
 *     one file nobody remembers to protect.
 *
 * NOT A TEMPLATE ENGINE
 * ---------------------
 * The HTML handed to render() is built by the calling service from typed values.
 * It is not user-authored HTML, and nothing here accepts a template body. See
 * ContractRenderer for the reasoning about administrator-typed contract text.
 */
final class PdfEngine
{
    /** A4 in points, used for the header/footer margins. */
    private const FONT = 'vazirmatn';

    /**
     * Render a complete HTML document to PDF bytes.
     *
     * @param  array{title?: string, subject?: string, keywords?: string}  $meta
     * @param  string  $css  per-document overrides, appended after the base
     *                       stylesheet. Documents have different densities: a
     *                       contract is prose and wants air, a payslip has eight
     *                       tables and has to stay on one page.
     */
    public function render(string $html, array $meta = [], string $css = ''): string
    {
        $tempDir = $this->tempDir();

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'tempDir' => $tempDir,
                'fontDir' => [$this->fontDir()],
                'fontdata' => [
                    self::FONT => [
                        'R' => 'Vazirmatn-Regular.ttf',
                        'B' => 'Vazirmatn-Bold.ttf',
                        // useOTL applies the OpenType layout features that turn
                        // isolated Arabic letters into joined words. Without it
                        // the text is still "correct" and completely unreadable.
                        'useOTL' => 0xFF,
                        'useKashida' => 75,
                    ],
                ],
                'default_font' => self::FONT,
                'default_font_size' => 11,
                'directionality' => 'rtl',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'margin_left' => 18,
                'margin_right' => 18,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'margin_footer' => 12,
            ]);

            $mpdf->SetTitle($meta['title'] ?? 'VIRA CRM');
            $mpdf->SetAuthor('VIRA CRM');
            if (isset($meta['subject'])) {
                $mpdf->SetSubject($meta['subject']);
            }

            /*
             * The document title as PDF metadata, and nothing else: mPDF writes
             * the *page* header/footer from here. Each document sets its own.
             */
            $mpdf->WriteHTML($this->stylesheet() . $css . $html);
            $mpdf->SetDisplayMode('fullwidth');

            return $mpdf->Output('', Destination::STRING_RETURN);
        } finally {
            $this->cleanup($tempDir);
        }
    }

    /**
     * The base stylesheet every document shares.
     *
     * Persians text is right-aligned here, in one place, rather than in each
     * document template — a template that forgets `text-align` produces
     * left-aligned Persian that nobody notices until it is printed.
     */
    public function stylesheet(): string
    {
        return <<<'CSS'
        <style>
            html, body { direction: rtl; }
            body {
                font-family: vazirmatn;
                font-size: 10.5pt;
                line-height: 1.9;
                /* directionality sets the bidi base; alignment is a separate
                   property and defaults to left. */
                text-align: right;
                color: #1a1a1a;
            }
            h1 { font-size: 16pt; margin: 0 0 4mm; }
            h2 { font-size: 12pt; margin: 6mm 0 2mm; }
            p  { margin: 0 0 2.5mm; }
            .doc-meta { font-size: 9pt; color: #555; }
            .doc-head { border-bottom: 1px solid #ccc; padding-bottom: 3mm; margin-bottom: 5mm; }
            .doc-foot { border-top: 1px solid #ccc; margin-top: 8mm; padding-top: 3mm; font-size: 8.5pt; color: #666; page-break-inside: avoid; }
            table { width: 100%; border-collapse: collapse; margin: 2mm 0 4mm; }
            th, td { border: 1px solid #bbb; padding: 1.6mm 2mm; text-align: right; vertical-align: top; }
            th { background: #f2f2f2; font-weight: bold; }
            /* Figures are read right-to-left in Persian accounting but each
               number itself is LTR, so numeric cells are aligned to the start
               of the cell in bidi terms without reversing their digits. */
            td.num, th.num { text-align: left; font-variant-numeric: tabular-nums; }
            tfoot td { font-weight: bold; background: #fafafa; }
            .ltr { direction: ltr; unicode-bidi: isolate; text-align: left; }
            .sign { margin-top: 12mm; width: 100%; page-break-inside: avoid; }
            .sign td { border: 0; border-top: 1px solid #999; padding-top: 2mm; text-align: center; font-size: 9pt; }
            .muted { color: #666; }
            .notice { border: 1px solid #c00; background: #fff5f5; padding: 2.5mm; margin: 3mm 0; font-size: 9.5pt; }
            .total { font-size: 11.5pt; font-weight: bold; }
        </style>
        CSS;
    }

    /** Absolute path to the bundled Vazirmatn TTFs. */
    private function fontDir(): string
    {
        $dir = base_path('docs/assets/fonts');

        if (! is_file($dir . '/Vazirmatn-Regular.ttf')) {
            // Silently falling back to a core font would produce a PDF full of
            // empty boxes, which is worse than an error the deploy notices.
            throw new RuntimeException('Vazirmatn fonts are missing from docs/assets/fonts.');
        }

        return $dir;
    }

    /**
     * A private temp directory for this render.
     *
     * Deliberately NOT the shared system temp dir: mPDF writes a font cache and
     * intermediate files there, and on a shared host that is a place another
     * tenant can read. It is created per call and removed in finally{}.
     */
    private function tempDir(): string
    {
        $base = storage_path('app/pdf-tmp');
        $dir = $base . '/run-' . bin2hex(random_bytes(8));

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create PDF temp directory: ' . $dir);
        }

        return $dir;
    }

    private function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    /* ── Small helpers the document builders share ─────────────────────── */

    /** A Persian-formatted amount, or an em dash when the user may not see money. */
    public static function money(?int $rial): string
    {
        if ($rial === null) {
            return '—';
        }

        return Money::fromRial($rial)->formatPersian();
    }

    /** Persian digits for identifiers, counts and dates. */
    public static function digits(string|int $value): string
    {
        return Money::toPersianDigits((string) $value);
    }

    /** Escape for HTML with ENT_QUOTES; every dynamic value goes through this. */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

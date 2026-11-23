<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkshopExport
{
    public const PDF_ROWS_PER_BATCH = 300;

    private const PDF_CHARACTERS_PER_BATCH = 9000;

    private const PDF_CELLS_PER_BATCH = 1500;

    private const COMPLEX_TEXT = '/[\p{Arabic}\p{Hebrew}\p{Syriac}\p{Thaana}\p{Nko}\p{Adlam}'
        .'\p{Devanagari}\p{Bengali}\p{Gurmukhi}\p{Gujarati}\p{Oriya}\p{Tamil}\p{Telugu}'
        .'\p{Kannada}\p{Malayalam}\p{Sinhala}\p{Thai}\p{Lao}\p{Tibetan}\p{Myanmar}'
        .'\p{Khmer}\p{Mongolian}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    public function download(string $format, string $title, array $headings, iterable $rows, int $batch = 1, string $context = ''): Response|StreamedResponse
    {
        $filename = str($title)->slug()->toString();
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headings, $rows) {
                $output = fopen('php://output', 'w');
                if ($output === false) {
                    throw new RuntimeException('Unable to open the CSV output stream.');
                }
                $this->writeCsv($output, $headings);
                foreach ($rows as $row) {
                    $this->writeCsv($output, $row);
                }
                fclose($output);
            }, $filename.'.csv', $headers + ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $selected = [];
        $more = false;
        $currentBatch = 1;
        $rowCount = 0;
        $characters = 0;
        $cells = 0;
        foreach ($this->pdfRows($rows) as $row) {
            $size = array_sum(array_map(fn ($value) => mb_strlen($value), $row));
            if ($rowCount === self::PDF_ROWS_PER_BATCH || ($rowCount > 0
                && ($characters + $size > self::PDF_CHARACTERS_PER_BATCH || $cells + count($row) > self::PDF_CELLS_PER_BATCH))) {
                $currentBatch++;
                $rowCount = 0;
                $characters = 0;
                $cells = 0;
            }
            if ($currentBatch > $batch) {
                $more = true;
                break;
            }
            $rowCount++;
            $characters += $size;
            $cells += count($row);
            if ($currentBatch === $batch) {
                $selected[] = $row;
            }
        }
        abort_if($batch > 1 && count($selected) === 0, 404, 'This PDF batch has no rows.');

        $options = new Options;
        $options->setFontDir(resource_path('fonts'));
        $options->setFontCache(storage_path('framework/cache'));
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->setPaper('a4', 'landscape');
        $text = implode("\n", array_merge([$title, $context], $headings, ...$selected));
        $complex = preg_match(self::COMPLEX_TEXT, $text);
        if ($complex === false) {
            throw new RuntimeException('Unable to validate PDF text: '.preg_last_error_msg());
        }
        abort_if($complex === 1, 422, 'This PDF contains text that cannot be rendered reliably. Download the CSV instead.');
        foreach ($pdf->getFontMetrics()->mapTextToFonts($text, ['DejaVu Sans', 'DroidFallback']) as $mapping) {
            abort_if($mapping['font'] === null, 422, 'This PDF contains characters not supported by its fonts. Download the CSV instead.');
        }
        $pdf->loadHtml(view('exports.pdf', compact('title', 'context', 'headings', 'batch', 'more') + ['rows' => $selected])->render(), 'UTF-8');
        $pdf->render();

        return response($pdf->output(), 200, $headers + [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'-batch-'.$batch.'.pdf"',
        ]);
    }

    private function writeCsv($output, array $row): void
    {
        $safe = array_map(function ($value) {
            if (is_string($value) && preg_match('/^[\\p{Z}\\x00-\\x20\\x{FEFF}]*[=+@\\-\\x09\\x0a\\x0d]/u', $value)) {
                return "'".$value;
            }

            return $value;
        }, $row);
        if (fputcsv($output, $safe, ',', '"', '') === false) {
            throw new RuntimeException('Unable to write the CSV export.');
        }
    }

    private function pdfRows(iterable $rows): iterable
    {
        foreach ($rows as $row) {
            $length = max(array_map(fn ($value) => mb_strlen((string) $value), $row));
            $parts = max(1, (int) ceil($length / 160));
            for ($part = 0; $part < $parts; $part++) {
                $cells = array_map(fn ($value) => mb_substr((string) $value, $part * 160, 160), $row);
                if ($part > 0 && $cells[0] === '') {
                    $cells[0] = mb_substr((string) $row[0], 0, 160).' (cont.)';
                }
                yield $cells;
            }
        }
    }
}

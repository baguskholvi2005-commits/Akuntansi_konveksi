<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExporter
{
    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    /** Teks selalu disimpan sebagai teks, supaya isi seperti "=1+1" tidak dijalankan sebagai rumus. */
    private function put($ws, string $coord, string $value): void
    {
        $ws->setCellValueExplicit($coord, $value, 's');
    }

    public function download(array $r, string $file): StreamedResponse
    {
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $ws->setTitle(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $r['title']), 0, 31));

        $this->put($ws, 'A1', \App\Models\Setting::val('company_name'));
        $this->put($ws, 'A2', $r['title']);
        $this->put($ws, 'A3', $r['subtitle']);
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getStyle('A2')->getFont()->setBold(true)->setSize(12);

        $line   = 5;
        $widths = [];

        foreach ($r['tables'] as $t) {
            if (!empty($t['heading'])) {
                $this->put($ws, 'A' . $line, $t['heading']);
                $ws->getStyle('A' . $line)->getFont()->setBold(true);
                $line++;
            }

            $last = $this->columnName(count($t['columns']));

            // Baris judul kolom
            foreach ($t['columns'] as $i => $c) {
                $coord = $this->columnName($i + 1) . $line;
                $this->put($ws, $coord, $c['label']);
                if ($c['type'] !== 'text') {
                    $ws->getStyle($coord)->getAlignment()->setHorizontal('right');
                }
                $widths[$i] = max($widths[$i] ?? 10, mb_strlen($c['label']) + 2);
            }
            $ws->getStyle("A{$line}:{$last}{$line}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E9ECEF']],
            ]);
            $line++;

            // Isi tabel
            foreach ($t['rows'] as $row) {
                foreach ($row['cells'] as $i => $v) {
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $coord = $this->columnName($i + 1) . $line;

                    if (is_int($v) || is_float($v)) {
                        // Angka disimpan sebagai angka asli, jadi bisa dijumlahkan di Excel
                        $ws->setCellValue($coord, $v);
                        $ws->getStyle($coord)->getNumberFormat()->setFormatCode(
                            ($t['columns'][$i]['type'] ?? 'money') === 'qty' ? '#,##0.00' : '#,##0'
                        );
                        $len = 16;
                    } else {
                        $this->put($ws, $coord, (string) $v);
                        $len = mb_strlen((string) $v) + ($i === 0 ? $row['indent'] * 2 : 0);
                    }
                    $widths[$i] = min(max($widths[$i] ?? 10, $len + 2), 60);
                }

                if ($row['bold']) {
                    $ws->getStyle("A{$line}:{$last}{$line}")->getFont()->setBold(true);
                }
                if ($row['indent'] > 0) {
                    $ws->getStyle('A' . $line)->getAlignment()
                       ->setHorizontal('left')->setIndent($row['indent']);
                }
                $line++;
            }

            $line++;   // satu baris kosong antar tabel
        }

        foreach ($widths as $i => $w) {
            $ws->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }

        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $file . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
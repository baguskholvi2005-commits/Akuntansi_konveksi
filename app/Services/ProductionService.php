<?php

namespace App\Services;

use App\Models\{Production, ProductionPayment};
use Illuminate\Support\Facades\DB;

class ProductionService
{
    public function __construct(
        private InventoryService $inv,
        private JournalService $journal,
        private LedgerService $ledger
    ) {}

    /** Sisa utang produksi ini: tenaga kerja (2103) dan overhead (2101). */
    public function outstanding(Production $p): array
    {
        $paid = ProductionPayment::where('production_id', $p->id)
            ->groupBy('kind')->selectRaw('kind, SUM(amount) AS s')->pluck('s', 'kind');

        return [
            'labor'    => round((float) $p->labor_payable - (float) ($paid['labor'] ?? 0), 2),
            'overhead' => round((float) $p->overhead_payable - (float) ($paid['overhead'] ?? 0), 2),
        ];
    }

    /** TAHAP 1: bahan baku keluar dari gudang, masuk Barang Dalam Proses. */
    public function start(Production $production): void
    {
        DB::transaction(function () use ($production) {
            $p = Production::with(['materials.item', 'item'])->lockForUpdate()->findOrFail($production->id);

            if ($p->status !== 'draft') {
                throw new \DomainException('Produksi ini sudah dimulai.');
            }
            if ($p->materials->isEmpty()) {
                throw new \DomainException('Produksi tidak memiliki bahan baku.');
            }

            $materialCost = 0;
            foreach ($p->materials as $m) {
                $value = $this->inv->out(
                    $m->item_id, $m->qty, $p->date, $p->number,
                    "Pemakaian bahan untuk produksi {$p->item->name}", $p
                );

                $m->update([
                    'unit_cost' => $m->qty > 0 ? round($value / $m->qty, 4) : 0,
                    'total'     => $value,
                ]);
                $materialCost += $value;
            }

            if ($materialCost <= 0) {
                throw new \DomainException('Nilai bahan baku nol. Pastikan bahan sudah pernah dibeli.');
            }

            $this->journal->post($p->date, "Pemakaian bahan baku produksi {$p->number}", [
                ['account' => '1302', 'debit'  => $materialCost],
                ['account' => '1301', 'credit' => $materialCost],
            ], $p, 'material');

            $p->update(['material_cost' => $materialCost, 'status' => 'proses']);
        });
    }

    /** TAHAP 2: catat tenaga kerja dan overhead, lalu pindahkan ke Barang Jadi. */
    public function finish(Production $production, string $date, float $labor, float $overhead,
                           string $laborSource, string $overheadSource): void
    {
        $laborAcc    = ['kas' => '1101', 'bank' => '1102', 'utang' => '2103'];
        $overheadAcc = ['kas' => '1101', 'bank' => '1102', 'utang' => '2101'];

        DB::transaction(function () use ($production, $date, $labor, $overhead,
                                         $laborSource, $overheadSource, $laborAcc, $overheadAcc) {
            $p = Production::with('item')->lockForUpdate()->findOrFail($production->id);

            if ($p->status !== 'proses') {
                throw new \DomainException('Hanya produksi berstatus "proses" yang dapat diselesaikan.');
            }
            if ($date < $p->date) {
                throw new \DomainException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
            }

            $labor    = round($labor, 2);
            $overhead = round($overhead, 2);

            // Pemeriksaan saldo: total yang akan keluar dari tiap akun kas/bank
            $need = [];
            if ($labor > 0 && $laborSource !== 'utang') {
                $need[$laborAcc[$laborSource]] = ($need[$laborAcc[$laborSource]] ?? 0) + $labor;
            }
            if ($overhead > 0 && $overheadSource !== 'utang') {
                $need[$overheadAcc[$overheadSource]] = ($need[$overheadAcc[$overheadSource]] ?? 0) + $overhead;
            }
            foreach ($need as $acc => $amount) {
                $saldo = $this->ledger->balance($acc);
                if ($amount > $saldo) {
                    throw new \DomainException(
                        $this->ledger->name($acc) . ' tidak cukup. Dibutuhkan Rp ' . number_format($amount, 0, ',', '.')
                        . ', saldo Rp ' . number_format($saldo, 0, ',', '.')
                    );
                }
            }

            if ($labor > 0) {
                $this->journal->post($date, "Biaya tenaga kerja langsung {$p->number}", [
                    ['account' => '1302', 'debit'  => $labor],
                    ['account' => $laborAcc[$laborSource], 'credit' => $labor],
                ], $p, 'labor');
            }

            if ($overhead > 0) {
                $this->journal->post($date, "Biaya overhead pabrik {$p->number}", [
                    ['account' => '1302', 'debit'  => $overhead],
                    ['account' => $overheadAcc[$overheadSource], 'credit' => $overhead],
                ], $p, 'overhead');
            }

            $total    = round($p->material_cost + $labor + $overhead, 2);
            $unitCost = $total / $p->qty;

            $this->inv->in(
                $p->item_id, $p->qty, $unitCost, $date, $p->number,
                "Hasil produksi {$p->item->name}", $p
            );

            $this->journal->post($date, "Produksi selesai {$p->number}", [
                ['account' => '1303', 'debit'  => $total],
                ['account' => '1302', 'credit' => $total],
            ], $p, 'finish');

            $p->update([
                'labor_cost'       => $labor,
                'overhead_cost'    => $overhead,
                'labor_payable'    => $laborSource === 'utang' ? $labor : 0,
                'overhead_payable' => $overheadSource === 'utang' ? $overhead : 0,
                'total_cost'       => $total,
                'unit_cost'        => round($unitCost, 4),
                'finished_date'    => $date,
                'status'           => 'selesai',
            ]);
        });
    }

    /** Bayar utang produksi. $kind: 'labor' (Utang Gaji 2103) atau 'overhead' (Utang Usaha 2101). */
    public function pay(Production $production, string $kind, string $date, float $amount, string $accountCode): void
    {
        DB::transaction(function () use ($production, $kind, $date, $amount, $accountCode) {
            $p = Production::lockForUpdate()->findOrFail($production->id);

            if ($p->status !== 'selesai') {
                throw new \DomainException('Utang produksi baru ada setelah produksi diselesaikan.');
            }
            if ($date < $p->finished_date) {
                throw new \DomainException('Tanggal pembayaran tidak boleh lebih awal dari tanggal selesai produksi.');
            }

            $isLabor = $kind === 'labor';
            $left    = $this->outstanding($p)[$kind];
            $amount  = round($amount, 2);

            if ($amount <= 0 || $amount > $left) {
                throw new \DomainException('Nominal tidak valid. Sisa utang: Rp ' . number_format($left, 0, ',', '.'));
            }

            $saldo = $this->ledger->balance($accountCode);
            if ($amount > $saldo) {
                throw new \DomainException(
                    $this->ledger->name($accountCode) . ' tidak cukup. Saldo: Rp ' . number_format($saldo, 0, ',', '.')
                );
            }

            $pay = ProductionPayment::create([
                'production_id' => $p->id, 'date' => $date, 'kind' => $kind,
                'amount' => $amount, 'account_code' => $accountCode, 'created_by' => auth()->id(),
            ]);

            $this->journal->post(
                $date,
                ($isLabor ? 'Bayar utang gaji produksi ' : 'Bayar utang overhead produksi ') . $p->number,
                [
                    ['account' => $isLabor ? '2103' : '2101', 'debit'  => $amount],
                    ['account' => $accountCode,                'credit' => $amount],
                ],
                $pay, 'pay'
            );
        });
    }
}
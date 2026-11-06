<?php

namespace Database\Seeders;

use App\Models\{Customer, Item, Journal, Production, Purchase, SalesOrder, Supplier};
use App\Services\{DocNumber, ExpenseService, OwnerService, ProductionService, PurchaseService, SalesService};
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleDataSeeder extends Seeder
{
    private PurchaseService $purchases;
    private ProductionService $prod;
    private SalesService $sales;
    private ExpenseService $exp;
    private OwnerService $owner;

    /** Tanggal relatif: ($bulanLalu, $tanggal). Tidak pernah melewati hari ini. */
    private function d(int $monthsAgo, int $day): string
    {
        $date = Carbon::today()->startOfMonth()->subMonthsNoOverflow($monthsAgo)->addDays($day - 1);

        return $date->greaterThan(Carbon::today())
            ? Carbon::today()->toDateString()
            : $date->toDateString();
    }

    public function run(): void
    {
        if (Purchase::exists() || Journal::exists()) {
            $this->command->error('Sudah ada data transaksi. Jalankan dulu: php artisan migrate:fresh --seed');
            return;
        }

        $this->purchases = app(PurchaseService::class);
        $this->prod      = app(ProductionService::class);
        $this->sales     = app(SalesService::class);
        $this->exp       = app(ExpenseService::class);
        $this->owner     = app(OwnerService::class);

        // Semua atau tidak sama sekali: jika ada yang gagal, semuanya dibatalkan
        DB::transaction(function () {
            $this->master();
            $this->transactions();
        });

        $this->command->info('Data contoh berhasil dibuat.');
    }

    // ------------------------------------------------------------------
    private function master(): void
    {
        Supplier::firstOrCreate(['code' => 'SUP-002'],
            ['name' => 'CV Aksesoris Jaya', 'phone' => '08131112222', 'address' => 'Semarang']);
        Customer::firstOrCreate(['code' => 'CUS-002'],
            ['name' => 'PT Maju Bersama', 'phone' => '08134445555', 'address' => 'Ungaran']);
        Customer::firstOrCreate(['code' => 'CUS-003'],
            ['name' => 'Koperasi Sekolah Harapan', 'phone' => '08136667777', 'address' => 'Demak']);
    }

    // ------------------------------------------------------------------
    private function transactions(): void
    {
        $kas = '1101';
        $bank = '1102';

        // ===== Modal awal (2 bulan lalu) =====
        $this->owner->create(['date' => $this->d(2, 1), 'type' => 'modal', 'amount' => 60000000, 'account_code' => $kas,  'notes' => 'Modal awal (kas)']);
        $this->owner->create(['date' => $this->d(2, 1), 'type' => 'modal', 'amount' => 40000000, 'account_code' => $bank, 'notes' => 'Modal awal (bank)']);

        // ===== Pembelian 2 bulan lalu =====
        $this->purchase($this->d(2, 3), 'SUP-001', 'tunai',  [['BB-001', 300, 38000], ['BB-003', 20, 14000]]);
        $p2 = $this->purchase($this->d(2, 5), 'SUP-001', 'kredit', [['BB-002', 200, 55000]]);
        $this->purchase($this->d(2, 6), 'SUP-002', 'tunai',  [['BB-004', 10, 120000], ['BB-005', 1000, 500], ['BB-006', 300, 2500]]);

        // ===== Produksi A: Kaos 100 pcs =====
        $a = $this->production($this->d(2, 8), 'PR-003', 100, [['BB-001', 80], ['BB-003', 2]]);
        $this->prod->finish($a, $this->d(2, 12), 1500000, 400000, 'kas', 'kas');

        // ===== Produksi B: PDH 40 pcs =====
        $b = $this->production($this->d(2, 10), 'PR-001', 40, [['BB-002', 60], ['BB-003', 2], ['BB-005', 200]]);
        $this->prod->finish($b, $this->d(2, 16), 1600000, 300000, 'bank', 'kas');

        $this->exp->create(['date' => $this->d(2, 15), 'category' => 'listrik', 'description' => 'Listrik bulan lalu-2', 'amount' => 850000, 'payment_account' => $bank]);
        $this->purchases->pay($p2, $this->d(2, 20), 5000000, $bank);

        // ===== Penjualan 1: tunai, Kaos 60 pcs =====
        $s1 = $this->order($this->d(2, 20), 'CUS-001', 'tunai', [['PR-003', 60, 65000]]);
        $this->sales->pay($s1, $this->d(2, 20), 1000000, $bank);   // uang muka
        $this->sales->pay($s1, $this->d(2, 22), 2900000, $kas);    // pelunasan sebelum kirim
        $this->sales->moveStatus($s1, 'selesai');
        $this->sales->deliver($s1, $this->d(2, 24));

        // ===== Penjualan 2: kredit, PDH 25 pcs, DP 1.500.000 =====
        $s2 = $this->order($this->d(2, 25), 'CUS-002', 'kredit', [['PR-001', 25, 185000]]);
        $this->sales->pay($s2, $this->d(2, 25), 1500000, $kas);
        $this->sales->moveStatus($s2, 'selesai');
        $this->sales->deliver($s2, $this->d(2, 28));

        // ===== Bulan lalu =====
        $p4 = $this->purchase($this->d(1, 5), 'SUP-001', 'kredit', [['BB-001', 220, 42000]]);

        // Produksi C: Jersey 60 pcs
        $c = $this->production($this->d(1, 8), 'PR-002', 60, [['BB-001', 60], ['BB-004', 2], ['BB-003', 2]]);

        // Penjualan 3: kredit, Kaos 40 pcs (stok lama), tanpa DP
        $s3 = $this->order($this->d(1, 10), 'CUS-001', 'kredit', [['PR-003', 40, 65000]]);
        $this->sales->moveStatus($s3, 'dalam_produksi');
        $this->sales->moveStatus($s3, 'selesai');
        $this->sales->deliver($s3, $this->d(1, 11));

        $this->prod->finish($c, $this->d(1, 12), 1800000, 350000, 'kas', 'bank');

        // Produksi D: Kaos 150 pcs
        $dd = $this->production($this->d(1, 14), 'PR-003', 150, [['BB-001', 120], ['BB-003', 5]]);
        $this->prod->finish($dd, $this->d(1, 18), 2400000, 530000, 'kas', 'bank');

        $this->exp->create(['date' => $this->d(1, 15), 'category' => 'listrik', 'description' => 'Listrik bulan lalu-1', 'amount' => 920000, 'payment_account' => $bank]);
        $this->exp->create(['date' => $this->d(1, 15), 'category' => 'air', 'description' => 'Air bulan lalu-1', 'amount' => 150000, 'payment_account' => $kas]);

        $this->sales->pay($s2, $this->d(1, 20), 3125000, $bank);   // pelunasan piutang
        $this->exp->create(['date' => $this->d(1, 20), 'category' => 'transportasi', 'description' => 'Transportasi bahan baku', 'amount' => 400000, 'payment_account' => $kas]);

        // Penjualan 4: tunai, Jersey 40 + PDH 10
        $s4 = $this->order($this->d(1, 22), 'CUS-003', 'tunai', [['PR-002', 40, 120000], ['PR-001', 10, 185000]]);
        $this->sales->pay($s4, $this->d(1, 22), 6650000, $bank);
        $this->sales->moveStatus($s4, 'selesai');
        $this->sales->deliver($s4, $this->d(1, 23));

        $this->exp->create(['date' => $this->d(1, 23), 'category' => 'pengiriman', 'description' => 'Ongkos kirim pesanan', 'amount' => 275000, 'payment_account' => $kas]);

        $this->purchases->pay($p2, $this->d(1, 25), 6000000, $kas);   // melunasi pembelian 2
        $this->purchases->pay($p4, $this->d(1, 25), 3000000, $bank);  // mencicil pembelian 4
        $this->sales->pay($s3, $this->d(1, 27), 1000000, $kas);       // cicilan piutang

        $this->exp->create(['date' => $this->d(1, 28), 'category' => 'gaji', 'description' => 'Gaji staf administrasi', 'amount' => 1000000, 'payment_account' => $bank]);
        $this->owner->create(['date' => $this->d(1, 28), 'type' => 'prive', 'amount' => 2000000, 'account_code' => $kas, 'notes' => 'Prive pemilik']);

        // ===== Bulan ini =====
        // Penjualan 5: kredit, Kaos 80 pcs, DP 2.000.000
        $s5 = $this->order($this->d(0, 3), 'CUS-002', 'kredit', [['PR-003', 80, 65000]]);
        $this->sales->pay($s5, $this->d(0, 3), 2000000, $bank);
        $this->sales->moveStatus($s5, 'selesai');
        $this->sales->deliver($s5, $this->d(0, 4));

        $this->exp->create(['date' => $this->d(0, 3), 'category' => 'administrasi', 'description' => 'ATK dan administrasi', 'amount' => 250000, 'payment_account' => $kas]);
        $this->exp->create(['date' => $this->d(0, 4), 'category' => 'listrik', 'description' => 'Listrik bulan ini', 'amount' => 780000, 'payment_account' => $bank]);

        // Penjualan 6: kredit, Jersey 15 pcs, sudah DP, belum dikirim
        $s6 = $this->order($this->d(0, 4), 'CUS-001', 'kredit', [['PR-002', 15, 120000]]);
        $this->sales->pay($s6, $this->d(0, 4), 500000, $kas);

        // Penjualan 7: tunai, PDH 5 pcs, belum dibayar (menunggu pembayaran)
        $this->order($this->d(0, 4), 'CUS-003', 'tunai', [['PR-001', 5, 185000]]);
    }

    // ------------------------------------------------------------------
    // Pembantu: membuat dokumen lalu memprosesnya lewat service
    // ------------------------------------------------------------------
    private function purchase(string $date, string $supplierCode, string $method, array $items): Purchase
    {
        $sup = Supplier::where('code', $supplierCode)->firstOrFail();
        $p = Purchase::create([
            'number' => DocNumber::make('PB', 'purchases'), 'date' => $date,
            'supplier_id' => $sup->id, 'payment_method' => $method, 'notes' => 'Data contoh',
        ]);

        $total = 0;
        foreach ($items as [$code, $qty, $price]) {
            $sub = round($qty * $price, 2);
            $p->items()->create([
                'item_id' => Item::where('code', $code)->firstOrFail()->id,
                'qty' => $qty, 'unit_price' => $price, 'subtotal' => $sub,
            ]);
            $total += $sub;
        }
        $p->update(['total' => $total]);

        $this->purchases->confirm($p);

        return $p->fresh();
    }

    private function production(string $date, string $productCode, float $qty, array $materials): Production
    {
        $p = Production::create([
            'number' => DocNumber::make('PRD', 'productions'), 'date' => $date,
            'item_id' => Item::where('code', $productCode)->firstOrFail()->id,
            'qty' => $qty, 'notes' => 'Data contoh',
        ]);

        foreach ($materials as [$code, $mq]) {
            $p->materials()->create([
                'item_id' => Item::where('code', $code)->firstOrFail()->id, 'qty' => $mq,
            ]);
        }

        $this->prod->start($p);

        return $p->fresh();
    }

    private function order(string $date, string $customerCode, string $type, array $items): SalesOrder
    {
        $cus = Customer::where('code', $customerCode)->firstOrFail();
        $o = SalesOrder::create([
            'number' => DocNumber::make('SO', 'sales_orders'), 'date' => $date,
            'customer_id' => $cus->id, 'payment_type' => $type, 'notes' => 'Data contoh',
        ]);

        $total = 0;
        foreach ($items as [$code, $qty, $price]) {
            $sub = round($qty * $price, 2);
            $o->items()->create([
                'item_id' => Item::where('code', $code)->firstOrFail()->id,
                'qty' => $qty, 'unit_price' => $price, 'subtotal' => $sub,
            ]);
            $total += $sub;
        }
        $o->update(['total' => $total]);

        return $o->fresh();
    }
}
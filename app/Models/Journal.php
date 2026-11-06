<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    protected $guarded = [];

    // Nama jenis transaksi yang ditampilkan di Jurnal Umum
    public const SOURCES = [
        Purchase::class         => 'Pembelian',
        PurchasePayment::class  => 'Bayar Utang',
        Production::class       => 'Produksi',
        ProductionPayment::class => 'Bayar Utang Produksi',
        SalesOrder::class       => 'Penjualan',
        SalesPayment::class     => 'Pembayaran Pelanggan',
        Expense::class          => 'Pengeluaran',
        OwnerTransaction::class => 'Modal / Prive',
        SalesReturn::class      => 'Retur Penjualan',
        StockAdjustment::class  => 'Penyesuaian Stok',
    ];

    public function lines()      { return $this->hasMany(JournalLine::class); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by'); }
    public function reversalOf() { return $this->belongsTo(self::class, 'reversal_of'); }
    public function reversedBy() { return $this->hasOne(self::class, 'reversal_of'); }

    public function getSourceLabelAttribute(): string
    {
        if (!$this->source_type) {
            return 'Jurnal manual';
        }
        return self::SOURCES[$this->source_type] ?? class_basename($this->source_type);
    }
}

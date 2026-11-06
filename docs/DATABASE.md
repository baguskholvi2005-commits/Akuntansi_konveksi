# Dokumentasi Database SIA Konveksi

Database: MySQL (`sia_konveksi`), dikelola lewat migration Laravel (`database/migrations`).
Prinsip: **semua laporan keuangan dihitung dari `journal_lines`**. Modul lain hanya membuat jurnal
lewat `JournalService`, dan mengubah stok lewat `InventoryService`.

## Daftar Tabel

### Pengguna dan master
| Tabel | Isi | Kolom penting |
|---|---|---|
| `users` | Pengguna dan peran | `email`, `password`, `role` (admin, keuangan, gudang, pemilik) |
| `accounts` | Daftar akun (kode rekening) | `code`, `name`, `type`, `normal_balance`, `is_cash` |
| `units` | Satuan | `name` |
| `categories` | Kategori barang | `name` |
| `suppliers` | Pemasok | `code`, `name`, `phone`, `address` |
| `customers` | Pelanggan | `code`, `name`, `phone`, `address` |
| `items` | Bahan baku dan produk jadi | `type` (raw_material/product), `category_id`, `unit_id`, `sale_price`, `min_stock`, `stock_qty`, `avg_cost` |

### Transaksi
| Tabel | Isi | Kolom penting |
|---|---|---|
| `purchases`, `purchase_items` | Pembelian bahan baku | `supplier_id`, `payment_method`, `total`, `paid_amount`, `status` |
| `purchase_payments` | Pembayaran utang pembelian | `purchase_id`, `amount`, `account_code` |
| `productions`, `production_materials` | Produksi dan bahan terpakai | `item_id`, `qty`, `material_cost`, `labor_cost`, `overhead_cost`, `total_cost`, `unit_cost`, `status` |
| `sales_orders`, `sales_order_items` | Pesanan dan penjualan | `customer_id`, `payment_type`, `total`, `paid_amount`, `status`, `delivered_date` |
| `sales_payments` | Uang muka dan pelunasan | `sales_order_id`, `type` (dp/pelunasan), `amount`, `account_code` |
| `expenses` | Pengeluaran operasional | `category`, `account_code`, `amount`, `payment_account_code`, `proof_path`, `status` |
| `owner_transactions` | Modal dan prive | `type` (modal/prive), `amount`, `account_code`, `status` |

### Akuntansi dan persediaan
| Tabel | Isi | Kolom penting |
|---|---|---|
| `journals` | Kepala jurnal | `number`, `date`, `description`, `source_type`/`source_id` (transaksi asal), `event`, `is_reversal`, `reversal_of` |
| `journal_lines` | Baris debit dan kredit | `journal_id`, `account_id`, `debit`, `credit` |
| `inventory_movements` | Kartu persediaan | `item_id`, `in_*`, `out_*`, `balance_*`, `source_type`/`source_id` |

Tabel sistem Laravel: `migrations`, `sessions`, `cache`, `jobs`, `password_reset_tokens`.

## Relasi Utama

| Dari | Ke | Jenis |
|---|---|---|
| `items.category_id` | `categories.id` | banyak ke satu |
| `items.unit_id` | `units.id` | banyak ke satu |
| `purchases.supplier_id` | `suppliers.id` | banyak ke satu |
| `purchase_items.purchase_id` / `item_id` | `purchases` / `items` | banyak ke satu |
| `purchase_payments.purchase_id` | `purchases.id` | banyak ke satu |
| `productions.item_id` | `items.id` (produk) | banyak ke satu |
| `production_materials.production_id` / `item_id` | `productions` / `items` (bahan) | banyak ke satu |
| `sales_orders.customer_id` | `customers.id` | banyak ke satu |
| `sales_order_items.sales_order_id` / `item_id` | `sales_orders` / `items` | banyak ke satu |
| `sales_payments.sales_order_id` | `sales_orders.id` | banyak ke satu |
| `journal_lines.journal_id` | `journals.id` | banyak ke satu |
| `journal_lines.account_id` | `accounts.id` | banyak ke satu |
| `journals.reversal_of` | `journals.id` | jurnal pembalik menunjuk jurnal asal |
| `inventory_movements.item_id` | `items.id` | banyak ke satu |
| `*.created_by` | `users.id` | pembuat transaksi |

Relasi polimorfik (`source_type`, `source_id`): `journals` dan `inventory_movements` menunjuk ke
transaksi asal (Purchase, PurchasePayment, Production, SalesOrder, SalesPayment, Expense,
OwnerTransaction). Jurnal manual memiliki `source_type` kosong.

## Aturan Integritas

- Jurnal harus seimbang (debit = kredit), dicek di `JournalService::post()`.
- Kombinasi `(source_type, source_id, event)` pada `journals` unik, sehingga satu peristiwa tidak
  bisa dijurnal dua kali.
- Transaksi yang sudah diposting tidak dihapus. Koreksi memakai jurnal pembalik (`reversal_of`).
- `items.stock_qty` dan `avg_cost` hanya diubah oleh `InventoryService`.
- Nilai uang memakai `DECIMAL`, bukan `FLOAT`.
- Nomor dokumen: `PB` (pembelian), `PRD` (produksi), `SO` (pesanan), `EXP` (pengeluaran),
  `MDL`/`PRV` (modal/prive), `JU` (jurnal).

## Pemetaan Jurnal

| Peristiwa | Jurnal |
|---|---|
| Pembelian tunai / kredit | D 1301 / K 1101 atau 2101 |
| Bayar utang | D 2101 / K 1101 atau 1102 |
| Pakai bahan baku produksi | D 1302 / K 1301 |
| Tenaga kerja / overhead | D 1302 / K 1101, 1102, 2103 (gaji) atau 2101 (overhead) |
| Produksi selesai | D 1303 / K 1302 |
| Uang muka pelanggan | D 1101 atau 1102 / K 2102 |
| Barang terkirim | D 2102 + D 1201 / K 4101, lalu D 5101 / K 1303 |
| Pelunasan piutang | D 1101 atau 1102 / K 1201 |
| Beban operasional | D 6101 sampai 6108 / K 1101 atau 1102 |
| Modal / prive | D 1101 atau 1102 / K 3101 ; D 3102 / K 1101 atau 1102 |

Metode persediaan: **rata-rata tertimbang** (konsisten di seluruh modul).
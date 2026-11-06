<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            ['1101','Kas','aset','debit',true],
            ['1102','Bank','aset','debit',true],
            ['1201','Piutang Usaha','aset','debit',false],
            ['1301','Persediaan Bahan Baku','aset','debit',false],
            ['1302','Persediaan Barang Dalam Proses','aset','debit',false],
            ['1303','Persediaan Barang Jadi','aset','debit',false],
            ['2101','Utang Usaha','liabilitas','kredit',false],
            ['2102','Uang Muka Pelanggan','liabilitas','kredit',false],
            ['2103','Utang Gaji','liabilitas','kredit',false],
            ['3101','Modal Pemilik','ekuitas','kredit',false],
            ['3102','Prive','ekuitas','debit',false],
            ['4101','Penjualan','pendapatan','kredit',false],
            ['4102','Retur Penjualan','pendapatan','debit',false],
            ['5101','Harga Pokok Penjualan','hpp','debit',false],
            ['6101','Beban Gaji','beban','debit',false],
            ['6102','Beban Listrik','beban','debit',false],
            ['6103','Beban Air','beban','debit',false],
            ['6104','Beban Transportasi','beban','debit',false],
            ['6105','Beban Pengiriman','beban','debit',false],
            ['6106','Beban Sewa','beban','debit',false],
            ['6107','Beban Administrasi','beban','debit',false],
            ['6108','Beban Lain-lain','beban','debit',false],
            ['6109','Selisih Persediaan','beban','debit',false],
        ];

        foreach ($akun as [$code, $name, $type, $nb, $cash]) {
            Account::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'normal_balance' => $nb, 'is_cash' => $cash]
            );
        }
    }
}
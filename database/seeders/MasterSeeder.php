<?php

namespace Database\Seeders;

use App\Models\{Unit, Category, Supplier, Customer, Item};
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['meter','pcs','roll','lusin','kg','botol'] as $u) {
            Unit::firstOrCreate(['name' => $u]);
        }
        foreach (['Kain','Benang','Tinta Sablon','Bahan Bordir','Aksesoris','PDH','Jersey','Kaos'] as $c) {
            Category::firstOrCreate(['name' => $c]);
        }

        Supplier::firstOrCreate(['code' => 'SUP-001'],
            ['name' => 'Toko Kain Makmur', 'phone' => '08123456789', 'address' => 'Semarang']);
        Customer::firstOrCreate(['code' => 'CUS-001'],
            ['name' => 'CV Sinar Jaya', 'phone' => '08129876543', 'address' => 'Semarang']);

        $unit = fn($n) => Unit::where('name', $n)->first()->id;
        $cat  = fn($n) => Category::where('name', $n)->first()->id;

        $items = [
            ['BB-001','Kain Katun Combed 30s','raw_material','Kain','meter',0,50],
            ['BB-002','Kain Drill Nagata','raw_material','Kain','meter',0,50],
            ['BB-003','Benang Jahit','raw_material','Benang','roll',0,10],
            ['BB-004','Tinta Sablon Plastisol','raw_material','Tinta Sablon','kg',0,5],
            ['BB-005','Kancing Baju','raw_material','Aksesoris','pcs',0,200],
            ['BB-006','Resleting','raw_material','Aksesoris','pcs',0,100],
            ['PR-001','PDH Lengan Panjang','product','PDH','pcs',185000,0],
            ['PR-002','Jersey Futsal','product','Jersey','pcs',120000,0],
            ['PR-003','Kaos Polos Combed','product','Kaos','pcs',65000,0],
        ];

        foreach ($items as [$code, $name, $type, $kat, $sat, $price, $min]) {
            Item::firstOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'category_id' => $cat($kat),
                'unit_id' => $unit($sat), 'sale_price' => $price, 'min_stock' => $min,
            ]);
        }
    }
}
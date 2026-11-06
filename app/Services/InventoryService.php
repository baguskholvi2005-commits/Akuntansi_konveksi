<?php

namespace App\Services;

use App\Models\{Item, InventoryMovement};
use Illuminate\Database\Eloquent\Model;

class InventoryService
{
    /** Barang MASUK. Mengembalikan nilai rupiahnya (untuk jurnal). Panggil di dalam DB::transaction(). */
    public function in(int $itemId, float $qty, float $unitCost, string $date,
                       string $ref, string $desc, Model $source): float
    {
        $item = Item::lockForUpdate()->findOrFail($itemId);

        $newQty   = $item->stock_qty + $qty;
        $newValue = ($item->stock_qty * $item->avg_cost) + ($qty * $unitCost);
        $newAvg   = $newQty > 0 ? $newValue / $newQty : 0;

        $item->update(['stock_qty' => $newQty, 'avg_cost' => $newAvg]);

        InventoryMovement::create([
            'item_id' => $item->id, 'date' => $date, 'reference' => $ref, 'description' => $desc,
            'source_type' => $source->getMorphClass(), 'source_id' => $source->id,
            'in_qty' => $qty, 'in_cost' => $unitCost, 'in_value' => round($qty * $unitCost, 2),
            'balance_qty' => $newQty, 'balance_cost' => $newAvg,
            'balance_value' => round($newQty * $newAvg, 2),
        ]);

        return round($qty * $unitCost, 2);
    }

    /** Barang KELUAR. Mengembalikan nilai rupiahnya. */
    public function out(int $itemId, float $qty, string $date,
                        string $ref, string $desc, Model $source): float
    {
        $item = Item::lockForUpdate()->findOrFail($itemId);

        if ($qty > $item->stock_qty) {
            throw new \DomainException("Stok {$item->name} tidak cukup (tersedia {$item->stock_qty}).");
        }

        $cost   = $item->avg_cost;
        $value  = round($qty * $cost, 2);
        $newQty = $item->stock_qty - $qty;

        $item->update(['stock_qty' => $newQty]);

        InventoryMovement::create([
            'item_id' => $item->id, 'date' => $date, 'reference' => $ref, 'description' => $desc,
            'source_type' => $source->getMorphClass(), 'source_id' => $source->id,
            'out_qty' => $qty, 'out_cost' => $cost, 'out_value' => $value,
            'balance_qty' => $newQty, 'balance_cost' => $cost,
            'balance_value' => round($newQty * $cost, 2),
        ]);

        return $value;
    }
}
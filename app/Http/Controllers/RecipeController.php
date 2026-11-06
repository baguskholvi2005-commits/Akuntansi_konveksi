<?php

namespace App\Http\Controllers;

use App\Models\{Item, Recipe};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecipeController extends Controller
{
    public function index()
    {
        return view('recipes.index', [
            'products' => Item::with('unit')->where('type', 'product')->orderBy('code')->get(),
            'counts'   => Recipe::groupBy('product_id')->selectRaw('product_id, COUNT(*) c')->pluck('c', 'product_id'),
        ]);
    }

    public function edit(Item $item)
    {
        abort_unless($item->type === 'product', 404);

        return view('recipes.edit', [
            'item'      => $item->load('unit'),
            'lines'     => Recipe::where('product_id', $item->id)->get(),
            'materials' => Item::with('unit')->where('type', 'raw_material')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $r, Item $item)
    {
        abort_unless($item->type === 'product', 404);

        $d = $r->validate([
            'lines'                => 'nullable|array',
            'lines.*.material_id'  => ['required', 'distinct', Rule::exists('items', 'id')->where('type', 'raw_material')],
            'lines.*.qty_per_unit' => 'required|numeric|gt:0',
            'lines.*.waste_pct'    => 'nullable|numeric|min:0|max:100',
        ], ['lines.*.material_id.distinct' => 'Satu bahan tidak boleh muncul dua kali dalam resep.']);

        DB::transaction(function () use ($item, $d) {
            Recipe::where('product_id', $item->id)->delete();
            foreach ($d['lines'] ?? [] as $l) {
                Recipe::create([
                    'product_id' => $item->id, 'material_id' => $l['material_id'],
                    'qty_per_unit' => $l['qty_per_unit'], 'waste_pct' => $l['waste_pct'] ?? 0,
                ]);
            }
        });

        return redirect()->route('recipes.index')->with('success', 'Resep ' . $item->name . ' disimpan.');
    }
}
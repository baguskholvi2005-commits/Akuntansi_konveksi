<?php

namespace App\Http\Controllers;

use App\Models\{Item, Category, Unit};
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $r)
    {
        $low = fn($q) => $q->where(fn($w) => $w->where('stock_qty', '<=', 0)
            ->orWhere(fn($x) => $x->where('min_stock', '>', 0)->whereColumn('stock_qty', '<=', 'min_stock')));

        $rows = Item::with(['category', 'unit'])
            ->when($r->q, fn($q) => $q->where(fn($w) =>
                $w->where('name', 'like', "%{$r->q}%")->orWhere('code', 'like', "%{$r->q}%")))
            ->when($r->type, fn($q) => $q->where('type', $r->type))
            ->when($r->category_id, fn($q) => $q->where('category_id', $r->category_id))
            ->when($r->unit_id, fn($q) => $q->where('unit_id', $r->unit_id))
            ->when($r->stock === 'low', $low)
            ->when($r->stock === 'ok', fn($q) => $q->where('stock_qty', '>', 0)
                ->where(fn($w) => $w->where('min_stock', '<=', 0)->orWhereColumn('stock_qty', '>', 'min_stock')))
            ->orderBy('code')->paginate(10)->withQueryString();

        $all = Item::with('category')->get();
        $val = fn($t) => round((float) $all->where('type', $t)->sum(fn($i) => $i->stock_qty * $i->avg_cost), 2);
        $isLow = fn($i) => $i->stock_qty <= 0 || ($i->min_stock > 0 && $i->stock_qty <= $i->min_stock);

        $topCat = $all->groupBy(fn($i) => $i->category->name ?? 'Tanpa kategori')
            ->map(fn($g) => ['n' => $g->count(), 'v' => round((float) $g->sum(fn($i) => $i->stock_qty * $i->avg_cost), 2)])
            ->sortByDesc('n')->take(1);

        $restock = $all->where('type', 'raw_material')->filter($isLow)->values();

        $sum = [
            'count'    => $all->count(),
            'raw_n'    => $all->where('type', 'raw_material')->count(),
            'fg_n'     => $all->where('type', 'product')->count(),
            'raw'      => $val('raw_material'),
            'fg'       => $val('product'),
            'low'      => $restock->count(),
            'low_names'=> $restock->take(3)->pluck('name')->implode(', '),
            'top_name' => $topCat->keys()->first() ?? '-',
            'top_n'    => $topCat->first()['n'] ?? 0,
            'top_v'    => $topCat->first()['v'] ?? 0,
        ];

        return view('items.index', [
            'rows' => $rows, 'sum' => $sum, 'restock' => $restock,
            'categories' => Category::orderBy('name')->get(), 'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('items.form', [
            'row' => null, 'categories' => Category::orderBy('name')->get(), 'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function store(Request $r)
    {
        Item::create($this->rules($r));
        return redirect()->route('items.index')->with('success', 'Barang berhasil ditambahkan.');
    }

    public function edit(Item $item)
    {
        return view('items.form', [
            'row' => $item, 'categories' => Category::orderBy('name')->get(), 'units' => Unit::orderBy('name')->get(),
        ]);
    }

    public function update(Request $r, Item $item)
    {
        $item->update($this->rules($r, $item->id));
        return redirect()->route('items.index')->with('success', 'Barang berhasil diubah.');
    }

    public function destroy(Item $item)
    {
        try {
            $item->delete();
            return back()->with('success', 'Barang dihapus.');
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', 'Barang tidak dapat dihapus karena sudah dipakai transaksi.');
        }
    }

    // Stok TIDAK ada di sini: stok hanya berubah lewat transaksi.
    private function rules(Request $r, $id = null): array
    {
        return $r->validate([
            'code'        => 'required|max:50|unique:items,code,' . $id,
            'name'        => 'required|max:255',
            'type'        => 'required|in:raw_material,product',
            'category_id' => 'nullable|exists:categories,id',
            'unit_id'     => 'required|exists:units,id',
            'sale_price'  => 'required|numeric|min:0',
            'min_stock'   => 'required|numeric|min:0',
        ]);
    }
}
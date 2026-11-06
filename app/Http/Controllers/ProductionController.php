<?php

namespace App\Http\Controllers;

use App\Models\{Production, ProductionPayment, Item, Journal, Recipe};
use App\Services\{ProductionService, DocNumber};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductionController extends Controller
{
    public function index(Request $r)
    {
        $tab = $r->tab ?: 'all';

        $base = Production::query()
            ->when($r->from, fn($q) => $q->whereDate('date', '>=', $r->from))
            ->when($r->to,   fn($q) => $q->whereDate('date', '<=', $r->to))
            ->when($r->q,    fn($q) => $q->where(fn($w) => $w->where('number', 'like', "%{$r->q}%")
                ->orWhereHas('item', fn($i) => $i->where('name', 'like', "%{$r->q}%"))))
            ->when(in_array($tab, ['draft', 'selesai']), fn($q) => $q->where('status', $tab))
            ->when(array_key_exists($tab, Production::STAGES), fn($q) => $q->where('status', 'proses')->where('stage', $tab));

        $rows = $base->with('item.unit')->orderByDesc('date')->orderByDesc('id')->paginate(10)->withQueryString();

        $byStage = Production::where('status', 'proses')->groupBy('stage')
            ->selectRaw('stage, COUNT(*) c, SUM(qty) q')->get()->keyBy('stage');
        $g = fn($k, $f) => (float) ($byStage[$k]->$f ?? 0);

        $stats = [
            'running'   => Production::where('status', 'proses')->count(),
            'run_qty'   => Production::where('status', 'proses')->sum('qty'),
            'early'     => $g('potong', 'c') + $g('sablon', 'c'),
            'early_qty' => $g('potong', 'q') + $g('sablon', 'q'),
            'late'      => $g('jahit', 'c') + $g('qc', 'c'),
            'late_qty'  => $g('jahit', 'q') + $g('qc', 'q'),
            'drafts'    => Production::where('status', 'draft')->count(),
            'done'      => Production::where('status', 'selesai')->whereDate('finished_date', '>=', date('Y-m-01'))->count(),
        ];

        $counts = [
            'all'     => Production::count(),
            'draft'   => $stats['drafts'],
            'selesai' => Production::where('status', 'selesai')->count(),
        ] + collect(Production::STAGES)->map(fn($l, $k) => (int) $g($k, 'c'))->all();

        return view('productions.index', compact('rows', 'stats', 'counts', 'tab'));
    }

    public function create()
    {
        return view('productions.create', [
            'products'  => Item::with('unit')->where('type', 'product')->orderBy('name')->get(),
            'materials' => Item::with('unit')->where('type', 'raw_material')->orderBy('name')->get(),
            'recipes'   => Recipe::all()->groupBy('product_id')->map(fn($g) => $g->map(fn($x) => [
                'm' => $x->material_id, 'q' => (float) $x->qty_per_unit, 'w' => (float) $x->waste_pct,
            ])->values()),
        ]);
    }

    public function stage(Request $r, Production $production)
    {
        $d = $r->validate(['stage' => 'required|in:' . implode(',', array_keys(Production::STAGES))]);

        if ($production->status !== 'proses') {
            return back()->with('error', 'Tahapan hanya dapat diubah saat produksi berstatus proses.');
        }
        $production->update(['stage' => $d['stage']]);

        return back()->with('success', 'Tahap produksi diperbarui.');
    }

    public function ticket(Production $production)
    {
        $production->load(['item.unit', 'materials.item.unit']);

        return view('productions.ticket', compact('production'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'date'                => 'required|date',
            'item_id'             => ['required', Rule::exists('items', 'id')->where('type', 'product')],
            'qty'                 => 'required|numeric|gt:0',
            'notes'               => 'nullable|max:500',
            'materials'           => 'required|array|min:1',
            'materials.*.item_id' => ['required', Rule::exists('items', 'id')->where('type', 'raw_material')],
            'materials.*.qty'     => 'required|numeric|gt:0',
        ]);

        $p = DB::transaction(function () use ($data) {
            $p = Production::create([
                'number'     => DocNumber::make('PRD', 'productions'),
                'date'       => $data['date'],
                'item_id'    => $data['item_id'],
                'qty'        => $data['qty'],
                'notes'      => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($data['materials'] as $m) {
                $p->materials()->create(['item_id' => $m['item_id'], 'qty' => $m['qty']]);
            }

            return $p;
        });

        return redirect()->route('productions.show', $p)
            ->with('success', 'Produksi disimpan sebagai draft. Klik "Mulai Produksi" untuk memakai bahan baku.');
    }

    public function show(Production $production, ProductionService $svc)
    {
        $production->load(['item.unit', 'materials.item.unit', 'payments']);

        $payIds = $production->payments->pluck('id');
        $journals = Journal::with('lines.account')
            ->where(function ($q) use ($production, $payIds) {
                $q->where(['source_type' => $production->getMorphClass(), 'source_id' => $production->id])
                  ->orWhere(function ($w) use ($payIds) {
                      $w->where('source_type', (new ProductionPayment)->getMorphClass())
                        ->whereIn('source_id', $payIds);
                  });
            })->orderBy('id')->get();

        $left = $svc->outstanding($production);

        return view('productions.show', compact('production', 'journals', 'left'));
    }

    public function start(Production $production, ProductionService $svc)
    {
        try {
            $svc->start($production);
            return back()->with('success', 'Produksi dimulai. Bahan baku sudah dipakai dan jurnal dibuat.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function finish(Request $r, Production $production, ProductionService $svc)
    {
        $data = $r->validate([
            'date'            => 'required|date',
            'labor_cost'      => 'required|numeric|min:0',
            'labor_source'    => 'required|in:kas,bank,utang',
            'overhead_cost'   => 'required|numeric|min:0',
            'overhead_source' => 'required|in:kas,bank,utang',
        ]);

        try {
            $svc->finish(
                $production, $data['date'],
                (float) $data['labor_cost'], (float) $data['overhead_cost'],
                $data['labor_source'], $data['overhead_source']
            );
            return back()->with('success', 'Produksi selesai. Stok produk jadi bertambah.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

     public function pay(Request $r, Production $production, ProductionService $svc)
    {
        $data = $r->validate([
            'kind'         => 'required|in:labor,overhead',
            'date'         => 'required|date|before_or_equal:today',
            'amount'       => 'required|numeric|gt:0',
            'account_code' => 'required|in:1101,1102',
        ]);

        try {
            $svc->pay($production, $data['kind'], $data['date'], (float) $data['amount'], $data['account_code']);
            return back()->with('success', 'Pembayaran utang produksi berhasil dicatat.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
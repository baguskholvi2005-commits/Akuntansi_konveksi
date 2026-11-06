<?php

namespace App\Http\Controllers;

use App\Models\{Purchase, PurchasePayment, Supplier, Item, Journal};
use App\Services\{PurchaseService, DocNumber};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index(Request $r)
    {
        $base = Purchase::query()
            ->when($r->from, fn($q) => $q->whereDate('date', '>=', $r->from))
            ->when($r->to,   fn($q) => $q->whereDate('date', '<=', $r->to))
            ->when($r->q,    fn($q) => $q->where(fn($w) =>
                $w->where('number', 'like', "%{$r->q}%")
                  ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', "%{$r->q}%"))))
            ->when($r->supplier_id, fn($q) => $q->where('supplier_id', $r->supplier_id))
            ->when(in_array($r->status, ['draft', 'confirmed']), fn($q) => $q->where('status', $r->status))
            ->when($r->status === 'unpaid', fn($q) => $q->where('status', 'confirmed')
                ->where('payment_method', 'kredit')->whereColumn('paid_amount', '<', 'total'));

        $per  = in_array((int) $r->per_page, [10, 25, 50]) ? (int) $r->per_page : 10;
        $rows = $base->with(['supplier', 'items.item'])
            ->orderByDesc('date')->orderByDesc('id')->paginate($per)->withQueryString();

        $unpaid = Purchase::where('status', 'confirmed')->where('payment_method', 'kredit')
            ->whereColumn('paid_amount', '<', 'total')
            ->selectRaw('COALESCE(SUM(total - paid_amount),0) AS s, COUNT(*) AS c')->first();

        $stats = [
            'month_total'  => (float) Purchase::where('status', 'confirmed')
                ->whereDate('date', '>=', date('Y-m-01'))->whereDate('date', '<=', date('Y-m-d'))->sum('total'),
            'unpaid_total' => (float) $unpaid->s,
            'unpaid_count' => (int) $unpaid->c,
            'drafts'       => Purchase::where('status', 'draft')->count(),
            'filtered'     => $rows->total(),
        ];

        return view('purchases.index', [
            'rows' => $rows, 'stats' => $stats, 'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('purchases.create', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'items'     => Item::with('unit')->where('type', 'raw_material')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'date'                => 'required|date',
            'due_date'            => 'nullable|date|after_or_equal:date|required_if:payment_method,kredit',
            'supplier_id'         => 'required|exists:suppliers,id',
            'payment_method'      => 'required|in:tunai,kredit',
            'discount'            => 'nullable|numeric|min:0',
            'shipping_cost'       => 'nullable|numeric|min:0',
            'notes'               => 'nullable|max:500',
            'items'               => 'required|array|min:1',
            'items.*.item_id'     => 'required|exists:items,id',
            'items.*.qty'         => 'required|numeric|gt:0',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.discount_pct'=> 'nullable|numeric|min:0|max:100',
        ]);

        $lines = [];
        $sub = 0;
        foreach ($data['items'] as $row) {
            $line = round($row['qty'] * $row['unit_price'] * (1 - ((float) ($row['discount_pct'] ?? 0)) / 100), 2);
            $lines[] = $row + ['subtotal' => $line];
            $sub += $line;
        }
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $shipping = round((float) ($data['shipping_cost'] ?? 0), 2);

        if ($discount > $sub) {
            return back()->withInput()->with('error', 'Diskon faktur tidak boleh melebihi subtotal barang.');
        }

        $purchase = DB::transaction(function () use ($data, $lines, $sub, $discount, $shipping) {
            $p = Purchase::create([
                'number'         => DocNumber::make('PB', 'purchases'),
                'date'           => $data['date'],
                'due_date'       => $data['payment_method'] === 'kredit' ? $data['due_date'] : null,
                'supplier_id'    => $data['supplier_id'],
                'payment_method' => $data['payment_method'],
                'discount'       => $discount,
                'shipping_cost'  => $shipping,
                'total'          => round($sub - $discount + $shipping, 2),
                'notes'          => $data['notes'] ?? null,
                'created_by'     => auth()->id(),
            ]);
            foreach ($lines as $row) {
                $p->items()->create([
                    'item_id' => $row['item_id'], 'qty' => $row['qty'], 'unit_price' => $row['unit_price'],
                    'discount_pct' => $row['discount_pct'] ?? 0, 'subtotal' => $row['subtotal'],
                ]);
            }

            return $p;
        });

        return redirect()->route('purchases.show', $purchase)
            ->with('success', 'Pembelian disimpan sebagai draft. Klik "Konfirmasi dan Posting" untuk memposting.');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'items.item.unit', 'payments']);

        $journals = Journal::with('lines.account')
            ->where(function ($q) use ($purchase) {
                $q->where(['source_type' => $purchase->getMorphClass(), 'source_id' => $purchase->id])
                  ->orWhere(function ($w) use ($purchase) {
                      $w->where('source_type', (new PurchasePayment)->getMorphClass())
                        ->whereIn('source_id', $purchase->payments->pluck('id'));
                  });
            })->orderBy('id')->get();

        return view('purchases.show', compact('purchase', 'journals'));
    }

    public function confirm(Purchase $purchase, PurchaseService $svc)
    {
        try {
            $svc->confirm($purchase);
            return back()->with('success', 'Pembelian berhasil diposting. Stok dan jurnal telah dibuat.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pay(Request $r, Purchase $purchase, PurchaseService $svc)
    {
        $data = $r->validate([
            'date'         => 'required|date',
            'amount'       => 'required|numeric|gt:0',
            'account_code' => 'required|in:1101,1102',
        ]);

        try {
            $svc->pay($purchase, $data['date'], (float) $data['amount'], $data['account_code']);
            return back()->with('success', 'Pembayaran utang berhasil dicatat.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Purchase $purchase)
    {
        if ($purchase->status !== 'draft') {
            return back()->with('error', 'Pembelian yang sudah diposting tidak boleh dihapus. Gunakan mekanisme koreksi.');
        }
        $purchase->delete();

        return redirect()->route('purchases.index')->with('success', 'Draft pembelian dihapus.');
    }
}
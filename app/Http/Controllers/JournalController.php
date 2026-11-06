<?php

namespace App\Http\Controllers;

use App\Models\{Journal, Account};
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    private function filtered(Request $r)
    {
        return Journal::query()
            ->when($r->from, fn($q) => $q->whereDate('date', '>=', $r->from))
            ->when($r->to,   fn($q) => $q->whereDate('date', '<=', $r->to))
            ->when($r->q,    fn($q) => $q->where(fn($w) =>
                $w->where('number', 'like', "%{$r->q}%")->orWhere('description', 'like', "%{$r->q}%")))
            ->when($r->account_id, fn($q) => $q->whereHas('lines', fn($l) => $l->where('account_id', $r->account_id)))
            ->when($r->source, function ($q) use ($r) {
                return $r->source === 'manual'
                    ? $q->whereNull('source_type')
                    : $q->where('source_type', $r->source);
            });
    }

    public function index(Request $r)
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'account_id' => 'nullable|exists:accounts,id']);

        if ($r->from && $r->to && $r->to < $r->from) {
            return back()->withInput()->with('error', 'Tanggal akhir tidak boleh sebelum tanggal awal.');
        }

        $base = $this->filtered($r);

        // Total semua jurnal yang lolos filter (bukan hanya halaman ini)
        $tot = DB::table('journal_lines')
            ->whereIn('journal_id', (clone $base)->select('journals.id'))
            ->selectRaw('COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c')->first();

        $stats = [
            'debit'  => round((float) $tot->d, 2),
            'credit' => round((float) $tot->c, 2),
            'count'  => (clone $base)->count(),
            'manual' => (clone $base)->whereNull('source_type')->count(),
        ];

        $rows = $base->with(['lines.account'])
            ->orderByDesc('date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('journals.index', [
            'rows' => $rows, 'stats' => $stats, 'accounts' => Account::orderBy('code')->get(),
        ]);
    }

    public function show(Journal $journal)
    {
        $journal->load(['lines.account', 'creator', 'reversalOf', 'reversedBy']);

        return view('journals.show', compact('journal'));
    }

    public function create()
    {
        // Akun persediaan tidak boleh dijurnal manual
        $accounts = Account::whereNotIn('code', ['1301', '1302', '1303'])->orderBy('code')->get();

        return view('journals.create', compact('accounts'));
    }

    public function store(Request $r, JournalService $svc)
    {
        $data = $r->validate([
            'date'             => 'required|date|before_or_equal:today',
            'description'      => 'required|max:200',
            'lines'            => 'required|array|min:2',
            'lines.*.account'  => 'required|exists:accounts,code|not_in:1301,1302,1303',
            'lines.*.debit'    => 'nullable|numeric|min:0',
            'lines.*.credit'   => 'nullable|numeric|min:0',
            'lines.*.memo'     => 'nullable|max:200',
        ], [
            'lines.*.account.not_in' => 'Akun persediaan (1301, 1302, 1303) tidak boleh dijurnal manual. Ubah persediaan lewat transaksi.',
            'lines.min'              => 'Jurnal minimal terdiri dari 2 baris.',
        ]);

        $lines = [];
        $n = 0;
        foreach ($data['lines'] as $l) {
            $n++;
            $d = round((float) ($l['debit'] ?? 0), 2);
            $c = round((float) ($l['credit'] ?? 0), 2);

            if (($d > 0) === ($c > 0)) {
                return back()->withInput()->with('error', "Baris $n: isi salah satu saja, debit atau kredit.");
            }
            $lines[] = ['account' => $l['account'], 'debit' => $d, 'credit' => $c, 'memo' => $l['memo'] ?? null];
        }

        try {
            $j = $svc->post($data['date'], 'Jurnal manual: ' . $data['description'], $lines);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('journals.show', $j)->with('success', 'Jurnal manual berhasil diposting.');
    }

    public function reverse(Request $r, Journal $journal, JournalService $svc)
    {
        $data = $r->validate(['reason' => 'required|max:200']);

        if ($journal->source_type !== null) {
            return back()->with('error', 'Hanya jurnal manual yang dapat dibalik dari sini. Koreksi jurnal lain lewat transaksi asalnya.');
        }
        if ($journal->is_reversal) {
            return back()->with('error', 'Jurnal pembalik tidak dapat dibalik lagi.');
        }
        if (Journal::where('reversal_of', $journal->id)->exists()) {
            return back()->with('error', 'Jurnal ini sudah pernah dibalik.');
        }

        try {
            $svc->reverse($journal, $data['reason']);
            return back()->with('success', 'Jurnal berhasil dibalik dengan jurnal pembalik.');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
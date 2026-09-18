<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSource;
use App\Models\Offer;
use App\Models\Watch;
use App\Services\AlertEvaluator;
use App\Services\Images\LocalOcr;
use App\Services\Matcher;
use App\Services\Providers\CsvFeedProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScannerController extends Controller
{
    public function index(Request $request, Matcher $matcher)
    {
        $request->validate(['q' => 'nullable|string|max:200']);
        $q = trim($request->input('q', ''));
        $results = [];
        if ($q !== '') {
            foreach (Offer::cursor() as $offer) {
                $match = $matcher->match($q, $offer->toArray());
                if ($match['kind'] !== 'none') {
                    $results[] = ['offer' => $offer, 'match' => $match];
                }
            }
        }
        usort($results, fn ($a, $b) => [$a['match']['kind'] !== 'exact', $a['offer']->availability !== 'in_stock', $a['offer']->total === null, $a['offer']->total ?? $a['offer']->price] <=> [$b['match']['kind'] !== 'exact', $b['offer']->availability !== 'in_stock', $b['offer']->total === null, $b['offer']->total ?? $b['offer']->price]);
        $eligible = collect($results)->filter(fn ($r) => $r['match']['kind'] === 'exact' && $r['offer']->availability === 'in_stock' && $r['offer']->total !== null && $r['offer']->checked_at->gt(now()->subHours(24)));
        $minimum = $eligible->min(fn ($r) => $r['offer']->total);

        $administration = Auth::check() ? [
            'states' => DB::table('source_states')->get()->keyBy('source'),
            'watches' => Watch::with('offer')->get(),
            'alerts' => DB::table('price_alerts')->join('watches', 'watches.id', '=', 'price_alerts.watch_id')->join('offers', 'offers.id', '=', 'watches.offer_id')->select('price_alerts.*', 'offers.title')->orderByDesc('price_alerts.id')->limit(30)->get(),
        ] : ['states' => collect(), 'watches' => collect(), 'alerts' => collect()];

        return view('scanner', $administration + ['q' => $q, 'results' => array_slice($results, 0, 100), 'count' => count($results), 'minimum' => $minimum]);
    }

    public function sync()
    {
        $configured = collect(config('scanner.sources'))->filter(fn (array $source): bool => (bool) $source['approved'] && filled($source['url']));
        foreach ($configured->keys() as $key) {
            SyncSource::dispatch($key);
        }

        return back()->with('message', $configured->isEmpty() ? 'Nu există surse aprobate și configurate.' : 'Verificările au fost puse în coadă.');
    }

    public function offer(Offer $offer)
    {
        $offer->load(['product', 'productMatch.product']);

        return view('offer', ['offer' => $offer, 'points' => $offer->points()->get()]);
    }

    public function watch(Request $request, Offer $offer)
    {
        $data = $request->validate(['threshold' => ['required', 'regex:/^\d{1,8}([.,]\d{1,2})?$/']]);
        $threshold = CsvFeedProvider::money($data['threshold']);
        abort_if($threshold <= 0, 422);
        Watch::updateOrCreate(['offer_id' => $offer->id], ['threshold' => $threshold]);
        if ($offer->checked_at->gt(now()->subHours(24))) {
            app(AlertEvaluator::class)->evaluate($offer);
        }

        return back()->with('message', 'Pragul a fost salvat. Alertele folosesc totalul cunoscut și stocul disponibil.');
    }

    public function unwatch(Watch $watch)
    {
        $watch->delete();

        return back()->with('message', 'Urmărirea a fost oprită.');
    }

    public function read(int $id)
    {
        DB::table('price_alerts')->where('id', $id)->update(['read_at' => now()]);

        return back();
    }

    public function image(Request $request, LocalOcr $ocr)
    {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=6000,max_height=6000']);
        $result = $ocr->identify($request->file('image')->getRealPath());

        return redirect('/')->with('message', $result['message'])->with('ocr_text', $result['text']);
    }

    public function export()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'source', 'seller', 'title', 'ean', 'model', 'sku', 'price_ron', 'shipping_ron', 'total_ron', 'availability', 'url', 'checked_at'], ',', '"', '');
            foreach (Offer::cursor() as $o) {
                $row = [$o->id, $o->source, $o->seller, $o->title, $o->ean, $o->model, $o->sku, number_format($o->price / 100, 2, '.', ''), $o->shipping === null ? '' : number_format($o->shipping / 100, 2, '.', ''), $o->total === null ? '' : number_format($o->total / 100, 2, '.', ''), $o->availability, $o->url, $o->checked_at->toIso8601String()];
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v, $row), ',', '"', '');
            }fclose($out);
        }, 'price-scanner.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSource;
use App\Models\CanonicalProduct;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Watch;
use App\Services\AlertEvaluator;
use App\Services\Images\LocalOcr;
use App\Services\Matcher;
use App\Services\ProductNormalizer;
use App\Services\Providers\CsvFeedProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScannerController extends Controller
{
    public function index(Request $request, Matcher $matcher, ProductNormalizer $normalizer)
    {
        $request->validate([
            'q' => 'nullable|string|max:200', 'region' => 'nullable|in:all,ro,eu,china',
            'channel' => 'nullable|in:all,B2B,B2C', 'match' => 'nullable|in:all,exact,probable,similar,unknown',
            'availability' => 'nullable|in:all,in_stock,out_of_stock,preorder,unknown',
            'currency' => 'nullable|in:all,RON,EUR,USD,PLN', 'source' => 'nullable|string|max:60',
            'merchant' => 'nullable|integer|min:1',
        ]);
        $q = trim($request->input('q', ''));
        $results = [];
        $products = collect();
        $filters = [
            'region' => $request->input('region', 'all'), 'channel' => $request->input('channel', 'all'),
            'match' => $request->input('match', 'all'), 'availability' => $request->input('availability', 'all'),
            'currency' => $request->input('currency', 'all'), 'source' => $request->input('source', 'all'),
            'merchant' => $request->integer('merchant'),
        ];
        if (Auth::check() && $q !== '') {
            $offers = Offer::with(['merchant', 'productMatch'])->when($filters['channel'] !== 'all', fn ($query) => $query->where('channel', $filters['channel']))
                ->when($filters['availability'] !== 'all', fn ($query) => $query->where('availability', $filters['availability']))
                ->when($filters['currency'] !== 'all', fn ($query) => $query->where('currency', $filters['currency']))
                ->when($filters['source'] !== 'all', fn ($query) => $query->where('source', $filters['source']))
                ->when($filters['merchant'] > 0, fn ($query) => $query->where('merchant_id', $filters['merchant']))
                ->when($filters['region'] === 'ro', fn ($query) => $query->where('country', 'RO'))
                ->when($filters['region'] === 'china', fn ($query) => $query->where('country', 'CN'))
                ->when($filters['region'] === 'eu', fn ($query) => $query->whereIn('country', $this->euCountries()))
                ->cursor();
            foreach ($offers as $offer) {
                $match = $matcher->match($q, $offer->toArray());
                $classification = $offer->productMatch?->classification ?? ($match['kind'] === 'exact' ? 'exact' : ($match['kind'] === 'similar' ? 'similar' : 'unknown'));
                if ($match['kind'] !== 'none' && ($filters['match'] === 'all' || $filters['match'] === $classification)) {
                    $match['classification'] = $classification;
                    $results[] = ['offer' => $offer, 'match' => $match];
                }
            }
            $normalizedQuery = $normalizer->normalize($q);
            $gtin = $matcher->gtin($q);
            $products = CanonicalProduct::with('identifiers')->get()->filter(function (CanonicalProduct $product) use ($normalizedQuery, $gtin, $normalizer): bool {
                if ($gtin && $product->identifiers->contains('normalized_value', $gtin)) {
                    return true;
                }

                return $normalizedQuery !== '' && str_contains($normalizer->normalize(implode(' ', [$product->normalized_name, ...($product->aliases ?? [])])), $normalizedQuery);
            })->take(20)->values();
        }
        usort($results, fn ($a, $b) => [$a['match']['kind'] !== 'exact', $a['offer']->availability !== 'in_stock', $a['offer']->total === null, $a['offer']->total ?? $a['offer']->price] <=> [$b['match']['kind'] !== 'exact', $b['offer']->availability !== 'in_stock', $b['offer']->total === null, $b['offer']->total ?? $b['offer']->price]);
        $eligible = collect($results)->filter(fn ($r) => $r['match']['kind'] === 'exact' && $r['offer']->availability === 'in_stock' && $r['offer']->total !== null && $r['offer']->checked_at->gt(now()->subHours(24)));
        $minimum = $eligible->min(fn ($r) => $r['offer']->total);

        $administration = Auth::check() ? [
            'states' => DB::table('source_states')->get()->keyBy('source'),
            'watches' => Watch::with('offer')->get(),
            'alerts' => DB::table('price_alerts')->join('watches', 'watches.id', '=', 'price_alerts.watch_id')->join('offers', 'offers.id', '=', 'watches.offer_id')->select('price_alerts.*', 'offers.title')->orderByDesc('price_alerts.id')->limit(30)->get(),
            'sources' => Offer::query()->distinct()->orderBy('source')->pluck('source'),
            'merchants' => Merchant::query()->orderBy('name')->get(['id', 'name']),
        ] : ['states' => collect(), 'watches' => collect(), 'alerts' => collect(), 'sources' => collect(), 'merchants' => collect()];

        return view('scanner', $administration + [
            'q' => $q, 'results' => array_slice($results, 0, 100), 'count' => count($results),
            'minimum' => $minimum, 'products' => $products, 'filters' => $filters,
        ]);
    }

    /** @return array<int, string> */
    private function euCountries(): array
    {
        return ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];
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
            fputcsv($out, ['id', 'source', 'seller', 'country', 'channel', 'title', 'ean', 'model', 'mpn', 'sku', 'price', 'shipping', 'total', 'currency', 'vat_included', 'moq', 'pack_quantity', 'availability', 'url', 'image_url', 'source_updated_at', 'checked_at'], ',', '"', '');
            foreach (Offer::cursor() as $o) {
                $row = [$o->id, $o->source, $o->seller, $o->country, $o->channel, $o->title, $o->ean, $o->model, $o->mpn, $o->sku, number_format($o->price / 100, 2, '.', ''), $o->shipping === null ? '' : number_format($o->shipping / 100, 2, '.', ''), $o->total === null ? '' : number_format($o->total / 100, 2, '.', ''), $o->currency, $o->vat_included === null ? '' : (int) $o->vat_included, $o->moq, $o->pack_quantity, $o->availability, $o->url, $o->image_url, $o->source_updated_at?->toIso8601String(), $o->checked_at->toIso8601String()];
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v, $row), ',', '"', '');
            }fclose($out);
        }, 'price-scanner.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

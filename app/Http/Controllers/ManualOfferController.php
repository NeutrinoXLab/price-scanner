<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManualOfferRequest;
use App\Services\OfferRecorder;
use App\Services\Providers\CsvFeedProvider;
use Illuminate\Http\RedirectResponse;

class ManualOfferController extends Controller
{
    public function store(StoreManualOfferRequest $request, OfferRecorder $recorder): RedirectResponse
    {
        $data = $request->validated();
        $data['seller'] = trim($data['seller']);
        $data['title'] = trim($data['title']);
        $data['country'] = strtoupper($data['country']);
        $data['price'] = CsvFeedProvider::money($data['price']);
        $data['shipping'] = CsvFeedProvider::money($data['shipping'] ?? '');
        $data['vat_included'] = $request->has('vat_included') ? $request->boolean('vat_included') : null;
        $data['external_id'] = hash('sha256', $data['url']);
        $data['access_method'] = 'manual';
        $data['raw_metadata'] = ['entered_by_user_id' => $request->user()->id];

        $offer = $recorder->record('manual', $data);

        return redirect()->route('offers.show', $offer)->with('message', 'Oferta manuală a fost salvată și analizată pentru matching.');
    }
}

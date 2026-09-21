<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewCsvImportRequest;
use App\Models\Offer;
use App\Services\OfferRecorder;
use App\Services\Providers\CsvFeedProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CsvImportController extends Controller
{
    public function create(): View
    {
        return view('imports.csv');
    }

    public function preview(PreviewCsvImportRequest $request): View
    {
        $csv = $request->file('catalog')->get();
        $report = (new CsvFeedProvider('https://invalid.local'))->preview($csv, $request->validated('default_source'));
        $token = Str::random(64);
        Storage::disk('local')->put("import-previews/{$token}.json", json_encode([
            'user_id' => $request->user()->id,
            'expires_at' => now()->addMinutes(30)->timestamp,
            'rows' => $report['rows'],
            'errors_count' => count($report['errors']),
        ], JSON_THROW_ON_ERROR));

        return view('imports.csv-preview', [
            'token' => $token,
            'rows' => $report['rows'],
            'importErrors' => $report['errors'],
        ]);
    }

    public function store(Request $request, OfferRecorder $recorder): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64', 'alpha_num']]);
        $path = "import-previews/{$data['token']}.json";
        abort_unless(Storage::disk('local')->exists($path), 422, 'Preview-ul a expirat sau nu există.');
        $preview = json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
        abort_unless((int) $preview['user_id'] === (int) $request->user()->id && (int) $preview['expires_at'] >= now()->timestamp, 403);

        $result = ['created' => 0, 'updated' => 0, 'ignored' => 0];
        foreach ($preview['rows'] as $row) {
            $source = $row['source'];
            unset($row['source']);
            $existing = Offer::where('source', $source)->where('external_id', $row['external_id'])->where('seller', $row['seller'])->first();
            $state = collect($row)->except(['raw_metadata', 'source_updated_at'])->all();
            if ($existing === null) {
                $result['created']++;
            } elseif ($existing->only(array_keys($state)) == $state) {
                $result['ignored']++;
            } else {
                $result['updated']++;
            }
            $row['access_method'] = 'csv_upload';
            $recorder->record($source, $row);
        }
        Storage::disk('local')->delete($path);

        $errors = (int) ($preview['errors_count'] ?? 0);

        return redirect()->route('home')->with('message', "Import finalizat: {$result['created']} noi, {$result['updated']} actualizate, {$result['ignored']} fără modificări, {$errors} erori neimportate.");
    }
}

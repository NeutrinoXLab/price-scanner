<?php

namespace App\Services;

use App\Models\Offer;
use App\Services\Providers\ProviderRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SourceSync
{
    public function run(string $key): void
    {
        $config = config('scanner.sources.'.$key);
        if (! $config) {
            return;
        }
        $lock = Cache::lock('source:'.$key, 300);
        if (! $lock->get()) {
            return;
        }
        try {
            DB::table('source_states')->insertOrIgnore(['source' => $key, 'status' => 'disabled']);
            if (! $config['approved'] || ! $config['url']) {
                DB::table('source_states')->where('source', $key)->update(['status' => 'disabled', 'message' => 'Necesită feed aprobat și configurat.']);

                return;
            }
            $state = DB::table('source_states')->where('source', $key)->first();
            if ($state->next_attempt && now()->lt($state->next_attempt)) {
                return;
            }
            DB::table('source_states')->where('source', $key)->update(['last_attempt' => now(), 'status' => 'running', 'next_attempt' => now()->addMinutes(config('scanner.interval_minutes'))]);
            try {
                $feed = app(ProviderRegistry::class)->for($key)->fetch($state->etag, $state->last_modified);
                DB::transaction(function () use ($key, $feed) {
                    if ($feed['offers'] === null) {
                        if (! Offer::where('source', $key)->exists()) {
                            throw new \RuntimeException('304 without catalog');
                        }
                        Offer::where('source', $key)->update(['checked_at' => now()]);
                    } else {
                        $ids = [];
                        foreach ($feed['offers'] as $row) {
                            $ids[] = app(OfferRecorder::class)->record($key, $row + ['access_method' => 'feed'])->id;
                        }
                        Offer::where('source', $key)->whereNotIn('id', $ids)->update(['availability' => 'unknown']);
                    }
                    DB::table('source_states')->where('source', $key)->update(['status' => 'ok', 'message' => $feed['offers'] === null ? 'Catalog neschimbat (304).' : 'Catalog verificat.', 'last_success' => now(), 'etag' => $feed['etag'], 'last_modified' => $feed['modified'], 'consecutive_failures' => 0, 'last_error_code' => null]);
                });
            } catch (\Throwable $e) {
                Log::warning('Source sync failed', ['source' => $key, 'exception' => get_class($e)]);
                DB::table('source_states')->where('source', $key)->increment('consecutive_failures');
                DB::table('source_states')->where('source', $key)->update(['status' => 'error', 'last_error_code' => class_basename($e), 'message' => 'Feed indisponibil sau format neacceptat. Verifică accesul și schema CSV; reîncercare la intervalul configurat.']);
            }
        } finally {
            $lock->release();
        }
    }
}

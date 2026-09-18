<?php

namespace App\Services\Providers;

class ProviderRegistry
{
    public function for(string $source): OfferProvider
    {
        return new CsvFeedProvider(config('scanner.sources.'.$source.'.url'));
    }
}

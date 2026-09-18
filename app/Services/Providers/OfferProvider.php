<?php

namespace App\Services\Providers;

interface OfferProvider
{
    /** Complete approved catalog, in canonical format; null means HTTP 304. */
    public function fetch(?string $etag, ?string $modified): array;
}

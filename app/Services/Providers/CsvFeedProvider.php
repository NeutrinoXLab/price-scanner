<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CsvFeedProvider implements OfferProvider
{
    public function __construct(private string $url) {}

    public static function money(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('/^\d{1,9}([.,]\d{1,2})?$/', $value)) {
            throw new RuntimeException('Invalid money');
        }
        $parts = explode('.', str_replace(',', '.', $value));

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public function fetch(?string $etag, ?string $modified): array
    {
        $host = parse_url($this->url, PHP_URL_HOST);
        if (parse_url($this->url, PHP_URL_SCHEME) !== 'https' || ! $host || filter_var($host, FILTER_VALIDATE_IP) || strtolower($host) === 'localhost') {
            throw new RuntimeException('Invalid feed URL');
        }
        $response = Http::connectTimeout(5)->timeout(25)->withOptions(['allow_redirects' => false,
            'on_headers' => function ($r) {
                if ((int) $r->getHeaderLine('Content-Length') > 10485760) {
                    throw new RuntimeException('Feed too large');
                }
            },
            'progress' => function ($total, $downloaded) {
                if ($downloaded > 10485760) {
                    throw new RuntimeException('Feed too large');
                }
            },
        ])->withHeaders(array_filter(['If-None-Match' => $etag, 'If-Modified-Since' => $modified]))->get($this->url);
        if ($response->status() === 304) {
            return ['offers' => null, 'etag' => $etag, 'modified' => $modified];
        }
        $response->throw();
        if ($response->status() !== 200) {
            throw new RuntimeException('Unexpected feed response');
        }

        return ['offers' => $this->parse($response->body()), 'etag' => $response->header('ETag'), 'modified' => $response->header('Last-Modified')];
    }

    public function parse(string $csv): array
    {
        if (strlen($csv) > 10485760) {
            throw new RuntimeException('Feed too large');
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        try {
            $headers = fgetcsv($stream, 0, ',', '"', '');
            if (! $headers) {
                throw new RuntimeException('Missing CSV header');
            }
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
            if (array_diff(['external_id', 'seller', 'title', 'url', 'price', 'shipping', 'currency', 'availability', 'ean', 'model', 'sku'], $headers)) {
                throw new RuntimeException('Unsupported feed schema');
            }
            $offers = [];
            $seen = [];
            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== count($headers)) {
                    throw new RuntimeException('Invalid CSV row');
                }
                $r = array_combine($headers, $row);
                foreach (['external_id', 'seller', 'title'] as $field) {
                    if (trim($r[$field]) === '' || mb_strlen($r[$field]) > 255) {
                        throw new RuntimeException('Missing or long field');
                    }
                }
                if (! filter_var($r['url'], FILTER_VALIDATE_URL) || ! in_array(parse_url($r['url'], PHP_URL_SCHEME), ['http', 'https'])) {
                    throw new RuntimeException('Invalid product URL');
                }
                $currency = strtoupper($r['currency']);
                if (! in_array($currency, config('scanner.currencies'), true) || ! in_array($r['availability'], ['in_stock', 'out_of_stock', 'preorder', 'unknown'])) {
                    throw new RuntimeException('Unsupported currency or availability');
                }
                $key = $r['external_id'].'|'.$r['seller'];
                if (isset($seen[$key])) {
                    throw new RuntimeException('Duplicate offer');
                }$seen[$key] = true;
                $offers[] = array_intersect_key($r, array_flip(['external_id', 'seller', 'title', 'url', 'availability'])) + [
                    'currency' => $currency,
                    'price' => self::money($r['price']) ?? throw new RuntimeException('Missing price'), 'shipping' => self::money($r['shipping']),
                    'ean' => $r['ean'] ?: null, 'model' => $r['model'] ?: null, 'sku' => $r['sku'] ?: null,
                    'old_price' => isset($r['old_price']) ? self::money($r['old_price']) : null,
                    'brand' => $r['brand'] ?? null, 'mpn' => $r['mpn'] ?? null,
                    'country' => strtoupper($r['country'] ?? 'RO'), 'raw_metadata' => $r,
                ];
                if (count($offers) > 30000) {
                    throw new RuntimeException('Too many offers');
                }
            }
            if (! $offers) {
                throw new RuntimeException('Empty catalog requires review');
            }

            return $offers;
        } finally {
            fclose($stream);
        }
    }
}

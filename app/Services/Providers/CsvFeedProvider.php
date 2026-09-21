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
        $report = $this->preview($csv, 'feed');
        if ($report['errors']) {
            throw new RuntimeException($report['errors'][0]['message']);
        }
        if (! $report['rows']) {
            throw new RuntimeException('Empty catalog requires review');
        }

        return $report['rows'];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, array{line: int, message: string}>}
     */
    public function preview(string $csv, ?string $defaultSource = null): array
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
            $headers = array_map(fn (string $header): string => strtolower(trim($header)), $headers);
            if (array_diff(['seller', 'title', 'url', 'price', 'currency'], $headers)) {
                throw new RuntimeException('Unsupported feed schema');
            }
            $offers = [];
            $errors = [];
            $seen = [];
            $line = 1;
            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if ($row === [null]) {
                    continue;
                }
                try {
                    if (count($row) !== count($headers)) {
                        throw new RuntimeException('Număr de coloane invalid.');
                    }
                    $record = array_combine($headers, $row);
                    $offers[] = $this->normalizeRow($record, $defaultSource, $seen);
                    if (count($offers) > 30000) {
                        throw new RuntimeException('Prea multe oferte.');
                    }
                } catch (RuntimeException $exception) {
                    $errors[] = ['line' => $line, 'message' => $exception->getMessage()];
                }
            }

            return ['rows' => $offers, 'errors' => $errors];
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, bool>  $seen
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, ?string $defaultSource, array &$seen): array
    {
        foreach (['seller', 'title'] as $field) {
            if (trim($row[$field] ?? '') === '' || mb_strlen($row[$field]) > 255) {
                throw new RuntimeException("Câmp obligatoriu lipsă sau prea lung: {$field}.");
            }
        }
        $url = trim($row['url'] ?? '');
        if (mb_strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('URL produs invalid.');
        }
        $currency = strtoupper(trim($row['currency'] ?? ''));
        $availability = trim($row['availability'] ?? '') ?: 'unknown';
        if (! in_array($currency, config('scanner.currencies'), true) || ! in_array($availability, ['in_stock', 'out_of_stock', 'preorder', 'unknown'], true)) {
            throw new RuntimeException('Monedă sau disponibilitate nesuportată.');
        }
        $source = strtolower(trim($row['source'] ?? $row['provider'] ?? $defaultSource ?? ''));
        if (! preg_match('/^[a-z0-9_-]{1,60}$/', $source)) {
            throw new RuntimeException('Sursă invalidă.');
        }
        $externalId = trim($row['external_id'] ?? '') ?: hash('sha256', $url);
        if (mb_strlen($externalId) > 255) {
            throw new RuntimeException('Identificator extern prea lung.');
        }
        $key = $source.'|'.$externalId.'|'.trim($row['seller']);
        if (isset($seen[$key])) {
            throw new RuntimeException('Ofertă duplicată în fișier.');
        }
        $seen[$key] = true;
        $country = strtoupper(trim($row['country'] ?? $row['merchant_country'] ?? 'RO'));
        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            throw new RuntimeException('Țară invalidă; folosește cod ISO din două litere.');
        }
        $channel = strtoupper(trim($row['channel'] ?? $row['market_type'] ?? 'B2C'));
        if (! in_array($channel, ['B2B', 'B2C'], true)) {
            throw new RuntimeException('Tipul comercial trebuie să fie B2B sau B2C.');
        }

        return [
            'source' => $source, 'external_id' => $externalId, 'seller' => trim($row['seller']),
            'title' => trim($row['title']), 'description' => $this->nullable($row['description'] ?? null, 5000),
            'category' => $this->nullable($row['category'] ?? null, 100), 'url' => $url,
            'image_url' => $this->optionalUrl($row['image_url'] ?? null),
            'price' => self::money($row['price']) ?? throw new RuntimeException('Preț lipsă.'),
            'shipping' => self::money($row['shipping'] ?? ''), 'currency' => $currency,
            'availability' => $availability, 'ean' => $this->nullable($row['ean'] ?? $row['gtin'] ?? null, 32),
            'model' => $this->nullable($row['model'] ?? null, 100), 'mpn' => $this->nullable($row['mpn'] ?? null, 100),
            'sku' => $this->nullable($row['sku'] ?? null, 100), 'brand' => $this->nullable($row['brand'] ?? null, 100),
            'country' => $country, 'channel' => $channel,
            'vat_included' => $this->optionalBoolean($row['vat_included'] ?? null),
            'moq' => $this->optionalPositiveInteger($row['moq'] ?? null, 'MOQ'),
            'pack_quantity' => $this->optionalPositiveInteger($row['pack_quantity'] ?? null, 'Cantitate pachet'),
            'notes' => $this->nullable($row['notes'] ?? null, 5000),
            'source_updated_at' => $this->optionalTimestamp($row['source_updated_at'] ?? $row['updated_at'] ?? null),
            'old_price' => isset($row['old_price']) ? self::money($row['old_price']) : null,
            'raw_metadata' => $row,
        ];
    }

    private function nullable(?string $value, ?int $maximumLength = null): ?string
    {
        $value = trim((string) $value);
        if ($maximumLength !== null && mb_strlen($value) > $maximumLength) {
            throw new RuntimeException('Câmp text prea lung.');
        }

        return $value === '' ? null : $value;
    }

    private function optionalUrl(?string $value): ?string
    {
        $value = $this->nullable($value, 2048);
        if ($value !== null && (! filter_var($value, FILTER_VALIDATE_URL) || ! in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true))) {
            throw new RuntimeException('URL imagine invalid.');
        }

        return $value;
    }

    private function optionalBoolean(?string $value): ?bool
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (! in_array($value, ['1', '0', 'true', 'false', 'yes', 'no', 'da', 'nu'], true)) {
            throw new RuntimeException('Valoare TVA invalidă.');
        }

        return in_array($value, ['1', 'true', 'yes', 'da'], true);
    }

    private function optionalPositiveInteger(?string $value, string $label): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! ctype_digit($value) || (int) $value < 1 || (int) $value > 1000000) {
            throw new RuntimeException("{$label} invalidă.");
        }

        return (int) $value;
    }

    private function optionalTimestamp(?string $value): ?string
    {
        $value = $this->nullable($value);
        if ($value === null) {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException('Data sursei este invalidă.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

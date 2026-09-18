<?php

namespace App\Services;

use Illuminate\Support\Str;

class ProductNormalizer
{
    private const SYNONYMS = [
        'tricouri' => 'haine', 'tricou' => 'haine', 'clothes' => 'haine', 'clothing' => 'haine',
        'organiser' => 'organizator', 'organizer' => 'organizator', 'organizatoare' => 'organizator',
        'separatoare' => 'organizator', 'stackable' => 'stivuibil', 'stivuibile' => 'stivuibil',
        'stivuibila' => 'stivuibil', 'bucati' => 'buc', 'pieces' => 'buc', 'piece' => 'buc',
        'pcs' => 'buc', 'pc' => 'buc', 'planse' => 'buc',
    ];

    public function normalize(string $value): string
    {
        $value = str_replace(['×', '✕'], 'x', $value);
        $value = strtolower(Str::ascii($value));
        $value = preg_replace('/(\d+(?:[.,]\d+)?)\s*[x×]\s*(\d+(?:[.,]\d+)?)\s*(cm|mm|m)?/u', ' $1x$2$3 ', $value);
        $tokens = preg_split('/[^a-z0-9.,x]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_filter($tokens, fn ($token) => preg_match('/[a-z0-9]/', $token)));
        $tokens = array_map(fn ($token) => self::SYNONYMS[$token] ?? str_replace(',', '.', $token), $tokens);

        return implode(' ', $tokens);
    }

    public function features(string $value): array
    {
        $normalized = $this->normalize($value);
        preg_match_all('/\b\d+(?:\.\d+)?x\d+(?:\.\d+)?(?:cm|mm|m)?\b/', $normalized, $dimensions);
        preg_match_all('/\b(\d+)\s*buc\b/', $normalized, $packs);

        return [
            'normalized' => $normalized,
            'tokens' => array_values(array_unique(preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY))),
            'dimensions' => array_values(array_unique($dimensions[0])),
            'pack_quantity' => isset($packs[1][0]) ? (int) $packs[1][0] : null,
        ];
    }
}

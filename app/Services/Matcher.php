<?php

namespace App\Services;

use Illuminate\Support\Str;

class Matcher
{
    public function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($value))));
    }

    public function gtin(string $value): ?string
    {
        $digits = preg_replace('/[\s-]/', '', $value);
        if (! preg_match('/^(\d{8}|\d{12}|\d{13}|\d{14})$/', $digits)) {
            return null;
        }
        $sum = 0;
        for ($i = strlen($digits) - 2, $weight = 3; $i >= 0; $i--, $weight = 4 - $weight) {
            $sum += (int) $digits[$i] * $weight;
        }

        return (10 - $sum % 10) % 10 === (int) substr($digits, -1) ? str_pad($digits, 14, '0', STR_PAD_LEFT) : null;
    }

    public function match(string $query, array $offer): array
    {
        $ean = $this->gtin($query);
        if ($ean) {
            $candidate = $this->gtin($offer['ean'] ?? '');

            return $candidate === $ean
                ? ['kind' => 'exact', 'score' => 100, 'reason' => 'GTIN valid identic; verifică separat starea și pachetul produsului.']
                : ['kind' => 'none', 'score' => 0, 'reason' => $candidate ? 'GTIN diferit.' : 'Sursa nu confirmă GTIN-ul.'];
        }
        $q = $this->normalize($query);
        if ($q === '') {
            return ['kind' => 'none', 'score' => 0, 'reason' => 'Căutare fără termeni utili.'];
        }
        $title = $this->normalize($offer['title']);
        $tokens = array_unique(explode(' ', $q));
        $hits = array_intersect($tokens, explode(' ', $title.' '.$this->normalize($offer['model'] ?? '').' '.$this->normalize($offer['sku'] ?? '')));
        $score = (int) round(count($hits) / max(1, count($tokens)) * 100);

        return ['kind' => $score >= 50 ? 'similar' : 'none', 'score' => $score, 'reason' => count($hits).'/'.count($tokens).' termeni potriviți. Denumirea/modelul nu confirmă varianta, capacitatea sau pachetul.'];
    }
}

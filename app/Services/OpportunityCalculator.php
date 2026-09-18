<?php

namespace App\Services;

use InvalidArgumentException;

class OpportunityCalculator
{
    public function calculate(array $input): array
    {
        $quantity = (int) ($input['quantity'] ?? 1);
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }
        $purchase = (int) $input['purchase_price'];
        $shared = (int) ($input['inbound_shipping'] ?? 0) + (int) ($input['customs_cost'] ?? 0) + (int) ($input['other_costs'] ?? 0);
        $outbound = (int) ($input['outbound_shipping'] ?? 0);
        $selling = isset($input['selling_price']) ? (int) $input['selling_price'] : null;
        $landed = $purchase + (int) round($shared / $quantity);
        $breakEven = $landed + $outbound;
        $margin = $selling === null ? null : $selling - $breakEven;

        return [
            'landed_unit_cost' => $landed, 'break_even_price' => $breakEven,
            'gross_margin_amount' => $margin,
            'gross_margin_percent' => $selling && $margin !== null ? round($margin / $selling * 100, 2) : null,
            'markup_percent' => $breakEven && $margin !== null ? round($margin / $breakEven * 100, 2) : null,
            'excluded_costs' => array_values(array_filter([
                empty($input['customs_included']) ? 'taxe vamale neconfirmate' : null,
                empty($input['vat_included']) ? 'TVA neconfirmat' : null,
            ])),
        ];
    }
}

<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class PriceThresholdReached implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly int $watchId, public readonly int $episode, public readonly int $total) {}
}

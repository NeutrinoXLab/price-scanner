<?php

namespace App\Jobs;

use App\Services\SourceSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 240;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public string $source) {}

    public function uniqueId(): string
    {
        return $this->source;
    }

    public function handle(SourceSync $sync): void
    {
        $sync->run($this->source);
    }
}

<?php

namespace App\Services\Images;

interface ImageIdentifier
{
    public function identify(string $path): array;
}

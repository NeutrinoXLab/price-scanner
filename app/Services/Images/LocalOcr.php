<?php

namespace App\Services\Images;

use Symfony\Component\Process\Process;

class LocalOcr implements ImageIdentifier
{
    public function identify(string $path): array
    {
        $binary = config('scanner.ocr_binary');
        if (! $binary || ! is_file($binary)) {
            return ['text' => '', 'message' => 'OCR local neconfigurat. Introdu manual brandul, modelul sau EAN-ul vizibil. Imaginea nu este trimisă unui serviciu extern.'];
        }
        try {
            $process = new Process([$binary, $path, 'stdout', '-l', 'eng', '--psm', '11']);
            $process->setTimeout(20);
            $process->mustRun();

            return ['text' => mb_substr(trim($process->getOutput()), 0, 200), 'message' => 'Text OCR propus: verifică și corectează înainte de căutare. Nu confirmă identitatea produsului.'];
        } catch (\Throwable) {
            return ['text' => '', 'message' => 'OCR nu a putut citi imaginea. Introdu manual identificatorul.'];
        }
    }
}

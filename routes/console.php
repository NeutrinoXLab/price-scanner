<?php

use App\Jobs\SyncSource;
use App\Models\User;
use App\Services\OfferRecorder;
use App\Services\Providers\CsvFeedProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('scanner:sync', function () {
    $configured = collect(config('scanner.sources'))->filter(fn (array $source): bool => (bool) $source['approved'] && filled($source['url']));
    foreach ($configured->keys() as $key) {
        SyncSource::dispatch($key);
    }
    $this->info($configured->isEmpty() ? 'Nu există surse aprobate și configurate.' : 'Source jobs dispatched.');
});
Schedule::command('scanner:sync')->hourly()->withoutOverlapping();
Artisan::command('scanner:import {source} {file} {--approved : Confirmă dreptul de utilizare a catalogului}', function () {
    $key = $this->argument('source');
    $file = $this->argument('file');
    if (! $this->option('approved') || ! array_key_exists($key, config('scanner.sources')) || ! is_file($file)) {
        $this->error('Sunt necesare sursa configurată, fișierul CSV și --approved pentru dreptul de utilizare.');

        return 1;
    }
    if (filesize($file) > 10485760) {
        $this->error('Maximum 10 MB.');

        return 1;
    }
    $lock = Cache::lock('source:'.$key, 300);
    if (! $lock->get()) {
        $this->error('Sursa este deja în lucru.');

        return 1;
    }
    try {
        $rows = (new CsvFeedProvider(''))->parse(file_get_contents($file));
        DB::transaction(function () use ($key, $rows) {
            foreach ($rows as $row) {
                app(OfferRecorder::class)->record($key, $row + ['access_method' => 'local_file']);
            }
        });
        $this->info(count($rows).' oferte importate din fișier local. Nu reprezintă o verificare online a magazinului.');
    } catch (Throwable $e) {
        $this->error('Import respins: verifică schema și valorile.');

        return 1;
    } finally {
        $lock->release();
    }
});

Artisan::command('scanner:admin {email}', function (): int {
    $email = (string) $this->argument('email');
    $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
    if ($validator->fails()) {
        $this->error('Adresa de email nu este validă.');

        return 1;
    }
    $password = (string) $this->secret('Parola administratorului (minimum 12 caractere)');
    if (mb_strlen($password) < 12) {
        $this->error('Parola trebuie să aibă minimum 12 caractere.');

        return 1;
    }
    User::updateOrCreate(['email' => $email], ['name' => 'Administrator Price Scanner', 'password' => Hash::make($password)]);
    $this->info('Administratorul a fost creat sau actualizat.');

    return 0;
})->purpose('Creează sau actualizează unicul cont administrativ, fără signup public');

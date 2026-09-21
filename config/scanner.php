<?php

return [
    'sources' => [
        'libris' => ['name' => 'Libris', 'url' => env('LIBRIS_FEED_URL'), 'approved' => env('LIBRIS_FEED_APPROVED', false)],
        'carturesti' => ['name' => 'Cărturești', 'url' => env('CARTURESTI_FEED_URL'), 'approved' => env('CARTURESTI_FEED_APPROVED', false)],
        'emag' => ['name' => 'eMAG / selleri', 'url' => env('EMAG_FEED_URL'), 'approved' => env('EMAG_FEED_APPROVED', false)],
        'two_performant' => ['name' => '2Performant', 'url' => env('TWOPERFORMANT_FEED_URL'), 'approved' => env('TWOPERFORMANT_FEED_APPROVED', false), 'requires' => 'Cont afiliat și acceptare în programele advertiserilor'],
        'profitshare' => ['name' => 'Profitshare', 'url' => env('PROFITSHARE_FEED_URL'), 'approved' => env('PROFITSHARE_FEED_APPROVED', false), 'requires' => 'Cont afiliat și acces la feed/API'],
        'awin' => ['name' => 'Awin', 'url' => env('AWIN_FEED_URL'), 'approved' => env('AWIN_FEED_APPROVED', false), 'requires' => 'Publisher ID, bearer token și permisiune pentru feed'],
    ],
    'interval_minutes' => max(60, (int) env('SCANNER_INTERVAL_MINUTES', 360)),
    'ocr_binary' => env('SCANNER_OCR_BINARY'),
    'currencies' => ['RON', 'EUR', 'USD', 'PLN'],
    'stale_after_hours' => max(1, (int) env('SCANNER_STALE_AFTER_HOURS', 24)),
];

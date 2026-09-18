# Price Scanner architecture

## Data flow

`fetch -> parse -> normalize -> match -> persist -> history -> analysis`

- Providers implement `OfferProvider`; `ProviderRegistry` selects an adapter. The executable adapter accepts an explicitly approved canonical CSV feed.
- `SourceSync` isolates each source, locks overlapping runs, records health and keeps the prior catalog when parsing fails.
- `OfferRecorder` is idempotent on source, external product ID and seller. It normalizes merchants, preserves source metadata and records only changed price states.
- `CanonicalProduct` describes physical goods. `Offer` describes a commercial listing. One product may have several verified GTINs.
- `ProductMatcher` stores classification, confidence and evidence. Probable and similar matches require manual review before attachment.
- `MarketAnalysis` deduplicates normalized merchants and calculates minimum, maximum, average and median.
- Suppliers remain separate from retail merchants. Exchange rates retain pair, value, provider and timestamp; no implicit conversion occurs.

## Operations

Run the queue worker and scheduler as supervised processes:

```text
php artisan queue:work --tries=1 --timeout=240
php artisan schedule:work
```

Import an authorized canonical CSV:

```text
php artisan scanner:import source_key C:\path\feed.csv --approved
```

Add a provider by implementing `OfferProvider`, registering it in `ProviderRegistry`, adding secret-free configuration to `config/scanner.php`, and testing with fixtures. Secrets belong only in `.env`.

## Security

The application is local-only. Laravel supplies CSRF protection and validation. Images are type, size and dimension limited and are not retained. Remote feeds require HTTPS, reject redirects and literal IP hosts, and cap response size. Logs contain source and exception class, without credentials.

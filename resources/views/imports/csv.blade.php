@extends('layout')
@section('title', 'Import CSV · Price Scanner')
@section('robots', 'noindex,nofollow')
@section('content')
<a href="{{ route('home') }}">← Înapoi la căutare</a><h1>Import CSV</h1>
<div class="card"><h2>1. Încarcă și verifică</h2><p>Fișierul este validat și afișat pentru confirmare înainte de orice scriere în baza de date. Maximum 10 MB.</p>
<form method="post" action="{{ route('imports.csv.preview') }}" enctype="multipart/form-data">@csrf
<label>Sursă implicită<input name="default_source" value="manual_csv" required pattern="[a-z0-9_-]+"><small>Este folosită când rândul nu conține coloana <code>source</code>.</small></label>
<label>Fișier CSV<input type="file" name="catalog" accept=".csv,text/csv,text/plain" required></label><button>Generează preview</button></form></div>
<div class="card"><h2>Schema canonicală</h2><p><strong>Obligatorii:</strong> seller, title, url, price, currency.</p><p><strong>Opționale:</strong> source/provider, external_id, country/merchant_country, channel/market_type, ean/gtin, sku, mpn, model, brand, description, category, vat_included, shipping, moq, pack_quantity, availability, image_url, source_updated_at/updated_at, notes.</p><p class="muted">Separator virgulă. Monede: {{ implode(', ',config('scanner.currencies')) }}. Disponibilitate: in_stock, out_of_stock, preorder, unknown.</p></div>
@endsection

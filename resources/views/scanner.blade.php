@extends('layout')
@section('content')
<section class="hero">
<span class="badge">PRICE COMPARISON SERVICE · ROMÂNIA</span>
<h1>Comparăm ofertele pentru același produs, cu sursa și gradul de potrivire la vedere.</h1>
<p class="lead">Price Scanner este un serviciu independent aflat în faza inițială de lansare. Construim un catalog verificabil de oferte din România, preluate numai din feeduri și surse pentru care există acces autorizat.</p>
</section>

<form class="card" method="get" action="{{ route('home') }}">
<label for="q"><strong>Caută un produs</strong></label>
<div class="search"><input id="q" name="q" maxlength="200" value="{{ $q }}" placeholder="Denumire, model, SKU sau cod EAN / GTIN" required><button>Caută</button></div>
<p class="muted">Catalogul comercial nu este încă populat. Dacă nu există rezultate, aplicația va spune acest lucru explicit.</p>
</form>

@if($q !== '')
<section class="card"><h2>{{ $count }} rezultate pentru „{{ $q }}”</h2>
@if(! $count)
<p>Nu există încă oferte autorizate potrivite în catalog. Nu afișăm produse, magazine sau prețuri demonstrative ca date reale.</p>
@else
<p class="muted">Sunt afișate maximum 100 de rezultate. Livrarea necunoscută nu este tratată ca fiind gratuită.</p>
@if(collect($results)->contains(fn ($result) => $result['offer']->access_method === 'local_file'))<div class="notice">Unele rezultate provin din fișiere importate local și sunt marcate ca atare în pagina ofertei.</div>@endif
@if($minimum !== null)<div class="notice">Cel mai mic total confirmat, disponibil și verificat recent: <strong>{{ number_format($minimum / 100, 2, ',', '.') }} lei</strong>.</div>@endif
<div class="table"><table><thead><tr><th>Produs / potrivire</th><th>Sursă / seller</th><th>Preț</th><th>Livrare</th><th>Total</th><th>Actualizare</th></tr></thead><tbody>
@foreach($results as $result)@php($offer=$result['offer'])<tr>
<td><a href="{{ route('offers.show', $offer) }}"><strong>{{ $offer->title }}</strong></a><p><span class="badge {{ $result['match']['kind'] === 'exact' ? 'good' : '' }}">{{ $result['match']['kind'] === 'exact' ? 'GTIN identic' : 'Similar · '.$result['match']['score'].'%' }}</span></p><small>{{ $result['match']['reason'] }}</small></td>
<td>{{ config('scanner.sources.'.$offer->source.'.name', $offer->source) }}<br>{{ $offer->seller }}</td>
<td>{{ number_format($offer->price / 100, 2, ',', '.') }} {{ $offer->currency }}</td>
<td>{{ $offer->shipping === null ? 'Necunoscută' : number_format($offer->shipping / 100, 2, ',', '.').' '.$offer->currency }}</td>
<td><strong>{{ $offer->total === null ? 'Necunoscut' : number_format($offer->total / 100, 2, ',', '.').' '.$offer->currency }}</strong></td>
<td>{{ $offer->checked_at->format('d.m.Y H:i') }}@if($offer->checked_at->lt(now()->subHours(config('scanner.stale_after_hours'))))<p class="badge">Date vechi</p>@endif</td>
</tr>@endforeach
</tbody></table></div>
@endif
</section>
@endif

<section id="cum-functioneaza" class="spacer"><h2>Cum funcționează</h2><div class="grid">
<div class="card"><h3>1. Identificare</h3><p>Folosim GTIN/EAN, model, MPN, denumire normalizată și caracteristici precum dimensiunea sau cantitatea din pachet.</p></div>
<div class="card"><h3>2. Potrivire transparentă</h3><p>Separăm potrivirile exacte, probabile și similare. Un EAN diferit nu exclude automat același produs fizic reambalat sau rebranduit.</p></div>
<div class="card"><h3>3. Comparare</h3><p>Ofertele autorizate pot fi comparate după preț, livrare, disponibilitate, seller, țară și momentul ultimei verificări.</p></div>
</div></section>

<section class="grid two spacer">
<div class="card"><h2>Sursele de date</h2><p>Acceptăm numai API-uri, feeduri comerciale, feeduri de afiliere sau importuri furnizate cu drept de utilizare. Nu ocolim CAPTCHA, Cloudflare sau limite anti-bot.</p><p class="muted">În acest moment sursele comerciale sunt în curs de conectare. Nu pretindem acoperirea tuturor magazinelor din România.</p></div>
<div class="card"><h2>Despre proiect</h2><p>Price Scanner este operat de NOVELION S.R.L. ca aplicație separată pentru observarea și compararea transparentă a ofertelor de pe piața românească.</p><p>Pentru colaborări sau acces la un feed autorizat: <a href="mailto:novelionprime@gmail.com">novelionprime@gmail.com</a>.</p></div>
</section>

@auth
<section class="card admin spacer"><span class="badge good">ADMINISTRARE</span><div class="row"><h2>Surse și operații interne</h2><form action="/sync" method="post">@csrf<button>Actualizează sursele</button></form><a href="/export">Export CSV</a></div>
<div class="grid">@foreach(config('scanner.sources') as $key => $source)@php($state=$states->get($key))<div class="card"><h3>{{ $source['name'] }}</h3><span class="badge {{ ($state->status ?? '') === 'ok' ? 'good' : '' }}">{{ ['ok'=>'Verificat','error'=>'Eroare','running'=>'În lucru','disabled'=>'Neactivat'][$state->status ?? 'disabled'] }}</span><p>{{ $state->message ?? 'Necesită acces aprobat la feed.' }}</p><small>Ultima reușită: {{ $state->last_success ?? '—' }}</small></div>@endforeach</div>
</section>
<section class="card admin"><h2>Produse urmărite</h2>@forelse($watches as $watch)<div class="row"><a href="{{ route('offers.show', $watch->offer) }}">{{ $watch->offer->title }} · sub {{ number_format($watch->threshold / 100, 2, ',', '.') }} lei</a><form action="/watches/{{ $watch->id }}" method="post">@csrf @method('DELETE')<button class="danger">Oprește</button></form></div>@empty<p class="muted">Nu există produse urmărite.</p>@endforelse</section>
<section class="card admin"><h2>Alerte interne</h2>@forelse($alerts as $alert)<p>{{ $alert->title }} · <strong>{{ number_format($alert->total / 100, 2, ',', '.') }} lei</strong></p>@if(! $alert->read_at)<form action="/alerts/{{ $alert->id }}/read" method="post">@csrf<button>Marchează citită</button></form>@endif @empty<p class="muted">Nicio alertă.</p>@endforelse</section>
<details class="card admin"><summary>Identificare internă din fotografie</summary><p>Imaginea este validată și procesată local dacă OCR este configurat; nu este păstrată permanent.</p><form action="/image" method="post" enctype="multipart/form-data">@csrf<label for="image">JPG, PNG sau WebP · maximum 5 MB<input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" required></label><button>Citește eticheta</button></form></details>
@endauth
@endsection

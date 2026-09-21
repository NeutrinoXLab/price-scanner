@extends('layout')
@section('content')
@guest
<section class="hero">
<span class="badge">PRICE COMPARISON SERVICE · ROMÂNIA</span>
<h1>Comparăm ofertele pentru același produs, cu sursa și gradul de potrivire la vedere.</h1>
<p class="lead">Price Scanner este un serviciu independent aflat în faza inițială de lansare. Construim un catalog verificabil de oferte din România, preluate numai din feeduri și surse pentru care există acces autorizat.</p>
<p><a class="button" href="{{ route('login') }}">Acces administrare</a></p>
</section>
<section id="cum-functioneaza" class="spacer"><h2>Cum funcționează</h2><div class="grid">
<div class="card"><h3>1. Identificare</h3><p>Folosim GTIN/EAN, model, MPN, denumire normalizată și caracteristici precum dimensiunea sau cantitatea din pachet.</p></div>
<div class="card"><h3>2. Potrivire transparentă</h3><p>Separăm potrivirile exacte, probabile și similare. Un EAN diferit nu exclude automat același produs fizic reambalat sau rebranduit.</p></div>
<div class="card"><h3>3. Comparare</h3><p>Ofertele autorizate pot fi comparate după preț, livrare, disponibilitate, seller, țară și momentul ultimei verificări.</p></div>
</div></section>
<section class="grid two spacer">
<div class="card"><h2>Sursele de date</h2><p>Acceptăm numai API-uri, feeduri comerciale, feeduri de afiliere sau importuri furnizate cu drept de utilizare. Nu ocolim CAPTCHA, Cloudflare sau limite anti-bot.</p><p class="muted">În acest moment sursele comerciale sunt în curs de conectare. Nu pretindem acoperirea tuturor magazinelor din România.</p></div>
<div class="card"><h2>Despre proiect</h2><p>Price Scanner este operat de NOVELION S.R.L. ca aplicație separată pentru observarea și compararea transparentă a ofertelor de pe piața românească.</p><p>Pentru colaborări sau acces la un feed autorizat: <a href="mailto:novelionprime@gmail.com">novelionprime@gmail.com</a>.</p></div>
</section>
@else
<section class="hero"><span class="badge good">INSTRUMENT INTERN</span><h1>Caută și compară produse și oferte</h1><p class="lead">Introdu un EAN/GTIN, model, MPN, SKU, brand sau denumire. Rezultatele arată sursa, potrivirea și datele comerciale cunoscute.</p></section>
<div class="row"><a class="button" href="{{ route('imports.csv.create') }}">Importă CSV</a><a class="button" href="#oferta-manuala">Adaugă ofertă manuală</a><a href="{{ route('export') }}">Exportă ofertele</a></div>

<form class="card" method="get" action="{{ route('home') }}">
<label for="q"><strong>Caută în baza locală</strong></label>
<div class="search"><input id="q" name="q" maxlength="200" value="{{ $q }}" placeholder="EAN/GTIN, denumire, brand, model, MPN sau SKU" required><button>Caută</button></div>
<div class="filter-grid">
<label>Regiune<select name="region"><option value="all">Toate</option><option value="ro" @selected($filters['region']==='ro')>România</option><option value="eu" @selected($filters['region']==='eu')>Uniunea Europeană</option><option value="china" @selected($filters['region']==='china')>China</option></select></label>
<label>Canal<select name="channel"><option value="all">B2B și B2C</option><option value="B2B" @selected($filters['channel']==='B2B')>B2B</option><option value="B2C" @selected($filters['channel']==='B2C')>B2C</option></select></label>
<label>Potrivire<select name="match"><option value="all">Toate</option>@foreach(['exact'=>'Exactă','probable'=>'Probabilă','similar'=>'Similară','unknown'=>'Necunoscută'] as $value=>$label)<option value="{{ $value }}" @selected($filters['match']===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Disponibilitate<select name="availability"><option value="all">Toate</option>@foreach(['in_stock'=>'În stoc','out_of_stock'=>'Fără stoc','preorder'=>'Precomandă','unknown'=>'Necunoscută'] as $value=>$label)<option value="{{ $value }}" @selected($filters['availability']===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Monedă<select name="currency"><option value="all">Toate</option>@foreach(config('scanner.currencies') as $currency)<option value="{{ $currency }}" @selected($filters['currency']===$currency)>{{ $currency }}</option>@endforeach</select></label>
<label>Sursă<select name="source"><option value="all">Toate</option>@foreach($sources as $source)<option value="{{ $source }}" @selected($filters['source']===$source)>{{ $source }}</option>@endforeach</select></label>
<label>Comerciant<select name="merchant"><option value="0">Toți</option>@foreach($merchants as $merchant)<option value="{{ $merchant->id }}" @selected($filters['merchant']===$merchant->id)>{{ $merchant->name }}</option>@endforeach</select></label>
</div>
</form>

@if($q !== '')
<section class="card"><h2>Produse canonice</h2>@forelse($products as $product)<p><a href="{{ route('products.show', $product) }}"><strong>{{ $product->name }}</strong></a> · {{ $product->brand ?: 'brand necunoscut' }} · {{ $product->model ?: $product->mpn ?: 'model necunoscut' }}</p>@empty<p class="muted">Niciun produs canonic identificat direct.</p>@endforelse</section>
<section class="card"><h2>{{ $count }} oferte pentru „{{ $q }}”</h2>
@if(! $count)<p>Nu există oferte potrivite în baza locală.</p>@else
<p class="muted">Ofertele sunt ordonate după potrivire, disponibilitate și cost total cunoscut. Livrarea necunoscută nu este tratată ca gratuită.</p>
<div class="table"><table><thead><tr><th>Produs / matching</th><th>Comerciant</th><th>Tip / țară</th><th>Preț / livrare</th><th>MOQ / pachet</th><th>Stare / verificare</th></tr></thead><tbody>
@foreach($results as $result)@php($offer=$result['offer'])<tr>
<td><a href="{{ route('offers.show', $offer) }}"><strong>{{ $offer->title }}</strong></a><p><span class="badge {{ $result['match']['classification'] === 'exact' ? 'good' : '' }}">{{ ucfirst($result['match']['classification']) }} · {{ $result['match']['score'] }}%</span></p><small>{{ $result['match']['reason'] }}</small></td>
<td>{{ $offer->merchant?->name ?: $offer->seller }}<br><small>{{ $offer->source }}</small></td><td>{{ $offer->channel }} · {{ $offer->country }}</td>
<td><strong>{{ number_format($offer->price/100,2,',','.') }} {{ $offer->currency }}</strong><br><small>Livrare: {{ $offer->shipping === null ? 'necunoscută' : number_format($offer->shipping/100,2,',','.').' '.$offer->currency }}</small></td>
<td>{{ $offer->moq ? 'MOQ '.$offer->moq : 'MOQ —' }}<br><small>{{ $offer->pack_quantity ? 'Pachet '.$offer->pack_quantity : 'Pachet —' }}</small></td>
<td>{{ ['in_stock'=>'În stoc','out_of_stock'=>'Fără stoc','preorder'=>'Precomandă','unknown'=>'Necunoscută'][$offer->availability] }}<br><small>{{ $offer->checked_at->format('d.m.Y H:i') }}</small></td>
</tr>@endforeach
</tbody></table></div>@endif</section>
@endif

<details id="oferta-manuala" class="card admin" @if($errors->any()) open @endif><summary><strong>Adaugă o ofertă manuală</strong></summary>
<p class="muted">Oferta intră în același flux de normalizare, matching, istoric și alerte ca un feed autorizat.</p>
<form method="post" action="{{ route('offers.manual.store') }}">@csrf
<div class="filter-grid"><label>URL produs<input name="url" type="url" value="{{ old('url') }}" required></label><label>Comerciant<input name="seller" value="{{ old('seller') }}" required maxlength="255"></label><label>Titlu<input name="title" value="{{ old('title') }}" required maxlength="255"></label><label>Preț<input name="price" inputmode="decimal" value="{{ old('price') }}" required></label><label>Monedă<select name="currency">@foreach(config('scanner.currencies') as $currency)<option @selected(old('currency','RON')===$currency)>{{ $currency }}</option>@endforeach</select></label><label>Transport<input name="shipping" inputmode="decimal" value="{{ old('shipping') }}"></label><label>EAN/GTIN<input name="ean" value="{{ old('ean') }}"></label><label>SKU<input name="sku" value="{{ old('sku') }}"></label><label>Model<input name="model" value="{{ old('model') }}"></label><label>MPN<input name="mpn" value="{{ old('mpn') }}"></label><label>Brand<input name="brand" value="{{ old('brand') }}"></label><label>Țară ISO<input name="country" value="{{ old('country','RO') }}" maxlength="2" required></label><label>Canal<select name="channel"><option>B2C</option><option @selected(old('channel')==='B2B')>B2B</option></select></label><label>MOQ<input name="moq" type="number" min="1" value="{{ old('moq') }}"></label><label>Cantitate pachet<input name="pack_quantity" type="number" min="1" value="{{ old('pack_quantity') }}"></label><label>Disponibilitate<select name="availability">@foreach(['in_stock'=>'În stoc','out_of_stock'=>'Fără stoc','preorder'=>'Precomandă','unknown'=>'Necunoscută'] as $value=>$label)<option value="{{ $value }}" @selected(old('availability','unknown')===$value)>{{ $label }}</option>@endforeach</select></label><label>URL imagine<input name="image_url" type="url" value="{{ old('image_url') }}"></label></div>
<label><input style="width:auto" type="checkbox" name="vat_included" value="1" @checked(old('vat_included'))> TVA declarat inclus de sursă</label><label>Descriere<textarea name="description" maxlength="5000">{{ old('description') }}</textarea></label><label>Note interne<textarea name="notes" maxlength="5000">{{ old('notes') }}</textarea></label><button>Salvează și analizează</button>
</form></details>

<section class="card admin spacer"><span class="badge good">ADMINISTRARE</span><div class="row"><h2>Surse configurate</h2><form action="/sync" method="post">@csrf<button>Actualizează sursele</button></form></div><div class="grid">@foreach(config('scanner.sources') as $key=>$source)@php($state=$states->get($key))<div class="card"><h3>{{ $source['name'] }}</h3><span class="badge {{ ($state->status??'')==='ok'?'good':'' }}">{{ ['ok'=>'Verificat','error'=>'Eroare','running'=>'În lucru','disabled'=>'Neactivat'][$state->status??'disabled'] }}</span><p>{{ $state->message??'Necesită acces aprobat la feed.' }}</p><small>Ultima reușită: {{ $state->last_success??'—' }}</small></div>@endforeach</div></section>
<section class="card admin"><h2>Produse urmărite</h2>@forelse($watches as $watch)<div class="row"><a href="{{ route('offers.show',$watch->offer) }}">{{ $watch->offer->title }} · sub {{ number_format($watch->threshold/100,2,',','.') }} lei</a><form action="/watches/{{ $watch->id }}" method="post">@csrf @method('DELETE')<button class="danger">Oprește</button></form></div>@empty<p class="muted">Nu există produse urmărite.</p>@endforelse</section>
<section class="card admin"><h2>Alerte interne</h2>@forelse($alerts as $alert)<p>{{ $alert->title }} · <strong>{{ number_format($alert->total/100,2,',','.') }} lei</strong></p>@if(!$alert->read_at)<form action="/alerts/{{ $alert->id }}/read" method="post">@csrf<button>Marchează citită</button></form>@endif @empty<p class="muted">Nicio alertă.</p>@endforelse</section>
<details class="card admin"><summary>Identificare internă din fotografie</summary><p>Imaginea este validată și procesată local dacă OCR este configurat; nu este păstrată permanent.</p><form action="/image" method="post" enctype="multipart/form-data">@csrf<label for="image">JPG, PNG sau WebP · maximum 5 MB<input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" required></label><button>Citește eticheta</button></form></details>
@endguest
@endsection

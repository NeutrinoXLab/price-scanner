@extends('layout')
@section('content')
<a href="/">← Căutare</a><span class="badge good">PRODUS CANONIC</span>
<h1>{{ $product->name }}</h1>
<p class="muted">{{ $product->brand ?: 'Brand necunoscut' }} · Model {{ $product->model ?: 'necunoscut' }} · MPN {{ $product->mpn ?: 'necunoscut' }}</p>
<div class="grid">
 <div class="card"><h3>Selleri RO</h3><div class="price">{{ $market['seller_count'] }}</div><small>{{ $market['offer_count'] }} oferte disponibile</small></div>
 <div class="card"><h3>Minim RO</h3><div class="price">{{ $market['minimum'] === null ? 'Fără date' : number_format($market['minimum']/100,2,',','.').' lei' }}</div></div>
 <div class="card"><h3>Mediană RO</h3><div class="price">{{ $market['median'] === null ? 'Fără date' : number_format($market['median']/100,2,',','.').' lei' }}</div></div>
 <div class="card"><h3>Medie / maxim RO</h3><p>{{ $market['average'] === null ? 'Fără date' : number_format($market['average']/100,2,',','.').' / '.number_format($market['maximum']/100,2,',').' lei' }}</p></div>
</div>
<div class="card"><h2>Identificatori și aliasuri</h2><p>GTIN: {{ $product->identifiers->where('type','gtin')->pluck('value')->join(', ') ?: '—' }}</p><p>Aliasuri: {{ collect($product->aliases)->join(' · ') ?: '—' }}</p></div>
<div class="card"><h2>Oferte asociate</h2>
@forelse($product->offers as $offer)<div class="row"><div><a href="/offers/{{ $offer->id }}"><strong>{{ $offer->title }}</strong></a><br>{{ $offer->merchant?->name ?: $offer->seller }} · {{ $offer->country }} · {{ number_format($offer->price/100,2,',','.') }} {{ $offer->currency }}<br><small>{{ $offer->productMatch?->classification ?? 'manual' }} · {{ $offer->productMatch?->confidence ?? 100 }}% · {{ $offer->checked_at->format('d.m.Y H:i') }}</small></div></div>@empty<p>Nu există oferte asociate.</p>@endforelse
</div>
<div class="card"><h2>Furnizori</h2>@forelse($product->supplierOffers as $supplierOffer)<p><strong>{{ $supplierOffer->supplier->company }}</strong> · {{ $supplierOffer->supplier->country }} · {{ number_format($supplierOffer->unit_price/100,2,',','.') }} {{ $supplierOffer->currency }} · MOQ {{ $supplierOffer->moq ?? 'necunoscut' }}</p>@empty<p class="muted">Nu există furnizori verificați pentru acest produs. Sellerii retail nu sunt tratați automat drept furnizori.</p>@endforelse</div>
<div class="card"><h2>Calculator oportunitate</h2><p class="muted">Valorile rămân în moneda aleasă. Nu se aplică un curs valutar implicit.</p>
<form method="post" action="/products/{{ $product->id }}/opportunity">@csrf
<div class="grid"><label>Cost achiziție/unitate<input name="purchase_price" type="number" step="0.01" min="0" value="{{ old('purchase_price') }}" required></label><label>Monedă<select name="currency">@foreach(config('scanner.currencies') as $currency)<option>{{ $currency }}</option>@endforeach</select></label><label>Cantitate / MOQ<input name="quantity" type="number" min="1" value="{{ old('quantity',1) }}" required></label><label>Transport intrare total<input name="inbound_shipping" type="number" step="0.01" min="0" value="{{ old('inbound_shipping') }}"></label><label>Vamă total<input name="customs_cost" type="number" step="0.01" min="0" value="{{ old('customs_cost') }}"></label><label>Alte costuri totale<input name="other_costs" type="number" step="0.01" min="0" value="{{ old('other_costs') }}"></label><label>Transport ieșire/unitate<input name="outbound_shipping" type="number" step="0.01" min="0" value="{{ old('outbound_shipping') }}"></label><label>Preț vânzare<input name="selling_price" type="number" step="0.01" min="0" value="{{ old('selling_price') }}"></label></div>
<label><input type="checkbox" name="customs_included" value="1"> Vama este inclusă</label><label><input type="checkbox" name="vat_included" value="1"> TVA este inclus</label><button>Calculează transparent</button></form>
@if($calculation)<div class="notice"><strong>Cost landed/unitate:</strong> {{ number_format($calculation['landed_unit_cost']/100,2,',','.') }} {{ $calculation['currency'] }} · <strong>Break-even:</strong> {{ number_format($calculation['break_even_price']/100,2,',','.') }} · <strong>Marjă brută:</strong> {{ $calculation['gross_margin_amount']===null?'—':number_format($calculation['gross_margin_amount']/100,2,',','.').' ('.$calculation['gross_margin_percent'].'%)' }} · <strong>Markup:</strong> {{ $calculation['markup_percent']===null?'—':$calculation['markup_percent'].'%' }}<br><small>Neincluse/neverificate: {{ implode(', ', $calculation['excluded_costs']) ?: 'nimic declarat' }}.</small></div>@endif
</div>
@endsection

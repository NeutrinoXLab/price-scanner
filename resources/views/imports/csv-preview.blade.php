@extends('layout')
@section('title', 'Confirmare import CSV · Price Scanner')
@section('robots', 'noindex,nofollow')
@section('content')
<a href="{{ route('imports.csv.create') }}">← Alt fișier</a><h1>Preview import CSV</h1>
<div class="grid"><div class="card"><h3>Rânduri valide</h3><div class="price">{{ count($rows) }}</div></div><div class="card"><h3>Erori</h3><div class="price">{{ count($importErrors) }}</div></div></div>
@if($importErrors)<div class="card"><h2>Raport erori</h2><div class="table"><table><thead><tr><th>Linia</th><th>Problemă</th></tr></thead><tbody>@foreach($importErrors as $error)<tr><td>{{ $error['line'] }}</td><td>{{ $error['message'] }}</td></tr>@endforeach</tbody></table></div></div>@endif
<div class="card"><h2>Primele {{ min(100,count($rows)) }} rânduri valide</h2><div class="table"><table><thead><tr><th>Sursă</th><th>Comerciant</th><th>Produs</th><th>Țară/tip</th><th>Preț</th><th>Identificatori</th></tr></thead><tbody>@foreach(array_slice($rows,0,100) as $row)<tr><td>{{ $row['source'] }}</td><td>{{ $row['seller'] }}</td><td>{{ $row['title'] }}</td><td>{{ $row['country'] }} · {{ $row['channel'] }}</td><td>{{ number_format($row['price']/100,2,',','.') }} {{ $row['currency'] }}</td><td>{{ $row['ean'] ?: $row['model'] ?: $row['sku'] ?: '—' }}</td></tr>@endforeach</tbody></table></div></div>
@if($rows)<form method="post" action="{{ route('imports.csv.store') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button>Confirmă și importă {{ count($rows) }} rânduri valide</button></form>@else<div class="notice">Nu există rânduri valide de importat.</div>@endif
@endsection

@extends('layout')
@section('title', 'Pagina nu a fost găsită · Price Scanner')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="card" style="max-width:680px;margin:40px auto"><span class="badge">404</span><h1>Pagina nu a fost găsită</h1><p>Adresa accesată nu corespunde unei pagini publice Price Scanner.</p><a class="button" href="{{ route('home') }}">Înapoi la Price Scanner</a></div>
@endsection

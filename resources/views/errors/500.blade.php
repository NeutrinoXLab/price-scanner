@extends('layout')
@section('title', 'Serviciu indisponibil · Price Scanner')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="card" style="max-width:680px;margin:40px auto"><span class="badge">EROARE</span><h1>Serviciul nu este disponibil momentan</h1><p>Încearcă din nou mai târziu. Dacă problema persistă, contactează operatorul folosind datele din subsol.</p><a class="button" href="{{ route('home') }}">Înapoi la pagina principală</a></div>
@endsection

@extends('layout')
@section('title', 'Administrare · Price Scanner')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="card" style="max-width:520px;margin:40px auto">
<span class="badge">ACCES INTERN</span><h1 style="font-size:30px">Administrare Price Scanner</h1>
<p class="muted">Nu există înregistrare publică. Accesul este rezervat operatorului aplicației.</p>
<form method="post" action="{{ route('login') }}">@csrf
<label for="email">Email<input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
<label for="password">Parolă<input id="password" name="password" type="password" autocomplete="current-password" required></label>
<label><input style="width:auto" name="remember" type="checkbox" value="1"> Păstrează sesiunea</label>
<button>Autentificare</button>
</form>
</div>
@endsection

<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Price Scanner este un serviciu independent de comparare a prețurilor pentru piața din România, operat de NOVELION S.R.L.">
<meta name="robots" content="@yield('robots', 'index,follow')">
<title>@yield('title', 'Price Scanner · Comparare prețuri România')</title>
<style>
:root{font-family:system-ui,-apple-system,sans-serif;color:#19312f;background:#f4f7f5}*{box-sizing:border-box}body{margin:0}header{background:#103c34;color:#fff;padding:20px max(24px,calc((100vw - 1160px)/2));display:flex;justify-content:space-between;align-items:center;gap:20px}header a{color:#fff;text-decoration:none}.brand{font-size:18px}.nav{display:flex;gap:16px;align-items:center}.nav form{margin:0}.nav button{padding:8px 12px;background:#fff;color:#103c34}main{max-width:1208px;margin:auto;padding:36px 24px}h1{font-size:40px;letter-spacing:-1.4px;margin:10px 0 14px;max-width:780px}h2{font-size:23px}h3{font-size:17px;margin-top:0}p{line-height:1.65}.lead{font-size:18px;max-width:780px}.muted,small{color:#526a64}.card{background:#fff;border:1px solid #d5e2db;border-radius:14px;padding:24px;margin:20px 0}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.grid .card{margin:0}.two{grid-template-columns:repeat(2,1fr)}.filter-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}input,select,textarea{padding:13px;border:1px solid #9bb5a8;border-radius:8px;font:inherit;max-width:100%;width:100%}button,.button{background:#166953;color:#fff;border:0;border-radius:8px;padding:13px 18px;font:inherit;cursor:pointer;text-decoration:none;display:inline-block}a{color:#11634d}label{display:block;margin:10px 0}.search{display:flex;gap:10px}.search input{flex:1;min-width:0}.badge{display:inline-block;border-radius:20px;background:#e8eee9;padding:5px 10px;font-size:12px}.good{background:#d3f0dd;color:#185033}.notice{background:#fff5d8;border:1px solid #e1c979;padding:16px;border-radius:10px;margin:16px 0}.table{overflow-x:auto}table{border-collapse:collapse;width:100%;font-size:14px}td,th{text-align:left;padding:16px 10px;border-bottom:1px solid #e4ebe6}th{color:#526a64;font-weight:500}td strong{white-space:nowrap}.price{font-size:21px;font-weight:700}.row{display:flex;gap:16px;align-items:center;flex-wrap:wrap}.spacer{margin-top:40px}footer{background:#e7eeea;padding:30px 24px;color:#405952}footer .inner{max-width:1160px;margin:auto;display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap}.danger{background:#8d453d}svg{width:100%;height:auto;max-height:260px}details{margin:20px 0}summary{cursor:pointer}.contact{font-style:normal}.hero{padding:18px 0 10px}.admin{border-color:#b7c9c0;background:#fbfdfc}@media(max-width:720px){.grid,.two,.filter-grid{grid-template-columns:1fr}h1{font-size:30px}.search{flex-direction:column}main{padding:24px 16px}header{padding:18px 16px}.card{padding:18px}.nav span{display:none}}
</style>
</head>
<body>
<header><a class="brand" href="{{ route('home') }}"><strong>◈ Price Scanner</strong></a><nav class="nav" aria-label="Navigație">@auth<span>Administrare</span><form method="post" action="{{ route('logout') }}">@csrf<button>Ieșire</button></form>@else<a href="#cum-functioneaza">Cum funcționează</a><a href="#contact">Contact</a>@endauth</nav></header>
<main>
@if(session('message'))<div class="notice" role="status">{{ session('message') }}</div>@endif
@if($errors->any())<div class="notice" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
@yield('content')
</main>
<footer id="contact"><div class="inner"><div><strong>Price Scanner</strong><br><small>Serviciu de comparare a prețurilor pentru piața din România. Proiect în faza inițială de lansare.</small></div><address class="contact"><strong>NOVELION S.R.L.</strong><br>CUI 52627291 · J2025075714007<br>Str. Daciei nr. 11, Ploiești, Prahova 100352, România<br><a href="mailto:novelionprime@gmail.com">novelionprime@gmail.com</a> · <a href="tel:+40750444672">0750 444 672</a><br>Luni–Vineri 09:00–17:00</address></div></footer>
</body>
</html>

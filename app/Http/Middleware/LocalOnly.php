<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocalOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1']), 403, 'Price Scanner este disponibil numai pe calculatorul local.');

        return $next($request);
    }
}

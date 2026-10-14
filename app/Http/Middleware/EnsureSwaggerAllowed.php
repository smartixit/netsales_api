<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSwaggerAllowed
{
    /**
     * Prevent accessing Swagger UI documentation in production.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            abort(404);
        }

        return $next($request);
    }
}

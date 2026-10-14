<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InputSanitization
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        array_walk_recursive($input, function (&$value, $key) {
            if (is_string($value) && ! in_array($key, ['password', 'password_confirmation', 'payload'])) {
                // Strip HTML tags globally array recursively ensuring payload XSS protection
                $value = strip_tags($value);

                // Enforce maximum length on search queries to prevent expensive LIKE '%...%' attacks
                if ($key === 'search' && mb_strlen($value) > 100) {
                    $value = mb_substr($value, 0, 100);
                }
            }
        });

        $request->merge($input);

        return $next($request);
    }
}

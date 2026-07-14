<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Middleware;

class SafeTerminateMiddleware
{
    public function handle($request, $next)
    {
        return $next($request);
    }

    public function terminate($request, $response): void
    {
        try {
            \Log::info('Request completed');
        } catch (\Throwable) {
        }
    }
}

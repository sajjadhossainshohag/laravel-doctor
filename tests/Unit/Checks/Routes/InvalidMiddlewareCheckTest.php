<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Routes;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Routes\InvalidMiddlewareCheck;
use SajjadHossain\Doctor\Enums\Severity;
use Illuminate\Support\Facades\Route;

class InvalidMiddlewareCheckTest extends TestCase
{
    /** @test */
    public function it_detects_unregistered_middleware_alias(): void
    {
        Route::get('/invalid-middleware', fn () => '')
            ->middleware('non.existent.middleware.alias')
            ->name('invalid');

        $result = (new InvalidMiddlewareCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
    }

    /** @test */
    public function it_passes_with_builtin_middleware(): void
    {
        Route::get('/valid', fn () => '')
            ->middleware('auth')
            ->name('valid');

        $result = (new InvalidMiddlewareCheck())->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_recognizes_kernel_registered_aliases(): void
    {
        // Register a custom alias into the booted Kernel, simulating
        // what app/Http/Kernel.php's $routeMiddleware property does.
        app('router')->aliasMiddleware('xss', \App\Http\Middleware\Authenticate::class);

        Route::get('/xss-protected', fn () => '')
            ->middleware('xss')
            ->name('xss');

        $result = (new InvalidMiddlewareCheck())->run();

        $this->assertCheckPassed($result, 'Middleware alias registered in Kernel should be recognized');
    }
}

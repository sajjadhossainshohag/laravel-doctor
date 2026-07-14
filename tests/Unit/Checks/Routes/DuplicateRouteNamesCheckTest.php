<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Routes;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Routes\DuplicateRouteNamesCheck;
use SajjadHossain\Doctor\Enums\Severity;
use Illuminate\Support\Facades\Route;

class DuplicateRouteNamesCheckTest extends TestCase
{
    /** @test */
    public function it_detects_duplicate_route_names(): void
    {
        Route::get('/a', fn () => '')->name('duplicate.name');
        Route::get('/b', fn () => '')->name('duplicate.name');

        $result = (new DuplicateRouteNamesCheck())->run();

        $this->assertCheckFailed($result, Severity::Error);
    }

    /** @test */
    public function it_passes_with_unique_route_names(): void
    {
        Route::get('/a', fn () => '')->name('unique.a');
        Route::get('/b', fn () => '')->name('unique.b');

        $result = (new DuplicateRouteNamesCheck())->run();

        $this->assertCheckPassed($result);
    }
}

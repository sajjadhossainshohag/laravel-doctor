<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Routes;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Routes\MissingControllerCheck;
use SajjadHossain\Doctor\Enums\Severity;
use Illuminate\Support\Facades\Route;

class MissingControllerCheckTest extends TestCase
{
    /** @test */
    public function it_detects_missing_controller_class(): void
    {
        Route::get('/broken-controller', 'App\\Http\\Controllers\\NonExistentController@index')
            ->name('broken');

        $result = (new MissingControllerCheck())->run();

        $this->assertCheckFailed($result, Severity::Error);
    }

    /** @test */
    public function it_passes_with_existing_controller(): void
    {
        Route::get('/ok', fn () => '')->name('ok');

        $result = (new MissingControllerCheck())->run();

        $this->assertCheckPassed($result);
    }
}

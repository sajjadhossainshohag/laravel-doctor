<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Livewire\V3\WireModel;

class V3WireModelComponent
{
    public string $view = 'livewire.v3-wire-model-component';

    // Has $name but NOT $missingProp — the view's wire:model="missingProp"
    // must be flagged.
    public string $name = '';

    public function save(): void {}
}
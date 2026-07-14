<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Http\Requests;

class BrokenRuleRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', new \NonExistentCustomRule()],
        ];
    }
}

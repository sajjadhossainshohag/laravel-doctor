<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlwaysTrueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => 'required'];
    }
}

<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlwaysFalseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return ['name' => 'required'];
    }
}

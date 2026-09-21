<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewCsvImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'catalog' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'default_source' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'],
        ];
    }
}

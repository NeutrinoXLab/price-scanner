<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualOfferRequest extends FormRequest
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
            'url' => ['required', 'url:http,https', 'max:2048'],
            'seller' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'regex:/^\d{1,9}([.,]\d{1,2})?$/'],
            'shipping' => ['nullable', 'regex:/^\d{1,9}([.,]\d{1,2})?$/'],
            'currency' => ['required', Rule::in(config('scanner.currencies'))],
            'ean' => ['nullable', 'string', 'max:32'],
            'sku' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'mpn' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'channel' => ['required', Rule::in(['B2B', 'B2C'])],
            'vat_included' => ['nullable', 'boolean'],
            'moq' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'pack_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'availability' => ['required', Rule::in(['in_stock', 'out_of_stock', 'preorder', 'unknown'])],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'requestType' => 'required|in:productRequest,pricingRequest,contactRequest',
            'industry' => 'required|string|max:255',
            'preferred_distance' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
        ];
    }
}
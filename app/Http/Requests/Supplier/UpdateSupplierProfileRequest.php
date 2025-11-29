<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['whoDoYouServe', 'targetCustomers'] as $arrKey) {
            if ($this->has($arrKey)) {
                $val = $this->input($arrKey);
                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $this->merge([$arrKey => $decoded]);
                    } else {
                        $this->merge([$arrKey => [$val]]);
                    }
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'businessName' => ['sometimes', 'string', 'max:255'],
            'businessType' => ['sometimes', 'string', Rule::in(\App\Support\Constants::BUSINESS_TYPES)],
            'category' => ['sometimes', 'string', 'max:255'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:255'],
            'services' => ['sometimes', 'array'],
            'services.*' => ['string', 'max:255'],
            'productKeywords' => ['sometimes', 'array'],
            'productKeywords.*' => ['string', 'max:255'],
            'targetCustomers' => ['sometimes', 'array'],
            'targetCustomers.*' => ['string', 'max:255'],
            'whoDoYouServe' => ['sometimes', 'array'],
            'whoDoYouServe.*' => ['string', 'max:255'],
            'serviceDistance' => ['sometimes', 'string', 'max:255'],
            'additionalPhones' => ['sometimes', 'array'],
            'additionalPhones.*.number' => ['required_with:additionalPhones', 'string', 'max:20'],
            'additionalPhones.*.name' => ['nullable', 'string', 'max:255'],
            'additionalPhones.*.type' => ['nullable', 'string', 'max:50'],
            'workingHours' => ['sometimes', 'array'],
            'workingHours.*.closed' => ['sometimes', 'boolean'],
            'workingHours.*.open' => ['sometimes', 'string'],
            'workingHours.*.close' => ['sometimes', 'string'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'contactEmail' => [
                'sometimes',
                'email',
                'max:255',
            ],
            'contactPhone' => ['sometimes', 'string', 'max:20'],
            'mainPhone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'location.lat' => ['sometimes', 'numeric'],
            'location.lng' => ['sometimes', 'numeric'],
            'hasBranches' => ['sometimes', 'boolean'],
            'branches' => ['sometimes', 'array'],
            'branches.*.name' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.phone' => ['required_with:branches', 'string', 'max:20'],
            'branches.*.email' => ['nullable', 'email', 'max:255'],
            'branches.*.address' => ['required_with:branches', 'string', 'max:500'],
            'branches.*.manager' => ['required_with:branches', 'string', 'max:255'],
            'branches.*.location.lat' => ['sometimes', 'numeric'],
            'branches.*.location.lng' => ['sometimes', 'numeric'],
            'branches.*.workingHours' => ['sometimes', 'array'],
            'branches.*.specialServices' => ['sometimes', 'array'],
            'branches.*.isMainBranch' => ['sometimes', 'boolean'],
            'document' => ['sometimes', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}

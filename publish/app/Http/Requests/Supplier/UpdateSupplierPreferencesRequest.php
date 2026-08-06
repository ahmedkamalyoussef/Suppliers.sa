<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierPreferencesRequest extends FormRequest
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
            // Notification Preferences
            'emailNotifications' => ['sometimes', 'boolean'],
            'smsNotifications' => ['sometimes', 'boolean'],
            'newInquiriesNotifications' => ['sometimes', 'boolean'],
            'profileViewsNotifications' => ['sometimes', 'boolean'],
            'weeklyReports' => ['sometimes', 'boolean'],
            'marketingEmails' => ['sometimes', 'boolean'],
            
            // Security Preferences
            'profileVisibility' => ['sometimes', 'string', 'in:public,limited'],
            'showEmailPublicly' => ['sometimes', 'boolean'],
            'showPhonePublicly' => ['sometimes', 'boolean'],
            'allowDirectContact' => ['sometimes', 'boolean'],
            'allowSearchEngineIndexing' => ['sometimes', 'boolean'],
        ];
    }
}

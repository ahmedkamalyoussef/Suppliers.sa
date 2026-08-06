<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Or add your authorization logic here
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user() ? $this->user()->id : null;
        
        return [
            // Profile fields
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($userId),
                Rule::unique('admins', 'email')
            ],
            'phone' => [
                'sometimes',
                'string',
                'max:20',
            ],
            'businessName' => ['sometimes', 'string', 'max:255'],
            
            // Notification preferences
            'emailNotifications' => ['sometimes', 'boolean'],
            'smsNotifications' => ['sometimes', 'boolean'],
            'newInquiriesNotifications' => ['sometimes', 'boolean'],
            'profileViewsNotifications' => ['sometimes', 'boolean'],
            'weeklyReports' => ['sometimes', 'boolean'],
            'marketingEmails' => ['sometimes', 'boolean'],
            
            // Privacy preferences
            'profileVisibility' => ['sometimes', 'string', 'in:public,limited,private'],
            'showEmailPublicly' => ['sometimes', 'boolean'],
            'showPhonePublicly' => ['sometimes', 'boolean'],
            'allowDirectContact' => ['sometimes', 'boolean'],
            'allowSearchEngineIndexing' => ['sometimes', 'boolean']
        ];
    }
}

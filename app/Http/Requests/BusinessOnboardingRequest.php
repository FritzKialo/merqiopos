<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessOnboardingRequest extends FormRequest {
    public function authorize(): bool {
        return $this->user() !== null;
    }

    public function rules(): array {
        return [
            'business_name'     => 'required|string|max:255',
            'business_email'    => 'required|email|unique:businesses,email',
            'business_phone'    => 'required|string|max:20',
            'business_address'  => 'nullable|string|max:255',
            'business_city'     => 'nullable|string|max:100',
            'industry'          => 'nullable|string|max:100',
        ];
    }

    public function messages(): array {
        return [
            'business_name.required'  => 'Business name is required.',
            'business_email.unique'   => 'This business email is already registered.',
        ];
    }
}

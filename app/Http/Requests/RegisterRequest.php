<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            // Business fields
            'business_name'     => 'required|string|max:255',
            'business_email'    => 'required|email|unique:businesses,email',
            'business_phone'    => 'required|string|max:20',
            'business_address'  => 'nullable|string|max:255',
            'business_city'     => 'nullable|string|max:100',
            'industry'          => 'nullable|string|max:100',

            // Owner fields
            'owner_name'        => 'required|string|max:255',
            'owner_email'       => 'required|email|unique:users,email',
            'password'          => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array {
        return [
            'business_name.required'  => 'Business name is required.',
            'business_email.unique'   => 'This business email is already registered.',
            'owner_email.unique'      => 'This email is already taken.',
            'password.confirmed'      => 'Passwords do not match.',
            'password.min'            => 'Password must be at least 8 characters.',
        ];
    }
}
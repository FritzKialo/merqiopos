<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasswordChangeRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'current_password' =>
                'required|string',
            'password'         =>
                'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array {
        return [
            'current_password.required' =>
                'Current password is required.',
            'password.required'         =>
                'New password is required.',
            'password.min'              =>
                'Password must be at least 8 characters.',
            'password.confirmed'        =>
                'Passwords do not match.',
        ];
    }
}
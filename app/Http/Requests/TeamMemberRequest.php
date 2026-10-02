<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamMemberRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $userId = $this->route('user')
            ? $this->route('user')->id
            : null;

        return [
            'name'     => 'required|string|max:255',
            'email'    => [
                'required',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($userId),
            ],
            'role'     => 'required|in:overall_manager,manager,cashier,staff',
            'password' => $userId
                // On edit password is optional
                ? 'nullable|string|min:8|confirmed'
                // On create password is required
                : 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array {
        return [
            'name.required'      =>
                'Full name is required.',
            'email.required'     =>
                'Email address is required.',
            'email.unique'       =>
                'This email is already registered.',
            'role.required'      =>
                'Role is required.',
            'password.required'  =>
                'Password is required.',
            'password.min'       =>
                'Password must be at least 8 characters.',
            'password.confirmed' =>
                'Passwords do not match.',
        ];
    }
}
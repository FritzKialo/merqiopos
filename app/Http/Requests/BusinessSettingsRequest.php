<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BusinessSettingsRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'name'     => 'required|string|max:255',
            'email'    => [
                'required',
                'email',
                // The form saves to currentBusiness() (whichever store is
                // active right now, per SettingsController::getBusiness())
                // — ignoring Auth::user()->business_id instead meant this
                // could reference a DIFFERENT business than the one being
                // saved for any owner with more than one store, causing
                // the store's own unchanged email to fail as "already in
                // use" against itself and blocking the whole form from
                // ever saving.
                Rule::unique('businesses', 'email')
                    ->ignore(
                        Auth::user()->currentBusiness()->id
                    ),
            ],
            'phone'    => 'required|string|max:20',
            'address'       => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'industry'      => 'nullable|string|max:100',
            'kra_pin'       => 'nullable|string|max:20',
            'payment_terms' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array {
        return [
            'name.required'  =>
                'Business name is required.',
            'email.required' =>
                'Business email is required.',
            'email.unique'   =>
                'This email is already in use.',
            'phone.required' =>
                'Phone number is required.',
        ];
    }
}
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $customerId = $this->route('customer')
            ? $this->route('customer')->id
            : null;

        return [
            'name'    => 'required|string|max:255',
            'phone'   => [
                'nullable',
                'string',
                'max:20',
                // Phone unique per business — scoped to currentBusiness(),
                // not Auth::user()->business_id (a different, not-
                // necessarily-current field for any owner with more than
                // one store; same bug as BusinessSettingsRequest's email
                // check).
                Rule::unique('customers', 'phone')
                    ->where('business_id',
                        Auth::user()->currentBusiness()->id)
                    // A deleted customer no longer holds its phone — without
                    // this, deleting a customer made that phone impossible
                    // to use again for a new one.
                    ->whereNull('deleted_at')
                    ->ignore($customerId),
            ],
            'email'   => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where('business_id',
                        Auth::user()->currentBusiness()->id)
                    // A deleted customer no longer holds its email — without
                    // this, deleting a customer made that email impossible
                    // to use again for a new one.
                    ->whereNull('deleted_at')
                    ->ignore($customerId),
            ],
            'address'       => 'nullable|string|max:255',
            'notes'         => 'nullable|string|max:500',
            'price_tier_id' => 'nullable|exists:price_tiers,id',
        ];
    }

    public function messages(): array {
        return [
            'name.required'  => 
                'Customer name is required.',
            'phone.unique'   => 
                'This phone number is already registered.',
            'email.unique'   => 
                'This email is already registered.',
            'email.email'    => 
                'Enter a valid email address.',
        ];
    }
}
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'customer_id'          => 'nullable|exists:customers,id',
            'quote_date'           => 'required|date',
            'valid_until'          => 'nullable|date|after_or_equal:quote_date',
            'notes'                => 'nullable|string|max:1000',
            'terms'                => 'nullable|string|max:2000',
            'discount_amount'      => 'nullable|numeric|min:0',
            'tax_rate'             => 'nullable|numeric|min:0|max:100',

            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'nullable|integer',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.description'  => 'nullable|string',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.quantity'     => 'required|integer|min:1',
        ];
    }

    public function messages(): array {
        return [
            'items.required'                  => 'Add at least one line item.',
            'items.min'                       => 'Add at least one line item.',
            'items.*.product_name.required'   => 'Product name is required for each row.',
            'items.*.unit_price.required'     => 'Unit price is required for each row.',
            'items.*.quantity.required'       => 'Quantity is required for each row.',
            'items.*.quantity.min'            => 'Quantity must be at least 1.',
            'valid_until.after_or_equal'      => 'Valid until must be on or after the quote date.',
        ];
    }
}

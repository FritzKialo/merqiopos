<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'offline_id'     => 'nullable|string|max:36',
            'customer_id'    => 'nullable|exists:customers,id',
            'payment_method' => 'required|in:cash,mpesa,bank_transfer,credit',
            'mpesa_reference'=> 'nullable|string|max:50',
            'paid_amount'    => 'required|numeric|min:0',
            'notes'          => 'nullable|string|max:500',
            'discount_amount'=> 'nullable|numeric|min:0',

            'discount_id'            => 'nullable|exists:discounts,id',
            'coupon_discount_amount'  => 'nullable|numeric|min:0',
            'loyalty_points_redeemed' => 'nullable|numeric|min:0',
            'loyalty_discount_amount' => 'nullable|numeric|min:0',

            // Items array validation
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount'   => 'nullable|numeric|min:0|max:100',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.serial_id'  => 'nullable|exists:serial_numbers,id',
            'items.*.bundle_id'  => 'nullable|exists:product_bundles,id',
        ];
    }

    public function messages(): array {
        return [
            'items.required'             => 
                'Add at least one product.',
            'items.min'                  => 
                'Add at least one product.',
            'items.*.product_id.required'=> 
                'Select a product for each row.',
            'items.*.quantity.required'  => 
                'Quantity is required.',
            'items.*.quantity.min'       => 
                'Quantity must be at least 1.',
            'items.*.unit_price.required'=> 
                'Unit price is required.',
        ];
    }
}
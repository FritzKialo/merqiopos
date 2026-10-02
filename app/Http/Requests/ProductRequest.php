<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $productId = $this->route('product')
            ? $this->route('product')->id
            : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                // Scoped to currentBusiness(), not Auth::user()->business_id
                // (a different, not-necessarily-current field for any
                // owner with more than one store; same bug as
                // BusinessSettingsRequest's email check).
                Rule::unique('products', 'sku')
                    ->where('business_id',
                        Auth::user()->currentBusiness()->id)
                    ->ignore($productId),
            ],
            'category_id' => [
                'nullable',
                'exists:categories,id',
            ],
            'sub_category_id' => [
                'nullable',
                'exists:categories,id',
            ],
            'brand'         => 'nullable|string|max:100',
            'barcode'       => 'nullable|string|max:100',
            'barcode_symbology' => 'nullable|string|in:CODE128,EAN13,EAN8,UPC,CODE39',
            'description'   => 'nullable|string|max:1000',
            'image'         => 'nullable|image|max:1024', // 1MB, matches Techlion's own limit
            'gallery.*'     => 'nullable|image|max:1024',
            'buying_price'  => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'tax_category'  => 'nullable|in:standard,reduced,zero_rated,exempt',
            'etims_item_cls_cd' => ['nullable', 'string', 'regex:/^\d{8,10}$/'],
            'stock_qty'     => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
            'unit'          => 'required|string|max:50',
            'buy_unit'          => 'nullable|string|max:40',
            'units_per_buy_unit' => 'nullable|integer|min:1',
            'status'        => 'required|in:active,inactive',
            'is_featured'   => 'nullable|boolean',
            'hide_in_pos'   => 'nullable|boolean',
            'hide_in_shop'  => 'nullable|boolean',
            'track_batches'     => 'nullable|boolean',
            'track_serials'     => 'nullable|boolean',
            'expiry_alert_days' => 'nullable|integer|min:1|max:3650',
            'supplier_ids'          => 'nullable|array',
            'supplier_ids.*'        => 'nullable|exists:suppliers,id',
            'supplier_part_no'      => 'nullable|array',
            'supplier_part_no.*'    => 'nullable|string|max:100',
            'supplier_price'        => 'nullable|array',
            'supplier_price.*'      => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array {
        return [
            'name.required'          => 
                'Product name is required.',
            'buying_price.required'  => 
                'Buying price is required.',
            'selling_price.required' => 
                'Selling price is required.',
            'stock_qty.required'     => 
                'Stock quantity is required.',
            'reorder_level.required' => 
                'Reorder level is required.',
            'sku.unique'             => 
                'This SKU is already in use.',
        ];
    }
}
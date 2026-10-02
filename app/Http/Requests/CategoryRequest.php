<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CategoryRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        // On edit, exclude current category
        // from unique check
        $categoryId = $this->route('category')
            ? $this->route('category')->id
            : null;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // Unique per business, ignore self on update — scoped to
                // currentBusiness() to match what InventoryController
                // actually saves against; Auth::user()->business_id is a
                // different, not-necessarily-current field for any owner
                // with more than one store (same bug as
                // BusinessSettingsRequest's email check).
                \Illuminate\Validation\Rule::unique(
                    'categories', 'name'
                )
                ->where('business_id',
                    Auth::user()->currentBusiness()->id)
                ->ignore($categoryId),
            ],
            'description' => 'nullable|string|max:255',
            'parent_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('categories', 'id')
                    ->where('business_id', Auth::user()->currentBusiness()->id),
                // A sub-category can't itself have children — keeps the
                // tree exactly one level deep, matching what the product
                // form's category/sub-category pair actually supports.
                function ($attribute, $value, $fail) use ($categoryId) {
                    if (!$value) return;
                    if ($value == $categoryId) {
                        $fail('A category cannot be its own parent.');
                        return;
                    }
                    $parent = \App\Models\Category::find($value);
                    if ($parent && $parent->parent_id) {
                        $fail('Choose a top-level category as the parent — sub-categories can\'t be nested further.');
                    }
                },
            ],
        ];
    }

    public function messages(): array {
        return [
            'name.required' => 'Category name is required.',
            'name.unique'   => 
                'This category already exists.',
        ];
    }
}
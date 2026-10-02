<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'title'               =>
                'required|string|max:255',
            'expense_category_id' =>
                'nullable|exists:expense_categories,id',
            'amount'              =>
                'required|numeric|min:0.01',
            'payment_method'      =>
                'required|in:cash,mpesa,bank_transfer',
            'reference'           =>
                'nullable|string|max:100',
            'expense_date'        =>
                'required|date|before_or_equal:today',
            'description'         =>
                'nullable|string|max:1000',
        ];
    }

    public function messages(): array {
        return [
            'title.required'          =>
                'Expense title is required.',
            'amount.required'         =>
                'Amount is required.',
            'amount.min'              =>
                'Amount must be greater than zero.',
            'expense_date.required'   =>
                'Expense date is required.',
            'expense_date.before_or_equal' =>
                'Expense date cannot be in the future.',
            'payment_method.required' =>
                'Payment method is required.',
        ];
    }
}
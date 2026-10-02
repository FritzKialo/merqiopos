<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductImportRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'import_file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls,ods',
                'max:5120', // 5MB
            ],
        ];
    }

    public function messages(): array {
        return [
            'import_file.required' => 'Please select a file to upload.',
            'import_file.mimes'    => 'Allowed formats: CSV, Excel (.xlsx / .xls), ODS.',
            'import_file.max'      => 'The file may not be larger than 5MB.',
        ];
    }
}

@extends('layouts.app')
@section('title', 'Import Products')

@section('content')
<div class="page">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Import Products</h1>
            <p class="page-subtitle">Upload a CSV, Excel or ODS file to add multiple products at once.</p>
        </div>
        <a href="{{ route('inventory.index') }}" class="btn btn--outline">
            
            Back to Inventory
        </a>
    </div>

    <div class="import-layout">

        {{-- Upload Card --}}
        <div class="report-section">
            <div class="report-section-header">
                <h2 class="card-title">Upload File</h2>
            </div>
            <div class="card-body">

                <form method="POST"
                      action="{{ route('inventory.import.store') }}"
                      enctype="multipart/form-data"
                      id="importForm">
                    @csrf

                    <div class="form-group">
                        <label class="form-label">Product file <span class="required">*</span></label>

                        <div class="file-drop-zone" id="dropZone">
                            <input type="file"
                                   name="import_file"
                                   id="import_file"
                                   accept=".csv,.txt,.xlsx,.xls,.ods"
                                   class="file-input"
                                   required>
                            <div class="file-drop-content" id="dropContent">

                                <p class="file-drop-label">
                                    Drop your file here or <span class="file-drop-link">browse</span>
                                </p>
                                <p class="file-drop-hint">CSV, Excel (.xlsx / .xls) or ODS — max 5MB</p>
                            </div>
                            <div class="file-selected" id="fileSelected" style="display:none;">
                                
                                <span id="fileName"></span>
                                <button type="button" class="file-clear" id="clearFile" title="Remove file" aria-label="Remove file">
                                    &times;
                                </button>
                            </div>
                        </div>

                        @error('import_file')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary btn--lg" id="importBtn">
                            
                            Import Products
                        </button>
                        <a href="{{ route('inventory.index') }}" class="btn btn--outline btn--lg">
                            Cancel
                        </a>
                    </div>
                </form>

            </div>
        </div>

        {{-- Instructions Card --}}
        <div class="report-section">
            <div class="report-section-header">
                <h2 class="card-title">File format</h2>
            </div>
            <div class="card-body">

                <p class="import-info">
                    Download the CSV template, fill in your products, and upload as CSV
                    <em>or</em> copy the columns into an Excel (.xlsx) or ODS spreadsheet.
                    New categories are created automatically.
                </p>

                <a href="{{ route('inventory.import.template') }}" class="btn btn--outline btn--sm mb-4">
                    
                    Download Template
                </a>

                <table class="import-schema-table table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Column</th>
                            <th>Required</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td data-label="Column"><code>name</code></td>
                            <td data-label="Required"><span class="badge badge--red">Required</span></td>
                            <td data-label="Notes">Product name</td>
                        </tr>
                        <tr>
                            <td data-label="Column"><code>selling_price</code></td>
                            <td data-label="Required"><span class="badge badge--red">Required</span></td>
                            <td data-label="Notes">Price in KES (numbers only)</td>
                        </tr>
                        <tr>
                            <td data-label="Column"><code>buying_price</code></td>
                            <td data-label="Required"><span class="badge badge--gray">Optional</span></td>
                            <td data-label="Notes">Cost price in KES (default 0)</td>
                        </tr>
                        <tr>
                            <td data-label="Column"><code>sku</code></td>
                            <td data-label="Required"><span class="badge badge--gray">Optional</span></td>
                            <td data-label="Notes">Leave blank to auto-generate</td>
                        </tr>
                        <tr>
                            <td data-label="Column"><code>category</code></td>
                            <td data-label="Required"><span class="badge badge--gray">Optional</span></td>
                            <td data-label="Notes">Category name — created if not found</td>
                        </tr>
                        <tr>
                            <td data-label="Column"><code>stock_qty</code></td>
                            <td data-label="Required"><span class="badge badge--gray">Optional</span></td>
                            <td data-label="Notes">Current stock quantity (default 0)</td>
                        </tr>
                        <tr>
                            <td data-label="Column"><code>reorder_level</code></td>
                            <td data-label="Required"><span class="badge badge--gray">Optional</span></td>
                            <td data-label="Notes">Low stock threshold (default 0)</td>
                        </tr>
                        <tr>
                            <td data-label="Column"><code>unit</code></td>
                            <td data-label="Required"><span class="badge badge--gray">Optional</span></td>
                            <td data-label="Notes">e.g. pcs, kg, litres</td>
                        </tr>
                        <tr>
                            <td data-label="Column"><code>description</code></td>
                            <td data-label="Required"><span class="badge badge--gray">Optional</span></td>
                            <td data-label="Notes">Short product description</td>
                        </tr>
                        <tr>
                            <td data-label="Column"><code>status</code></td>
                            <td data-label="Required"><span class="badge badge--gray">Optional</span></td>
                            <td data-label="Notes"><code>active</code> or <code>inactive</code> (default active)</td>
                        </tr>
                    </tbody>
                </table>

            </div>
        </div>

    </div>{{-- /.import-layout --}}
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input    = document.getElementById('import_file');
    const dropZone = document.getElementById('dropZone');
    const content  = document.getElementById('dropContent');
    const selected = document.getElementById('fileSelected');
    const nameEl   = document.getElementById('fileName');
    const clearBtn = document.getElementById('clearFile');
    const form     = document.getElementById('importForm');
    const importBtn = document.getElementById('importBtn');

    function showFile(name) {
        nameEl.textContent = name;
        content.style.display  = 'none';
        selected.style.display = 'flex';
    }

    function clearFile() {
        input.value = '';
        content.style.display  = '';
        selected.style.display = 'none';
    }

    input.addEventListener('change', () => {
        if (input.files[0]) showFile(input.files[0].name);
    });

    clearBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        clearFile();
    });

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-over');
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            showFile(file.name);
        }
    });

    form.addEventListener('submit', () => {
        importBtn.disabled = true;
        importBtn.innerHTML = ' Importing…';
    });
}());
</script>
@endpush

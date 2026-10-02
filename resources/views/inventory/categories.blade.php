@extends('layouts.app')
@section('title', 'Categories')

@push('styles')
    <link rel="stylesheet" 
          href="{{ asset('css/inventory.css') }}?v={{ @filemtime(public_path('css/inventory.css')) ?: '1' }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">
            &#128193; Categories
        </h1>
        <p class="page-subtitle">
            Organise your products into categories
        </p>
    </div>
    <a href="{{ route('inventory.index') }}" 
       class="btn btn-outline">
        &#8592; Back to Inventory
    </a>
</div>

<div style="display: grid; 
            grid-template-columns: 1fr 1.5fr; 
            gap: var(--space-lg); 
            align-items: start;">

    {{-- Add Category Form --}}
    <div class="form-card" 
         style="max-width: 100%;">
        <div class="form-section-title">
            Add New Category
        </div>

        <form method="POST" 
              action="{{ route(
                'inventory.categories.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="name">
                    Category Name *
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name"
                    class="form-control 
                        {{ $errors->has('name') 
                            ? 'is-invalid' : '' }}"
                    value="{{ old('name') }}"
                    placeholder="e.g. Beverages"
                    required>
                @error('name')
                    <span class="invalid-feedback">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label"
                       for="description">
                    Description
                </label>
                <input
                    type="text"
                    id="description"
                    name="description"
                    class="form-control"
                    value="{{ old('description') }}"
                    placeholder="Optional">
            </div>

            <div class="form-group">
                <label class="form-label" for="parent_id">Parent Category</label>
                <select id="parent_id" name="parent_id" class="form-control">
                    <option value="">— None (top-level) —</option>
                    @foreach($topCategories as $top)
                        <option value="{{ $top->id }}" {{ old('parent_id') == $top->id ? 'selected' : '' }}>{{ $top->name }}</option>
                    @endforeach
                </select>
                <span class="form-hint">Leave as "None" for a top-level category, or pick one to make this a sub-category.</span>
            </div>

            <button type="submit"
                    class="btn btn-primary">
                &#43; Add Category
            </button>
        </form>
    </div>

    {{-- Categories Table --}}
    <div class="report-section">
        <div class="report-section-header">
            <h2>All Categories 
                ({{ $categories->count() }})
            </h2>
        </div>

        @if($categories->isEmpty())
            <div class="empty-state">
                <div class="empty-state-icon">
                    &#128193;
                </div>
                <h3>No categories yet</h3>
                <p>Add your first category.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $cat)
                        <tr>
                            <td data-label="Name">
                                @if($cat->parent_id)
                                    <span style="color:var(--color-text-muted);">&#8627;</span>
                                @endif
                                <strong>{{ $cat->name }}</strong>
                                @if($cat->parent)
                                    <span style="color:var(--color-text-muted); font-size:12px;">under {{ $cat->parent->name }}</span>
                                @endif
                            </td>
                            <td data-label="Description">
                                {{ $cat->description ?? '—' }}
                            </td>
                            <td data-label="Products">
                                <span class="badge
                                    badge-neutral">
                                    {{ $cat->products_count }}
                                </span>
                            </td>
                            <td data-label="">
                                <div class="action-buttons">
                                    <button
                                        class="btn btn-outline btn-sm"
                                        onclick="editCategory(
                                            {{ $cat->id }},
                                            '{{ addslashes($cat->name) }}',
                                            '{{ addslashes($cat->description ?? '') }}',
                                            '{{ $cat->parent_id }}'
                                        )">
                                        Edit
                                    </button>
                                    <form 
                                        method="POST"
                                        action="{{ route(
                                            'inventory.categories.destroy',
                                            $cat) }}"
                                        onsubmit="return confirm(
                                            'Delete {{ addslashes($cat->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-danger btn-sm">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Edit Category Modal --}}
<div class="modal-overlay" id="editCategoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Category</h3>
            <button class="modal-close" 
                    onclick="
                        document.getElementById(
                            'editCategoryModal'
                        ).classList.remove('open')">
                &times;
            </button>
        </div>
        <form method="POST" id="editCategoryForm">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label class="form-label">
                    Category Name *
                </label>
                <input 
                    type="text" 
                    name="name"
                    id="editCategoryName"
                    class="form-control"
                    required>
            </div>
            <div class="form-group">
                <label class="form-label">
                    Description
                </label>
                <input
                    type="text"
                    name="description"
                    id="editCategoryDescription"
                    class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Parent Category</label>
                <select name="parent_id" id="editCategoryParent" class="form-control">
                    <option value="">— None (top-level) —</option>
                    @foreach($topCategories as $top)
                        <option value="{{ $top->id }}">{{ $top->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button 
                    type="button"
                    class="btn btn-outline"
                    onclick="
                        document.getElementById(
                            'editCategoryModal'
                        ).classList.remove('open')">
                    Cancel
                </button>
                <button type="submit" 
                        class="btn btn-primary">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
    <script src="{{ asset('js/inventory.js') }}?v={{ @filemtime(public_path('js/inventory.js')) ?: '1' }}"></script>
@endpush
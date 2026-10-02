<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model {
    use HasFactory;

    protected $fillable = [
        'business_id',
        'parent_id',
        'name',
        'description',
    ];

    // Category belongs to a business
    public function business() {
        return $this->belongsTo(Business::class);
    }

    // Parent category (null for a top-level category)
    public function parent() {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Sub-categories directly under this one
    public function children() {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('name');
    }

    public function isSubCategory(): bool {
        return !is_null($this->parent_id);
    }

    // Category has many products
    public function products() {
        return $this->hasMany(Product::class);
    }

    // Count only active products
    public function activeProducts() {
        return $this->hasMany(Product::class)
            ->where('status', 'active');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = [
        'sku', 'mpn', 'name', 'slug', 'brand_id', 'primary_category_id',
        'condition', 'price_cents', 'stock', 'is_active', 'attributes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'attributes' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot(['is_primary', 'source']);
    }

    public function models(): BelongsToMany
    {
        return $this->belongsToMany(DeviceModel::class, 'product_model')
            ->withPivot('source');
    }

    /** All appliance types this product fits, derived from compatibility. */
    public function applianceTypeIds(): array
    {
        return $this->models->pluck('appliance_category_id')->unique()->values()->all();
    }
}

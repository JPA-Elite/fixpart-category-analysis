<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A physical appliance model / type number (Typennummer, PNC).
 * Table is "models"; the class is DeviceModel to avoid clashing with Eloquent's Model.
 */
class DeviceModel extends Model
{
    protected $table = 'models';

    protected $fillable = [
        'brand_id', 'appliance_category_id', 'type_number', 'name', 'slug', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function applianceCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'appliance_category_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_model')
            ->withPivot('source');
    }
}

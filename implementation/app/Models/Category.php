<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'parent_id', 'type', 'name', 'slug', 'path', 'depth',
        'position', 'is_active', 'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['is_primary', 'source']);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    /**
     * All ids of this node and its descendants. Used to build the Meilisearch
     * filter for a category page (one filter, no hierarchy support needed).
     *
     * @return array<int>
     */
    public function subtreeIds(): array
    {
        return static::query()
            ->where('type', $this->type)
            ->where(fn ($q) => $q->where('id', $this->id)
                ->orWhere('path', 'like', $this->path . '.%'))
            ->pluck('id')
            ->all();
    }

    public function ancestors(): array
    {
        $chain = [];
        $node = $this->parent;
        $guard = 0;

        while ($node && $guard++ < 10) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }

    public static function ensurePath(string $type, array $segments, ?int $parentId = null, int $depth = 1): ?self
    {
        if ($segments === []) {
            return null;
        }

        $name = trim(array_shift($segments));
        $slug = Str::slug($name);

        $category = static::firstOrCreate(
            ['type' => $type, 'parent_id' => $parentId, 'slug' => $slug],
            [
                'name' => $name,
                'depth' => $depth,
                'path' => $parentId
                    ? static::findOrFail($parentId)->path . '.' . $slug
                    : $slug,
            ]
        );

        return $segments === [] ? $category : static::ensurePath($type, $segments, $category->id, $depth + 1);
    }
}

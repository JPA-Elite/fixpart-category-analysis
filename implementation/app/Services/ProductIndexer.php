<?php

namespace App\Services;

use App\Models\Product;
use Meilisearch\Client;

/**
 * Builds one Meilisearch document per product. Everything the storefront must
 * filter or facet on is denormalized into the document; the hierarchy itself
 * stays in PostgreSQL.
 */
class ProductIndexer
{
    public function __construct(private Client $client)
    {
    }

    public function index(Product $product): void
    {
        $product->loadMissing(['brand', 'categories.parent', 'models.brand', 'models.applianceCategory']);

        $primary = $product->categories->firstWhere('pivot.is_primary', true);
        $ancestors = $primary ? $this->ancestorChain($primary) : [];

        $document = [
            'id' => $product->id,
            'sku' => $product->sku,
            'mpn' => array_values(array_filter([$product->mpn])),
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand?->name,
            'brand_id' => $product->brand_id,
            'price' => $product->price_cents !== null ? $product->price_cents / 100 : null,
            'in_stock' => $product->stock > 0,
            'condition' => $product->condition,
            'created_at' => $product->created_at?->timestamp,

            // Flat category path: the frontend renders breadcrumbs from this,
            // filters use category_ids (subtree resolved in PostgreSQL).
            'category_ids' => $product->categories->pluck('id')->all(),
            'category_names' => $product->categories->pluck('name')->all(),
            'category_slugs' => $product->categories->pluck('slug')->all(),
            'primary_category_id' => $primary?->id,
            'primary_category_slug' => $primary?->slug,
            'category_path' => array_map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug], $ancestors),

            // Denormalized appliance dimension (facet + filters).
            'appliance_types' => $product->models
                ->pluck('applianceCategory.name')->filter()->unique()->values()->all(),
            'appliance_type_ids' => $product->models
                ->pluck('applianceCategory.id')->filter()->unique()->values()->all(),
            'device_brands' => $product->models
                ->pluck('brand.name')->filter()->unique()->values()->all(),

            // Compatibility is searched separately (see models index), but the
            // most common type numbers are useful for direct product search.
            'model_numbers' => $product->models->pluck('type_number')->unique()->take(50)->values()->all(),
        ];

        $this->client->index('products')->addDocuments([$document], 'id');
    }

    /** @return array<int, \App\Models\Category> */
    private function ancestorChain($category): array
    {
        $chain = [];
        $node = $category;

        while ($node) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }

    /**
     * Separate, tiny index for "find part by type number".
     * One document per model; filter by brand/appliance, then show products.
     */
    public function indexModel(\App\Models\DeviceModel $model, array $productIds): void
    {
        $this->client->index('models')->addDocuments([[
            'id' => $model->id,
            'type_number' => $model->type_number,
            'type_number_normalized' => str_replace([' ', '-', '/'], '', $model->type_number),
            'brand' => $model->brand?->name,
            'appliance' => $model->applianceCategory?->name,
            'product_ids' => $productIds,
        ]], 'id');
    }
}

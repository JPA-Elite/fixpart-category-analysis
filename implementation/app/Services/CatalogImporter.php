<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\DeviceModel;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Maps one normalized article from the wholesaler feed into our catalog.
 *
 * Expected (normalized) payload:
 * [
 *   'sku'             => '0005283590',
 *   'mpn'             => '00705901',
 *   'name'            => 'Bosch Siemens 00705901 Türfach',
 *   'brand'           => 'Bosch Siemens',              // brand of the part
 *   'category_path'   => 'Mechanisch > Gehäuseteil > Tür/Ventil/Deckel > Tür > Türfach',
 *   'appliance_types' => ['Kühlschrank', 'Gefrierschrank'],
 *   'models'          => [['brand' => 'Bosch', 'type_number' => 'KGN39XI31/08'], ...],
 *   'condition'       => 'original',                    // original|aftermarket|refurbished
 *   'price_cents'     => 3095,
 *   'stock'           => 12,
 * ]
 */
class CatalogImporter
{
    public function __construct(private ProductIndexer $indexer)
    {
    }

    public function importArticle(array $row): Product
    {
        return DB::transaction(function () use ($row) {
            $brand = $this->brand($row['brand'] ?? null);

            $product = Product::updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'mpn' => $row['mpn'] ?? null,
                    'name' => $row['name'],
                    'slug' => $this->uniqueProductSlug($row['name'], $row['sku']),
                    'brand_id' => $brand?->id,
                    'condition' => $row['condition'] ?? 'original',
                    'price_cents' => $row['price_cents'] ?? null,
                    'stock' => $row['stock'] ?? 0,
                    'is_active' => true,
                ]
            );

            // 1. Primary classification (single path from the feed).
            $path = $this->splitPath($row['category_path'] ?? '');
            if ($path !== []) {
                $leaf = $this->category('part', $path);
                $product->categories()->syncWithoutDetaching([
                    $leaf->id => ['is_primary' => true, 'source' => 'feed'],
                ]);
                $product->primary_category_id = $leaf->id;
                $product->save();
            }

            // 2. Device dimension. Appliance types are categories of type "appliance";
            //    they are NOT part of the part tree and never duplicate products in it.
            foreach ($row['appliance_types'] ?? [] as $appliance) {
                $this->category('appliance', [$appliance]);
            }

            // 3. Compatibility (product <-> model). This is what makes a part
            //    appear under /waschmaschine-ersatzteile/turgummi without being
            //    assigned to a second category.
            $defaultAppliance = $row['appliance_types'][0] ?? null;

            foreach ($row['models'] ?? [] as $entry) {
                $model = $this->model(
                    $entry['brand'] ?? null,
                    $entry['appliance'] ?? $defaultAppliance,
                    $entry['type_number'] ?? null
                );
                if ($model) {
                    $product->models()->syncWithoutDetaching([$model->id => ['source' => 'feed']]);
                }
            }

            return $product;
        });
    }

    /**
     * Creates/finds a category chain. Uniqueness is per parent, so "Tür" can
     * exist below "Fenster" and below "Tür/Ventil/Deckel" (as on FixPart),
     * while the public URL slug stays unique per tree via the SEO landing table.
     *
     * @param  array<int, string>  $segments
     */
    public function category(string $type, array $segments): Category
    {
        if ($segments === []) {
            throw new \InvalidArgumentException('Category path must not be empty.');
        }

        $parent = null;
        $path = '';
        $depth = 1;
        $category = null;

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $slug = Str::slug($segment);
            $path = $path === '' ? $slug : $path . '.' . $slug;

            $category = Category::firstOrCreate(
                ['type' => $type, 'parent_id' => $parent?->id, 'slug' => $slug],
                [
                    'name' => $segment,
                    'path' => $path,
                    'depth' => $depth,
                    'meta' => ['source_path' => implode(' > ', array_slice($segments, 0, $depth))],
                ]
            );

            $parent = $category;
            $depth++;
        }

        return $category; // deepest existing/created node
    }

    private function brand(?string $name): ?Brand
    {
        if (! $name) {
            return null;
        }

        return Brand::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name]
        );
    }

    private function model(?string $brandName, ?string $appliance, ?string $typeNumber): ?DeviceModel
    {
        if (! $brandName || ! $typeNumber) {
            return null;
        }

        $brand = $this->brand($brandName);
        $applianceName = $appliance ?: 'Sonstiges';
        $applianceCategory = Category::firstOrCreate(
            ['type' => 'appliance', 'parent_id' => null, 'slug' => Str::slug($applianceName)],
            ['name' => $applianceName, 'path' => Str::slug($applianceName), 'depth' => 1]
        );

        return DeviceModel::firstOrCreate(
            [
                'brand_id' => $brand->id,
                'appliance_category_id' => $applianceCategory->id,
                'type_number' => $typeNumber,
            ],
            ['slug' => Str::slug($brand->name . '-' . $typeNumber)]
        );
    }

    /** @return array<int, string> */
    private function splitPath(string $path): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s*>\s*/', $path) ?: [])));
    }

    private function uniqueProductSlug(string $name, string $sku): string
    {
        $base = Str::slug($name);
        $slug = $base;

        // Products that only differ by number keep a stable, readable URL.
        if (Product::where('slug', $slug)->where('sku', '!=', $sku)->exists()) {
            $slug = $base . '-' . Str::slug($sku);
        }

        return $slug;
    }
}

# FixPart-style category layer — Laravel/PostgreSQL/Meilisearch

Copy the files into a Laravel 11 app:

```
app/Models/Category.php
app/Models/Brand.php
app/Models/Product.php
app/Models/DeviceModel.php
app/Services/CatalogImporter.php
app/Services/ProductIndexer.php
database/migrations/2026_11_03_000001_create_catalog_tables.php
config/meilisearch-settings.json   (apply once, see below)
```

`composer require meilisearch/meilisearch-php` and register `Meilisearch\Client`
in a service provider (host + key from `config/services.php`).

## Model

Two orthogonal axes — this is the core of the FixPart design:

```
categories (type = 'part')            categories (type = 'appliance')
Mechanisch                             Waschmaschine
└─ Gehäuseteil                         Kühlschrank
   └─ Tür/Ventil/Deckel                Backofen
      └─ Tür                           … 105 groups
         └─ Türfach   ← product hangs here (one primary path)

brands ──< models >── categories(type=appliance)
             │
             └──< product_model >── products ──< product_category >── categories
```

* `categories` is an adjacency list **plus** a materialized `path`
  (`mechanisch.gehauseteil.tur-ventil-deckel.tur.turfach`) so subtree reads are
  a single indexed `LIKE 'path.%'` query — no recursive CTE on every request.
* A product gets **one** primary part category (the feed's product-category
  path). It is *not* copied into every appliance category.
* A product appears on `/waschmaschine-ersatzteile/turgummi` because of
  `product_model` compatibility rows, not because it has a second category.
* Brands are their own table. Never model "AEG-Waschmaschine" as a category —
  that is the trap the three wholesaler shops fall into.
* `seo_landings` stores the generated appliance × brand × part pages
  (title, intro, indexable flag, product count) without duplicating products.

## Import pipeline

1. **Stage** the API response into `feed_articles` (`jsonb` payload + hash) so
   re-imports are idempotent and debuggable.
2. **Normalize** with mapping tables: supplier brand spelling → `brands`,
   supplier device type → `appliance` category, supplier part group → `part`
   category path.
3. **Upsert** the product and its primary category (`CatalogImporter`).
4. **Attach compatibility**: `product_model` rows from the feed's model list.
   This is the largest table; insert with `upsert()` in chunks of 5–10k.
5. **Derive** facet columns (`appliance_types`, `device_brands`) for the index.
6. **Queue** `IndexProduct` jobs; index in batches of 500.

Re-run the whole import nightly; keep `category_aliases` so that renamed
supplier paths do not create duplicate categories.

## Meilisearch

Apply settings once:

```bash
curl -X PATCH "$MEILI_HOST/indexes/products/settings" \
  -H "Authorization: Bearer $MEILI_KEY" \
  -H 'Content-Type: application/json' \
  --data @config/meilisearch-settings.json   # use the "products" object
```

Category page query — resolve the subtree in PostgreSQL, filter in Meilisearch:

```php
$ids = $category->subtreeIds();          // e.g. [12, 18, 19, 27]

$results = MeiliSearch::index('products')->search('', [
    'filter' => 'category_ids IN [' . implode(',', $ids) . ']',
    'facets' => ['brand', 'appliance_types', 'condition', 'in_stock', 'price'],
    'sort' => ['price:asc'],
    'hitsPerPage' => 24,
]);
```

Facet counts come back in `facetDistribution`; the tree/breadcrumb comes from
PostgreSQL. Meilisearch has no hierarchical facets, so flatten the levels into
`category_ids` (and optionally `category_l1_id … category_l5_id`) and let the
UI render the tree.

"Find part by type number" uses the `models` index:

```php
MeiliSearch::index('models')->search('WGG244Z40', [
    'filter' => 'brand = "Bosch" AND appliance = "Waschmaschine"',
]);
// -> product_ids -> fetch products
```

## Operational notes

* `categories` ≈ 500 rows (375 part + 105 appliance + intermediate levels).
  It is small enough to cache the whole tree in Redis and rebuild on save.
* `product_model` can reach millions of rows. Partition by `brand_id` hash if
  needed; always query via `model_id` or `product_id` (both indexed).
* Only publish `seo_landings` with `product_count >= 3` and `is_indexable`;
  everything else gets `noindex` to avoid thin pages.

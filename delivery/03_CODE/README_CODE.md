# FixPart category analysis — code and data archive

This archive contains everything technical behind the submission: the captured
data, the scripts that produced it, the full technical report, and the Laravel /
PostgreSQL / Meilisearch implementation sketch.

The presentation and the 2-page result are delivered separately, because the
first review is not done by a programmer.

## Contents

```
README_CODE.md                  this file
REPORT.md                       full technical report (analysis + implementation reasoning)
build_data.py                   rebuilds every file in /data from the captured pages
render_report.py                renders REPORT.md to HTML for PDF printing
data/                           captured category tree, appliances, brands, part categories, samples
research/                       evidence: captured fixpart.de pages and comparison-shop snapshots
implementation/                 Laravel 11 code
delivery_sources/               HTML sources of the presentation and the 2-page result
```

## The implementation

Copy the files into a Laravel 11 application:

```
app/Models/{Category,Brand,Product,DeviceModel}.php
app/Services/{CatalogImporter,ProductIndexer}.php
database/migrations/2026_11_03_000001_create_catalog_tables.php
config/meilisearch-settings.json
```

Then:

```bash
composer require meilisearch/meilisearch-php
php artisan migrate
```

Register `Meilisearch\Client` in a service provider (host and key from
`config/services.php`), and apply `config/meilisearch-settings.json` to the
`products` index once.

### Data model in one paragraph

`categories` is an adjacency list (`parent_id`) **plus** a materialised dot path
(`mechanisch.gehaeuseteil.fenster.tuer`). A subtree is one indexed
`LIKE 'path.%'` query, with no recursive CTE per request. A product has **one**
primary part category from the feed. It appears under appliance and brand pages
through `product_model` compatibility rows, not through extra category
assignments. `seo_landings` stores generated appliance × brand × part pages
without duplicating products. Uniqueness is `(type, parent_id, slug)`, so “Tür”
can exist under both “Fenster” and “Tür/Ventil/Deckel” as it does on FixPart.

### Import pipeline

1. Stage the raw API response in `feed_articles` (JSONB + hash) so re-imports
   are idempotent and debuggable.
2. Normalise supplier strings through mapping tables / `category_aliases`.
3. Upsert the product with one primary category from the feed path.
4. Attach `product_model` compatibility rows (the largest table; chunked
   upserts of 5–10k).
5. Derive facet columns (`appliance_types`, `device_brands`) and queue a
   Meilisearch reindex in batches of 500.
6. Re-run nightly; `category_aliases` keeps renamed supplier paths stable.

### Meilisearch

One `products` index with `category_ids`, `brand`, `appliance_types`,
`condition`, `price`, `in_stock`, `category_path` as filterable/searchable
attributes. Category pages resolve the subtree ids in PostgreSQL and filter
`category_ids IN [...]`; facet counts come from `facetDistribution`. A separate
`models` index (`type_number_normalized`) powers “find part by type number”.
Meilisearch has no hierarchical facets, so the tree and breadcrumbs stay in
PostgreSQL.

## Rebuilding the captured data

```bash
python build_data.py
```

This requires the harvested pages in `research/` and rewrites `/data`. The
capture used public pages read through a rendering proxy because fixpart.de
blocks plain clients with a JS captcha.

## Honest limitations

- Brand count is the union of the public A–Z directory at capture time
  (1,511); FixPart claims 2,000+.
- Models cannot be enumerated from outside; they must come from the
  compatibility feed.
- The 5-level classification is a verified sample (33 nodes / 39 products), not
  a complete dump. Only the supplier feed contains the full set.
- Research used public pages only. The production taxonomy should be built from
  our own feed plus editorial work, not from scraped FixPart content.

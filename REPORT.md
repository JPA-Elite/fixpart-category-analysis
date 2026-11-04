# FixPart.de category structure — analysis and rebuild plan

**Date:** 2026-11-03 · **Method:** public fixpart.de pages read through a rendering proxy (the site blocks plain clients with a JS captcha), plus archived snapshots of the three comparison shops. Evidence: `research/`, `build_data.py`.

## 1. What the “tree” actually is

FixPart has **two orthogonal axes**, not one deep clickable tree:

| Axis | Level | Count | Example |
|---|---|---|---|
| Device | L0 appliance group | **105** | Waschmaschine, Kühlschrank, Backofen |
| Device | L1 brand | **1,511 extracted** (site claims 2,000+) | Bosch, Siemens, Miele |
| Device | L2 model / type number | not enumerated (thousands per brand; Bosch washing machines: 14,228 hits) | WGG244Z40 |
| Part | L1 browsable part category (flat) | **375** | Türgummi, Türfach, Pumpe |
| Part | separate classification path (5 own levels) | depth 5; verified sample 33 nodes / 39 products; roots seen: Mechanisch, Elektrische Komponente, Stecker/Kabel | Mechanisch > Gehäuseteil > Tür/Ventil/Deckel > Tür > Türfach |

The 375 part categories are **siblings**, not a hierarchy: `/produktkategorien` lists them A–Z and a category page (`/ersatzteile/turgummi`) links only to appliance groups, brands and popular products — never to sub-categories. The hierarchy exists only as the `Produktkategorie` field on the product (3–5 levels), and the breadcrumb shows just its leaf (`Home > Türfach > product`). FixPart markets “5,000+ categories”, but only 375 have index pages; the rest are classification leaves and generated combinations (Q3).

## 2. The logic

**Navigation is device-first:** appliance group → brand → part category → type-number filter → product. The part index is the second entry point for people who know the part name.

**URLs are a fixed matrix of the two axes:**

```
/gerate · /{appliance}-ersatzteile · /{appliance}-ersatzteile/{brand}
/{appliance}-ersatzteile/{part} · /{appliance}-ersatzteile/{brand}/{part}
/ersatzteile/{part} · /ersatzteile/{brand} · /ersatzteile/{brand}/{part}
/produkt/{slug}
```

**Breadcrumbs come from those segments**, not from the classification: `/waschmaschine-ersatzteile/turgummi` → `Home > Waschmaschine Ersatzteile > Türgummi`; product → `Home > Türfach > <product>`.

**Filters are the third dimension:** brand pages expose *Gerät*, *Marke* and a long *Typennummer* picker plus the result count; part pages add brand and appliance tiles. Effective facets: **appliance type, brand, model/type number, part category**, plus price/availability on the listing.

**One product, many listings.** Each product has exactly **one** classification path. It appears under many appliances and brands through compatibility data: the sample lists `Gerätemarke(n): Bosch, Siemens`, `Geräteart(en): Gefrierschrank, Kühl-Gefrierkombination, Kühlschrank` and “1,802 compatible devices”. So one SKU is reachable via dozens of appliance×part pages without dozens of category assignments. “Alternative Produkte” (original / aftermarket / refurbished) are separate SKUs, cross-linked.

## 3. Comparison with the three API shops

| Shop | Navigation | Depth | Curated layer |
|---|---|---|---|
| **ersatzteilblitz.de** | *Gerätegruppen A–Z* (`/gruppen0/a.html`), *Artikelgruppen A–Z* (`/artikel0/a.html`), `/artikel.php?link=…`; inquiries deep-link to `shop.euras.com` | 2 | none; supplier dimensions only |
| **wildbihler-shop.de** | Hersteller A–Z (`/hersteller.html`), Geräte A–Z (`/geraete.html`), `/{brand}_{model}_ersatzteil_{id}.html` | 2–3 | none; brand and brand+device are separate pseudo-manufacturers (`AEG-DAMPFBÜGELEISEN`) |
| **ersatzteile-24.com** | Brand index `/A.php`, `/{brand}-ersatzteile.html`, brand×device `/BOSCH/Waschmaschine.html` (paginated model lists), `/ersatzteile_WASCHMASCHINE.php` | 3 | none; `/shop.php` assistant only |
| **fixpart.de** | 105 appliance groups → ~1,500 brands → 375 part categories → type number | 3 navigable + 5 classification | **yes**: renamed groups, part categories, classification, SEO landings, fit guarantee |

**Identical:** all four expose the same dimensions — part brand, device brand, device type, model/type number, article number, description, compatibility.

**Different:** the three API shops publish the raw supplier dimensions (brand, device, model, article group) and even treat brand×device combinations as separate “manufacturers”; FixPart normalizes them into one appliance list, adds its own part-category and classification layer, and generates landing pages from the cross-product of both axes.

**API provides directly:** article number, MPN, description, part type/group in supplier wording, device brand(s), device type(s), model/type numbers and compatibility, price/stock. **API does not provide:** FixPart’s appliance-group names, its 375 part-category names, the 5-level “Mechanisch > Gehäuseteil > …” path, SEO copy or landing-page composition. That editorial layer is why FixPart looks like a store and the other three look like databases.

## 4. Implementation (Laravel / PostgreSQL / Meilisearch)

**Schema** (`implementation/database/migrations/…create_catalog_tables.php`):

```
categories(id, parent_id, type[part|appliance], name, slug, path, depth, position, is_active, meta)
brands(id, name, slug, logo_path)
models(id, brand_id, appliance_category_id, type_number, name, slug)
products(id, sku, mpn, name, slug, brand_id, primary_category_id, condition, price_cents, stock, is_active)
product_category(product_id, category_id, is_primary, source)
product_model(product_id, model_id, source)          -- compatibility
category_aliases(source, source_path, category_id)   -- stable re-imports
seo_landings(appliance_category_id, brand_id, category_id, path, title, intro, product_count, is_indexable)
```

`categories` is an adjacency list **plus** a materialized dot path, so a subtree is one indexed `LIKE 'mechanisch.%'` query — no recursive CTE per request. Uniqueness is `(type, parent_id, slug)`, which allows “Tür” under both `Fenster` and `Tür/Ventil/Deckel` as FixPart does; public URL uniqueness lives in `seo_landings`.

**Mapping** (`CatalogImporter.php`): normalize supplier strings via aliases → upsert product with **one** primary category from the feed path, creating intermediate nodes idempotently → attach `product_model` compatibility rows (this drives appliance/brand listings, not extra categories) → derive facet columns → queue reindex → nightly full import.

**Meilisearch:** one `products` index with denormalized `category_ids`, `category_path`, `brand`, `appliance_types`, `device_brands`, `condition`, `price`, `in_stock` as filterables. Category pages resolve subtree ids in PostgreSQL and filter `category_ids IN [...]`; facet counts come from `facetDistribution`. Meilisearch has no hierarchical facets, so the tree/breadcrumb stays in Postgres. “Find by type number” uses a separate `models` index (`type_number_normalized`) returning `product_ids`.

## 5. Open questions and assumptions

1. **Wholesaler identity.** *Assumption:* an EURAS-type catalog — ersatzteilblitz deep-links to `shop.euras.com`, and the others mirror the same dimensions. Confirm the contract and delivered fields.
2. **Does the feed contain a product-group path?** If yes, map it 1:1; if not, the 375-node list must be curated (`data/part-categories.json` is a starting point).
3. **5,000 vs 375.** The difference is probably classification leaves and generated combinations; only product-level data reveals the full set, so the tree file contains a verified sample, not a complete dump.
4. **Model counts** cannot be enumerated from outside (type-number picker, no model URLs); they must come from the compatibility feed.
5. **Coverage.** Brand count is the unique union of the A–Z directory at capture time (1,511); FixPart states “over 2,000”, so some brands are probably not linked in the directory. Appliance and part lists are complete for that moment.
6. **Legal.** Research used public pages only. Build our taxonomy from our own feed plus editorial work, not from scraped FixPart content.

**Files:** `data/category-tree.json` (tree, counts, URL patterns) · `data/category-tree.csv` (flat, `;`, UTF-8-BOM) · per-axis JSONs · `implementation/` (migration, importer, indexer, Meilisearch settings).

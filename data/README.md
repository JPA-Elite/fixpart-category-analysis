# Captured data — fixpart.de (2026-11-03)

| File | Content |
|---|---|
| `category-tree.json` | Complete captured tree + counts + URL patterns + classification sample |
| `category-tree.csv` | Same data as flat rows, `;` separated, UTF-8 BOM (Excel-safe) |
| `appliances.json` | 105 appliance groups (name, slug, URL) |
| `part-categories.json` | 375 browsable part categories (name, slug, URL) |
| `brands.json` | 1,511 unique brand slugs extracted from the A–Z directory |
| `classification-samples.json` | 39 product pages with their `Produktkategorie` path (the 5-level internal classification) |

`category-tree.csv` columns:

- `axis` — `device`, `part` (browsable index) or `part-classification` (internal path)
- `level` — depth within that axis
- `level_name` — human label
- `name`, `slug`
- `parent` — parent name (empty for roots / flat index entries)
- `path` — full path from the root
- `url` — public URL (empty for non-browsable classification nodes)
- `browsable` — `yes` if the node has its own page

Regenerate with `python build_data.py` (requires the harvested pages in `research/`).

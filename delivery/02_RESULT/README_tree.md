# Category tree — files and column guide

Captured from public fixpart.de pages on **2026-11-03**.

| File | What it contains |
|---|---|
| `category-tree.json` | Complete captured structure: metadata, counts, device axis (105 appliances + 1,511 brands), part axis (375 categories), the verified 5-level classification sample, classification paths and all URL patterns. |
| `category-tree.csv` | The same structure as flat rows. `;` separated, UTF-8 with BOM, so it opens correctly in Excel. |
| `appliances.json` | 105 appliance groups with name, slug and URL. |
| `part-categories.json` | 375 browsable part categories with name, slug and URL. |
| `brands.json` | 1,511 unique brand slugs extracted from the A–Z directory. |
| `classification-samples.json` | 39 product pages with their `Produktkategorie` path — the evidence for the 5-level classification. |

## CSV columns

| Column | Meaning |
|---|---|
| `axis` | `device`, `part` (browsable index) or `part-classification` (internal path) |
| `level` | Depth within that axis |
| `level_name` | Human-readable level label |
| `name`, `slug` | Node name and URL slug |
| `parent` | Parent name (empty for roots / flat index entries) |
| `path` | Full path from the root |
| `url` | Public URL (empty for non-browsable classification nodes) |
| `browsable` | `yes` if the node has its own public page |

## Caveats

- Brand count is the unique union of the public A–Z directory at capture time. FixPart states “over 2,000”; some brands are probably not linked in the directory.
- Models are not a page hierarchy: they are selected by type number inside a brand page, so they cannot be enumerated from outside.
- The internal classification is only fully available in the supplier feed. The JSON contains a verified sample (33 nodes / 39 products), not a complete census.
- Research used public pages only. Build the production taxonomy from our own feed plus editorial work, not from scraped FixPart content.

# FixPart.de category analysis — Ersatzteilstore submission

This repository contains the complete submission for the Ersatzteilstore category
analysis task.

**Start here (non-technical):**

- [Presentation — 11 slides](https://jpa-elite.github.io/fixpart-category-analysis/downloads/01_Presentation_FixPart_Category_Analysis.pdf)
- [Final result — 2 pages](https://jpa-elite.github.io/fixpart-category-analysis/downloads/02_Final_Result_FixPart_Category_Analysis.pdf)
- [Category tree — CSV](https://jpa-elite.github.io/fixpart-category-analysis/downloads/category-tree.csv) · [JSON](https://jpa-elite.github.io/fixpart-category-analysis/downloads/category-tree.json)

**For the technical team:**

- [Code and data archive — ZIP](https://jpa-elite.github.io/fixpart-category-analysis/downloads/03_Code_and_Data.zip)
- [`implementation/`](implementation/) — Laravel migration, importer, Meilisearch indexer
- [`data/`](data/) — captured category tree, appliances, brands, part categories
- [`research/`](research/) — captured pages and comparison-shop evidence
- [`REPORT.md`](REPORT.md) — full technical report
- [`README_CODE.md`](delivery/03_CODE/README_CODE.md) — how to run the code and rebuild the data

## The finding in one paragraph

FixPart is not a deep product tree. It is a product catalogue with two
orthogonal navigation axes — device (105 appliance groups → 1,511 brands →
model / type number) and part (375 browsable categories → a 5-level internal
classification) — connected by compatibility data. One product has one
classification path but appears on many appliance × brand × part pages through
its compatibility list. The wholesaler API supplies the facts; FixPart's
curated taxonomy, landing pages and fit guarantee are the editorial layer that
makes it feel like a shop rather than a database.

## Contents

```
docs/                    GitHub Pages landing page + downloadable PDFs/data
data/                    captured category tree (CSV / JSON) and supporting data
research/                evidence: captured fixpart.de and comparison-shop pages
implementation/          Laravel 11 code (migration, models, importer, indexer)
build_data.py            rebuilds /data from /research
render_report.py         renders REPORT.md to HTML
REPORT.md                full technical report
delivery/                presentation + result sources and PDFs
```

Captured on 3 November 2026 from public pages. Every number is reproducible
from the files in `data/`.

"""Builds the FixPart category-tree deliverables from the harvested pages."""
import re, json, csv, glob, html, sys, os
from collections import Counter, OrderedDict

sys.stdout.reconfigure(encoding="utf-8", errors="replace")
RAW = "research/raw"
OUT = "data"


def read(path):
    return open(path, encoding="utf-8", errors="replace").read()


def cards(path):
    """Extract [![Image n: alt](img)Anchor text](url) cards."""
    t = read(path)
    out = []
    for m in re.finditer(
        r'\[!\[Image \d+: ([^\]]*)\]\([^)]*\)([^\]]*)\]\((https://fixpart\.de/[^)\s]+)\)', t
    ):
        name = (m.group(2).strip() or m.group(1).strip())
        name = re.sub(r"\s+Ersatzteile$", "", name).strip()
        out.append((name, m.group(3)))
    return out


def slug_of(url):
    return url.rstrip("/").rsplit("/", 1)[-1]


# --- level 0: appliance groups -------------------------------------------------
appliances = []
for name, url in cards(f"{RAW}/gerate.md"):
    if url.endswith("-ersatzteile"):
        appliances.append({"name": name, "slug": slug_of(url), "url": url})
# dedupe
seen = set()
appliances = [a for a in appliances if not (a["slug"] in seen or seen.add(a["slug"]))]

# --- level 1 (part axis): browsable product categories -------------------------
parts = []
for name, url in cards(f"{RAW}/produktkategorien.md"):
    if "/ersatzteile/" in url and url.count("/") == 4:
        parts.append({"name": name, "slug": slug_of(url), "url": url})
seen = set()
parts = [p for p in parts if not (p["slug"] in seen or seen.add(p["slug"]))]

# --- level 1 (device axis): brands (paginated by letter) -----------------------
brands = OrderedDict()
for f in [f"{RAW}/marken.md"] + sorted(glob.glob(f"{RAW}/../brands/*.md")):
    t = read(f)
    for m in re.finditer(r'\[([^\]]+)\]\((https://fixpart\.de/ersatzteile/[^)\s]+)\)', t):
        name = m.group(1).strip()
        url = m.group(2)
        if not name or "!" in name or url.count("/") > 4:
            continue
        brands.setdefault(slug_of(url), {"name": name, "slug": slug_of(url), "url": url})
brands = list(brands.values())

# --- deep product-classification paths (sampled product pages) ----------------
paths = []
for f in glob.glob("research/products/*.html"):
    h = read(f)
    i = h.find("Produktkategorie")
    j = h.find('class="rtl"', i)
    if j < 0:
        continue
    j = h.find(">", j) + 1
    seg = h[j:j + 800].split("</div>")[0]
    txt = html.unescape(re.sub(r"<[^>]+>", "", seg)).strip()
    path = [x.strip() for x in txt.split(">") if x.strip()]
    if len(path) >= 2:
        paths.append({"product": os.path.basename(f)[:-5], "path": path})

# add the verified manual sample (Bosch Siemens 00705901 Türfach)
extra = {
    "product": "bosch-siemens-00705901-turfach",
    "path": ["Mechanisch", "Gehäuseteil", "Tür/Ventil/Deckel", "Tür", "Türfach"],
}
if not any(p["path"] == extra["path"] for p in paths):
    paths.append(extra)
paths.sort(key=lambda p: (len(p["path"]), p["path"]))

# --- classification tree inferred from samples --------------------------------
tree = {"name": "(root)", "children": OrderedDict()}
for p in paths:
    node = tree
    for part in p["path"]:
        node = node["children"].setdefault(part, {"name": part, "children": OrderedDict()})
tree = tree["children"]

def to_plain(node):
    return {"name": node["name"], "children": [to_plain(c) for c in node["children"].values()]}

class_tree = [to_plain(n) for n in tree.values()]

def count_nodes(nodes, depth=1):
    return sum(1 + count_nodes(n["children"], depth + 1) for n in nodes)

# --- write flat CSV ------------------------------------------------------------
flat = []
for a in appliances:
    flat.append(dict(axis="device", level=1, level_name="Geräteart / appliance group",
                     name=a["name"], slug=a["slug"], parent="", path=a["name"],
                     url=a["url"], browsable="yes"))
for b in brands:
    flat.append(dict(axis="device", level=2, level_name="Marke / brand",
                     name=b["name"], slug=b["slug"], parent="(all appliance groups)",
                     path=f"{b['name']}", url=b["url"], browsable="yes"))
for p in parts:
    flat.append(dict(axis="part", level=1, level_name="Produktkategorie / part category",
                     name=p["name"], slug=p["slug"], parent="(Produktkategorien index)",
                     path=p["name"], url=p["url"], browsable="yes"))

seen_nodes = set()
for p in paths:
    for i, n in enumerate(p["path"]):
        key = tuple(p["path"][: i + 1])
        if key in seen_nodes:
            continue
        seen_nodes.add(key)
        flat.append(dict(axis="part-classification", level=i + 1, level_name=f"Klassifikation L{i+1}",
                         name=n, slug="", parent=p["path"][i - 1] if i else "(root)",
                         path=" > ".join(p["path"][: i + 1]), url="", browsable="no"))

with open(f"{OUT}/category-tree.csv", "w", newline="", encoding="utf-8-sig") as fh:
    w = csv.DictWriter(fh, fieldnames=["axis", "level", "level_name", "name", "slug",
                                       "parent", "path", "url", "browsable"],
                       delimiter=";")
    w.writeheader()
    w.writerows(flat)

# --- write JSON ---------------------------------------------------------------
tree_json = {
    "meta": {
        "site": "https://fixpart.de",
        "captured": "2026-11-03",
        "method": "Rendered pages fetched through r.jina.ai (site blocks direct clients with a JS captcha); "
                  "card lists parsed from /gerate, /produktkategorien and /marken?letter=*; deep classification "
                  "paths parsed from product pages (Produktkategorie spec field).",
        "caveats": [
            "Brand count is the unique union of the A-Z brand directory pages at capture time (site states over 2,000).",
            "Model level is not a page hierarchy: models are selected by type number inside a brand page.",
            "The internal 5-level classification is only fully available in the supplier feed; the tree below "
            "is a verified sample.",
        ],
    },
    "counts": {
        "device_axis": {"appliance_groups": len(appliances), "brands_extracted": len(brands),
                        "brands_site_claim": "over 2,000",
                        "models": "thousands per brand (type-number list, not enumerated)"},
        "part_axis": {"browsable_categories": len(parts),
                      "classification_sample_nodes": count_nodes(class_tree),
                      "classification_max_depth": max(len(p["path"]) for p in paths)},
    },
    "device_axis": appliances,
    "brands": brands,
    "part_axis": parts,
    "classification_tree_sample": class_tree,
    "classification_paths": paths,
    "url_patterns": [
        {"pattern": "/gerate", "meaning": "index of all appliance groups"},
        {"pattern": "/{appliance}-ersatzteile", "meaning": "appliance group landing page (brands + part categories + popular products)"},
        {"pattern": "/{appliance}-ersatzteile/{brand}", "meaning": "brand x appliance listing (product list + type-number filter)"},
        {"pattern": "/{appliance}-ersatzteile/{part}", "meaning": "part category inside appliance (listing)"},
        {"pattern": "/{appliance}-ersatzteile/{brand}/{part}", "meaning": "brand x appliance x part (deepest browse landing page)"},
        {"pattern": "/ersatzteile/{part}", "meaning": "part category landing page (all appliances/brands)"},
        {"pattern": "/ersatzteile/{brand}", "meaning": "brand landing page"},
        {"pattern": "/ersatzteile/{brand}/{part}", "meaning": "brand x part (without appliance)"},
        {"pattern": "/produkt/{slug}", "meaning": "product detail page"},
        {"pattern": "/produktkategorien", "meaning": "index of all browsable part categories"},
        {"pattern": "/marken?letter={A-Z|0-9}", "meaning": "brand directory, paginated by letter"},
    ],
}
json.dump(tree_json, open(f"{OUT}/category-tree.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)

json.dump(appliances, open(f"{OUT}/appliances.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
json.dump(parts, open(f"{OUT}/part-categories.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
json.dump(brands, open(f"{OUT}/brands.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
json.dump(paths, open(f"{OUT}/classification-samples.json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)

print("appliances:", len(appliances))
print("part categories:", len(parts))
print("brands:", len(brands))
print("classification samples:", len(paths), "nodes:", count_nodes(class_tree))
print("levels:", Counter(len(p["path"]) for p in paths))
print("roots:", [n["name"] for n in class_tree])

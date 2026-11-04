"""Render REPORT.md to HTML for PDF printing."""
import markdown

CSS = """
@page { size: A4; margin: 14mm 14mm 12mm 14mm; }
body { font-family: "Segoe UI", Arial, sans-serif; font-size: 9.5pt; line-height: 1.3; color: #111; }
h1 { font-size: 16pt; margin: 0 0 4pt 0; }
h2 { font-size: 11pt; margin: 8pt 0 2pt 0; border-bottom: 0.6pt solid #ccc; padding-bottom: 2pt; }
p { margin: 3pt 0; }
ul, ol { margin: 3pt 0 3pt 14pt; padding: 0; }
li { margin: 1pt 0; }
table { border-collapse: collapse; width: 100%; margin: 4pt 0; font-size: 8.4pt; }
th, td { border: 0.5pt solid #bbb; padding: 2.5pt 3.5pt; vertical-align: top; text-align: left; }
th { background: #eee; }
code { font-family: Consolas, monospace; font-size: 8.4pt; background: #f4f4f4; padding: 0 1pt; }
pre { background: #f4f4f4; padding: 4pt 5pt; font-size: 8.2pt; line-height: 1.3; white-space: pre-wrap; }
hr { display: none; }
"""

md = open("REPORT.md", encoding="utf-8").read()
body = markdown.markdown(md, extensions=["tables", "fenced_code", "sane_lists"])
html = f"<!doctype html><html><head><meta charset='utf-8'><style>{CSS}</style></head><body>{body}</body></html>"
open("REPORT.html", "w", encoding="utf-8").write(html)
print("html written")

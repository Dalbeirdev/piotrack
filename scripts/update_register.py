"""Update tracking fields on Feature Register rows.

Usage:
  python scripts/update_register.py DEVX-001 --status Implemented --backend Done --notes "..."
  python scripts/update_register.py DEVX-001 DEVX-002 --status "In Progress"

Allowed fields: status, backend, frontend, tests, docs, depends_on, notes.
"""

import argparse
import csv
from pathlib import Path

CSV_PATH = Path(__file__).resolve().parent.parent / "docs" / "register" / "feature-register.csv"
FIELDS = ("status", "backend", "frontend", "tests", "docs", "depends_on", "notes")


def main():
    p = argparse.ArgumentParser()
    p.add_argument("ids", nargs="+", help="Feature IDs to update")
    for f in FIELDS:
        p.add_argument(f"--{f}")
    args = p.parse_args()

    updates = {f: getattr(args, f) for f in FIELDS if getattr(args, f) is not None}
    if not updates:
        p.error("no fields to update")

    with open(CSV_PATH, encoding="utf-8-sig") as fh:
        reader = csv.DictReader(fh)
        fieldnames = reader.fieldnames
        rows = list(reader)

    by_id = {r["id"]: r for r in rows}
    missing = [i for i in args.ids if i not in by_id]
    if missing:
        raise SystemExit(f"Unknown feature IDs: {missing}")

    for fid in args.ids:
        by_id[fid].update(updates)
        print(f"{fid}: " + ", ".join(f"{k}={v}" for k, v in updates.items()))

    with open(CSV_PATH, "w", newline="", encoding="utf-8-sig") as fh:
        w = csv.DictWriter(fh, fieldnames=fieldnames)
        w.writeheader()
        w.writerows(rows)


if __name__ == "__main__":
    main()

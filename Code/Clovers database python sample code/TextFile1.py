# PSEUDOCODE / PLAN (detailed)
# 1. Parse CLI arguments:
#    - --excel: path to the Excel file (optional). Default: Downloads/"EMR mental health database .xlsx"
#    - --db: path to output sqlite file (optional). Default: ./emr_mental_health.db
#    - --sheets: comma-separated list of sheets to import (optional). If omitted import all sheets.
#    - --preview: if set, just print sheet names and row counts, don't write DB.
# 2. Resolve Excel path robustly:
#    - If user provided a path, use it.
#    - Otherwise try common variants in the user's Downloads folder:
#         "EMR mental health database .xlsx" and "EMR mental health database.xlsx"
#    - If not found, show error and exit.
# 3. Use pandas to read the Excel file:
#    - Use pandas.ExcelFile to enumerate sheets.
#    - For each selected sheet:
#         - Read sheet into DataFrame with pd.read_excel(..., sheet_name=sheet)
#         - Normalize column names (strip whitespace)
#         - Optionally coerce dtypes if needed (left minimal here)
# 4. If preview flag: print sheet name and number of rows and columns.
# 5. Otherwise open sqlite3 connection and for each DataFrame:
#    - Generate a safe table name from the sheet name (lowercase, replace non-alnum with _)
#    - Use df.to_sql(table_name, conn, if_exists='replace', index=False)
#    - Log rows written
# 6. Close connection and exit.
#
# Implementation notes:
# - Requires: pandas (pip install pandas openpyxl)
# - Uses stdlib sqlite3, pathlib, argparse, logging
# - Minimal transformation to preserve data; this creates a straightforward sqlite DB
# - The script is idempotent: running again replaces tables with same names.

import argparse
import logging
import sqlite3
from pathlib import Path
import sys
import re
import random

try:
    import pandas as pd
except Exception as e:
    sys.exit("Missing dependency: pandas (and openpyxl). Install with: pip install pandas openpyxl")

LOG = logging.getLogger("excel_to_sqlite")
logging.basicConfig(level=logging.INFO, format="%(levelname)s: %(message)s")

def find_excel_file(provided: str | None) -> Path | None:
    # Try provided path first
    if provided:
        p = Path(provided).expanduser()
        if p.exists():
            return p
        # try stripping whitespace in filename
        alt = p.with_name(p.name.strip())
        if alt.exists():
            return alt
    # Default: Downloads folder variants
    home = Path.home()
    downloads = home / "Downloads"
    candidates = [
        downloads / "EMR mental health database .xlsx",
        downloads / "EMR mental health database.xlsx",
        downloads / "EMR mental health database.xls",
    ]
    for c in candidates:
        if c.exists():
            return c
    return None

def safe_table_name(name: str) -> str:
    name = name.strip().lower()
    # replace non-alphanumeric with underscore
    name = re.sub(r"[^0-9a-z]+", "_", name)
    # ensure it doesn't start with digit
    if re.match(r"^\d", name):
        name = "_" + name
    return name.strip("_") or "sheet"

def list_tables(conn: sqlite3.Connection) -> list[str]:
    cur = conn.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';")
    return [row[0] for row in cur.fetchall()]

def print_random_samples(db_path: Path, table: str | None, n: int):
    if not db_path.exists():
        LOG.error("Database file does not exist: %s", db_path)
        return
    conn = sqlite3.connect(str(db_path))
    try:
        tables = list_tables(conn)
        if not tables:
            LOG.info("No tables found in database: %s", db_path)
            return
        if table:
            if table not in tables:
                LOG.error("Requested table '%s' not found in DB. Available tables: %s", table, ", ".join(tables))
                return
            chosen = table
        else:
            chosen = random.choice(tables)
        LOG.info("Sampling %d row(s) from table: %s", n, chosen)
        # Validate chosen table name is exactly one of the known tables before interpolating
        sql = f'SELECT * FROM "{chosen}" ORDER BY RANDOM() LIMIT {int(n)}'
        try:
            df = pd.read_sql_query(sql, conn)
        except Exception as ex:
            LOG.error("Failed to query table '%s': %s", chosen, ex)
            return
        if df.empty:
            LOG.info("Table '%s' has no rows.", chosen)
            return
        # Print a concise header then the dataframe
        print(f"\nTable: {chosen}  (columns: {len(df.columns)}  rows returned: {len(df)})\n")
        print(df.to_string(index=False))
        print()
    finally:
        conn.close()

def import_to_sqlite(excel_path: Path, db_path: Path, sheets: list[str] | None, preview: bool):
    LOG.info("Opening Excel file: %s", excel_path)
    xls = pd.ExcelFile(excel_path, engine="openpyxl")
    available = xls.sheet_names
    LOG.info("Found sheets: %s", ", ".join(available))
    if sheets:
        selected = [s for s in sheets if s in available]
        if not selected:
            LOG.error("No matching sheets found for requested names. Exiting.")
            return
    else:
        selected = available

    if preview:
        for s in selected:
            df = pd.read_excel(xls, sheet_name=s)
            LOG.info("Preview - sheet: %s rows: %d cols: %d", s, len(df), len(df.columns))
        return

    db_path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(db_path))
    try:
        for s in selected:
            LOG.info("Reading sheet: %s", s)
            df = pd.read_excel(xls, sheet_name=s)
            # Normalize column names
            df.columns = [str(c).strip() for c in df.columns]
            table = safe_table_name(s)
            LOG.info("Writing %d rows to table: %s", len(df), table)
            # Write to sqlite
            df.to_sql(table, conn, if_exists="replace", index=False)
        LOG.info("Database written to: %s", db_path)
    finally:
        conn.close()

def parse_args():
    ap = argparse.ArgumentParser(description="Import Excel workbook to a SQLite database and sample data.")
    ap.add_argument("--excel", "-e", help="Path to Excel file. Default: Downloads/EMR mental health database .xlsx")
    ap.add_argument("--db", "-d", help="Output sqlite DB path. Default: ./emr_mental_health.db")
    ap.add_argument("--sheets", "-s", help="Comma-separated sheet names to import. Default: all sheets")
    ap.add_argument("--preview", action="store_true", help="Preview sheet names and row counts, do not write DB")
    ap.add_argument("--sample", action="store_true", help="Print a random sample of data from the DB and exit")
    ap.add_argument("--sample-size", type=int, default=5, help="Number of random rows to print when sampling (default 5)")
    ap.add_argument("--sample-table", help="Specific table to sample (optional). If omitted a random table is chosen.")
    return ap.parse_args()

def main():
    args = parse_args()
    # Determine DB path early so sampling can run without Excel
    db_path = Path(args.db) if args.db else Path.cwd() / "emr_mental_health.db"

    if args.sample:
        # Sampling mode: do not require Excel file
        print_random_samples(db_path, args.sample_table, args.sample_size)
        return

    # Normal import mode: require Excel file
    excel_path = find_excel_file(args.excel)
    if not excel_path:
        LOG.error("Excel file not found. Provide path with --excel or place file in Downloads.")
        sys.exit(2)
    sheets = [s.strip() for s in args.sheets.split(",")] if args.sheets else None
    import_to_sqlite(excel_path, db_path, sheets, args.preview)

if __name__ == "__main__":
    main()
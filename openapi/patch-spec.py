#!/usr/bin/env python3
"""
Patches the upstream InvoiceNinja OpenAPI *source* files with fixes needed for code generation.

This script operates on the split source files in the upstream repo (openapi/ directory),
NOT on the compiled api-docs.yaml. It is intended to be run on a local checkout of
invoiceninja/invoiceninja (branch v5-develop) to prepare a PR.

This script is idempotent - safe to run multiple times.
Uses line-based editing to preserve original formatting exactly.

Patches applied:
  1. openapi/paths.yaml — Add per_page_meta/page_meta params to list endpoints missing them
  2. openapi/components/schemas/*.yaml — Add readOnly: true to 'id' fields

Usage:
  python3 patch-upstream-spec.py /path/to/invoiceninja/openapi
"""

import sys
import re
from pathlib import Path


# --- Pagination patch (line-based insertion into paths.yaml) ---

# Endpoints that need pagination params but are missing them in upstream
PAGINATION_ENDPOINTS = [
    ("get", "/api/v1/bank_integrations"),
    ("get", "/api/v1/bank_transactions"),
    ("get", "/api/v1/bank_transaction_rules"),
    ("get", "/api/v1/client_gateway_tokens"),
    ("get", "/api/v1/companies"),
    ("get", "/api/v1/company_gateways"),
    ("get", "/api/v1/designs"),
    ("get", "/api/v1/documents"),
    ("get", "/api/v1/expense_categories"),
    ("get", "/api/v1/expenses"),
    ("get", "/api/v1/group_settings"),
    ("get", "/api/v1/payment_terms"),
    ("get", "/api/v1/recurring_expenses"),
    ("get", "/api/v1/recurring_quotes"),
    ("get", "/api/v1/subscriptions"),
    ("get", "/api/v1/system_logs"),
    ("get", "/api/v1/task_schedulers/"),
    ("get", "/api/v1/task_schedulers/create"),
    ("get", "/api/v1/task_statuses"),
    ("get", "/api/v1/tax_rates"),
    ("get", "/api/v1/tokens"),
    ("get", "/api/v1/users"),
    ("get", "/api/v1/webhooks"),
    ("post", "/api/v1/reports/clients"),
    ("post", "/api/v1/reports/contacts"),
    ("post", "/api/v1/reports/credit"),
    ("post", "/api/v1/reports/documents"),
    ("post", "/api/v1/reports/expense"),
    ("post", "/api/v1/reports/invoice_items"),
    ("post", "/api/v1/reports/invoices"),
    ("post", "/api/v1/reports/payments"),
    ("post", "/api/v1/reports/product_sales"),
    ("post", "/api/v1/reports/products"),
    ("post", "/api/v1/reports/profitloss"),
    ("post", "/api/v1/reports/quote_items"),
    ("post", "/api/v1/reports/quotes"),
    ("post", "/api/v1/reports/recurring_invoices"),
    ("post", "/api/v1/reports/tasks"),
    ("post", "/api/v1/templates"),
]


def patch_pagination_params(paths_file: Path) -> int:
    """Add per_page_meta and page_meta parameter refs using line-based editing."""
    lines = paths_file.read_text().splitlines(keepends=True)
    count = 0

    # Build a set of targets for quick lookup
    targets = set()
    for method, path in PAGINATION_ENDPOINTS:
        targets.add((method, path))

    # State machine: find path -> find method -> find parameters block -> find end of params
    i = 0
    current_path = None
    current_method = None
    in_params = False
    param_indent = ""
    result = []

    while i < len(lines):
        line = lines[i]
        stripped = line.rstrip()

        # Match a path key like: '  /api/v1/expenses:'  or  '  "/api/v1/expenses":'
        path_match = re.match(r'^(\s{2})(?:"([^"]+)"|(\S[^:]*)):\s*$', stripped)
        if path_match:
            raw = path_match.group(2) or path_match.group(3)
            if raw and raw.startswith("/api/"):
                current_path = raw
                current_method = None
                in_params = False

        # Match a method key like: '    get:'
        method_match = re.match(r"^(\s{4})(get|post|put|patch|delete):\s*$", stripped)
        if method_match and current_path:
            current_method = method_match.group(2)
            in_params = False

        # Match 'parameters:' under a method
        param_match = re.match(r"^(\s+)parameters:\s*$", stripped)
        if param_match and current_method:
            in_params = True
            param_indent = param_match.group(1)

        # Detect end of parameters list
        if in_params and not re.match(r"^(\s+)parameters:\s*$", stripped):
            entry_indent = param_indent + "  "
            # Lines inside the params block are either:
            #   - new entries:     '        - $ref: ...' or '        - name: ...'
            #   - continuations:   '          in: query' (indented deeper than entry start)
            is_new_entry = stripped.startswith(entry_indent + "- ")
            is_continuation = (
                len(line) > len(entry_indent)
                and line[: len(entry_indent) + 2].strip() == ""
                and stripped != ""
            )
            is_inside_params = is_new_entry or is_continuation or stripped == ""

            if not is_inside_params and stripped:
                # We've left the parameters block — inject before this line if needed
                if (current_method, current_path) in targets:
                    # Check we haven't already added per_page_meta
                    recent = "".join(result[-20:])
                    if "per_page_meta" not in recent:
                        result.append(
                            f'{entry_indent}- $ref: "#/components/parameters/per_page_meta"\n'
                        )
                        result.append(
                            f'{entry_indent}- $ref: "#/components/parameters/page_meta"\n'
                        )
                        count += 1
                in_params = False
                current_method = None

        result.append(line)
        i += 1

    paths_file.write_text("".join(result))
    return count


# --- readOnly patch (line-based insertion into schema files) ---


def patch_readonly_ids(schemas_dir: Path) -> int:
    """Add readOnly: true to 'id' properties using line-based editing."""
    count = 0

    for schema_file in sorted(schemas_dir.glob("*.yaml")):
        lines = schema_file.read_text().splitlines(keepends=True)
        result = []
        i = 0
        changed = False

        while i < len(lines):
            result.append(lines[i])
            stripped = lines[i].rstrip()

            # Match an 'id:' property key, e.g. '        id:'
            id_match = re.match(r"^(\s+)id:\s*$", stripped)
            if id_match:
                id_indent = id_match.group(1)
                prop_indent = id_indent + "  "

                # Look ahead to check if readOnly already exists for this id block
                has_readonly = False
                j = i + 1
                while j < len(lines):
                    next_stripped = lines[j].rstrip()
                    if next_stripped == "" or next_stripped.startswith(prop_indent):
                        if "readOnly:" in next_stripped:
                            has_readonly = True
                        j += 1
                    else:
                        break

                if not has_readonly:
                    # Find the last property line of this id block and insert after it
                    # Copy all existing id sub-properties first, then append readOnly
                    insert_pos = i + 1
                    while insert_pos < len(lines):
                        next_stripped = lines[insert_pos].rstrip()
                        if next_stripped == "" or next_stripped.startswith(prop_indent):
                            result.append(lines[insert_pos])
                            insert_pos += 1
                        else:
                            break

                    result.append(f"{prop_indent}readOnly: true\n")
                    changed = True
                    count += 1
                    i = insert_pos
                    continue

            i += 1

        if changed:
            schema_file.write_text("".join(result))

    return count


def main():
    if len(sys.argv) < 2:
        print("Usage: python3 patch-upstream-spec.py /path/to/invoiceninja/openapi")
        print()
        print(
            "Run on a local checkout of invoiceninja/invoiceninja (branch v5-develop)."
        )
        print("The path should point to the openapi/ directory.")
        sys.exit(1)

    openapi_dir = Path(sys.argv[1])

    paths_file = openapi_dir / "paths.yaml"
    schemas_dir = openapi_dir / "components" / "schemas"

    if not paths_file.exists():
        print(f"ERROR: {paths_file} not found")
        sys.exit(1)

    if not schemas_dir.exists():
        print(f"ERROR: {schemas_dir} not found")
        sys.exit(1)

    print(f"Patching upstream source files in {openapi_dir}...")

    n = patch_pagination_params(paths_file)
    print(f"  paths.yaml: added pagination params to {n} endpoints")

    n = patch_readonly_ids(schemas_dir)
    print(f"  components/schemas/: added readOnly to {n} id fields")

    print("Done.")


if __name__ == "__main__":
    main()

# Taxonomy Package Stress Test Notes (Car Dealer Domain)

Date: 2026-03-29

This note summarizes what the automated tests demonstrate and what they *do not* guarantee.

## GO / NO-GO

NO-GO for a production car dealer app *as-is* if you need hard guarantees for:
- single-select enforcement (exactly one value per context)
- blocking inactive items
- hard failures on invalid assignments (instead of silent no-op)
- clean, reusable query helpers for filtering

GO for an MVP or internal app if you accept:
- app-level enforcement (service layer / DTO validation)
- some verbose query code
- extending models to add metadata columns and "active" behavior

## Strengths

- Config-driven model override works (vendor -> app extension is realistic).
- Core attach/detach/sync behavior works for multi-select contexts.
- Context-based retrieval of items is reasonable via `TaxonomyItem::forType(...)`.
- Minimal core schema makes it easy to extend in host app.

## Weaknesses Exposed by Tests

- Single-select is not enforced.
  - `tests/Feature/Taxonomy/TaxonomyContextValidationTest.php` shows a car can have multiple `brand` items.
- Invalid context assignment is a silent no-op.
  - Attaching a `fuel` item while passing context `brand` does not throw; it simply does not attach.
- No "active-only" constraint.
  - Inactive items remain attachable unless the app blocks them.
- Filtering queries are verbose and fragile.
  - `tests/Feature/Taxonomy/TaxonomyFilteringTest.php` works but repeats a lot of boilerplate and is easy to mis-specify.
- Trait keeps the base relation protected.
  - Real apps end up adding a public relation like `Car::taxonomyItems()` to query/filter/group.

## Missing Capabilities (for the dealer app)

- First-class single-select API:
  - `setTaxonomy('brand', $itemId)` with enforced uniqueness.
- Validation hooks / policies:
  - reject wrong context
  - reject inactive items
  - optional "required contexts" checks
- Query helper layer:
  - reusable scopes/helpers for filtering cars by one or more contexts/items.
- Optional metadata standardization:
  - group/item fields like `is_required`, `is_active`, `show_on_card`, etc are not part of core.
  - they must be added via model overrides + app migrations.

## GitHub readiness

Ready to publish as a *generic core taxonomy package* if README clearly states:
- single vs multi-select enforcement is app-level today
- "active" and other business rules are app-level
- filtering helpers are not included (yet)

Not ready to publish as a "production-ready dealer taxonomy system" without adding:
- enforcement + validation layer
- filtering/query ergonomics


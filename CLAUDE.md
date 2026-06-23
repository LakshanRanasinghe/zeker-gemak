# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Full setup (install deps, key, migrate, build)
composer run setup

# Development (runs Laravel server, queue, logs, Vite concurrently)
composer run dev

# Lint (Laravel Pint)
composer run lint          # fix
composer run lint:check    # check only

# Tests (clears config, checks lint, runs Pest)
composer run test
./vendor/bin/pest --filter=TestName   # single test

# Elasticsearch
make elastic-setup         # create index
make elastic-reindex       # reindex products
```

## Architecture

**Laravel 12 + Livewire 4** e-commerce admin for a label/printing business. Uses **Vanilo Framework v5.1** as the e-commerce layer.

### Livewire Component Pattern

Components live as inline class-based `.blade.php` files prefixed with `⚡`:
- `resources/views/components/{entity}/⚡create-update.blade.php` — single component handles both create and edit
- `resources/views/components/{entity}/⚡index.blade.php` — list views

These use **Flux / Flux Pro** UI components for forms and layout.

### Data Tables

All tables extend `PowerGridComponent` in `app/Livewire/`. They use complex SQL with joins for:
- Vanilo translations (multi-locale: `en`, `nl`)
- Spatie Media Library (images)
- Taxon relationships

### Models & Vanilo

Models in `app/Models/` extend Vanilo base models. Key relationships:
- `MasterProduct` → has many `Product` (variants)
- Products use Vanilo's translation system for multi-locale fields
- Soft deletes on products and master_products

### Services

- `ProductCatalogService` — main catalog/product logic
- `ElasticCatalogSearchGateway` — Elasticsearch via Scout

### Routes

- `routes/web.php` — Livewire routes using `Route::livewire()` for all admin CRUD
- `routes/api.php` — REST API (products, customers, orders, addresses) with Sanctum/Passport auth

### Configuration

- `config/products.php` — product meta field options (materials, shapes, sizes, finishes)
- `config/app.php` — locales (`en`, `nl`), currency (`EUR`)
- Multi-locale editing supported in create/update forms with locale switcher

### Database

MySQL in production, SQLite in-memory for tests. Migrations in `database/migrations/`.

### Authentication & Authorization

- Spatie Laravel Permission for roles/RBAC
- Laravel Passport + Sanctum for API auth
- Fortify for web auth

### CI

GitHub Actions: `.github/workflows/lint.yml` and `tests.yml`

## Cross-Repo API Sync Protocol

This Laravel backend serves a **Next.js frontend** at `/Users/hasanaftab/Desktop/Projects/businesslabels-new`.

### When modifying any API endpoint, you MUST also update the frontend:

1. **Route change** (`routes/api.php`) → update the matching function in `src/lib/api/{domain}.js`
2. **Resource change** (`app/Http/Resources/`) → update JSDoc types in `src/lib/api/types.js`
3. **New endpoint** → add function in the appropriate `src/lib/api/{domain}.js`, export from `index.js`, add types
4. **Removed endpoint** → remove the function, remove export, remove unused types
5. **Validation change** (`app/Http/Requests/`) → update JSDoc param types on the corresponding API function

### API module → Frontend file mapping

| Laravel Controller | Frontend Module |
|---|---|
| `API\AuthController` | `src/lib/api/auth.js` |
| `API\ProductController` | `src/lib/api/products.js` |
| `API\CategoryController` | `src/lib/api/categories.js` |
| `API\FilterController` | `src/lib/api/filters.js` |
| `API\OrderController` | `src/lib/api/orders.js` |
| `API\ProfileController` | `src/lib/api/profile.js` |
| `API\CustomerAddressController` | `src/lib/api/addresses.js` |
| `API\FavoriteProductController` | `src/lib/api/favorites.js` |
| `API\FavoritePrinterController` | `src/lib/api/favorites.js` |
| `API\CouponController` | `src/lib/api/coupons.js` |
| `API\CustomerController` | `src/lib/api/addresses.js` (customer sub-routes) |
| `API\CustomerReviewController` | `src/lib/api/reviews.js` |
| `Api\FaqController` | `src/lib/api/faq.js` |

### Resource → Type mapping

| Laravel Resource | JSDoc Type |
|---|---|
| `Api\ProductResource` | `Product`, `ProductListResponse` |
| `Api\CategoryGroupResource` | `CategoryGroup` |
| `Api\CategoryResource` | `Category` |
| `Api\ProfileResource` | `Profile` |
| `Api\PrinterResource` | `Printer` |
| `OrderResource` | `Order`, `OrderItem`, `BillingAddress`, `ShippingAddress` |
| `CustomerResource` | (admin only — not consumed by frontend) |
| `CustomerAddressResource` | `CustomerAddress` |
| `CouponResource` | `Coupon` |
| `Api\CustomerReviewResource` | `CustomerReview`, `CreateReviewRequest` |
| `Api\FaqPageResource` | `FaqPage`, `FaqSection`, `FaqItem` |

## graphify

This project has a graphify knowledge graph at graphify-out/.

Rules:

- Before answering architecture or codebase questions, read graphify-out/GRAPH_REPORT.md for god nodes and community structure
- If graphify-out/wiki/index.md exists, navigate it instead of reading raw files
- For cross-module "how does X relate to Y" questions, prefer `graphify query "<question>"`, `graphify path "<A>" "<B>"`, or `graphify explain "<concept>"` over grep — these traverse the graph's EXTRACTED + INFERRED edges instead of scanning files
- After modifying code files in this session, run `graphify update .` to keep the graph current (AST-only, no API cost)
- **Always exclude `vendor/` and `node_modules/` from graphify runs.** The graph covers only application code: `app/`, `resources/`, `routes/`, `database/`, `config/`, `tests/`. Never run `/graphify .` on the repo root — always target a specific app subfolder or pass explicit paths.

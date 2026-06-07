# ZERA — AI-Powered E-Commerce Platform

ZERA is a full-stack shopping experience that combines a classic e-commerce flow (catalog, cart, checkout, orders, wishlist, user accounts) with an AI shopping assistant, bilingual UI, and server-side personalization. Built as a learning/portfolio project using **PHP 8.4**, **MySQL/MariaDB**, and **vanilla JavaScript** — no frontend framework.

> **Stack:** PHP 8.4+ · MySQL/MariaDB · Vanilla JS · OpenAI API (optional) · MAMP (local)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Features](#2-features)
3. [Project Structure](#3-project-structure)
4. [Requirements](#4-requirements)
5. [Installation](#5-installation)
6. [Configuration](#6-configuration)
7. [Database](#7-database)
8. [Running the App](#8-running-the-app)
9. [Chatbot Architecture](#9-chatbot-architecture)
10. [Recommendations & Personalization](#10-recommendations--personalization)
11. [Inventory Management](#11-inventory-management)
12. [Authentication & Security](#12-authentication--security)
13. [Internationalization (i18n)](#13-internationalization-i18n)
14. [Product Import & Backfill](#14-product-import--backfill)
15. [Categories & Subcategories](#15-categories--subcategories)
16. [API & Endpoints](#16-api--endpoints)
17. [Known Limitations](#17-known-limitations)
18. [Development Journey](#18-development-journey)
19. [License & Contributing](#19-license--contributing)

---

## 1. Overview

ZERA is organized in three layers:

| Layer | Responsibility |
|--------|----------------|
| **Frontend** | Vanilla JS + custom CSS. Cart, favorites, and recently viewed products live in `localStorage`. Chatbot UI is embedded in the global footer. |
| **Backend** | Plain PHP with PDO. Pages and JSON APIs coexist in the same files (e.g. `checkout.php` serves both the checkout page and a JSON checkout endpoint). |
| **AI** | Regex-based intent detection + optional OpenAI `gpt-4o-mini` when `OPENAI_API_KEY` is set. No vector database or RAG pipeline. |

The application lives under `E-Commerce/`. Point your web server document root (or MAMP URL) at that folder path.

---

## 2. Features

### Shopping

- **Guest browsing** — Browse products, search, filter, and view details without logging in.
- **Cart** — Client-side cart stored in `localStorage` (`zera_cart`). No server-side cart sync.
- **Checkout** — Login required. [iyzico](https://www.iyzico.com/) Checkout Form for card payments (sandbox or live). Stock is validated at checkout; decremented only after successful payment.
- **Orders** — View order history with payment status; cancel unpaid orders (`awaiting_payment` / `failed`).
- **Wishlist** — Client-side favorites in `localStorage` (`zera_favorites`). Persists per browser/device; no login or database sync. Missing prices are hydrated via `wishlist_prices.php`. Legacy keys (`story_*`) migrate automatically on first load.
- **Product badges** — Marketplace-style labels (e.g. Best Seller, On Sale, Fast Delivery) via JSON column or section-based defaults.

### AI & Personalization

- **Floating chatbot** — Product search, add-to-cart suggestions, policy answers, session memory.
- **“Recommended for you”** — Server-side scoring from past orders (`recommended.php`). Favorites in `localStorage` are not used for server recommendations.
- **Previously bought** — Logged-in users see items from order history on the homepage.

### Platform

- **Bilingual UI** — Turkish and English via dictionary files and `?lang=tr|en`.
- **Environment-based DB config** — `.env` file with TCP or Unix socket support.
- **One-click product import** — Pull catalog data from Fake Store API or DummyJSON.

---

## 3. Project Structure

```
chatbotv2/
├── .gitignore
├── LICENSE                 # MIT
├── README.md
├── tools/                  # eval_intents, eval_dialogue, eval_answer_quality, eval_tr_search, eval_platform, eval_all
├── docs/                   # *_test_set.json, eval result reports
└── E-Commerce/
    ├── .env.example          # Copy to .env (not committed)
    ├── db.php                # PDO connection + .env loader
    ├── functions.php         # Auth guards, categories, badges, product helpers
    ├── i18n.php              # Translation engine
    ├── locales/
    │   ├── en.php
    │   └── tr.php
    ├── auth.php              # Login & registration (page)
    ├── auth_api.php          # JSON sign-in / join / forgot-password
    ├── forgot_password.php
    ├── reset_password.php
    ├── oauth_start.php
    ├── oauth_callback.php
    ├── admin_orders.php
    ├── admin_update_order.php
    ├── chatbot_feedback.php
    ├── profile.php
    ├── recommended_api.php
    ├── logout.php
    ├── index.php             # Homepage (hero, categories, featured, recommendations)
    ├── products.php          # Catalog with filters & search
    ├── product_detail.php
    ├── checkout.php          # Checkout page + JSON API (starts iyzico session)
    ├── payment_callback.php  # iyzico return URL — verifies payment, fulfills stock
    ├── orders.php
    ├── cancel_order.php      # Cancel order JSON API
    ├── payments/
    │   ├── bootstrap.php     # Composer autoload, site URL helpers
    │   ├── OrderService.php  # Order creation, stock fulfill, payment records
    │   └── IyzicoService.php # iyzico Checkout Form init + retrieve
    ├── composer.json         # iyzico/iyzipay-php SDK
    ├── profile.php
    ├── wishlist.php          # Wishlist page (UI; data lives in localStorage)
    ├── wishlist_prices.php   # JSON API — hydrate favorite prices by product name
    ├── recommended.php       # Homepage recommendation scoring
    ├── security/
    │   └── Security.php      # CSRF, session cookies, login rate limits
    ├── auth/
    │   ├── AuthService.php
    │   └── OAuthService.php
    ├── mail/
    │   └── PasswordResetService.php
    ├── import_products.php   # External API import + backfill tools
    ├── chatbot_api.php       # Chatbot entry point
    ├── chatbot/
    │   ├── intent.php        # Intent classification (regex + optional LLM)
    │   ├── helpers.php       # Entity extraction (price, color, brand, etc.)
    │   ├── actions.php       # SQL product search, cart actions
    │   ├── responses.php     # Response formatting
    │   └── ai.php            # OpenAI cURL wrapper
    ├── knowledge/
    │   └── policies.php      # Static policy knowledge for chatbot
    ├── includes/
    │   ├── header.php        # Navbar + language switcher
    │   ├── footer.php        # Footer + chatbot widget + auth modal
    │   ├── product_card.php
    │   └── home_product_card.php
    ├── schema.sql            # Full DB schema (fresh install)
    ├── database/
    │   └── SchemaService.php # Runtime schema guards (sizes, feedback, chat log)
    ├── migrations/           # Incremental upgrades for existing DBs
    ├── setup_users.sql       # Legacy users-only bootstrap (superseded by schema.sql)
    └── assets/
        ├── css/              # style, navbar, homepage, products, auth, etc.
        └── js/
            ├── main.js       # Cart, favorites, chatbot UI, checkout
            ├── homepage.js
            ├── products.js
            ├── orders.js
            └── profile.js
```

---

## 4. Requirements

- **PHP 8.4+** (8.1+ may work; tested on 8.4)
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Apache** (e.g. MAMP) or any PHP-capable web server
- **Optional:** [OpenAI API key](https://platform.openai.com/) for LLM-enhanced chatbot replies

---

## 5. Installation

### 1. Clone the repository

```bash
cd /Applications/MAMP/htdocs   # or your web root
git clone <repository-url> chatbotv2
cd chatbotv2
```

### 2. Create environment file

```bash
cp E-Commerce/.env.example E-Commerce/.env
```

Edit `E-Commerce/.env` with your database credentials (see [Configuration](#6-configuration)).

### 3. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE chatbotv2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Import the full schema (all tables + default categories):

```bash
mysql -u root -p chatbotv2_db < E-Commerce/schema.sql
```

> `schema.sql` replaces the older piecemeal bootstrap (`setup_users.sql` + individual migrations) for fresh installs. Existing databases can keep using incremental files under `migrations/`.

### 4. Install PHP dependencies (iyzico SDK)

```bash
cd E-Commerce
php composer.phar install
# or: composer install
```

This installs `iyzico/iyzipay-php` into `vendor/` (gitignored).

### 5. Start MAMP (or your stack)

Ensure Apache and MySQL are running.

### 6. (Optional) Run evaluation suite

After the DB is seeded and products imported:

```bash
php tools/eval_all.php
php tools/eval_tr_search.php --failures   # Turkish search only
```

See [Evaluation & quality (CLI)](#evaluation--quality-cli) in §9.

### 7. Seed products (first run)

In `.env`, enable import for local dev only:

```env
IMPORT_PRODUCTS_ENABLED=true
ADMIN_EMAIL=your@email.com
```

Log in with `ADMIN_EMAIL`, then open in the browser:

```
http://localhost:8888/chatbotv2/E-Commerce/import_products.php?source=dummy&limit=100
```

Then backfill subcategories:

```
http://localhost:8888/chatbotv2/E-Commerce/import_products.php?backfill_subcat=1
```

**Production:** do not set `IMPORT_PRODUCTS_ENABLED` (defaults to disabled; URL returns 403).

Adjust host/port if your MAMP setup differs (default Apache port is often `8888`, MySQL `8889`).

---

## 6. Configuration

### Database (`.env`)

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=chatbotv2_db
DB_USER=root
DB_PASS=root
DB_CHARSET=utf8mb4

# Optional: Unix socket (MAMP) — overrides host/port when set
# DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
```

Priority: MAMP defaults in `db.php` → `.env` file → environment variables (`DB_HOST`, `DB_PORT`, etc.).

### OpenAI (optional)

```env
OPENAI_API_KEY=sk-...
```

Without this key, the chatbot still works using regex intents and rule-based responses.

### Product import (local only)

```env
IMPORT_PRODUCTS_ENABLED=true
ADMIN_EMAIL=admin@example.com
```

`import_products.php` is **disabled by default**. When enabled, browser access requires an admin session (`ADMIN_EMAIL`); CLI (`php -f import_products.php`) only checks the env flag. Leave unset on production servers.

### Admin

```env
ADMIN_EMAIL=admin@example.com
```

The logged-in user whose email matches `ADMIN_EMAIL` can open `admin_orders.php` and update order status via `admin_update_order.php`. Also required for browser access to `import_products.php` when `IMPORT_PRODUCTS_ENABLED=true`.

### OAuth (Google / Facebook)

Social login buttons stay **disabled** until both ID and secret are set for a provider (`auth/OAuthService.php` → `oauth_provider_enabled()`).

```env
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
FACEBOOK_APP_ID=your-facebook-app-id
FACEBOOK_APP_SECRET=your-facebook-app-secret
OAUTH_REDIRECT_URI=http://localhost:8888/chatbotv2/E-Commerce/oauth_callback.php
```

| Variable | Purpose |
|----------|---------|
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Google Cloud OAuth 2.0 client |
| `FACEBOOK_APP_ID` / `FACEBOOK_APP_SECRET` | Meta developer app credentials |
| `OAUTH_REDIRECT_URI` | Optional; if empty, derived from the current host + `oauth_callback.php` |

Register the redirect URI in each provider console. Flow: `oauth_start.php` → provider → `oauth_callback.php` → session.

### Email / SMTP

Required for password reset (`forgot_password.php`, `auth_api.php`). Welcome mail on registration is optional.

```env
MAIL_ENABLED=true
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=ZERA
MAIL_SITE_NAME=ZERA
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your@gmail.com
SMTP_PASS=your-app-password
SMTP_ENCRYPTION=tls
```

| Variable | Purpose |
|----------|---------|
| `MAIL_ENABLED` | `true` to send mail; `false` skips SMTP (reset links still work in dev logs if mail fails) |
| `MAIL_FROM_*` / `MAIL_SITE_NAME` | From header and template branding |
| `SMTP_*` | PHPMailer transport (`mail/MailService.php`) |

### iyzico (checkout)

Get sandbox keys from [iyzico Sandbox Merchant](https://sandbox-merchant.iyzipay.com):

```env
IYZICO_API_KEY=sandbox-xxxxxxxx
IYZICO_SECRET_KEY=sandbox-xxxxxxxx
IYZICO_BASE_URL=https://sandbox-api.iyzipay.com
IYZICO_USD_TRY_RATE=34.0
IYZICO_DEFAULT_IDENTITY=11111111111
```

| Variable | Purpose |
|----------|---------|
| `IYZICO_API_KEY` / `IYZICO_SECRET_KEY` | API credentials |
| `IYZICO_BASE_URL` | Sandbox or production API base |
| `IYZICO_USD_TRY_RATE` | Catalog prices are USD; charged amount is converted to TRY for iyzico |
| `IYZICO_DEFAULT_IDENTITY` | Fallback Turkish ID (sandbox test identity) when not collected in the form |

**Flow:** User submits shipping on `checkout.php` → JSON API creates an `awaiting_payment` order → browser redirects to iyzico → `payment_callback.php` verifies the token → on success, stock is decremented and `payment_status` becomes `paid`.

Without API keys, checkout shows a warning and the JSON endpoint returns `iyzico_not_configured`.

---

## 7. Database

### Fresh install

```bash
mysql -u root -p -e "CREATE DATABASE chatbotv2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p chatbotv2_db < E-Commerce/schema.sql
```

`schema.sql` creates every table, seeds the six default categories, and includes columns from all incremental migrations (OAuth, notification prefs, badges, sizes, fulfillment, chatbot feedback, etc.).

### Core tables

| Table | Purpose |
|--------|---------|
| `users` | Accounts + OAuth IDs + `email_notifications` preference |
| `categories` | Top-level categories (seeded: Electronics, Fashion, Home, men's/women's clothing, jewelery) |
| `products` | Catalog (`badges`, `sizes`, `sub_category`, `stock_quantity`, …) |
| `orders` | Orders + payment/fulfillment fields (`tracking_number`, `shipped_at`, …) |
| `order_items` | Line items per order |
| `payments` | iyzico payment log |
| `password_resets` | Forgot-password tokens |
| `support_interactions` | Logged-in chatbot message log |
| `chatbot_feedback` | Thumbs up/down feedback |

> **Wishlist:** Favorites are **not** stored in the database. They live in browser `localStorage` (`zera_favorites`; keys `zera_cart`, `zera_recent` for cart/recently viewed). The legacy `user_favorites` table was removed — see `migrations/drop_user_favorites.sql`.

### Incremental migrations

Fresh installs: use `schema.sql` only — it already includes the structural changes below.

Upgrade an **existing** database with the SQL files, or rely on runtime guards where noted.

| File | What it does | In `schema.sql`? | Auto at runtime? | If skipped |
|------|----------------|------------------|------------------|------------|
| `add_product_badges.sql` | `products.badges` JSON | Yes | Yes (`SchemaService`) | Badge UI defaults only |
| `add_product_sizes.sql` | `products.sizes` | Yes | Yes (`SchemaService`) | Chatbot/UI size lists empty |
| `add_iyzico_payments.sql` | `orders` payment cols + `payments` table | Yes | No | Checkout/iyzico fails |
| `add_order_fulfillment.sql` | `tracking_number`, `shipped_at`, … on `orders` | Yes | Yes (`OrderStatusService`) | Admin tracking columns missing |
| `add_oauth_providers.sql` | `google_id`, `facebook_id` on `users` | Yes | Yes (`OAuthService`) | Social login IDs not stored |
| `add_password_resets.sql` | `password_resets` table | Yes | Yes (`PasswordResetService`) | Forgot-password fails |
| `add_newsletter_preferences.sql` | `users.email_notifications` column (legacy filename) | Yes | Yes | N/A |
| `drop_newsletter_subscribers.sql` | Drop legacy newsletter list table | N/A | No | Stale table remains (harmless) |
| `drop_newsletter_opt_in.sql` | Drop legacy `newsletter_opt_in` column | N/A | No | Unused column remains (harmless) |
| `add_chatbot_feedback.sql` | `chatbot_feedback` table | Yes | Yes (`SchemaService`) | 👍👎 feedback save fails |
| `fix_support_interactions_sender_check.sql` | Allow `sender='bot'` in chat log | Yes | Yes (`SchemaService`) | Bot chat messages not logged |
| `drop_user_favorites.sql` | Drop legacy wishlist table | N/A | No | Stale table remains (harmless) |
| `seed_womens_clothing.sql` | Women's t-shirt seed rows | No | No | No women's shirts in catalog |
| `reclassify_products.sql` | Fix known mis-tagged products | No | No | Wok/shoe etc. stay wrong |
| `fix_strainer_subcategory.sql` | One-off strainer row fix | No | No | Single product wrong subcategory |

**Data / catalog migrations** (`seed_womens_clothing.sql`, `reclassify_products.sql`, `fix_strainer_subcategory.sql`) are intentionally manual — they change product rows, not schema. Run after import:

```bash
mysql -u root -p chatbotv2_db < E-Commerce/migrations/reclassify_products.sql
mysql -u root -p chatbotv2_db < E-Commerce/migrations/seed_womens_clothing.sql
```

For bulk classification refresh, use `import_products.php?backfill_subcat=1&force=1` (admin + `IMPORT_PRODUCTS_ENABLED=true`).

---

## 8. Running the App

**Homepage:**

```
http://localhost:8888/chatbotv2/E-Commerce/index.php
```

**Other pages:**

| Page | URL |
|------|-----|
| Products | `.../E-Commerce/products.php` |
| Login / Register | `.../E-Commerce/auth.php` |
| Profile | `.../E-Commerce/profile.php` |
| Orders | `.../E-Commerce/orders.php` |
| Wishlist | `.../E-Commerce/wishlist.php` |

Switch language: append `?lang=en` or `?lang=tr` to any page.

---

## 9. Chatbot Architecture

Not a RAG system — a **hybrid of regex intent matching and optional LLM fallback**.

```
User message
     │
     ▼
┌─────────────────────┐
│ chatbot/intent.php  │  Regex patterns + optional OpenAI
└─────────────────────┘
     │
     ▼
┌─────────────────────┐
│ chatbot/helpers.php │  Extract entities (price, category, color, brand…)
└─────────────────────┘
     │
     ▼
┌─────────────────────┐
│ chatbot/actions.php │  SQL search, cart hints, policy lookup
└─────────────────────┘
     │
     ▼
┌──────────────────────┐
│ chatbot/responses.php│  Format user-facing reply + product cards
└──────────────────────┘
     │
     ▼
$_SESSION['chatbot_memory'] updated → JSON response
```

**Entry point:** `POST` to `chatbot_api.php` with JSON body:

```json
{ "message": "show me red dresses under 500", "cart": [], "page": "products" }
```

**Policy answers** are served from `knowledge/policies.php` when the user asks about shipping, returns, etc.

**Order tracking (logged-in users):** `order_status` intent queries the `orders` and `order_items` tables (not static templates).

### Evaluation & quality (CLI)

Reproducible checks live under `tools/` — no PHPUnit required. JSON fixtures in `docs/*_test_set.json` drive most suites.

| Script | Scope |
|--------|--------|
| `eval_intents.php` | Single-turn intent classification |
| `eval_dialogue.php` | Multi-turn intent + reply assertions |
| `eval_answer_quality.php` | DB grounding + AI guardrails |
| `eval_tr_search.php` | Turkish entity extraction + SQL product search |
| `eval_platform.php` | Auth, OAuth, user prefs, checkout, admin, CSRF |
| `eval_all.php` | Runs all of the above; exits non-zero if any suite fails |

```bash
php tools/eval_all.php
php tools/eval_all.php --failures    # show per-suite output snippets
php tools/eval_all.php --json        # machine-readable summary
```

Most chatbot evaluators support `--failures`, `--save` (writes `docs/eval_*_results.json`), and `--json` where noted below. `eval_tr_search.php` supports `--failures` only.

### Intent evaluation (thesis / reproducibility)

Labeled test set and metrics pipeline for `detect_intent()`:

```bash
php tools/eval_intents.php
php tools/eval_intents.php --failures --save
php tools/eval_intents.php --json
```

| Artifact | Path |
|----------|------|
| Test set (90 utterances, 7 intents) | `docs/intent_test_set.json` |
| Evaluator script | `tools/eval_intents.php` |
| Latest report (after `--save`) | `docs/eval_intents_results.json` |

### Dialogue evaluation (multi-turn end-to-end)

Labeled **multi-turn scenarios** test intent routing **and** reply correctness (not just single-turn intent):

```bash
php tools/eval_dialogue.php
php tools/eval_dialogue.php --failures --save
php tools/eval_dialogue.php --scenario dlg_01
php tools/eval_dialogue.php --json
```

| Artifact | Path |
|----------|------|
| Dialogue scenarios (12 dialogs, 19 turns) | `docs/dialogue_test_set.json` |
| Evaluator script | `tools/eval_dialogue.php` |
| Latest report (after `--save`) | `docs/eval_dialogue_results.json` |

**Metrics:** `scenario_success_rate`, `turn_intent_accuracy`, `turn_reply_accuracy` (assertions: `reply_contains`, `min_suggested_products`, `product_name_contains`, etc.).

**Example scenario (`dlg_01`):** `I want tshirt` → `does it have Size S` → `stock?` — expects `product_search` then `product_followup` with grounded size/stock replies.

### Answer quality evaluation (grounding + guardrails)

Measures whether replies are **factually grounded** (not just syntactically plausible):

```bash
php tools/eval_answer_quality.php
php tools/eval_answer_quality.php --failures --save
php tools/eval_answer_quality.php --json
```

| Check | What it verifies |
|-------|------------------|
| `stock_matches_db` | Stock reply quantity/status matches `products.stock_quantity` |
| `sizes_match_product` | Size list / requested size matches `get_product_sizes()` |
| `policy_intent` | Policy reply overlaps `knowledge/policies.php` content |
| `prices_match_products` | `$ prices` in reply exist in suggested products |
| `guardrail_cases` | Synthetic hallucination traps rejected by `is_ai_reply_grounded()` |

| Artifact | Path |
|----------|------|
| Grounding fields in dialogue turns | `docs/dialogue_test_set.json` → `expect.grounding` |
| Guardrail + policy probes | `docs/answer_quality_test_set.json` |
| Evaluator | `tools/eval_answer_quality.php` |
| Latest report | `docs/eval_answer_quality_results.json` |

**Key metric:** `task_success_rate` (= `answer_accuracy`) — share of all grounding/guardrail checks passed.

### Turkish product search evaluation

End-to-end regression for **Turkish catalog queries** — not just intent labels. Each case runs the same pipeline as live product search:

`extract_entities()` → optional session context (`apply_product_search_session_context`) → `expand_entities_for_product_search()` → `search_products_advanced()` → `filter_products_for_entities()`.

Requires a populated MySQL catalog (`schema.sql` + product import). Exit code `1` if any case fails.

```bash
php tools/eval_tr_search.php
php tools/eval_tr_search.php --failures
```

| Assertion field | What it verifies |
|-----------------|------------------|
| `expect_product_type` | Entity `product_type` after TR synonym expansion |
| `expect_audience` | `women` / `men` audience filter |
| `expect_size` | Size token (e.g. `S`) |
| `expect_brand` | Brand entity (e.g. `nike`) |
| `expect_sort_by` | Sort hint (e.g. `price_asc` for “en ucuz”) |
| `expect_max_price_usd` | Budget parsed from TL / € / $ phrasing |
| `expect_no_max_price` | Stale session budget must not leak into a new query |
| `min_results` | Minimum products returned (use `0` when catalog may be empty) |
| `expect_any_sub` | At least one hit in listed `sub_category` values |
| `max_product_price` | Every result price ≤ cap |
| `reject_name_contains` | No result name contains forbidden tokens (cross-category leaks) |
| `session_memory_entities` | Simulates `$_SESSION['chatbot_memory']` from a prior turn |

| Artifact | Path |
|----------|------|
| Test set (65 Turkish queries) | `docs/tr_search_test_set.json` |
| Evaluator | `tools/eval_tr_search.php` |
| Code under test | `chatbot/tr_synonyms.php`, `chatbot/helpers.php`, `chatbot/actions.php` |

**Key metric:** `pass_rate` — share of cases with zero assertion errors.

**Example cases:** `tr_05` (`kadın elbise göster` → `product_type=dress`, `audience=women`), `tr_23` (kitchen query must not inherit shirt budget from session memory), `tr_58` (`nike ayakkabı` → brand + shoe results).

### Full evaluation suite (`eval_all.php`)

Single entry point for CI or pre-release checks. Runs suites **in order** and prints a PASS/FAIL summary:

1. `eval_intents.php`
2. `eval_tr_search.php`
3. `eval_dialogue.php`
4. `eval_answer_quality.php`
5. `eval_platform.php`

```bash
php tools/eval_all.php
php tools/eval_all.php --failures   # print first lines of each suite output
php tools/eval_all.php --json       # `{ "passed": true, "suites": { ... } }`
```

| Behavior | Detail |
|----------|--------|
| Exit code | `0` only if **all** suites pass |
| `--failures` | Forwards to `eval_tr_search`, `eval_dialogue`, `eval_answer_quality`, `eval_platform` (shows failing cases) |
| `--json` | Aggregated pass/fail per suite; individual suite `--save` flags are **not** forwarded — run evaluators directly to refresh `docs/eval_*_results.json` |

### Platform evaluation (auth, OAuth, checkout, payments, admin, user prefs)

CLI smoke tests for non-chatbot services. No PHPUnit required — same pattern as chatbot eval scripts.

```bash
php tools/eval_platform.php
php tools/eval_platform.php --failures --save
php tools/eval_platform.php --json
php tools/eval_platform.php --no-db   # unit cases only (no MySQL)
```

| Suite | What it verifies |
|-------|------------------|
| **auth** | `auth_safe_return_url()`, sign-in/join validation (`AuthService.php`) |
| **oauth** | Provider enable/disable, `oauth_start_url()` when credentials present/absent |
| **user_prefs** | Profile `email_notifications` flag on `users` (`UserPreferencesService.php`) |
| **checkout** | `normalize_cart_lines()`, empty-cart guard, `create_awaiting_payment_order()` (rolled back) |
| **payments** | `iyzico_is_configured()`, USD→TRY rate helper |
| **admin** | `is_admin_user()` vs `ADMIN_EMAIL`; `update_order_status()` error paths |
| **orders** | Payment status normalization, display status, allowed transitions |
| **security** | CSRF token generation, verify, rotate |

| Artifact | Path |
|----------|------|
| Declarative expectations | `docs/platform_test_set.json` |
| Evaluator | `tools/eval_platform.php` |
| Shared helpers | `tools/lib/eval_helpers.php` |
| Latest report (after `--save`) | `docs/eval_platform_results.json` |

Integration cases need a running MySQL database (`E-Commerce/.env`). When DB is down, JSON-driven unit tests still run; DB-dependent cases are reported as **skipped**.

---

## 10. Recommendations & Personalization

**File:** `recommended.php` — function `get_ai_recommendations()`

Scoring:

| Signal | Weight | Source |
|--------|--------|--------|
| Favorite in category | +5 | `zera_favorites` in `localStorage` → `zera_fav_ids` cookie / `recommended_api.php` |
| Ordered from category | +8 | `orders` + `order_items` (logged-in users) |

Top-scoring categories drive a SQL query for in-stock suggestions. Already-favorited product IDs are excluded from the result set. Guests with favorites still get favorite-based scoring; without signals, featured/random fallback is used.

**Favorites bridge:** Wishlist data stays in `localStorage`, but `main.js` mirrors product IDs to the `zera_fav_ids` cookie. `index.php` reads that cookie on first paint; `recommended_api.php` refreshes the homepage grid after load when favorites exist.

The homepage (`index.php`) also loads:

- **Featured** products (`is_featured = 1`)
- **Previously bought** (from `orders` + `order_items`)
- **Best sellers / deals** (random samples for demo sections)

---

## 11. Inventory Management

Real stock handling — not cosmetic counters.

| Event | Behavior |
|--------|----------|
| **Checkout (pre-payment)** | Stock availability is validated when the order is created (`awaiting_payment`); no decrement yet. |
| **Payment success** | `payment_callback.php` runs `fulfill_order_stock()` — `FOR UPDATE` + atomic decrement per line. |
| **Insufficient stock** | Order creation or fulfillment fails; client receives a clear error. |
| **Order cancel** | `cancel_order.php` restores stock only for **paid** orders being cancelled; unpaid orders can be cancelled without stock change. |
| **Add to cart** | No reservation — stock is checked at checkout / payment (by design). |

Default import stock: `100` units per product (configurable in `import_products.php`).

---

## 12. Authentication & Security

### Session & access control (`functions.php`, `security/Security.php`)

```php
require_login(): int       // Protected pages/APIs — 401 JSON or redirect to auth.php
require_owner($ownerId, $userId): void
require_admin(): void      // ADMIN_EMAIL must match logged-in user
csrf_require($asJson): void
```

| Feature | Implementation |
|---------|----------------|
| **Password hashing** | `password_hash()` / `password_verify()` (bcrypt) |
| **Session fixation** | `session_regenerate_id(true)` on first boot + login (`AuthService`) |
| **Cookie flags** | `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS (or `FORCE_HTTPS=true`) |
| **Open redirects** | `auth_safe_return_url()` — relative in-app paths only |
| **Owner checks** | Orders and profile data scoped to `user_id` |
| **CSRF** | Session token via `csrf_token()`; forms use `csrf_field_html()`; JSON APIs accept `X-CSRF-Token` (`window.csrfHeaders()` in `main.js`) |
| **Login rate limit** | IP-based file bucket in `security/Security.php` (`login_rate_limited()`, 15‑min lockout) |
| **Password reset rate limit** | IP-based (`password_reset_rate_limited()` in `PasswordResetService.php`) |
| **Chatbot rate limit** | Session-based 20 messages/minute (`chatbot_api.php`) |
| **Password reset** | `forgot_password.php`, `reset_password.php`, `auth_api.php` (`action=forgot`), `mail/PasswordResetService.php` |
| **OAuth** | Google / Facebook via `oauth_start.php` + `oauth_callback.php` (`auth/OAuthService.php`) |
| **Admin** | `admin_orders.php` + `admin_update_order.php` (`require_admin()`, `ADMIN_EMAIL` in `.env`) |

### CSRF-protected POST endpoints (sample)

`checkout.php`, `cancel_order.php`, `chatbot_api.php`, `chatbot_feedback.php`, `auth_api.php`, `wishlist_prices.php`, `recommended_api.php`, `admin_update_order.php`, auth/profile forms.

### Protected resources

| File | Protection |
|------|------------|
| `profile.php` | `require_login()` + CSRF on POST |
| `orders.php` | `require_login()` |
| `checkout.php` (JSON POST) | `require_login()` + CSRF |
| `cancel_order.php` | `require_login()` + CSRF |
| `admin_orders.php` | `require_admin()` |
| `import_products.php` | `IMPORT_PRODUCTS_ENABLED=true` + admin (browser) |

### Still not included (see [Known Limitations](#17-known-limitations))

Email verification on signup, CAPTCHA, WAF, IP-based chatbot rate limiting, and production-grade secrets rotation.

---

## 13. Internationalization (i18n)

**Engine:** `i18n.php` + locale dictionaries.

```php
echo t('home.hero.new_arrivals', 'New arrivals just dropped');
```

**Language resolution order:**

1. `?lang=tr` or `?lang=en`
2. `$_SESSION['lang']`
3. URI prefix `/tr/` or `/en/` (if used)
4. Default: `en`

**Dictionaries:** `locales/en.php`, `locales/tr.php` (~250+ keys each).

Category labels use `localized_category_label()` with slug normalization so DB variants like `Women's Clothing` and `women` map to one UI label.

---

## 14. Product Import & Backfill

**Script:** `import_products.php` (browser or CLI via `php -f` if configured).

| URL | Action |
|-----|--------|
| `import_products.php` | Fake Store API, 20 products |
| `import_products.php?limit=50` | Fake Store, custom limit |
| `import_products.php?source=dummy&limit=100` | DummyJSON (richer descriptions) |
| `import_products.php?backfill_stock=1` | Set NULL/0 stock to 100 |
| `import_products.php?backfill_subcat=1` | Infer `sub_category` from name/description |
| `import_products.php?backfill_subcat=1&force=1` | Recompute all subcategories |

Import maps external categories to local `category_id`, infers `sub_category` slugs, and uses upsert logic so re-imports do not wipe manually edited stock or subcategories (`COALESCE` guards).

---

## 15. Categories & Subcategories

Historical DB labels (`women's clothing`, `Jewelery`) are normalized to canonical slugs (`women`, `jewelry`) via helpers in `functions.php`:

- `normalize_category_slug()` — display/filter slug
- `db_category_aliases()` — SQL `IN (...)` alias list for filters
- `get_subcategory_search_keywords()` — keyword map for subcategory filters

Navigation deduplicates categories after normalization so the same logical category does not appear twice.

---

## 16. API & Endpoints

JSON POST endpoints expect `Content-Type: application/json` and a valid CSRF token (`X-CSRF-Token` header or `csrf_token` field) unless noted.

### Chatbot & recommendations

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `chatbot_api.php` | POST JSON | Optional | Main chatbot (`message`, `cart`, `page`) |
| `chatbot_feedback.php` | POST JSON | Optional | Thumbs up/down (`action=submit`, `helpful`, intent metadata) |
| `recommended_api.php` | POST JSON | Optional | Homepage recommendation grid HTML (`favorite_ids[]`) |
| `wishlist_prices.php` | POST JSON | Optional | Favorite price lookup by product name (`names[]`) |

### Commerce & orders

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `checkout.php` | POST JSON | Required | Create `awaiting_payment` order + iyzico `payment_page_url` |
| `payment_callback.php` | POST | Session | iyzico return URL — verify token, fulfill stock |
| `cancel_order.php` | POST JSON | Required | Cancel unpaid / failed orders |

### Auth & account

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `auth_api.php` | POST JSON | Optional | Modal auth: `action=signin` \| `join` \| `forgot` |
| `auth.php` | GET/POST | Optional | Login / register page + forms |
| `forgot_password.php` | GET/POST | Guest | Password reset request form |
| `reset_password.php` | GET/POST | Guest | Set new password from email token |
| `oauth_start.php` | GET | Guest | Redirect to Google/Facebook (`?provider=google\|facebook`) |
| `oauth_callback.php` | GET | Guest | OAuth return handler — creates/links user |
| `logout.php` | GET | Optional | End session |

### Admin & catalog ops

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `admin_orders.php` | GET | Admin | Order list + status/tracking UI |
| `admin_update_order.php` | POST | Admin + CSRF | Update order status / tracking (`order_id`, `status`, …) |
| `import_products.php` | GET | Admin + env | Import/backfill (`source`, `limit`, `women=1`, `backfill_subcat=1`, …) |

### Storefront pages (HTML)

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `wishlist.php` | GET | Optional | Wishlist page (data from `localStorage`) |
| `index.php`, `products.php`, … | GET | Optional | Standard storefront pages |

---

## 17. Known Limitations

Suitable for demos and portfolios; harden further before production traffic.

| Area | Gap |
|------|-----|
| **Security** | No email verification; no CAPTCHA; chatbot rate limit is per-session (not IP); login/reset limits use temp files (not Redis) |
| **Payments** | iyzico only; USD→TRY uses a fixed `.env` rate (not live FX) |
| **Email** | Password reset requires `MAIL_*` / SMTP config; welcome mail optional |
| **Orders** | Fulfillment flow is simplified (`pending` → `shipped` → `delivered` via admin); no carrier API integration |
| **Admin** | Lightweight `admin_orders.php` only — no full catalog/user CMS |
| **Wishlist** | Favorites are browser `localStorage` only (no cross-device sync) |
| **Images** | Product images are external URLs only (no local CDN/upload pipeline) |
| **Tests** | No PHPUnit/CI pipeline — `tools/eval_*.php` + `docs/*_test_set.json`: chatbot (intents, dialogue, answer quality, TR search) and platform (auth, OAuth, user prefs, checkout, payments, admin) via `eval_platform.php` / `eval_all.php` |
| **Import script** | Disabled by default (`IMPORT_PRODUCTS_ENABLED`); must stay off in production |

---

## 18. Development Journey

Chronological log of how the project reached its current state.  
**Last documented milestone before this section:** item **12** (category deduplication + full i18n on `auth.php`).  
**Items 13+** cover work after that write-up.

### 1. AI shopping assistant + personalized homepage
- Added `personalized_home_api.php` (later evolved; homepage now uses `recommended.php`).
- `index.php`: personalized section hooks; `main.js`: `loadPersonalizedHome()`, chat signals in `localStorage`.
- Locale keys for personalized copy.

### 2. Chatbot architecture review
- Confirmed: no vector DB / RAG; regex intent + optional OpenAI LLM.

### 3. Product import fixes
- `Unknown column 'category'` → migrated to `category_id`.
- Fixed insert/update counting (`SELECT` before upsert).
- Default `stock_quantity = 100`; `?backfill_stock=1`.

### 4. Subcategory filtering
- `sub_category` column usage in `products.php`.
- `get_subcategory_search_keywords()` for keyword-based filters.

### 5. Git workflow (`main` vs `newlast`)
- Branch merges and conflict resolution; `newlast` work folded into `main`.

### 6. Chatbot metrics (historical)
- Early `chatbot_metrics` table/UI was removed. **`chatbot_feedback`** (thumbs up/down) was later re-added — see `chatbot_feedback.php`, `migrations/add_chatbot_feedback.sql`, footer UI.

### 7. Subcategory backfill
- `import_products.php?backfill_subcat=1` — filled subcategories for catalog (e.g. 105/105 products).

### 8. Richer import payload
- Import now saves `description`, `category_id`, `sub_category`, `stock_quantity` (not only name/price/image).
- DummyJSON re-import improved description coverage.

### 9. Real stock management
- `checkout.php`: `FOR UPDATE`, atomic `stock_quantity` decrement, insufficient-stock errors.
- `cancel_order.php`: restores stock on pending order cancellation.

### 10. Centralized authentication
- `require_login()`, `require_owner()` in `functions.php`.
- Closed checkout `user_id ?? 1` hole; `safe_return_url()`; `session_regenerate_id(true)` on login.

### 11. Environment-based database config
- `db.php` rewritten: `.env` loader, TCP or Unix socket.
- `.env.example` + `.gitignore` for secrets.

### 12. Category deduplication + i18n cleanup
- `normalize_category_slug()`, `db_category_aliases()`.
- Nav no longer shows duplicate category rows (`women` vs `women's clothing`).
- `auth.php` fully wrapped with `t()` — no hardcoded English leaks on TR pages.

---

### 13. Release commit on `main` (2026-05-26)
- Commit `697beee`: bundled stock management, auth guards, env-based DB, and i18n cleanup into one `main` release.

### 14. English README rewrite (2026-06-01)
- Commit `485d874`: replaced Turkish README with structured English documentation.
- Corrected architecture docs: recommendations via `recommended.php` / `get_ai_recommendations()` (removed stale `personalized_home_api.php` references).
- Added setup guide, API table, stock/auth/i18n sections, and known limitations.

### 15. GitHub publish & branch cleanup (2026-06-01)
- Verified `main` already includes all `newlast` commits (`main` is one commit ahead of `newlast`).
- Merged `newlast` into `main` → already up to date.
- Deleted local branches `add-ecommerce` and `chatbot-ai` (`chatbot-ai` required `-D` — not fully merged).
- Pushed `main` to `https://github.com/zeynepiince/E-Commerce` via **HTTPS** (SSH port 22 timed out on this network).
- Remote deletion of `add-ecommerce` / `chatbot-ai` may still be pending if SSH push failed earlier; use:
  `git push origin --delete add-ecommerce chatbot-ai`

### 16. Catalog re-import (2026-06-01)
- Ran `import_products.php` with `source=dummy`, `limit=100` (MAMP PHP CLI).
- Result: **100 processed**, **0 inserted**, **100 updated** (catalog refresh, not new SKUs).

### 17. Documentation & ops notes (2026-06-01)
- Confirmed stock decrement is live in checkout JSON API (not only documented — see §11).
- Local app URL documented: `http://localhost:8888/chatbotv2/E-Commerce/index.php` (MAMP default port).

---

## 19. License & Contributing

This project is released under the [MIT License](LICENSE). It is intended for **learning and portfolio** use.

**Contributing**

- Open an issue for bugs or feature ideas.
- Keep pull requests focused with a clear description of changes.

---

## Quick Reference

```bash
# Copy config
cp E-Commerce/.env.example E-Commerce/.env

# Create DB
mysql -u root -p -e "CREATE DATABASE chatbotv2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Bootstrap tables
mysql -u root -p chatbotv2_db < E-Commerce/schema.sql

# Open app
open http://localhost:8888/chatbotv2/E-Commerce/index.php

# Run all eval suites (chatbot + platform)
php tools/eval_all.php
php tools/eval_tr_search.php --failures
```

**Brand name in UI:** ZERA (formerly referenced as “STORY” in older assets).

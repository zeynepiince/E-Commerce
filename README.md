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
18. [License & Contributing](#18-license--contributing)

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
- **Cart** — Client-side cart stored in `localStorage` (`story_cart`). No server-side cart sync.
- **Checkout** — Login required. Real stock validation and atomic decrement at order time.
- **Orders** — View order history; cancel **pending** orders (stock is restored).
- **Wishlist** — Database-backed favorites (login required to persist).
- **Product badges** — Marketplace-style labels (e.g. Best Seller, On Sale, Fast Delivery) via JSON column or section-based defaults.

### AI & Personalization

- **Floating chatbot** — Product search, add-to-cart suggestions, policy answers, session memory.
- **“Recommended for you”** — Server-side scoring from favorites and past orders (`recommended.php`).
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
├── README.md
└── E-Commerce/
    ├── .env.example          # Copy to .env (not committed)
    ├── db.php                # PDO connection + .env loader
    ├── functions.php         # Auth guards, categories, badges, product helpers
    ├── i18n.php              # Translation engine
    ├── locales/
    │   ├── en.php
    │   └── tr.php
    ├── auth.php              # Login & registration
    ├── logout.php
    ├── index.php             # Homepage (hero, categories, featured, recommendations)
    ├── products.php          # Catalog with filters & search
    ├── product_detail.php
    ├── checkout.php          # Checkout page + JSON API
    ├── orders.php
    ├── cancel_order.php      # Cancel order JSON API
    ├── profile.php
    ├── wishlist.php
    ├── recommended.php       # AI recommendation scoring logic
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
    ├── migrations/
    │   └── add_product_badges.sql
    ├── setup_users.sql       # Users table bootstrap
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

Import your schema if you have a SQL dump. At minimum, run:

```bash
mysql -u root -p chatbotv2_db < E-Commerce/setup_users.sql
mysql -u root -p chatbotv2_db < E-Commerce/migrations/add_product_badges.sql
```

> The repo does not ship a full schema dump. You need existing `products`, `categories`, `orders`, and related tables, or import from a backup.

### 4. Start MAMP (or your stack)

Ensure Apache and MySQL are running.

### 5. Seed products (first run)

Open in the browser:

```
http://localhost:8888/chatbotv2/E-Commerce/import_products.php?source=dummy&limit=100
```

Then backfill subcategories:

```
http://localhost:8888/chatbotv2/E-Commerce/import_products.php?backfill_subcat=1
```

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

---

## 7. Database

### Core tables

| Table | Purpose |
|--------|---------|
| `users` | Accounts (`user_id`, `full_name`, `email`, `password_hash`, …) |
| `products` | Catalog (`product_id`, `name`, `price`, `description`, `image_url`, `category_id`, `sub_category`, `stock_quantity`, `badges`, `is_featured`, …) |
| `categories` | Top-level categories (`category_id`, `category_name`) |
| `orders` | Orders (`order_id`, `user_id`, `total_amount`, `status`, `created_at`) |
| `order_items` | Line items per order |
| `user_favorites` | Wishlist rows (`user_id`, `product_id`) |

### Migrations

| File | Action |
|------|--------|
| `setup_users.sql` | Creates `users` table if missing |
| `migrations/add_product_badges.sql` | Adds `products.badges` JSON column |

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

---

## 10. Recommendations & Personalization

**File:** `recommended.php` — function `get_ai_recommendations()`

Scoring (logged-in users):

| Signal | Weight |
|--------|--------|
| Favorite in category | +5 |
| Ordered from category | +8 |

Top categories drive a SQL query for fresh product suggestions. Guests receive randomized picks.

The homepage (`index.php`) also loads:

- **Featured** products (`is_featured = 1`)
- **Previously bought** (from `orders` + `order_items`)
- **Best sellers / deals** (random samples for demo sections)

Client-side signals (`story_recent`, `story_favorites`, `story_cart` in `localStorage`) support UX; primary server personalization uses DB data when logged in.

---

## 11. Inventory Management

Real stock handling — not cosmetic counters.

| Event | Behavior |
|--------|----------|
| **Checkout** | `SELECT … FOR UPDATE`, then `UPDATE products SET stock_quantity = stock_quantity - ? WHERE … AND stock_quantity >= ?` per line item inside a transaction. |
| **Insufficient stock** | Transaction rolls back; client receives a clear error. |
| **Order cancel** | `cancel_order.php` restores quantities from `order_items`. |
| **Add to cart** | No reservation — stock is checked only at checkout (by design). |

Default import stock: `100` units per product (configurable in `import_products.php`).

---

## 12. Authentication & Security

### Helpers (`functions.php`)

```php
require_login(): int      // Protected pages/APIs — 401 JSON or redirect to auth.php
require_owner($ownerId, $userId): void
```

### Implemented

- **bcrypt** passwords via `password_hash()`
- **`session_regenerate_id(true)`** on login (session fixation mitigation)
- **Open-redirect protection** — `safe_return_url()` only allows relative in-app paths
- **Owner checks** on orders and profile data

### Protected resources

| File | Protection |
|------|------------|
| `profile.php` | `require_login()` |
| `orders.php` | `require_login()` |
| `checkout.php` (JSON POST) | `require_login()` |
| `cancel_order.php` | `require_login()` |

### Not included (see [Known Limitations](#17-known-limitations))

CSRF tokens, rate limiting, HTTPS-only cookies, email verification, password reset flow.

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

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `chatbot_api.php` | POST JSON | Optional | Chatbot messages |
| `checkout.php` | POST JSON | Required | Place order |
| `cancel_order.php` | POST JSON | Required | Cancel pending order |
| `wishlist.php` | GET/POST | Mixed | Wishlist CRUD |
| `import_products.php` | GET | None | Import/backfill (restrict in production) |

**Content-Type:** JSON endpoints expect `Content-Type: application/json`.

---

## 17. Known Limitations

This project is suitable for demos and portfolios, not production as-is.

| Area | Gap |
|------|-----|
| **Security** | No CSRF tokens; no login rate limiting; no strict cookie flags |
| **Payments** | Checkout is simulated — no Stripe/PayPal integration |
| **Email** | No verification or password reset |
| **Orders** | Status flow is basic (`pending` → `cancelled` only) |
| **Admin** | No admin panel — manage data via DB or phpMyAdmin |
| **Images** | Product images are external URLs only |
| **Tests** | No automated test suite |
| **Import script** | Publicly callable — disable or protect in production |

---

## 18. License & Contributing

This repository is intended for **learning and portfolio** use. Add a `LICENSE` file (e.g. MIT) if you publish it openly.

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
mysql -u root -p chatbotv2_db < E-Commerce/setup_users.sql
mysql -u root -p chatbotv2_db < E-Commerce/migrations/add_product_badges.sql

# Open app
open http://localhost:8888/chatbotv2/E-Commerce/index.php
```

**Brand name in UI:** ZERA (formerly referenced as “STORY” in older assets).

# ZERA — Akıllı E-Ticaret + Chatbot

Yapay zeka destekli alışveriş asistanı, kişiselleştirilmiş anasayfa ve klasik bir e-ticaret akışını (ürünler, sepet, sipariş, favori, kullanıcı yönetimi) tek bir PHP uygulamasında birleştiren proje.

> **Yığın:** PHP 8.4 · MySQL/MariaDB · Vanilla JS · OpenAI API (opsiyonel) · MAMP (local)

---

## İçindekiler

1. [Genel Bakış](#1-genel-bakış)
2. [Klasör Yapısı](#2-klasör-yapısı)
3. [Kurulum](#3-kurulum)
4. [Veritabanı Şeması](#4-veritabanı-şeması)
5. [Özellikler](#5-özellikler)
6. [Chatbot Mimarisi](#6-chatbot-mimarisi)
7. [Kişiselleştirilmiş Anasayfa](#7-kişiselleştirilmiş-anasayfa)
8. [Stok Yönetimi](#8-stok-yönetimi)
9. [Yetkilendirme ve Güvenlik](#9-yetkilendirme-ve-güvenlik)
10. [Çoklu Dil (i18n)](#10-çoklu-dil-i18n)
11. [Ürün İçeri Aktarma & Backfill](#11-ürün-içeri-aktarma--backfill)
12. [Kategoriler ve Alt Kategoriler](#12-kategoriler-ve-alt-kategoriler)
13. [Geliştirme Yolculuğu (Bu projede neler değişti)](#13-geliştirme-yolculuğu)
14. [Geleceğe Yönelik Eksikler](#14-geleceğe-yönelik-eksikler)

---

## 1. Genel Bakış

ZERA, küçük-orta ölçekli bir e-ticaret deneyimini şu üç katmanda sunar:

| Katman | Sorumluluk |
|---|---|
| **Frontend** | Vanilla JS + custom CSS, hiç framework yok. `localStorage`'ta sepet, favoriler, son görüntülenen ürünler ve chatbot sinyalleri. |
| **Backend** | Saf PHP 8.4, PDO. Sayfalar ve JSON API'lar birlikte dosyalarda (örn. `checkout.php` aynı anda hem sayfa hem `application/json` endpoint'i). |
| **Yapay Zeka** | Rule-based intent çözümleyici + OpenAI `gpt-4o-mini` (opsiyonel, `OPENAI_API_KEY` set edilirse). Vektör DB / RAG yok. |

---

## 2. Klasör Yapısı

```
chatbotv2/
├── .gitignore              # .env, vendor/, node_modules/, IDE dosyaları
└── E-Commerce/
    ├── .env.example        # Üretime taşırken kopyala → .env
    ├── db.php              # Env tabanlı DB bağlantısı (TCP veya unix socket)
    ├── functions.php       # Ortak helper'lar (i18n, auth guard, kategori normalize, ürün veri çekme)
    ├── i18n.php            # Çoklu dil motoru (tr/en)
    ├── locales/
    │   ├── tr.php          # Türkçe sözlük
    │   └── en.php          # İngilizce sözlük
    ├── auth.php            # Giriş + Kayıt (standalone sayfa, tam i18n)
    ├── logout.php
    ├── index.php           # Anasayfa: hero slider, kategori navigasyonu, öne çıkanlar, kişiselleştirilmiş bölüm
    ├── products.php        # Ürün listeleme + filtre (kategori, alt kategori, fiyat, sıralama, arama, sezon)
    ├── product_detail.php  # Ürün detay sayfası
    ├── checkout.php        # Ödeme sayfası + checkout JSON API (stok düşme dahil)
    ├── orders.php          # Sipariş listesi
    ├── cancel_order.php    # Sipariş iptali JSON API (stok geri yansıması dahil)
    ├── profile.php         # Kullanıcı profili (son siparişler dahil)
    ├── wishlist.php        # Favori listesi
    ├── recommended.php     # Öneri API
    ├── personalized_home_api.php  # Kişiselleştirilmiş anasayfa API
    ├── import_products.php # Fake Store / DummyJSON'dan ürün importu + sub_category backfill
    ├── chatbot_api.php     # Chatbot ana giriş noktası
    ├── chatbot/
    │   ├── intent.php      # Niyet sınıflandırma (regex tabanlı + opsiyonel LLM)
    │   ├── actions.php     # SQL-tabanlı ürün araması, sepet aksiyonları
    │   ├── responses.php   # Yanıt formatlama
    │   ├── helpers.php     # Entity çıkarımı (renk, marka, fiyat, vs.)
    │   └── ai.php          # OpenAI cURL wrapper'ı
    ├── includes/
    │   ├── header.php      # Navbar + dil değiştirici
    │   ├── footer.php      # Footer + chatbot widget + auth modal
    │   ├── product_card.php
    │   └── home_product_card.php
    └── assets/
        ├── css/            # Tema CSS dosyaları
        └── js/main.js      # Tek dosya: sepet, favori, chatbot UI, kişiselleştirme
```

---

## 3. Kurulum

### Gereksinimler

- MAMP (veya benzer Apache + MySQL/MariaDB)
- PHP **8.4+** (8.1+ çalışır ama 8.4'te test edildi)
- MySQL/MariaDB ile `chatbotv2_db` adlı veritabanı

### Adımlar

```bash
# 1. Repo'yu MAMP htdocs klasörüne klonla
cd /Applications/MAMP/htdocs
git clone <repo-url> chatbotv2

# 2. .env oluştur
cp E-Commerce/.env.example E-Commerce/.env
# .env içini düzenle (MAMP default: user=root, pass=root, port 8889)

# 3. Veritabanını oluştur (phpMyAdmin'de veya CLI'da)
mysql -u root -p -e "CREATE DATABASE chatbotv2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 4. (Şema otomatik oluşmuyorsa) tabloları SQL dump'tan import et
# Bu projede SQL dump dosyası repo'da yer almıyor; mevcut bir kopyadan
# içe aktarmalısınız. Tablolar §4'te listelendi.

# 5. MAMP'ı başlat ve şu URL'yi aç:
# http://localhost:8888/chatbotv2/E-Commerce/index.php

# 6. (İlk kez) Ürün importu çalıştır:
# http://localhost:8888/chatbotv2/E-Commerce/import_products.php?source=dummy&limit=100

# 7. Alt kategorileri doldur:
# http://localhost:8888/chatbotv2/E-Commerce/import_products.php?backfill_subcat=1
```

### .env Yapılandırması

```env
# TCP üzerinden bağlantı (varsayılan)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=chatbotv2_db
DB_USER=root
DB_PASS=root
DB_CHARSET=utf8mb4

# Alternatif: Unix socket (MAMP/Homebrew için pratik)
# DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock

# Chatbot için (opsiyonel)
# OPENAI_API_KEY=sk-...
```

`DB_SOCKET` set edilirse `DB_HOST`/`DB_PORT` görmezden gelinir.

---

## 4. Veritabanı Şeması

Ana tablolar:

| Tablo | Önemli kolonlar |
|---|---|
| `users` | `user_id`, `full_name`, `email`, `password_hash`, `created_at` |
| `products` | `product_id`, `external_id`, `name`, `price`, `description`, `image_url`, `category_id`, `sub_category`, `stock_quantity`, `is_featured`, `badges` (JSON) |
| `categories` | `category_id`, `category_name` |
| `orders` | `order_id`, `user_id`, `total_amount`, `status` (pending/cancelled), `created_at` |
| `order_items` | `order_id`, `product_id`, `quantity`, `unit_price` |
| `user_favorites` | `user_id`, `product_id`, `created_at` |

> Daha önce mevcut olan `chatbot_metrics` ve `chatbot_feedback` tabloları projeden çıkarıldı (bkz. §13).

---

## 5. Özellikler

### Mevcut Akışlar

- **Misafir gezintisi:** Giriş yapmadan ürün listeleme, arama, detay, sepete ekleme (localStorage).
- **Kayıt ve giriş:** `auth.php` — bcrypt parola, session_regenerate_id, open-redirect güvenli return URL.
- **Sepet:** Tamamen client-side (`localStorage` → `story_cart`). Server senkronizasyonu yok.
- **Checkout:** Login zorunlu; gerçek stok kontrolü ve düşmesi (bkz. §8).
- **Sipariş listesi & iptal:** "Pending" durumdaki siparişler iptal edilebilir, stok otomatik geri yansır.
- **Favori (wishlist):** DB tabanlı, login gerekli.
- **Çoklu dil:** TR / EN, anlık geçişle. URL'de `?lang=tr|en`.
- **Chatbot:** Sağ alt köşede widget; ürün arama, sepete ekleme, kategori önerme, politika cevapları.
- **Kişiselleştirilmiş anasayfa:** Browsing history + favoriler + sepet + chatbot sinyallerine göre ürün önerisi (bkz. §7).
- **Ürün importu:** Fake Store API veya DummyJSON'dan tek tıkla içe aktarma (bkz. §11).

---

## 6. Chatbot Mimarisi

Klasik RAG değil; **regex tabanlı niyet sınıflandırma + opsiyonel LLM** karmasıdır.

```
Kullanıcı mesajı
      │
      ▼
┌─────────────────────────────────┐
│ chatbot/intent.php              │
│  • Regex pattern eşleştirme     │
│  • LLM fallback (anahtar varsa) │  ← OPENAI_API_KEY
└─────────────────────────────────┘
      │
      ▼
┌─────────────────────────────────┐
│ chatbot/helpers.php              │
│  • Entity çıkar (renk, marka,    │
│    fiyat, kategori, audience)    │
└─────────────────────────────────┘
      │
      ▼
┌─────────────────────────────────┐
│ chatbot/actions.php              │
│  • SQL: products WHERE name LIKE │
│  • Sepet/favori aksiyonları      │
│  • Politika cevapları (statik)   │
└─────────────────────────────────┘
      │
      ▼
┌─────────────────────────────────┐
│ chatbot/responses.php            │
│  • Sonuçları kullanıcı dostu     │
│    HTML/JSON yanıta dönüştür     │
└─────────────────────────────────┘
      │
      ▼
$_SESSION['chatbot_memory'] güncellenir
      │
      ▼
JSON yanıt → frontend
```

Frontend (`main.js`) yanıtı aldığında `localStorage['story_chat_signals']` içine son mesajı, intent'i, çıkarılan entity'leri ve kullanıcı profili sinyallerini yazar. Bu sinyaller bir sonraki ziyarette **kişiselleştirilmiş anasayfa**ya yansır.

---

## 7. Kişiselleştirilmiş Anasayfa

**Dosyalar:** `personalized_home_api.php` · `assets/js/main.js` · `index.php`

### Akış

1. Sayfa yüklenince `main.js` → `loadPersonalizedHome()`:
   - `localStorage`'tan son görüntülenenler, favoriler, sepet ve chat sinyallerini toplar.
   - POST `personalized_home_api.php`'ye yollar.
2. Backend:
   - Client sinyalleri + `$_SESSION['chatbot_memory']` + `$_SESSION['user_profile']`'ı birleştirir.
   - **Median fiyat** ile bütçe ipucu çıkarır.
   - **Sezon kelimeleri** ekler (ay bazlı: yaz/kış).
   - Tarama/favori/sepet'teki ürünleri **hariç tutar** (zaten bilinenler).
   - SQL'i kategoriler, anahtar kelime LIKE'ları ve fiyat aralığıyla şekillendirir.
   - Sonuç boşsa rastgele ürünlerle doldurur (fallback).
3. Backend ayrıca dinamik bir **Türkçe açıklama** üretir:
   > "Sepetinizdeki spor ürünlere bakarak benzer kategoride seçtiklerim."

4. Frontend gelen ürünleri `personalizedHomeSection` içine render eder.

---

## 8. Stok Yönetimi

Sembolik değil, gerçek bir sistem:

| Senaryo | Davranış |
|---|---|
| **Sipariş tamamlandı** | Her item için `UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?` (atomik). `FOR UPDATE` ile satır kilidi. |
| **Stok yetersiz** | Transaction rollback, sipariş oluşmaz. Frontend'e net mesaj: *"Sepetinizdeki bazı ürünler stokta yok…"* |
| **Eş zamanlı yarış** | `WHERE stock_quantity >= ?` koşulu yarış'ı atomik kırıyor — kaybeden taraf hata alıyor. |
| **Sipariş iptali** | `cancel_order.php` transaction içinde `order_items`'ı okur, her birinin `quantity`'sini `stock_quantity`'ye geri ekler. |
| **Sepete ekleme** | Client-side, rezervasyon yok (kasıtlı tercih). Doğrulama checkout anında yapılır. |

Test senaryoları başarılı:
- 100 stoklu ürün → 3 adet sipariş → stok 97 → iptal → stok 100 ✓
- 2 stoklu üründen 5 talep → sipariş engellendi, transaction rollback ✓

---

## 9. Yetkilendirme ve Güvenlik

### Merkezi guard

`functions.php` içindeki iki helper:

```php
require_login(): int    // Korumalı sayfaların başına çağrılır
require_owner($ownerId, $userId): void
```

- JSON istekleri (XHR, `application/json`) için: HTTP 401 + JSON gövdesi.
- Normal sayfa istekleri için: `auth.php?return=<gelinen URL>`'ye yönlendirme.
- Login başarılı olduğunda `return` parametresine geri yönlendirir.

### Open-redirect koruması

`safe_return_url()` filtresi:
- Sadece **göreceli yollar** kabul edilir.
- `//evil.com`, `https://...`, `javascript:...` reddedilir → `index.php` fallback.

### Session güvenliği

- Login anında `session_regenerate_id(true)` → session fixation engellenir.
- Parolalar `password_hash(PASSWORD_DEFAULT)` ile bcrypt.

### Korumalı endpoint'ler

| Dosya | Korumalı mı? |
|---|---|
| `profile.php` | ✓ require_login |
| `orders.php` | ✓ require_login |
| `checkout.php` (GET + POST) | ✓ require_login |
| `cancel_order.php` | ✓ require_login |
| `wishlist.php` | Misafire boş listede gösterir (kasıtlı) |

> **Üretim için eksikler (üzerinde durulmadı):** CSRF token, rate limiting (auth.php brute-force), HTTPS-only/SameSite cookie bayrakları, logout'ta cookie clear. Bkz. §14.

---

## 10. Çoklu Dil (i18n)

### Motor

`i18n.php` — sözlük tabanlı basit motor:

```php
echo t("home.hero.new_arrivals", "New arrivals just dropped");
```

Aktif dil sırası:
1. `?lang=tr|en` query parametresi
2. `_SESSION['lang']`
3. URI'da `/tr/` veya `/en/` prefix'i
4. `DEFAULT_LANG` (en)

### Sözlükler

- `locales/tr.php` — Türkçe (~250+ anahtar)
- `locales/en.php` — İngilizce (~250+ anahtar)

### Kategori etiketleri

`localized_category_label("women")` → TR: *"Kadın"*, EN: *"Women"*.

Slug normalizasyonu (bkz. §12) sayesinde DB'de "women's clothing", "Women", "WOMEN" gibi varyasyonların hepsi aynı slug'a düşer ve doğru etiketi alır.

### TR sayfada İngilizce sızıntısı sıfır

`products.php`, `auth.php`, header, footer dahil tüm sayfa metni `t()` ile çevriliyor. Hardcoded İngilizce string kalmadı.

---

## 11. Ürün İçeri Aktarma & Backfill

### Kullanım

| URL | Ne yapar? |
|---|---|
| `import_products.php` | Fake Store API'dan ilk 20 ürünü çeker |
| `import_products.php?limit=50` | 50 ürün çeker |
| `import_products.php?source=dummy&limit=100` | DummyJSON'dan 100 ürün (description'lar zengin) |
| `import_products.php?backfill_stock=1` | Mevcut `NULL`/`0` stoklu ürünleri 100'e çeker |
| `import_products.php?backfill_subcat=1` | `sub_category` boş olanlara isim/açıklamaya bakıp slug atar |
| `import_products.php?backfill_subcat=1&force=1` | Dolu olanları da yeniden hesaplar |

### Yapılan iyileştirmeler (önceki "1054 Unknown column" hatasından bu yana)

1. Önceki `category` VARCHAR yerine doğru `category_id` foreign key kullanılıyor.
2. **`resolve_category_id()`** — API'nin dönüştürdüğü kategori isimlerini (`womens-dresses`, `mens-shirts`, `kitchen-accessories`, `fragrances`...) DB'deki 6 ana kategoriye eşler.
3. **`infer_subcategory()`** — Adı ve açıklamadan keyword-tabanlı slug çıkarımı. Site navigasyonundaki tüm slug'lar destekli.
4. **`stock_quantity = 100`** import sırasında varsayılan.
5. **`COALESCE(VALUES, products.stock_quantity)`** — manuel düzenlenmiş stoklar üzerine yazılmaz.
6. **`COALESCE(VALUES, products.sub_category)`** — backfill ile atanmış subcat re-import'ta kaybedilmez.
7. Insert vs Update sayımı: önce `SELECT` ile var olma kontrolü (çünkü `ON DUPLICATE KEY UPDATE`'in `rowCount()` davranışı güvenilmez).

---

## 12. Kategoriler ve Alt Kategoriler

### Sorun

DB'de tarihten gelen tutarsız adlar (`women's clothing`, `Jewelery`), site genelindeki temiz slug'larla (`women`, `jewelry`) çakışıyordu. Sonuç: anasayfa navında **aynı kategori iki defa görünüyordu**.

### Çözüm

`functions.php`'de iki yönlü çevirici:

```php
normalize_category_slug("Women's Clothing")   // → "women"
normalize_category_slug("Jewelery")            // → "jewelry"

db_category_aliases("women")
// → ["women", "women's clothing", "womens clothing"]
```

- **Nav (`index.php`):** DB sorgusu sonrası tüm kategori adlarını slug'a indirip birleştirir.
- **Filter (`products.php`):** Kullanıcı `?category=women` ile geldiğinde DB'deki tüm alias'larla `WHERE LOWER(category_name) IN (...)` kullanır.
- **Dropdown:** Slug'lardan benzersizleştirilmiş liste.

### Alt kategori (sub_category)

`products.sub_category` kolonu boştu → tüm alt kategori menüleri boş geliyordu. Çözüm:

1. **`get_subcategory_search_keywords($parent, $slug)`** — `functions.php`'de keyword haritası. Örn. `women → dress` slug'ı için `['dress','gown','sundress','frock','maxi']`.
2. **Backfill (`?backfill_subcat=1`)** — tüm 105 ürün için isim+açıklama'dan çıkarım: 105/105 dolduruldu.
3. Import sırasında otomatik `infer_subcategory()` çağrısı.

---

## 13. Geliştirme Yolculuğu

Bu projeyi bugünkü haline getirmek için izlenen adımlar (kronolojik):

### 1. AI Shopping Assistant + Kişiselleştirilmiş Anasayfa
- `personalized_home_api.php` oluşturuldu.
- `index.php`'ye gizli `personalizedHomeSection` bölümü eklendi.
- `main.js`'e `loadPersonalizedHome()` ve `persistChatSignalsForHome()` eklendi.
- Locale dosyalarına `home.personalized_title` vb. eklendi.

### 2. Chatbot RAG sorusu → Mevcut mimarinin doğrulanması
- Kullanıcının "RAG yapısı var mı?" sorusu üzerine analiz: standart RAG yok, regex+LLM hibrit.
- Bu seviyede RAG eklemenin proje boyutu için aşırı olduğu kararlaştırıldı.

### 3. Daha fazla ürün → Import zorluk denemeleri
- Yanlış kolon hatası: `Unknown column 'category'` → `category_id`'ye geçildi.
- "Inserted: 0 Updated: 0" → `ON DUPLICATE KEY UPDATE`'in `rowCount()` davranışı yüzünden, `SELECT` ile ön kontrol eklendi.
- "Out of Stock" sorunu → `stock_quantity = 100` default + `?backfill_stock=1`.

### 4. Sub-category çalışmıyor sorunu
- `products.php`'de var olmayan `p.sub_category` kolonuna sorgu → kolon DB'ye eklendi.
- `get_subcategory_search_keywords()` keyword tabanlı filtre yazıldı.

### 5. Git akışı (main vs newlast)
- `main`'e push, çakışmalar, `newlast` branch'ine geçiş ve geri merge denemeleri.
- Aktif branch: kullanıcının tercih ettiği son durum.

### 6. `chatbot_metrics` & `chatbot_feedback` kaldırıldı
- Kullanıcı talebiyle metrik ve feedback altyapısı tamamen silindi (kod + tablolar + UI).

### 7. Sub-category backfill (bu konuşma)
- `?backfill_subcat=1` endpoint'i eklendi → 105/105 ürün dolduruldu.

### 8. Import'ta description kaydet (bu konuşma)
- Eski insert sadece name/price/image_url içeriyordu.
- Yeni hali: description, category_id, sub_category, stock_quantity — hepsi.
- DummyJSON re-import → 0/105 description boştan 100/105 doluya.

### 9. Gerçek stok yönetimi (bu konuşma)
- `checkout.php`'ye FOR UPDATE row lock + atomik decrement.
- `cancel_order.php` transaction içinde stok geri yükleme.
- Yetersiz stokta TR/EN dostu mesaj.

### 10. Yetkilendirme merkezileştirildi (bu konuşma)
- `require_login()` helper'ı.
- `checkout.php` POST'taki `user_id ?? 1` açığı kapatıldı.
- `safe_return_url()` open-redirect filtresi.
- `session_regenerate_id(true)` login'de.

### 11. Üretime alma — env tabanlı DB (bu konuşma)
- `db.php` baştan yazıldı: `.env` parser, TCP veya socket destekli.
- `.env.example` ve `.gitignore` eklendi.

### 12. Kategori dublikasyonu + TR/EN sızıntısı (bu konuşma)
- `normalize_category_slug()` ve `db_category_aliases()` helper'ları.
- Nav'da `women + women's clothing` tek satıra düştü.
- `auth.php` baştan sona `t()`'ye sarıldı, hardcoded İngilizce kalmadı.

---

## 14. Geleceğe Yönelik Eksikler

Aşağıdakiler bu projede **yer almıyor**; üretime almak istenirse eklenmelidir:

### Güvenlik
- [ ] **CSRF token** — POST endpoint'leri same-origin'e dayanıyor.
- [ ] **Rate limiting** — `auth.php` brute-force'a açık.
- [ ] **HTTPS-only / SameSite=Strict cookie** bayrakları.
- [ ] **Logout cookie clear** — şu an sadece `session_destroy()`, tarayıcıda PHPSESSID kalıyor.
- [ ] Hata mesajlarının sanitize edilmesi (DB exception detayı sızabilir).

### Kullanıcı akışı
- [ ] **Şifre sıfırlama** — "Forgot password?" linki boş.
- [ ] **Email doğrulama** — Herhangi bir email ile kayıt mümkün.
- [ ] **Gerçek ödeme entegrasyonu** — Şu an checkout sahte (kart bilgisi alıp atıyor).

### Sipariş yönetimi
- [ ] Sipariş statü akışı: sadece `pending → cancelled`. `shipped`, `delivered` yok.
- [ ] **Sepet rezervasyonu** (TTL'li) — stok yarışı checkout'ta çözülüyor, sepete ekleme anında değil.

### Yönetim & operasyon
- [ ] **Admin paneli** — Stok / ürün / sipariş yönetimi şu an DB üzerinden.
- [ ] **Resim upload** — Tüm ürün görselleri dış URL.
- [ ] **Test (PHPUnit)** — Demo için kabul edildi, üretime alınırsa gerekli.
- [ ] **Logging / monitoring** — `error_log()` ötesinde bir altyapı yok.

---

## Lisans

Bu repo öğrenim/portföy amaçlıdır. Açık kaynak bir lisans (MIT/Apache-2.0) eklemek istersen `LICENSE` dosyası ekle.

## Katkı

- **Hata bildirimi:** GitHub Issues.
- **PR:** Lütfen önce issue açın; küçük PR'lar açıklamalı geçişlerle birleştirilir.

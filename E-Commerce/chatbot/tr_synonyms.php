<?php

/**
 * Türkçe alışveriş sorguları → DB arama terimleri (sub_category / ürün adı İngilizce).
 * extract_entities() ve search_products_advanced() bu modülü kullanır.
 */

function turkish_typo_fixes(): array
{
    return [
        'kıyafer' => 'kıyafet',
        'kiyafer' => 'kıyafet',
        'tisort' => 'tişört',
        'tısort' => 'tişört',
        'tişör' => 'tişört',
        'tisör' => 'tişört',
        'tişörleri' => 'tişört',
        'tişörler' => 'tişört',
        'tisörleri' => 'tişört',
        'ayakkabi' => 'ayakkabı',
        'ayakkabisi' => 'ayakkabı',
        'kulaklik' => 'kulaklık',
        'parfum' => 'parfüm',
        'gomlek' => 'gömlek',
        'pantalon' => 'pantolon',
        'telefonu' => 'telefon',
        'mutfak esyasi' => 'mutfak eşyası',
        'mutfak esyalari' => 'mutfak eşyası',
        'ev esyasi' => 'ev eşyası',
        'kadin' => 'kadın',
        'kiz' => 'kız',
        'cocuk' => 'çocuk',
        'sampuan' => 'şampuan',
        'yazici' => 'yazıcı',
        'sarj' => 'şarj',
        'canta' => 'çanta',
        'saat' => 'saat',
        'kolye' => 'kolye',
        'kupe' => 'küpe',
        'yuzuk' => 'yüzük',
        'bicak' => 'bıçak',
        'kasik' => 'kaşık',
        'catal' => 'çatal',
        'bardak' => 'bardak',
        'tabak' => 'tabak',
        'sehpa' => 'sehpa',
        'koltuk' => 'koltuk',
        'yorgan' => 'yorgan',
        'nevresim' => 'nevresim',
        'hali' => 'halı',
        'avize' => 'avize',
        'koşu' => 'koşu',
        'kosu' => 'koşu',
        'gurultu' => 'gürültü',
        'goster' => 'göster',
        'oner' => 'öner',
        'lutfen' => 'lütfen',
        'tesekkur' => 'teşekkür',
        'siparis' => 'sipariş',
        'iade' => 'iade',
        'odeme' => 'ödeme',
        'urun' => 'ürün',
        'urunler' => 'ürünler',
        'esyasi' => 'eşyası',
        'esya' => 'eşya',
        'kiyafet' => 'kıyafet',
        'kiyafetler' => 'kıyafetler',
        'yesil' => 'yeşil',
        'kirmizi' => 'kırmızı',
        'sari' => 'sarı',
        'turuncu' => 'turuncu',
        'mor' => 'mor',
        'pembe' => 'pembe',
        'lacivert' => 'lacivert',
        'bej' => 'bej',
        'pahali' => 'pahalı',
        'ucuz' => 'ucuz',
        'beden' => 'beden',
        'numara' => 'numara',
        'kopek' => 'köpek',
        'kedi' => 'kedi',
        'icecek' => 'içecek',
        'atistirmalik' => 'atıştırmalık',
        'gida' => 'gıda',
        'kitap' => 'kitap',
        'oyuncak' => 'oyuncak',
        'cocuk' => 'çocuk',
        'bebek' => 'bebek',
        'vitamin' => 'vitamin',
        'ilac' => 'ilaç',
        'krem' => 'krem',
        'ruj' => 'ruj',
        'makyaj' => 'makyaj',
        'spor' => 'spor',
        'kamp' => 'kamp',
        'cadir' => 'çadır',
        'bisiklet' => 'bisiklet',
        'yoga' => 'yoga',
        'fitness' => 'fitness',
        'dekor' => 'dekor',
        'mobilya' => 'mobilya',
        'mutfak' => 'mutfak',
        'tencere' => 'tencere',
        'tava' => 'tava',
        'blender' => 'blender',
        'kahve' => 'kahve',
        'cay' => 'çay',
        'su' => 'su',
        'ekmek' => 'ekmek',
        'sebze' => 'sebze',
        'meyve' => 'meyve',
        'balik' => 'balık',
        'et' => 'et',
        'tavuk' => 'tavuk',
        'yumurta' => 'yumurta',
        'peynir' => 'peynir',
        'sut' => 'süt',
        'bal' => 'bal',
        'zeytin' => 'zeytin',
        'corap' => 'çorap',
        'corabi' => 'çorabı',
        'sort' => 'şort',
        'sortu' => 'şort',
        'mayo' => 'mayo',
        'bikini' => 'bikini',
        'esofman' => 'eşofman',
        'esofmani' => 'eşofman',
        'kazak' => 'kazak',
        'hirka' => 'hırka',
        'hirka' => 'hırka',
        'mont' => 'mont',
        'ceket' => 'ceket',
        'yelek' => 'yelek',
        'kaban' => 'kaban',
        'palto' => 'palto',
        'etek' => 'etek',
        'bluz' => 'bluz',
        'elbise' => 'elbise',
        'tunik' => 'tunik',
        'sal' => 'şal',
        'sapka' => 'şapka',
        'gozluk' => 'gözlük',
        'kemer' => 'kemer',
        'cuzdan' => 'cüzdan',
        'anahtarlik' => 'anahtarlık',
        'powerbank' => 'powerbank',
        'sarj aleti' => 'şarj aleti',
        'kablo' => 'kablo',
        'usb' => 'usb',
        'mouse' => 'mouse',
        'klavye' => 'klavye',
        'monitor' => 'monitör',
        'monitör' => 'monitör',
        'ekran' => 'ekran',
        'kamera' => 'kamera',
        'fotograf' => 'fotoğraf',
        'video' => 'video',
        'oyun' => 'oyun',
        'konsol' => 'konsol',
        'playstation' => 'playstation',
        'xbox' => 'xbox',
        'nintendo' => 'nintendo',
        'tablet' => 'tablet',
        'ipad' => 'ipad',
        'iphone' => 'iphone',
        'samsung' => 'samsung',
        'huawei' => 'huawei',
        'xiaomi' => 'xiaomi',
        'lenovo' => 'lenovo',
        'asus' => 'asus',
        'dell' => 'dell',
        'hp' => 'hp',
        'acer' => 'acer',
        'macbook' => 'macbook',
        'laptop' => 'laptop',
        'notebook' => 'notebook',
        'bilgisayar' => 'bilgisayar',
        'pc' => 'pc',
        'televizyon' => 'televizyon',
        'hoparlor' => 'hoparlör',
        'hoparlör' => 'hoparlör',
        'soundbar' => 'soundbar',
        'mikrofon' => 'mikrofon',
        'webcam' => 'webcam',
        'yazici' => 'yazıcı',
        'toner' => 'toner',
        'kartus' => 'kartuş',
        'kartuş' => 'kartuş',
        'drone' => 'drone',
        'akilli saat' => 'akıllı saat',
        'akıllı saat' => 'akıllı saat',
        'smartwatch' => 'smartwatch',
        'fitness band' => 'fitness band',
        'bileklik' => 'bileklik',
        'kolye' => 'kolye',
        'bilezik' => 'bilezik',
        'kupe' => 'küpe',
        'yuzuk' => 'yüzük',
        'taki' => 'takı',
        'takı' => 'takı',
        'mucevher' => 'mücevher',
        'mücevher' => 'mücevher',
        'altin' => 'altın',
        'gumus' => 'gümüş',
        'gümüş' => 'gümüş',
        'altın' => 'altın',
        'rolex' => 'rolex',
        'kol saati' => 'kol saati',
        'saat' => 'saat',
        'canta' => 'çanta',
        'sırt çantası' => 'sırt çantası',
        'sirt cantasi' => 'sırt çantası',
        'valiz' => 'valiz',
        'bavul' => 'bavul',
        'cüzdan' => 'cüzdan',
        'cuzdan' => 'cüzdan',
        'kopek mamasi' => 'köpek maması',
        'köpek maması' => 'köpek maması',
        'kedi mamasi' => 'kedi maması',
        'kedi maması' => 'kedi maması',
        'mama' => 'mama',
        'kum' => 'kum',
        'tasma' => 'tasma',
        'kemirgen' => 'kemirgen',
        'kus' => 'kuş',
        'balik yemi' => 'balık yemi',
        'bitki' => 'bitki',
        'saksı' => 'saksı',
        'saksi' => 'saksı',
        'tohum' => 'tohum',
        'gubre' => 'gübre',
        'gübre' => 'gübre',
        'bahce' => 'bahçe',
        'bahçe' => 'bahçe',
        'bahce aleti' => 'bahçe aleti',
        'cekic' => 'çekiç',
        'tornavida' => 'tornavida',
        'matkap' => 'matkap',
        'testere' => 'testere',
        'boya' => 'boya',
        'firca' => 'fırça',
        'fırça' => 'fırça',
        'kagit' => 'kağıt',
        'kağıt' => 'kağıt',
        'zımba' => 'zımba',
        'zimba' => 'zımba',
        'dosya' => 'dosya',
        'klasor' => 'klasör',
        'klasör' => 'klasör',
        'kalem' => 'kalem',
        'defter' => 'defter',
        'silgi' => 'silgi',
        'cetvel' => 'cetvel',
        'makas' => 'makas',
        'yapistirici' => 'yapıştırıcı',
        'yapıştırıcı' => 'yapıştırıcı',
        'bant' => 'bant',
        'canta' => 'çanta',
    ];
}

/**
 * Para birimi → USD kuru (DB fiyatları USD).
 */
function budget_currency_to_usd_rate(string $currency): float
{
    return match (strtoupper(trim($currency))) {
        'EUR' => 1.08,
        'TRY' => 0.032,
        default => 1.0,
    };
}

function normalize_budget_currency_token(string $token, string $context): string
{
    $t = to_lower(trim($token));
    if (in_array($t, ['€', 'eur', 'euro', 'euros'], true)) {
        return 'EUR';
    }
    if (in_array($t, ['tl', '₺', 'try'], true)) {
        return 'TRY';
    }
    if (in_array($t, ['$', 'usd', 'dollar', 'dollars', 'dolar'], true)) {
        return 'USD';
    }
    if (preg_match('/\b(euro|eur|€)\b/ui', $context)) {
        return 'EUR';
    }
    if (preg_match('/\b(tl|₺|try)\b/ui', $context)) {
        return 'TRY';
    }
    if (preg_match('/\b(usd|dollar|dolar|\$)\b/ui', $context)) {
        return 'USD';
    }
    return 'USD';
}

/**
 * @return array{max_amount:?float,min_amount:?float,currency:string,max_price_usd:?float,min_price_usd:?float}
 */
function parse_shopping_budget(string $rawMessage): array
{
    $result = [
        'max_amount' => null,
        'min_amount' => null,
        'currency' => 'USD',
        'max_price_usd' => null,
        'min_price_usd' => null,
    ];

    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(€|eur|euro|euros|tl|₺|try|\$|usd|dollar|dollars|dolar)?\s*(alt[ıi]|altinda|altında|alti|under|below|den\s+ucuz|dan\s+ucuz)/ui', $rawMessage, $m)
        || preg_match('/(\d+(?:[.,]\d+)?)(€)\s*(alt[ıi]|altinda|altında|alti)/ui', $rawMessage, $m)) {
        $result['max_amount'] = (float) str_replace(',', '.', $m[1]);
        $result['currency'] = normalize_budget_currency_token((string) ($m[2] ?? ''), $rawMessage);
    } elseif (preg_match('/(?:alt[ıi]|altinda|altında|under|below)\s*(\d+(?:[.,]\d+)?)\s*(€|eur|euro|tl|₺|try|\$|usd|dollar|dolar)?/ui', $rawMessage, $m)) {
        $result['max_amount'] = (float) str_replace(',', '.', $m[1]);
        $result['currency'] = normalize_budget_currency_token((string) ($m[2] ?? ''), $rawMessage);
    } elseif (preg_match('/under\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m) || preg_match('/below\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) {
        $result['max_amount'] = (float) $m[1];
        $result['currency'] = normalize_budget_currency_token('', $rawMessage);
    }

    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(€|eur|euro|tl|₺|try|\$|usd|dollar)?\s*(ust[üu]|üstü|ustu|uzeri|üzeri|over|above|den\s+pahalı|dan\s+pahali)/ui', $rawMessage, $m)) {
        $result['min_amount'] = (float) str_replace(',', '.', $m[1]);
        $result['currency'] = normalize_budget_currency_token((string) ($m[2] ?? ''), $rawMessage);
    } elseif (preg_match('/over\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m) || preg_match('/above\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) {
        $result['min_amount'] = (float) $m[1];
        $result['currency'] = normalize_budget_currency_token('', $rawMessage);
    }

    if ($result['max_amount'] !== null) {
        $result['max_price_usd'] = round($result['max_amount'] * budget_currency_to_usd_rate($result['currency']), 2);
    }
    if ($result['min_amount'] !== null) {
        $result['min_price_usd'] = round($result['min_amount'] * budget_currency_to_usd_rate($result['currency']), 2);
    }

    return $result;
}

function normalize_turkish_shopping_query(string $rawMessage): string
{
    $text = $rawMessage;
    foreach (turkish_typo_fixes() as $from => $to) {
        $text = preg_replace('/\b' . preg_quote($from, '/') . '\b/ui', $to, $text) ?? $text;
    }

    // Türkçe ekler: tişörtü → tişört, elbiseyi → elbise, ayakkabılar → ayakkabı
    $inflections = [
        '/\b(tişört|tisort|tişör|tisör)(?:ü|u|ler|leri|ları|lari|yı|yi)\b/ui' => 'tişört',
        '/\b(elbise)(?:yi|leri|ları|lari|ler)\b/ui' => '$1',
        '/\b(ayakkabı|ayakkabi)(?:yı|yi|lar|leri|ları|lari)\b/ui' => '$1',
        '/\b(gömlek|gomlek)(?:i|leri|ları|lari|ler)\b/ui' => '$1',
        '/\b(pantolon)(?:u|lar|ları|lari|leri)\b/ui' => '$1',
        '/\b(etek)(?:i|ler|leri|ları|lari)\b/ui' => '$1',
        '/\b(çanta|canta)(?:yı|yi|lar|ları|lari|leri)\b/ui' => '$1',
        '/\b(kulaklık|kulaklik)(?:ı|i|lar|ları|lari|leri)\b/ui' => '$1',
    ];
    foreach ($inflections as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text) ?? $text;
    }

    return $text;
}

/**
 * Öncelikli konu kuralları (ilk eşleşen category_like / product_type belirler).
 *
 * @return array<int, array{pattern:string,category_like?:string,product_type?:string,audience?:string,keywords?:array<int,string>,features?:array<int,string>}>
 */
function turkish_product_topic_rules(): array
{
    return [
        ['pattern' => '/\b(cook(?:ing)?\s+(?:for\s+)?(?:dinner|lunch|breakfast|a\s+meal|tonight|today)|(?:want|need)\s+to\s+cook|(?:make|prepare|plan)\s+(?:dinner|lunch|breakfast|a\s+meal|food)|(?:dinner|lunch|breakfast|meal)\s+(?:ingredients|shopping|groceries)|akşam\s+yemeği|aksam\s+yemegi|yemek\s+yap|market\s+alışveriş)\b/ui', 'category_like' => 'food', 'product_type' => 'snacks'],
        ['pattern' => '/\b(mutfak\s+eşya\w*|mutfak\s+alet\w*|mutfak\s+ürün\w*|mutfak\s+urun\w*)\b/ui', 'category_like' => 'kitchen', 'product_type' => 'kitchen', 'keywords' => ['kitchen', 'cookware']],
        ['pattern' => '/\b(pişirme\s+malzemeler\w*|pisirme\s+malzemeler\w*)\b/ui', 'category_like' => 'kitchen', 'product_type' => 'kitchen', 'keywords' => ['kitchen', 'cookware']],
        ['pattern' => '/\b(mutfak|tencere|tava|wok|blender|tost\s+makinesi|mikser|rende|süzgeç|suzgec|kepçe|spatula|çaydanlık|caydanlik|demlik|fincan|bardak|tabak|çatal|catal|kaşık|kasik|bıçak|bicak|tupperware|saklama\s+kap\w*)\b/ui', 'category_like' => 'kitchen', 'product_type' => 'kitchen', 'keywords' => ['kitchen']],
        ['pattern' => '/\b(ev\s+eşya\w*|ev\s+esya\w*|ev\s+dekor\w*|ev\s+tekstil\w*)\b/ui', 'category_like' => 'home', 'product_type' => 'furniture', 'keywords' => ['home', 'furniture']],
        ['pattern' => '/\b(mobilya|koltuk|kanepe|sehpa|masa|sandalye|dolap|gardırop|gardirob|komodin|kitaplık|kitaplik|yatak|yatak\s+odası|yatak\s+odasi|nevresim|yorgan|battaniye|yastık|yastik|halı|hali|kilim|perde|avize|lamba|ayna|vazo)\b/ui', 'category_like' => 'home', 'product_type' => 'furniture', 'keywords' => ['furniture', 'decor', 'bedding']],
        ['pattern' => '/\b(parfüm|parfum|fragrance|cologne|eau\s+de|kozmetik|makyaj|ruj|rimel|fondöten|fondoten|allık|allik|oje|tırnak|tirnak|cilt\s+bakım\w*|cilt\s+bakim\w*|nemlendirici|serum|güneş\s+krem\w*|gunes\s+krem\w*|temizleyici|tonik|peeling)\b/ui', 'category_like' => 'beauty', 'product_type' => 'perfume', 'keywords' => ['perfume', 'makeup', 'skincare']],
        ['pattern' => '/\b(şampuan|sampuan|saç\s+bakım\w*|sac\s+bakim\w*|saç\s+kremi|sac\s+kremi|saç\s+spreyi|sac\s+spreyi|fön\s+makinesi|fon\s+makinesi|düzleştirici|duzlestirici)\b/ui', 'category_like' => 'beauty', 'product_type' => 'hair', 'keywords' => ['hair', 'shampoo']],
        ['pattern' => '/\b(kulaklık|kulaklik|headphone|headphones|earbuds|earphone|airpods|kulak\s+üstü|kulak\s+ustu|kablosuz\s+kulaklık|kablosuz\s+kulaklik)\b/ui', 'category_like' => 'headphone', 'product_type' => 'headphone', 'keywords' => ['headphone', 'earbud'], 'features' => ['wireless']],
        ['pattern' => '/\b(kablosuz|bluetooth|wireless)\b/ui', 'keywords' => ['wireless'], 'features' => ['wireless']],
        ['pattern' => '/\b(akıllı\s+saat|akilli\s+saat|smartwatch|apple\s+watch|fitness\s+tracker|bileklik\s+saat)\b/ui', 'category_like' => 'smartwatch', 'product_type' => 'smartwatch', 'keywords' => ['smartwatch', 'watch']],
        ['pattern' => '/\b(kol\s+saati|saat|rolex|mücevher\s+saat|mucevher\s+saat|timepiece)\b/ui', 'category_like' => 'watches', 'product_type' => 'watches', 'keywords' => ['watch', 'watches']],
        ['pattern' => '/\b(takı|taki|küpe|kupe|kolye|yüzük|yuzuk|bilezik|bileklik|mücevher|mucevher|jewelry|jewellery)\b/ui', 'category_like' => 'jewelry', 'product_type' => 'rings', 'keywords' => ['jewelry', 'ring', 'necklace', 'earring']],
        ['pattern' => '/\b(kadın\s+tişört\w*|kadın\s+tisort\w*|kadın\s+tişör\w*|bayan\s+tişört\w*|bayan\s+tisort\w*|women\s+t\s*shirt\w*|women\s+tshirt\w*)\b/ui', 'category_like' => 'shirt', 'product_type' => 'shirt', 'audience' => 'women', 'keywords' => ['shirt', 'tshirt', 'women']],
        ['pattern' => '/\b((?:beden(?:i)?|numara)\s+(?:xxs|xs|s|m|l|xl|xxl|2xl|3xl)\s+olan\s+)?(?:kadın|bayan)\s+tişört\w*\b/ui', 'category_like' => 'shirt', 'product_type' => 'shirt', 'audience' => 'women', 'keywords' => ['shirt', 'tshirt', 'women']],
        ['pattern' => '/\b(erkek\s+tişört\w*|erkek\s+tisort\w*|men\s+t\s*shirt\w*|men\s+tshirt\w*)\b/ui', 'category_like' => 'shirt', 'product_type' => 'shirt', 'audience' => 'men', 'keywords' => ['shirt', 'tshirt', 'men']],
        ['pattern' => '/\b(kadın\s+elbise\w*|bayan\s+elbise\w*|kadın\s+giyim|bayan\s+giyim)\b/ui', 'category_like' => 'dress', 'product_type' => 'dress', 'audience' => 'women', 'keywords' => ['dress']],
        ['pattern' => '/\b(elbise|gown|sundress|maxi\s+elbise)\b/ui', 'category_like' => 'dress', 'product_type' => 'dress', 'keywords' => ['dress']],
        ['pattern' => '/\b(bluz|blouse|crop\s+top|tunik)\b/ui', 'category_like' => 'blouse', 'product_type' => 'blouse', 'keywords' => ['blouse']],
        ['pattern' => '/\b(etek|skirt)\b/ui', 'category_like' => 'skirts', 'product_type' => 'skirts', 'keywords' => ['skirt']],
        ['pattern' => '/\b(kadın\s+ayakkabı\w*|bayan\s+ayakkabı\w*|kadın\s+ayakkabi\w*|topuklu|stiletto)\b/ui', 'category_like' => 'women-shoes', 'product_type' => 'shoe', 'audience' => 'women', 'keywords' => ['women-shoes', 'heel']],
        ['pattern' => '/\b(erkek\s+ayakkabı\w*|erkek\s+ayakkabi\w*|erkek\s+spor\s+ayakkabı\w*)\b/ui', 'category_like' => 'men-shoes', 'product_type' => 'shoe', 'audience' => 'men', 'keywords' => ['men-shoes', 'sneaker']],
        ['pattern' => '/\b(ayakkabı|ayakkabi|sneaker|spor\s+ayakkabı|spor\s+ayakkabi|koşu\s+ayakkabı\w*|kosu\s+ayakkabi\w*|bot|sandalet|terlik|klasik\s+ayakkabı\w*|klasik\s+ayakkabi\w*)\b/ui', 'category_like' => 'shoe', 'product_type' => 'shoe', 'keywords' => ['shoe', 'sneaker', 'men-shoes']],
        ['pattern' => '/\b(tişört\w*|tisort\w*|t-shirt\w*|tshirt\w*)\b/ui', 'category_like' => 'shirt', 'product_type' => 'shirt', 'keywords' => ['shirt', 'tshirt']],
        ['pattern' => '/\b(gömlek|gomlek|shirt|shirts)\b/ui', 'category_like' => 'shirt', 'product_type' => 'shirt', 'keywords' => ['shirt']],
        ['pattern' => '/\b(pantolon|jean|kot|trouser|chino|jogger|şort|sort|eşofman|esofman)\b/ui', 'category_like' => 'pants', 'product_type' => 'pants', 'keywords' => ['pants', 'jean']],
        ['pattern' => '/\b(ceket|mont|kaban|palto|yelek|blazer|hoodie|sweatshirt|kazak|hırka|hirka|parka|bomber)\b/ui', 'category_like' => 'jacket', 'product_type' => 'jacket', 'keywords' => ['jacket', 'coat', 'hoodie']],
        ['pattern' => '/\b(kıyafet|kiyafet|giyim|clothing|apparel|kıyafetler|kiyafetler|moda)\b/ui', 'category_like' => 'clothing', 'product_type' => 'clothing', 'keywords' => ['clothing', 'shirt', 'dress']],
        ['pattern' => '/\b(çanta|canta|sırt\s+çantası|sirt\s+cantasi|backpack|handbag|clutch|valiz|bavul|cüzdan|cuzdan|kemer|şapka|sapka|gözlük|gozluk|atkı|atki|eldiven)\b/ui', 'category_like' => 'bags', 'product_type' => 'bags', 'keywords' => ['bag', 'backpack', 'wallet']],
        ['pattern' => '/\b(cep\s+telefonu|akıllı\s+telefon|akilli\s+telefon|telefon|smartphone|iphone|samsung\s+galaxy|android\s+telefon)\b/ui', 'category_like' => 'phone', 'product_type' => 'phone', 'keywords' => ['phone', 'smartphone']],
        ['pattern' => '/\b(laptop|notebook|bilgisayar|macbook|chromebook|tablet|ipad|monitör|monitor|klavye|keyboard|mouse|ssd|hard\s+drive)\b/ui', 'category_like' => 'computer-tablet', 'product_type' => 'computer-tablet', 'keywords' => ['laptop', 'tablet', 'computer-tablet']],
        ['pattern' => '/\b(televizyon|tv|oled|qled|smart\s+tv)\b/ui', 'category_like' => 'tv', 'product_type' => 'tv', 'keywords' => ['tv', 'television']],
        ['pattern' => '/\b(hoparlör|hoparlor|speaker|soundbar|subwoofer)\b/ui', 'category_like' => 'speakers', 'product_type' => 'speakers', 'keywords' => ['speaker', 'soundbar']],
        ['pattern' => '/\b(kamera|camera|dslr|mirrorless|lens|webcam|fotoğraf\s+makinesi|fotograf\s+makinesi)\b/ui', 'category_like' => 'camera', 'product_type' => 'camera', 'keywords' => ['camera', 'lens']],
        ['pattern' => '/\b(yazıcı|yazici|printer|toner|kartuş|kartus|mürekkep|murekkep)\b/ui', 'category_like' => 'printer', 'product_type' => 'printer', 'keywords' => ['printer']],
        ['pattern' => '/\b(şarj|sarj|charger|power\s*bank|powerbank|kablo|usb|adaptör|adaptor|kılıf|kilif|kapak)\b/ui', 'category_like' => 'gadgets-accessories', 'product_type' => 'gadgets-accessories', 'keywords' => ['charger', 'cable', 'usb']],
        ['pattern' => '/\b(elektronik|electronics|elektrikli\s+alet\w*)\b/ui', 'category_like' => 'electronics', 'product_type' => 'electronics', 'keywords' => ['electronics']],
        ['pattern' => '/\b(koşu|kosu|running|maraton|jogging)\b/ui', 'category_like' => 'running', 'product_type' => 'running', 'keywords' => ['running', 'sneaker']],
        ['pattern' => '/\b(bisiklet|bike|bicycle|cycling|cycle)\b/ui', 'category_like' => 'cycling', 'product_type' => 'cycling', 'keywords' => ['bike', 'cycling']],
        ['pattern' => '/\b(fitness|gym|dambıl|dambil|kettlebell|yoga|pilates|spor\s+salonu|spor\s+ekipman\w*)\b/ui', 'category_like' => 'fitness', 'product_type' => 'fitness', 'keywords' => ['fitness', 'gym', 'yoga']],
        ['pattern' => '/\b(kamp|çadır|cadir|tent|hiking|trekking|outdoor|dağcılık|dagcilik)\b/ui', 'category_like' => 'outdoor', 'product_type' => 'outdoor', 'keywords' => ['outdoor', 'camping', 'tent']],
        ['pattern' => '/\b(oyuncak|toy|lego|peluş|pelus|bebek\s+oyuncak|action\s+figure|puzzle|yapboz)\b/ui', 'category_like' => 'kids-toys', 'product_type' => 'kids-toys', 'keywords' => ['toy', 'puzzle']],
        ['pattern' => '/\b(çocuk\s+kıyafet|cocuk\s+kiyafet|bebek\s+kıyafet|bebek\s+kiyafet|kids\s+clothing)\b/ui', 'category_like' => 'kids-clothing', 'product_type' => 'kids-clothing', 'keywords' => ['kids-clothing', 'baby']],
        ['pattern' => '/\b(kitap|book|roman|novel|hikaye|ders\s+kitabı|ders\s+kitabi|textbook)\b/ui', 'category_like' => 'fiction', 'product_type' => 'fiction', 'keywords' => ['book', 'fiction']],
        ['pattern' => '/\b(köpek\s+maması|kopek\s+mamasi|dog\s+food|kedi\s+maması|kedi\s+mamasi|cat\s+food|pet\s+food|mama)\b/ui', 'category_like' => 'pet-food', 'product_type' => 'pet-food', 'keywords' => ['pet-food', 'dog food', 'cat food']],
        ['pattern' => '/\b(köpek|kopek|kedi|pet|puppy|kitten)\b/ui', 'category_like' => 'pet', 'product_type' => 'dog', 'keywords' => ['pet', 'dog', 'cat']],
        ['pattern' => '/\b(gıda|gida|atıştırmalık|atistirmalik|snack|içecek|icecek|beverage|kahve|çay|cay|tea|coffee|meyve|sebze|balık|balik|et|tavuk)\b/ui', 'category_like' => 'snacks', 'product_type' => 'snacks', 'keywords' => ['snacks', 'beverages', 'gourmet']],
        ['pattern' => '/\b(vitamin|supplement|takviye|ilaç|ilac|medical|wellness|sağlık|saglik)\b/ui', 'category_like' => 'vitamins', 'product_type' => 'vitamins', 'keywords' => ['vitamin', 'supplement']],
        ['pattern' => '/\b(bebe|bebek|diaper|bez|biberon|emzik|stroller|puset)\b/ui', 'category_like' => 'baby-care', 'product_type' => 'baby-care', 'keywords' => ['baby', 'diaper']],
        ['pattern' => '/\b(bitki|saksı|saksi|tohum|bahçe|bahce|garden|çiçek|cicek|plant)\b/ui', 'category_like' => 'outdoor-plants', 'product_type' => 'outdoor-plants', 'keywords' => ['plant', 'garden']],
        ['pattern' => '/\b(kalem|defter|notebook|stationery|kağıt|kagit|zımba|zimba|dosya|klasör|klasor|ofis)\b/ui', 'category_like' => 'stationery', 'product_type' => 'stationery', 'keywords' => ['stationery', 'pen', 'notebook']],
        ['pattern' => '/\b(oyun\s+konsolu|konsol|playstation|xbox|nintendo|gaming|oyuncu)\b/ui', 'category_like' => 'electronics', 'product_type' => 'electronics', 'keywords' => ['gaming'], 'features' => ['gaming']],
        ['pattern' => '/\b(gürültü\s+engelle\w*|gurultu\s+engelle\w*|noise\s+cancell\w*|anc)\b/ui', 'features' => ['noise_cancelling']],
        ['pattern' => '/\b(mikrofon|microphone|mic)\b/ui', 'features' => ['microphone']],
    ];
}

/**
 * Tek kelime / kısa ifade → DB arama terimleri.
 *
 * @return array<string, array<int, string>>
 */
function turkish_token_search_map(): array
{
    return [
        'ayakkabı' => ['shoe', 'sneaker', 'men-shoes', 'women-shoes'],
        'sneaker' => ['sneaker', 'men-shoes'],
        'koşu' => ['running', 'sneaker'],
        'tişört' => ['shirt', 'tshirt'],
        'gömlek' => ['shirt'],
        'elbise' => ['dress'],
        'etek' => ['skirt', 'skirts'],
        'pantolon' => ['pants', 'jean'],
        'ceket' => ['jacket', 'coat'],
        'mont' => ['jacket', 'parka'],
        'kazak' => ['sweater', 'hoodie'],
        'kıyafet' => ['clothing', 'shirt', 'dress'],
        'giyim' => ['clothing'],
        'çanta' => ['bag', 'backpack'],
        'saat' => ['watch', 'watches'],
        'takı' => ['jewelry', 'ring', 'necklace'],
        'küpe' => ['earring', 'earrings'],
        'kolye' => ['necklace', 'necklaces'],
        'yüzük' => ['ring', 'rings'],
        'telefon' => ['phone', 'smartphone'],
        'laptop' => ['laptop', 'computer-tablet'],
        'bilgisayar' => ['laptop', 'computer-tablet'],
        'tablet' => ['tablet', 'computer-tablet'],
        'kulaklık' => ['headphone', 'earbud', 'headphones'],
        'hoparlör' => ['speaker', 'speakers'],
        'televizyon' => ['tv', 'television'],
        'kamera' => ['camera'],
        'yazıcı' => ['printer'],
        'mutfak' => ['kitchen'],
        'tencere' => ['pot', 'kitchen'],
        'tava' => ['pan', 'kitchen'],
        'mobilya' => ['furniture'],
        'dekor' => ['decor'],
        'yatak' => ['bedding'],
        'parfüm' => ['perfume', 'fragrance'],
        'makyaj' => ['makeup'],
        'şampuan' => ['shampoo', 'hair'],
        'spor' => ['sports', 'fitness'],
        'bisiklet' => ['bike', 'cycling'],
        'köpek' => ['dog', 'pet'],
        'kedi' => ['cat', 'pet'],
        'mama' => ['pet-food', 'dog food'],
        'kitap' => ['book', 'fiction'],
        'oyuncak' => ['toy', 'kids-toys'],
        'bebek' => ['baby', 'baby-care'],
        'vitamin' => ['vitamin', 'vitamins'],
        'kahve' => ['coffee', 'beverages'],
        'çay' => ['tea', 'beverages'],
        'içecek' => ['beverage', 'beverages'],
        'atıştırmalık' => ['snack', 'snacks'],
        'gıda' => ['snacks', 'gourmet'],
        'kalem' => ['pen', 'stationery'],
        'defter' => ['notebook', 'stationery'],
        'bitki' => ['plant', 'outdoor-plants'],
        'bahçe' => ['garden', 'outdoor-plants'],
        'kadın' => ['women'],
        'erkek' => ['men'],
        'kablosuz' => ['wireless'],
        'hediye' => ['gift'],
    ];
}

function turkish_search_stop_words(): array
{
    return [
        'i', 'need', 'under', 'over', 'show', 'me', 'for', 'the', 'a', 'an', 'to', 'my', 've', 'ile', 'olan', 'olanları', 'göster', 'bana',
        'öner', 'öneri', 'önersene', 'recommend', 'suggest', 'suggestion', 'please', 'lütfen',
        'almak', 'istiyorum', 'istiyor', 'lazım', 'var', 'yok', 'some', 'any', 'bir', 'buy', 'want',
        'eşyası', 'eşya', 'ürün', 'ürünler', 'urun', 'urunler', 'malzemeler', 'malzeme', 'alet', 'aletler',
        'gibi', 'için', 'icin', 'olan', 'olanlar', 'hangi', 'nasıl', 'nasil', 'ne', 'biraz', 'çok', 'cok',
        'en', 'iyi', 'güzel', 'guzel', 'uygun', 'popüler', 'populer', 'yeni', 'eski', 'marka', 'model',
        'arıyorum', 'ariyorum', 'bakıyorum', 'bakiyorum', 'bul', 'ara', 'satın', 'satin', 'al', 'alsam',
        'hediye', 'lazim', 'lutfen', 'tesekkur', 'merhaba', 'selam',
        'sadece', 'only', 'just', 'olsun',
        'euro', 'eur', 'altında', 'altinda', 'alti', 'altı', 'under', 'below',
        'cheaper', 'affordable', 'alternatives', 'alternative', 'alternatif', 'alternatifler',
        'product', 'products',
        'order', 'orders', 'ordering', 'sipariş', 'siparis', 'siparişi', 'siparisi',
        'best', 'seller', 'sellers', 'selling', 'popular', 'satan', 'satanlar', 'satanları',
        'cook', 'cooking', 'cooked', 'dinner', 'lunch', 'breakfast', 'meal', 'meals', 'tonight',
        'prepare', 'yemek', 'akşam', 'aksam', 'yemeği', 'yemegi', 'pişir', 'pisir',
    ];
}

function is_meal_cooking_shopping_query(string $message): bool
{
    $text = to_lower(trim($message));
    if ($text === '') {
        return false;
    }

    return (bool) preg_match(
        '/\b(?:'
        . 'cook(?:ing)?\s+(?:for\s+)?(?:dinner|lunch|breakfast|a\s+meal|tonight|today)'
        . '|(?:want|need)\s+to\s+cook'
        . '|(?:make|prepare|plan)\s+(?:dinner|lunch|breakfast|a\s+meal|food|something\s+to\s+eat)'
        . '|(?:dinner|lunch|breakfast|meal)\s+(?:ingredients|shopping|groceries|ideas)'
        . '|(?:what\s+(?:do\s+i\s+need|should\s+i\s+buy)\s+(?:for\s+)?(?:dinner|cooking|a\s+meal))'
        . '|(?:akşam|aksam)\s+yemeği'
        . '|yemek\s+yap'
        . '|pişir(?:eceğim|ecegim|mek\s+istiyorum|mek\s+lazim|iyorum)'
        . '|market\s+alışveriş'
        . '|grocery\s+shopping'
        . ')\b/ui',
        $text
    );
}

/**
 * "Cook for dinner" gibi niyet cümlelerinde "cook" → "cooking oil" yanlış eşleşmesini önler.
 *
 * @param array<string, mixed> $entities
 * @return array<string, mixed>
 */
function apply_meal_cooking_entities(array $entities, string $rawMessage): array
{
    if (!is_meal_cooking_shopping_query($rawMessage)) {
        return $entities;
    }

    $entities['_meal_cooking_search'] = true;
    $entities['category_like'] = 'food';
    $entities['product_type'] = 'snacks';

    $noise = [
        'cook', 'cooking', 'cooked', 'dinner', 'lunches', 'lunch', 'breakfast', 'meal', 'meals',
        'tonight', 'today', 'want', 'prepare', 'make', 'plan', 'food', 'for', 'something', 'eat',
        'ingredients', 'shopping', 'groceries', 'ideas', 'need', 'buy',
    ];
    $keywords = is_array($entities['keywords'] ?? null) ? $entities['keywords'] : [];
    $entities['keywords'] = array_values(array_filter($keywords, static function ($kw) use ($noise): bool {
        $lower = to_lower(trim((string) $kw));
        return $lower !== '' && !in_array($lower, $noise, true);
    }));

    return $entities;
}

function turkish_footwear_terms(): array
{
    return [
        'ayakkabı', 'ayakkabi', 'sneaker', 'sneakers', 'shoe', 'shoes', 'koşu', 'kosu',
        'bot', 'sandalet', 'terlik', 'men-shoes', 'women-shoes', 'cleat', 'cleats', 'trainer', 'trainers',
    ];
}

/**
 * @param array<string, mixed> $entities
 * @return array<string, mixed>
 */
function enrich_entities_from_turkish(string $text, array $entities): array
{
    $text = to_lower($text);

    foreach (turkish_product_topic_rules() as $rule) {
        if (!preg_match($rule['pattern'], $text)) {
            continue;
        }
        if (!empty($rule['category_like']) && empty($entities['category_like'])) {
            $entities['category_like'] = $rule['category_like'];
        }
        if (!empty($rule['product_type']) && empty($entities['product_type'])) {
            $entities['product_type'] = $rule['product_type'];
        }
        if (!empty($rule['audience']) && empty($entities['audience'])) {
            $entities['audience'] = $rule['audience'];
        }
        if (!empty($rule['keywords']) && is_array($rule['keywords'])) {
            foreach ($rule['keywords'] as $kw) {
                $entities['keywords'][] = $kw;
            }
        }
        if (!empty($rule['features']) && is_array($rule['features'])) {
            foreach ($rule['features'] as $feature) {
                $entities['features'][] = $feature;
            }
        }
    }

    $audienceOnly = function_exists('is_audience_correction_message')
        && is_audience_correction_message($text);

    if (preg_match('/\b(erkek|men|male)\b/ui', $text)) {
        $entities['audience'] = 'men';
        if (!$audienceOnly
            && (empty($entities['category_like']) || in_array($entities['category_like'], ['clothing', 'men'], true))) {
            $entities['category_like'] = 'men';
        }
    } elseif (preg_match('/\b(kadın|kadin|women|female|bayan)\b/ui', $text)) {
        $entities['audience'] = 'women';
        if (!$audienceOnly
            && (empty($entities['category_like']) || in_array($entities['category_like'], ['clothing', 'women'], true))) {
            $entities['category_like'] = 'women';
        }
    }

    if ($audienceOnly) {
        $entities['_audience_correction'] = true;
        $entities['_strict_audience'] = true;
    }

    $entities['keywords'] = array_values(array_unique(is_array($entities['keywords'] ?? null) ? $entities['keywords'] : []));
    $entities['features'] = array_values(array_unique(is_array($entities['features'] ?? null) ? $entities['features'] : []));

    return $entities;
}

/**
 * Anahtar kelimeleri DB'de eşleşecek İngilizce / slug terimlere genişletir.
 *
 * @param array<int, string> $keywords
 * @return array<int, string>
 */
function expand_turkish_search_keywords(array $keywords, ?string $categoryLike = null, ?string $productType = null): array
{
    $map = turkish_token_search_map();
    $out = [];

    foreach ([$categoryLike, $productType] as $ctx) {
        if ($ctx === null || $ctx === '') {
            continue;
        }
        $key = to_lower($ctx);
        $out[] = $ctx;
        if (isset($map[$key])) {
            foreach ($map[$key] as $term) {
                $out[] = $term;
            }
        }
    }

    foreach ($keywords as $kw) {
        $kw = trim((string) $kw);
        if ($kw === '') {
            continue;
        }
        $out[] = $kw;
        $lower = to_lower($kw);
        if (isset($map[$lower])) {
            foreach ($map[$lower] as $term) {
                $out[] = $term;
            }
        }
    }

    return array_values(array_unique($out));
}

/**
 * Soyut category_like / product_type → DB'deki sub_category ve kategori adları.
 *
 * @return array<int, string>
 */
function category_like_db_search_terms(?string $categoryLike, ?string $productType = null, ?string $audience = null): array
{
    $terms = [];
    foreach ([$categoryLike, $productType] as $raw) {
        if ($raw === null || $raw === '') {
            continue;
        }
        $key = to_lower((string) $raw);
        $terms[] = $key;
    }

    $map = [
        'beauty' => ['perfume', 'skincare', 'makeup', 'hair'],
        'perfume' => ['perfume', 'skincare', 'makeup'],
        'hair' => ['hair', 'shampoo'],
        'makeup' => ['makeup', 'skincare'],
        'skincare' => ['skincare', 'makeup'],
        'clothing' => ['shirt', 'dress', 'pants', 'jacket', 'blouse', 'skirts'],
        'shirt' => ['shirt'],
        'dress' => ['dress', 'blouse', 'skirts'],
        'blouse' => ['blouse', 'shirt'],
        'skirts' => ['skirts', 'dress'],
        'pants' => ['pants', 'jean'],
        'jacket' => ['jacket', 'coat', 'hoodie'],
        'shoe' => ['men-shoes', 'women-shoes', 'running'],
        'sneaker' => ['men-shoes', 'women-shoes', 'running'],
        'men-shoes' => ['men-shoes', 'running'],
        'women-shoes' => ['women-shoes', 'running'],
        'running' => ['running', 'men-shoes', 'fitness'],
        'headphone' => ['headphones', 'phone'],
        'headphones' => ['headphones'],
        'electronics' => ['phone', 'computer-tablet', 'tv', 'speakers', 'camera', 'printer', 'smart-home', 'headphones'],
        'phone' => ['phone'],
        'computer-tablet' => ['computer-tablet', 'laptop', 'tablet'],
        'tv' => ['tv', 'speakers', 'computer-tablet'],
        'speakers' => ['speakers', 'headphones'],
        'camera' => ['camera'],
        'printer' => ['printer'],
        'smartwatch' => ['smartwatch', 'watches'],
        'watches' => ['watches', 'smartwatch'],
        'jewelry' => ['watches', 'rings', 'necklaces', 'bracelets', 'earrings'],
        'rings' => ['rings', 'earrings'],
        'kitchen' => ['kitchen'],
        'home' => ['kitchen', 'furniture', 'decor', 'bedding'],
        'furniture' => ['furniture', 'decor'],
        'decor' => ['decor', 'furniture'],
        'bedding' => ['bedding'],
        'men' => ['shirt', 'pants', 'jacket', 'men-shoes', 'men-accessories', 'bags'],
        'women' => ['dress', 'blouse', 'skirts', 'women-shoes', 'bags', 'women-accessories'],
        'bags' => ['bags', 'women-accessories', 'men-accessories'],
        'pet' => ['pet-food', 'dog', 'cat', 'pet-toys'],
        'pet-food' => ['pet-food'],
        'snacks' => ['snacks', 'gourmet', 'beverages'],
        'food' => ['snacks', 'beverages', 'gourmet'],
        'beverages' => ['beverages', 'snacks'],
        'gourmet' => ['gourmet', 'snacks'],
        'vitamins' => ['vitamins', 'wellness', 'medical'],
        'baby-care' => ['baby-care', 'baby-toys', 'baby-clothing'],
        'kids-toys' => ['kids-toys', 'toys', 'games'],
        'kids-clothing' => ['kids-clothing', 'baby-clothing'],
        'fiction' => ['fiction', 'non-fiction', 'education', 'kids-books'],
        'stationery' => ['stationery', 'office-supplies', 'desk'],
        'outdoor-plants' => ['outdoor-plants', 'garden-tools'],
        'fitness' => ['fitness', 'running', 'outdoor'],
        'outdoor' => ['outdoor', 'fitness', 'cycling'],
        'cycling' => ['cycling', 'fitness'],
        'gadgets-accessories' => ['gadgets-accessories', 'headphones', 'phone'],
        'smart-home' => ['smart-home', 'electronics'],
        'hediye' => ['gift'],
        'gift' => ['gift'],
    ];

    $expanded = [];
    foreach ($terms as $key) {
        if (isset($map[$key])) {
            foreach ($map[$key] as $t) {
                $expanded[] = $t;
            }
        } else {
            $expanded[] = $key;
        }
    }

    $specificTypes = [
        'shirt', 'dress', 'pants', 'jacket', 'shoe', 'sneaker', 'blouse', 'skirts', 'bags', 'phone',
        'kitchen', 'headphone', 'laptop', 'computer-tablet', 'tv', 'perfume', 'watches', 'jewelry',
        'men-shoes', 'women-shoes', 'running', 'pet-food', 'snacks', 'vitamins', 'stationery',
    ];
    $isSpecific = false;
    foreach (array_merge($terms, [$productType !== null && $productType !== '' ? to_lower($productType) : '']) as $key) {
        if ($key !== '' && in_array(to_lower((string) $key), $specificTypes, true)) {
            $isSpecific = true;
            break;
        }
    }

    if ($audience === 'women' && !$isSpecific) {
        $expanded = array_merge($expanded, ['dress', 'blouse', 'skirts', 'bags', 'women-accessories', 'women-shoes']);
    } elseif ($audience === 'men' && !$isSpecific) {
        $expanded = array_merge($expanded, ['shirt', 'pants', 'jacket', 'men-shoes', 'men-accessories']);
    }

    return array_values(array_unique(array_filter($expanded)));
}

function is_turkish_footwear_search(array $entities): bool
{
    foreach ([$entities['category_like'] ?? null, $entities['product_type'] ?? null] as $val) {
        if ($val !== null && in_array(to_lower((string) $val), ['shoe', 'shoes', 'sneaker', 'sneakers', 'men-shoes', 'women-shoes', 'running'], true)) {
            return true;
        }
    }
    foreach (expand_turkish_search_keywords(
        is_array($entities['keywords'] ?? null) ? $entities['keywords'] : [],
        isset($entities['category_like']) ? (string) $entities['category_like'] : null,
        isset($entities['product_type']) ? (string) $entities['product_type'] : null
    ) as $kw) {
        if (in_array(to_lower($kw), turkish_footwear_terms(), true)) {
            return true;
        }
    }
    return false;
}

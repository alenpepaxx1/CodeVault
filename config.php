<?php
/**
 * CodeVault bootstrap and database layer.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);

const APP_NAME = 'CodeVault';
const APP_VERSION = '4.0.0';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.sid_length', '64');
ini_set('session.sid_bits_per_character', '6');
$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('CVSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$dataDir = getenv('CODEVAULT_DATA_DIR') ?: __DIR__ . '/data';
if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true) && !is_dir($dataDir)) {
    http_response_code(500); exit('Storage is unavailable.');
}
$packageDir = $dataDir . '/packages';
if (!is_dir($packageDir) && !mkdir($packageDir, 0750, true) && !is_dir($packageDir)) {
    http_response_code(500); exit('Package storage is unavailable.');
}

try {
    $db = new PDO('sqlite:' . $dataDir . '/store.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
        category TEXT NOT NULL, description TEXT NOT NULL, tech TEXT NOT NULL,
        price REAL NOT NULL CHECK(price > 0), featured INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS product_translations (
        product_id INTEGER NOT NULL, lang TEXT NOT NULL CHECK(lang IN ("sq","en","de")),
        title TEXT NOT NULL, description TEXT NOT NULL,
        PRIMARY KEY(product_id,lang), FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, customer_name TEXT NOT NULL,
        customer_email TEXT NOT NULL, product_id INTEGER NOT NULL, amount REAL NOT NULL,
        status TEXT NOT NULL DEFAULT "pending", payment_method TEXT NOT NULL DEFAULT "manual",
        payment_reference TEXT, ip_hash TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(product_id) REFERENCES products(id)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL DEFAULT "")');
    $db->exec('CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL,
        last_login_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS security_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, event_type TEXT NOT NULL, identifier_hash TEXT NOT NULL,
        success INTEGER NOT NULL DEFAULT 0, metadata TEXT, created_at INTEGER NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_security_lookup ON security_events(event_type,identifier_hash,created_at)');
    $db->exec('CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, admin_id INTEGER, action TEXT NOT NULL,
        object_type TEXT, object_id TEXT, ip_hash TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, product_id INTEGER NOT NULL,
        title_snapshot TEXT NOT NULL, price_snapshot REAL NOT NULL CHECK(price_snapshot >= 0), quantity INTEGER NOT NULL DEFAULT 1 CHECK(quantity = 1),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(order_id,product_id), FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY(product_id) REFERENCES products(id)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS product_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, storage_name TEXT NOT NULL UNIQUE,
        original_name TEXT NOT NULL, mime_type TEXT NOT NULL, size_bytes INTEGER NOT NULL,
        sha256 TEXT NOT NULL, version TEXT NOT NULL DEFAULT "1.0.0", active INTEGER NOT NULL DEFAULT 1,
        uploaded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS licenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT, public_key TEXT NOT NULL UNIQUE, order_id INTEGER NOT NULL,
        order_item_id INTEGER NOT NULL UNIQUE, product_id INTEGER NOT NULL, product_file_id INTEGER,
        customer_email TEXT NOT NULL, download_count INTEGER NOT NULL DEFAULT 0, download_limit INTEGER NOT NULL DEFAULT 10,
        status TEXT NOT NULL DEFAULT "active" CHECK(status IN ("active","revoked")),
        issued_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
        FOREIGN KEY(product_id) REFERENCES products(id), FOREIGN KEY(product_file_id) REFERENCES product_files(id)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS download_grants (
        id INTEGER PRIMARY KEY AUTOINCREMENT, license_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE,
        expires_at INTEGER NOT NULL, max_downloads INTEGER NOT NULL DEFAULT 3,
        download_count INTEGER NOT NULL DEFAULT 0, last_download_at INTEGER,
        created_at INTEGER NOT NULL, FOREIGN KEY(license_id) REFERENCES licenses(id) ON DELETE CASCADE
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS download_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, license_id INTEGER, product_file_id INTEGER,
        ip_hash TEXT NOT NULL, success INTEGER NOT NULL DEFAULT 0, reason TEXT NOT NULL,
        created_at INTEGER NOT NULL, FOREIGN KEY(license_id) REFERENCES licenses(id),
        FOREIGN KEY(product_file_id) REFERENCES product_files(id)
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS admin_recovery_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, admin_id INTEGER NOT NULL, code_hash TEXT NOT NULL,
        used_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
    )');

    $orderColumns = array_column($db->query('PRAGMA table_info(orders)')->fetchAll(), 'name');
    $orderMigrations = [
        'public_id'=>'ALTER TABLE orders ADD COLUMN public_id TEXT',
        'payment_method'=>'ALTER TABLE orders ADD COLUMN payment_method TEXT NOT NULL DEFAULT "manual"',
        'payment_reference'=>'ALTER TABLE orders ADD COLUMN payment_reference TEXT',
        'ip_hash'=>'ALTER TABLE orders ADD COLUMN ip_hash TEXT',
        'updated_at'=>'ALTER TABLE orders ADD COLUMN updated_at TEXT',
        'access_token_hash'=>'ALTER TABLE orders ADD COLUMN access_token_hash TEXT'
    ];
    foreach ($orderMigrations as $column=>$sql) if (!in_array($column,$orderColumns,true)) $db->exec($sql);
    $productColumns = array_column($db->query('PRAGMA table_info(products)')->fetchAll(), 'name');
    if (!in_array('active',$productColumns,true)) $db->exec('ALTER TABLE products ADD COLUMN active INTEGER NOT NULL DEFAULT 1');
    if (!in_array('version',$productColumns,true)) $db->exec('ALTER TABLE products ADD COLUMN version TEXT NOT NULL DEFAULT "1.0.0"');
    if (!in_array('updated_at',$productColumns,true)) $db->exec('ALTER TABLE products ADD COLUMN updated_at TEXT');
    $adminColumns = array_column($db->query('PRAGMA table_info(admin_users)')->fetchAll(), 'name');
    if (!in_array('totp_secret',$adminColumns,true)) $db->exec('ALTER TABLE admin_users ADD COLUMN totp_secret TEXT');
    if (!in_array('mfa_enabled',$adminColumns,true)) $db->exec('ALTER TABLE admin_users ADD COLUMN mfa_enabled INTEGER NOT NULL DEFAULT 0');
    if (!in_array('totp_last_step',$adminColumns,true)) $db->exec('ALTER TABLE admin_users ADD COLUMN totp_last_step INTEGER');
    $licenseColumns = array_column($db->query('PRAGMA table_info(licenses)')->fetchAll(), 'name');
    if (!in_array('product_file_id',$licenseColumns,true)) $db->exec('ALTER TABLE licenses ADD COLUMN product_file_id INTEGER');
    if (!in_array('download_count',$licenseColumns,true)) $db->exec('ALTER TABLE licenses ADD COLUMN download_count INTEGER NOT NULL DEFAULT 0');
    if (!in_array('download_limit',$licenseColumns,true)) $db->exec('ALTER TABLE licenses ADD COLUMN download_limit INTEGER NOT NULL DEFAULT 10');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_product_files_active ON product_files(product_id,active)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_licenses_order ON licenses(order_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_download_grants_lookup ON download_grants(token_hash,expires_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_download_events_time ON download_events(ip_hash,created_at)');
    $db->exec('UPDATE licenses SET product_file_id=(SELECT id FROM product_files WHERE product_files.product_id=licenses.product_id AND product_files.active=1 ORDER BY id DESC LIMIT 1) WHERE product_file_id IS NULL');

    if ((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $seed = [
            ['saas-dashboard-pro','Dashboard','PHP · SQLite · Chart.js',59,1],
            ['invoice-flow','Business','PHP · MySQL · Bootstrap',39,1],
            ['authkit-secure','Component','PHP · PDO · SMTP',24,0],
            ['shoplite-engine','E-commerce','PHP · SQLite · JavaScript',79,1],
            ['booking-calendar','Business','PHP · FullCalendar',45,0],
            ['portfolio-cms','CMS','PHP · SQLite · CSS',29,0]
        ];
        $copy = [
            'saas-dashboard-pro'=>[
                'sq'=>['SaaS Dashboard Pro','Panel analitik me grafikë, role përdoruesish dhe dark mode.'],
                'en'=>['SaaS Dashboard Pro','Analytics dashboard with charts, user roles, and dark mode.'],
                'de'=>['SaaS Dashboard Pro','Analyse-Dashboard mit Diagrammen, Benutzerrollen und Dark Mode.']],
            'invoice-flow'=>[
                'sq'=>['InvoiceFlow','Sistem faturimi me klientë, PDF dhe raporte mujore.'],
                'en'=>['InvoiceFlow','Invoicing system with clients, PDF exports, and monthly reports.'],
                'de'=>['InvoiceFlow','Rechnungssystem mit Kunden, PDF-Export und Monatsberichten.']],
            'authkit-secure'=>[
                'sq'=>['AuthKit Secure','Autentikim me verifikim emaili, role dhe rikuperim fjalëkalimi.'],
                'en'=>['AuthKit Secure','Authentication with email verification, roles, and password recovery.'],
                'de'=>['AuthKit Secure','Authentifizierung mit E-Mail-Verifizierung, Rollen und Passwort-Wiederherstellung.']],
            'shoplite-engine'=>[
                'sq'=>['ShopLite Engine','Dyqan modular me katalog, shportë, kuponë dhe administrim porosish.'],
                'en'=>['ShopLite Engine','Modular store with catalog, cart, coupons, and order management.'],
                'de'=>['ShopLite Engine','Modularer Shop mit Katalog, Warenkorb, Gutscheinen und Bestellverwaltung.']],
            'booking-calendar'=>[
                'sq'=>['Booking Calendar','Rezervime me kalendar, orare, njoftime dhe eksport CSV.'],
                'en'=>['Booking Calendar','Bookings with calendar, schedules, notifications, and CSV export.'],
                'de'=>['Booking Calendar','Buchungen mit Kalender, Zeitplänen, Benachrichtigungen und CSV-Export.']],
            'portfolio-cms'=>[
                'sq'=>['Portfolio CMS','Portfolio minimalist me CMS të thjeshtë dhe SEO të integruar.'],
                'en'=>['Portfolio CMS','Minimal portfolio with a simple CMS and built-in SEO.'],
                'de'=>['Portfolio CMS','Minimalistisches Portfolio mit einfachem CMS und integriertem SEO.']]
        ];
        $insertProduct=$db->prepare('INSERT INTO products(title,slug,category,description,tech,price,featured) VALUES(?,?,?,?,?,?,?)');
        $insertTranslation=$db->prepare('INSERT INTO product_translations(product_id,lang,title,description) VALUES(?,?,?,?)');
        foreach($seed as [$slug,$category,$tech,$price,$featured]){
            [$title,$description]=$copy[$slug]['sq'];
            $insertProduct->execute([$title,$slug,$category,$description,$tech,$price,$featured]);
            $id=(int)$db->lastInsertId();
            foreach($copy[$slug] as $lang=>[$translatedTitle,$translatedDescription]) $insertTranslation->execute([$id,$lang,$translatedTitle,$translatedDescription]);
        }
    }
    $db->exec('UPDATE orders SET public_id=lower(hex(randomblob(8))) WHERE public_id IS NULL OR public_id=""');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_public_id ON orders(public_id)');
    $db->exec('UPDATE orders SET status="pending" WHERE status IN ("E re","Në pritje")');
    $db->exec('UPDATE orders SET status="paid" WHERE status="Paguar"');
    $db->exec('UPDATE orders SET status="paying" WHERE status="Duke paguar"');
    $db->exec('UPDATE orders SET status="payment_error" WHERE status="Gabim pagese"');
    $db->exec('UPDATE orders SET status="cancelled" WHERE status="Anuluar"');
    $db->exec('UPDATE orders SET status="refunded" WHERE status="Rimbursuar"');
    $db->exec('INSERT OR IGNORE INTO order_items(order_id,product_id,title_snapshot,price_snapshot)
        SELECT orders.id,orders.product_id,COALESCE(products.title,"Digital product"),orders.amount
        FROM orders LEFT JOIN products ON products.id=orders.product_id');
} catch (Throwable $e) {
    error_log('CodeVault database error: '.$e->getMessage());
    http_response_code(500); exit('Application storage error.');
}

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/security.php';

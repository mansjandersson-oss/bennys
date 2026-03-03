<?php
declare(strict_types=1);

session_start();

const DEFAULT_GLOBAL_LAYOUT_PRESET_VERSION = '20260220A';
const DEFAULT_GLOBAL_LAYOUT_MAP = [
    'receipts' => ['x' => 0, 'y' => 0, 'w' => 2084, 'h' => 1202, 'z' => 17],
    'customers' => ['x' => 0, 'y' => 0, 'w' => 2049, 'h' => 2049, 'z' => 23],
    'vehicles' => ['x' => 0, 'y' => 0, 'w' => 2084, 'h' => 2048, 'z' => 25],
    'prices' => ['x' => 0, 'y' => 0, 'w' => 2015, 'h' => 1548, 'z' => 31],
    'payroll' => ['x' => 8, 'y' => 11, 'w' => 2084, 'h' => 975, 'z' => 36],
    'admin' => ['x' => 0, 'y' => 3, 'w' => 2084, 'h' => 2400, 'z' => 46],
    'card::receipts__skapa-kvitto' => ['x' => 24, 'y' => 24, 'w' => 887, 'h' => 837, 'z' => 18],
    'card::receipts__registrerade-kvitton' => ['x' => 918, 'y' => 24, 'w' => 1142, 'h' => 837, 'z' => 19],
    'card::customers__kundregister' => ['x' => 24, 'y' => 24, 'w' => 350, 'h' => 365, 'z' => 9],
    'card::customers__kunder' => ['x' => 379, 'y' => 24, 'w' => 1646, 'h' => 1006, 'z' => 11],
    'card::vehicles__fordonsdatabas' => ['x' => 24, 'y' => 24, 'w' => 347, 'h' => 291, 'z' => 4],
    'card::vehicles__sparade-fordon' => ['x' => 371, 'y' => 24, 'w' => 1665, 'h' => 951, 'z' => 7],
    'card::prices__prislista' => ['x' => 24, 'y' => 24, 'w' => 1967, 'h' => 1500, 'z' => 3],
    'card::payroll__lonhantering' => ['x' => 24, 'y' => 24, 'w' => 420, 'h' => 405, 'z' => 4],
    'card::payroll__lonehistorik' => ['x' => 443, 'y' => 24, 'w' => 1617, 'h' => 927, 'z' => 6],
    'card::admin__adminpanel' => ['x' => 24, 'y' => 24, 'w' => 875, 'h' => 794, 'z' => 30],
    'card::admin__rabatter' => ['x' => 899, 'y' => 24, 'w' => 1150, 'h' => 794, 'z' => 34],
    'card::admin__skapa-uppdatera-anvandare' => ['x' => 24, 'y' => 818, 'w' => 875, 'h' => 1304, 'z' => 26],
    'card::admin__ranker' => ['x' => 899, 'y' => 818, 'w' => 1161, 'h' => 1304, 'z' => 25],
    '_metaPresetVersion' => DEFAULT_GLOBAL_LAYOUT_PRESET_VERSION,
];

$dbDir = __DIR__ . '/data';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}
$dbPath = $dbDir . '/bennys.sqlite';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    http_response_code(500);
    echo 'PHP saknar SQLite-drivrutin (pdo_sqlite).';
    exit;
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbVersion = (int) ($pdo->query('PRAGMA user_version')->fetchColumn() ?: 0);

$pdo->exec('CREATE TABLE IF NOT EXISTS ranks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    can_view_admin INTEGER NOT NULL DEFAULT 0,
    can_manage_users INTEGER NOT NULL DEFAULT 0,
    can_manage_prices INTEGER NOT NULL DEFAULT 0,
    can_edit_receipts INTEGER NOT NULL DEFAULT 0,
    can_view_customers INTEGER NOT NULL DEFAULT 0,
    can_view_vehicles INTEGER NOT NULL DEFAULT 0,
    can_view_prices INTEGER NOT NULL DEFAULT 0
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    personnummer TEXT UNIQUE NOT NULL,
    full_name TEXT NOT NULL DEFAULT \'Okänd\',
    password TEXT NOT NULL,
    rank_id INTEGER,
    is_admin INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(rank_id) REFERENCES ranks(id)
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS receipts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mechanic TEXT NOT NULL,
    work_type TEXT NOT NULL,
    styling_parts INTEGER,
    performance_parts INTEGER,
    amount REAL NOT NULL,
    expense_total REAL NOT NULL DEFAULT 0,
    discount_name TEXT,
    discount_percent REAL NOT NULL DEFAULT 0,
    customer TEXT NOT NULL,
    customer_personnummer TEXT,
    plate TEXT NOT NULL,
    order_comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS customer_registry (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_name TEXT NOT NULL,
    personnummer TEXT,
    phone TEXT,
    discount_preset_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS vehicle_registry (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plate TEXT UNIQUE NOT NULL,
    vehicle_model TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS discount_presets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    percent REAL NOT NULL DEFAULT 0
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS service_prices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_name TEXT UNIQUE NOT NULL,
    sale_price REAL NOT NULL DEFAULT 0,
    expense_cost REAL NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    has_dropdown INTEGER NOT NULL DEFAULT 0,
    service_category TEXT NOT NULL DEFAULT "Övrigt"
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS payroll_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payee_name TEXT NOT NULL,
    amount REAL NOT NULL DEFAULT 0,
    pay_date TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    actor_personnummer TEXT,
    actor_name TEXT,
    action_type TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER,
    description TEXT,
    meta_json TEXT
)');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log(created_at, id)');

$pdo->exec('CREATE TABLE IF NOT EXISTS layout_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    layout_key TEXT UNIQUE NOT NULL,
    config_json TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$serviceColumns = $pdo->query('PRAGMA table_info(service_prices)')->fetchAll(PDO::FETCH_ASSOC);
$hasDropdownColumn = false;
$hasCategoryColumn = false;
foreach ($serviceColumns as $column) {
    if ((string) ($column['name'] ?? '') === 'has_dropdown') {
        $hasDropdownColumn = true;
    }
    if ((string) ($column['name'] ?? '') === 'service_category') {
        $hasCategoryColumn = true;
    }
}
if (!$hasDropdownColumn) {
    $pdo->exec('ALTER TABLE service_prices ADD COLUMN has_dropdown INTEGER NOT NULL DEFAULT 0');
}
if (!$hasCategoryColumn) {
    $pdo->exec('ALTER TABLE service_prices ADD COLUMN service_category TEXT NOT NULL DEFAULT "Övrigt"');
}

$customerColumns = $pdo->query('PRAGMA table_info(customer_registry)')->fetchAll(PDO::FETCH_ASSOC);
$customerColumnNames = array_map(static fn(array $c): string => (string) ($c['name'] ?? ''), $customerColumns);
$customerTableSql = (string) ($pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='customer_registry'")->fetchColumn() ?: '');
$needsPersonnummerColumn = !in_array('personnummer', $customerColumnNames, true);
$hasUniqueCustomerNameConstraint = strpos($customerTableSql, 'customer_name TEXT UNIQUE') !== false;
if ($needsPersonnummerColumn || $hasUniqueCustomerNameConstraint) {
    $pdo->exec('ALTER TABLE customer_registry RENAME TO customer_registry_old');
    $pdo->exec('CREATE TABLE customer_registry (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_name TEXT NOT NULL,
        personnummer TEXT,
        phone TEXT,
        discount_preset_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $personnummerSelect = $needsPersonnummerColumn ? 'NULL' : 'personnummer';
    $pdo->exec("INSERT INTO customer_registry (id, customer_name, personnummer, phone, discount_preset_id, created_at)
        SELECT id, customer_name, $personnummerSelect, phone, discount_preset_id, created_at FROM customer_registry_old");
    $pdo->exec('DROP TABLE customer_registry_old');
    $customerColumns = $pdo->query('PRAGMA table_info(customer_registry)')->fetchAll(PDO::FETCH_ASSOC);
    $customerColumnNames = array_map(static fn(array $c): string => (string) ($c['name'] ?? ''), $customerColumns);
}
if (!in_array('discount_preset_id', $customerColumnNames, true)) {
    $pdo->exec('ALTER TABLE customer_registry ADD COLUMN discount_preset_id INTEGER');
}
$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_customer_personnummer ON customer_registry(personnummer) WHERE personnummer IS NOT NULL');

$rankColumns = $pdo->query('PRAGMA table_info(ranks)')->fetchAll(PDO::FETCH_ASSOC);
$rankColumnNames = array_map(static fn(array $c): string => (string) ($c['name'] ?? ''), $rankColumns);
if (!in_array('can_view_customers', $rankColumnNames, true)) {
    $pdo->exec('ALTER TABLE ranks ADD COLUMN can_view_customers INTEGER NOT NULL DEFAULT 0');
}
if (!in_array('can_view_vehicles', $rankColumnNames, true)) {
    $pdo->exec('ALTER TABLE ranks ADD COLUMN can_view_vehicles INTEGER NOT NULL DEFAULT 0');
}
if (!in_array('can_view_prices', $rankColumnNames, true)) {
    $pdo->exec('ALTER TABLE ranks ADD COLUMN can_view_prices INTEGER NOT NULL DEFAULT 0');
}

$receiptColumns = $pdo->query('PRAGMA table_info(receipts)')->fetchAll(PDO::FETCH_ASSOC);
$hasStylingParts = false;
$hasPerformanceParts = false;
$hasLegacyPartsCount = false;
$hasIsSent = false;
$hasExpenseTotal = false;
$hasDiscountName = false;
$hasDiscountPercent = false;
$hasCustomerPersonnummer = false;
foreach ($receiptColumns as $column) {
    $name = (string) ($column['name'] ?? '');
    if ($name === 'styling_parts') {
        $hasStylingParts = true;
    }
    if ($name === 'performance_parts') {
        $hasPerformanceParts = true;
    }
    if ($name === 'parts_count') {
        $hasLegacyPartsCount = true;
    }
    if ($name === 'is_sent') {
        $hasIsSent = true;
    }
    if ($name === 'expense_total') {
        $hasExpenseTotal = true;
    }
    if ($name === 'discount_name') {
        $hasDiscountName = true;
    }
    if ($name === 'discount_percent') {
        $hasDiscountPercent = true;
    }
    if ($name === 'customer_personnummer') {
        $hasCustomerPersonnummer = true;
    }
}
if (!$hasStylingParts) {
    $pdo->exec('ALTER TABLE receipts ADD COLUMN styling_parts INTEGER');
}
if (!$hasPerformanceParts) {
    $pdo->exec('ALTER TABLE receipts ADD COLUMN performance_parts INTEGER');
}
if ($hasLegacyPartsCount) {
    $pdo->exec("UPDATE receipts SET styling_parts = COALESCE(styling_parts, parts_count) WHERE work_type = 'Styling'");
    $pdo->exec("UPDATE receipts SET performance_parts = COALESCE(performance_parts, parts_count) WHERE work_type = 'Prestanda'");
}
if (!$hasIsSent) {
    $pdo->exec('ALTER TABLE receipts ADD COLUMN is_sent INTEGER NOT NULL DEFAULT 0');
}
if (!$hasExpenseTotal) {
    $pdo->exec('ALTER TABLE receipts ADD COLUMN expense_total REAL NOT NULL DEFAULT 0');
}
if (!$hasDiscountName) {
    $pdo->exec('ALTER TABLE receipts ADD COLUMN discount_name TEXT');
}
if (!$hasDiscountPercent) {
    $pdo->exec('ALTER TABLE receipts ADD COLUMN discount_percent REAL NOT NULL DEFAULT 0');
}
if (!$hasCustomerPersonnummer) {
    $pdo->exec('ALTER TABLE receipts ADD COLUMN customer_personnummer TEXT');
}

$userColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
$userColumnNames = array_map(static fn(array $c): string => (string) ($c['name'] ?? ''), $userColumns);
$userLoginColumn = 'personnummer';
if (!in_array('personnummer', $userColumnNames, true) && in_array('username', $userColumnNames, true)) {
    $userLoginColumn = 'username';
}
if (!in_array('personnummer', $userColumnNames, true) && !in_array('username', $userColumnNames, true)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN personnummer TEXT NOT NULL DEFAULT 'okand'");
    $userLoginColumn = 'personnummer';
}
if (!in_array('full_name', $userColumnNames, true)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN full_name TEXT NOT NULL DEFAULT 'Okänd'");
}
if (!in_array('rank_id', $userColumnNames, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN rank_id INTEGER');
}
if (!in_array('is_admin', $userColumnNames, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
}

$vehicleColumns = $pdo->query('PRAGMA table_info(vehicle_registry)')->fetchAll(PDO::FETCH_ASSOC);
$hasVehicleModel = false;
$hasVehicleType = false;
foreach ($vehicleColumns as $column) {
    $name = (string) ($column['name'] ?? '');
    if ($name === 'vehicle_model') {
        $hasVehicleModel = true;
    }
    if ($name === 'vehicle_type') {
        $hasVehicleType = true;
    }
}
if (!$hasVehicleModel) {
    $pdo->exec('ALTER TABLE vehicle_registry ADD COLUMN vehicle_model TEXT');
}
if ($hasVehicleType) {
    $pdo->exec('UPDATE vehicle_registry SET vehicle_model = COALESCE(vehicle_model, vehicle_type)');
}

if ($dbVersion < 2) {
    $hasNamedAnstalld = (int) ($pdo->query("SELECT COUNT(*) FROM ranks WHERE name = 'Anställd'")->fetchColumn() ?: 0);
    if ($hasNamedAnstalld === 0) {
        $pdo->exec("UPDATE ranks SET name = 'Anställd' WHERE name = 'Mekaniker'");
    }
}

// Ensure former "Admin" rank is renamed to "Ägare"
$pdo->exec("UPDATE ranks SET name = 'Ägare' WHERE name = 'Admin'");

$defaultRanks = [
    'Provanställd' => [0, 0, 0, 0, 0, 0, 0],
    'Anställd' => [0, 0, 0, 0, 1, 1, 0],
    'Chefmekaniker' => [0, 0, 1, 1, 1, 1, 1],
    'Ägare' => [1, 1, 1, 1, 1, 1, 1],
];

$rankCount = (int) ($pdo->query('SELECT COUNT(*) FROM ranks')->fetchColumn() ?: 0);
if ($rankCount === 0) {
    $rankInsertStmt = $pdo->prepare('INSERT OR IGNORE INTO ranks (name, can_view_admin, can_manage_users, can_manage_prices, can_edit_receipts, can_view_customers, can_view_vehicles, can_view_prices) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($defaultRanks as $rankName => $perms) {
        $rankInsertStmt->execute(array_merge([$rankName], $perms));
    }
}

if ($dbVersion < 2) {
    $rankUpdateStmt = $pdo->prepare('UPDATE ranks SET can_view_admin = ?, can_manage_users = ?, can_manage_prices = ?, can_edit_receipts = ?, can_view_customers = ?, can_view_vehicles = ?, can_view_prices = ? WHERE name = ?');
    foreach ($defaultRanks as $rankName => $perms) {
        $rankUpdateStmt->execute(array_merge($perms, [$rankName]));
    }
    $pdo->exec('PRAGMA user_version = 2');
}

$defaultDiscounts = [
    ['Stammis', 10],
    ['Avtalskund', 15],
];
$discountInsertStmt = $pdo->prepare('INSERT OR IGNORE INTO discount_presets (name, percent) VALUES (?, ?)');
foreach ($defaultDiscounts as $preset) {
    $discountInsertStmt->execute($preset);
}

$getRankId = static function (PDO $pdo, string $name): int {
    $stmt = $pdo->prepare('SELECT id FROM ranks WHERE name = ?');
    $stmt->execute([$name]);
    return (int) ($stmt->fetchColumn() ?: 0);
};

$adminRankId = $getRankId($pdo, 'Ägare');
$mechRankId = $getRankId($pdo, 'Mekaniker');

$seedStmt = $pdo->prepare("INSERT OR IGNORE INTO users ($userLoginColumn, full_name, password, rank_id, is_admin) VALUES (?, ?, ?, ?, ?)");

$serviceSeed = $pdo->prepare('INSERT OR IGNORE INTO service_prices (service_name, sale_price, expense_cost, is_active, has_dropdown, service_category) VALUES (?, ?, ?, 1, 0, "Övrigt")');

// Database health check: ensure file is available/writable and integrity is ok
if (!is_file($dbPath) || !is_readable($dbPath) || !is_writable($dbPath)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Databasfilen är inte tillgänglig eller skrivbar.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $res = $pdo->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN);
    $integrity = is_array($res) && count($res) > 0 ? (string) $res[0] : '';
} catch (Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Databasvalidering misslyckades: ' . (string) $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($integrity !== 'ok') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Databasintegritetskontroll misslyckades: ' . $integrity], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function normalize_personnummer(?string $value): string
{
    $trimmed = trim((string) $value);
    if ($trimmed === '') {
        return '';
    }
    $digits = preg_replace('/\D+/', '', $trimmed);
    if (strlen($digits) !== 12) {
        return '';
    }
    return substr($digits, 0, 8) . '-' . substr($digits, 8);
}

function normalize_plate(?string $value): string
{
    $trimmed = strtoupper(trim((string) $value));
    if ($trimmed === '') {
        return '';
    }
    $compact = preg_replace('/[^A-Z0-9]/', '', $trimmed);
    if (!is_string($compact) || strlen($compact) !== 6) {
        return $trimmed;
    }
    return substr($compact, 0, 3) . '-' . substr($compact, 3, 3);
}

function is_valid_plate(string $plate): bool
{
    return (bool) preg_match('/^[A-Z]{3}-[A-Z0-9]{3}$/', $plate);
}

function normalize_layout_payload(array $layoutRaw): array
{
    $normalized = [];
    foreach ($layoutRaw as $key => $config) {
        if (!is_string($key) || $key === '') {
            continue;
        }
        if ($key[0] === '_' && !is_array($config)) {
            $normalized[$key] = $config;
            continue;
        }
        if (!is_array($config)) {
            continue;
        }
        $x = (int) floor((float) ($config['x'] ?? 0));
        $y = (int) floor((float) ($config['y'] ?? 0));
        $w = (int) ceil((float) ($config['w'] ?? 0));
        $h = (int) ceil((float) ($config['h'] ?? 0));
        $z = (int) ceil((float) ($config['z'] ?? 1));
        $x = max(0, min(8000, $x));
        $y = max(0, min(8000, $y));
        $w = max(200, min(8000, $w));
        $h = max(200, min(8000, $h));
        $z = max(1, min(999, $z));
        $normalized[$key] = [
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
            'z' => $z,
        ];
    }
    return $normalized;
}

function log_activity(PDO $pdo, array $user, string $actionType, string $entityType, ?int $entityId, string $description, ?array $meta = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (actor_personnummer, actor_name, action_type, entity_type, entity_id, description, meta_json) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user['personnummer'] ?? null,
            $user['full_name'] ?? null,
            $actionType,
            $entityType,
            $entityId,
            $description,
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $e) {
        // Logging should never block primary actions.
    }
}

function session_user(): array
{
    return [
        'personnummer' => (string) ($_SESSION['personnummer'] ?? ''),
        'full_name' => (string) ($_SESSION['full_name'] ?? ''),
        'rank_id' => (int) ($_SESSION['rank_id'] ?? 0),
        'rank_name' => (string) ($_SESSION['rank_name'] ?? ''),
        'permissions' => (array) ($_SESSION['permissions'] ?? []),
    ];
}

function require_login(): array
{
    $user = session_user();
    if ($user['personnummer'] === '') {
        json_response(['ok' => false, 'error' => 'Ej inloggad.'], 401);
    }
    return $user;
}

function require_permission(string $permission): void
{
    $permissions = (array) ($_SESSION['permissions'] ?? []);
    if ((int) ($permissions[$permission] ?? 0) !== 1) {
        json_response(['ok' => false, 'error' => 'Du saknar behörighet för detta.'], 403);
    }
}

$action = $_GET['action'] ?? '';

if ($action === 'api_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = read_json_input();
    $personnummer = trim((string) ($data['personnummer'] ?? ''));
    $password = trim((string) ($data['password'] ?? ''));

    $stmt = $pdo->prepare("SELECT u.$userLoginColumn AS login_id, u.full_name, u.password, u.rank_id, u.is_admin, r.name AS rank_name,
        COALESCE(r.can_view_admin, 0) AS can_view_admin,
        COALESCE(r.can_manage_users, 0) AS can_manage_users,
        COALESCE(r.can_manage_prices, 0) AS can_manage_prices,
        COALESCE(r.can_edit_receipts, 0) AS can_edit_receipts,
        COALESCE(r.can_view_customers, 0) AS can_view_customers,
        COALESCE(r.can_view_vehicles, 0) AS can_view_vehicles,
        COALESCE(r.can_view_prices, 0) AS can_view_prices
        FROM users u
        LEFT JOIN ranks r ON r.id = u.rank_id
        WHERE u.$userLoginColumn = ? AND u.password = ?");
    $stmt->execute([$personnummer, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        json_response(['ok' => false, 'error' => 'Fel personnummer eller lösenord.'], 401);
    }

    $permissions = [
        'can_view_admin' => (int) ($user['can_view_admin'] ?? 0),
        'can_manage_users' => (int) ($user['can_manage_users'] ?? 0),
        'can_manage_prices' => (int) ($user['can_manage_prices'] ?? 0),
        'can_edit_receipts' => (int) ($user['can_edit_receipts'] ?? 0),
        'can_view_customers' => (int) ($user['can_view_customers'] ?? 0),
        'can_view_vehicles' => (int) ($user['can_view_vehicles'] ?? 0),
        'can_view_prices' => (int) ($user['can_view_prices'] ?? 0),
    ];
    if ((int) ($user['is_admin'] ?? 0) === 1) {
        $permissions = [
            'can_view_admin' => 1,
            'can_manage_users' => 1,
            'can_manage_prices' => 1,
            'can_edit_receipts' => 1,
            'can_view_customers' => 1,
            'can_view_vehicles' => 1,
            'can_view_prices' => 1,
        ];
    }

    $_SESSION['personnummer'] = (string) ($user['login_id'] ?? '');
    $_SESSION['full_name'] = (string) ($user['full_name'] ?? '');
    $_SESSION['rank_id'] = (int) ($user['rank_id'] ?? 0);
    $_SESSION['rank_name'] = (string) ($user['rank_name'] ?? 'Mekaniker');
    $_SESSION['permissions'] = $permissions;

    json_response(['ok' => true, 'user' => session_user()]);
}

if ($action === 'api_logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_unset();
    session_destroy();
    session_start();
    json_response(['ok' => true]);
}

if ($action === 'api_delete_rank' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_users');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt rank-ID.'], 422);
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE users SET rank_id = NULL WHERE rank_id = ?');
        $stmt->execute([$id]);
        $stmt = $pdo->prepare('DELETE FROM ranks WHERE id = ?');
        $stmt->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['ok' => false, 'error' => 'Kunde inte ta bort rank: ' . $e->getMessage()], 500);
    }
    json_response(['ok' => true]);
}

if ($action === 'api_upsert_customer_from_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = require_login();

  try {
    $data = read_json_input();
    $name = trim((string)($data['customer_name'] ?? ''));

    if ($name === '') {
      json_response(['ok' => false, 'error' => 'Kundnamn saknas.'], 422);
    }

    // Skapa bara om den inte finns (SQLite)
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO customer_registry (customer_name, phone, discount_preset_id) VALUES (?, "", 0)');
    $stmt->execute([$name]);

    // Prevent duplicate customers by name (case-insensitive, trimmed)
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_customer_registry_name_ci
            ON customer_registry (lower(trim(customer_name)))');

    json_response(['ok' => true]);
  } catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Serverfel: ' . $e->getMessage()], 500);
  }
}

if ($action === 'api_delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_users');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt användar-ID.'], 422);
    }
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}

if ($action === 'api_me') {
    $user = session_user();
    if ($user['personnummer'] === '') {
        json_response(['ok' => true, 'user' => null]);
    }
    $stmt = $pdo->prepare("SELECT u.full_name, u.rank_id, u.is_admin, COALESCE(r.name, 'Mekaniker') AS rank_name,
        COALESCE(r.can_view_admin, 0) AS can_view_admin,
        COALESCE(r.can_manage_users, 0) AS can_manage_users,
        COALESCE(r.can_manage_prices, 0) AS can_manage_prices,
        COALESCE(r.can_edit_receipts, 0) AS can_edit_receipts,
        COALESCE(r.can_view_customers, 0) AS can_view_customers,
        COALESCE(r.can_view_vehicles, 0) AS can_view_vehicles,
        COALESCE(r.can_view_prices, 0) AS can_view_prices
        FROM users u
        LEFT JOIN ranks r ON r.id = u.rank_id
        WHERE u.$userLoginColumn = ?");
    $stmt->execute([$user['personnummer']]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissions = [
            'can_view_admin' => (int) ($row['can_view_admin'] ?? 0),
            'can_manage_users' => (int) ($row['can_manage_users'] ?? 0),
            'can_manage_prices' => (int) ($row['can_manage_prices'] ?? 0),
            'can_edit_receipts' => (int) ($row['can_edit_receipts'] ?? 0),
            'can_view_customers' => (int) ($row['can_view_customers'] ?? 0),
            'can_view_vehicles' => (int) ($row['can_view_vehicles'] ?? 0),
            'can_view_prices' => (int) ($row['can_view_prices'] ?? 0),
        ];
        if ((int) ($row['is_admin'] ?? 0) === 1) {
            $permissions = [
                'can_view_admin' => 1,
                'can_manage_users' => 1,
                'can_manage_prices' => 1,
                'can_edit_receipts' => 1,
                'can_view_customers' => 1,
                'can_view_vehicles' => 1,
                'can_view_prices' => 1,
            ];
        }
        $_SESSION['full_name'] = (string) ($row['full_name'] ?? '');
        $_SESSION['rank_id'] = (int) ($row['rank_id'] ?? 0);
        $_SESSION['rank_name'] = (string) ($row['rank_name'] ?? '');
        $_SESSION['permissions'] = $permissions;
    }
    json_response(['ok' => true, 'user' => session_user()]);
}

if ($action === 'api_service_prices' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $rows = $pdo->query('SELECT id, service_name, sale_price, expense_cost, is_active, has_dropdown, COALESCE(service_category, "Övrigt") AS service_category FROM service_prices ORDER BY service_category ASC, service_name ASC')->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'services' => $rows]);
}

if ($action === 'api_discount_presets' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $rows = $pdo->query('SELECT id, name, percent FROM discount_presets ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'discounts' => $rows]);
}

if ($action === 'api_save_discount_preset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_prices');
    $data = read_json_input();
    $name = trim((string) ($data['name'] ?? ''));
    $percent = (float) ($data['percent'] ?? 0);
    $id = (int) ($data['id'] ?? 0);

    if ($name === '') {
        json_response(['ok' => false, 'error' => 'Namn på rabatten krävs.'], 422);
    }
    if ($percent < 0 || $percent > 100) {
        json_response(['ok' => false, 'error' => 'Rabatt måste vara mellan 0 och 100 %.'], 422);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO discount_presets (id, name, percent) VALUES ((SELECT id FROM discount_presets WHERE id = ?), ?, ?)');
        $stmt->execute([$id, $name, $percent]);
    } else {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO discount_presets (id, name, percent) VALUES ((SELECT id FROM discount_presets WHERE name = ?), ?, ?)');
        $stmt->execute([$name, $name, $percent]);
    }

    json_response(['ok' => true]);
}

if ($action === 'api_delete_discount_preset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_prices');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt rabatt-ID.'], 422);
    }
    $pdo->prepare('UPDATE customer_registry SET discount_preset_id = NULL WHERE discount_preset_id = ?')->execute([$id]);
    $stmt = $pdo->prepare('DELETE FROM discount_presets WHERE id = ?');
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}

if ($action === 'api_save_service_price' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_prices');
    $data = read_json_input();
    $name = trim((string) ($data['service_name'] ?? ''));
    $salePrice = (float) ($data['sale_price'] ?? 0);
    $expenseCost = (float) ($data['expense_cost'] ?? 0);
    $isActive = (int) (($data['is_active'] ?? 0) ? 1 : 0);
    $hasDropdown = (int) (($data['has_dropdown'] ?? 0) ? 1 : 0);
    $category = trim((string) ($data['service_category'] ?? ''));
    if ($category === '') {
        $category = 'Övrigt';
    }

    if ($name === '') {
        json_response(['ok' => false, 'error' => 'Tjänstens namn måste anges.'], 422);
    }
    $serviceId = (int) ($data['id'] ?? 0);
    if ($serviceId > 0) {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO service_prices (id, service_name, sale_price, expense_cost, is_active, has_dropdown, service_category) VALUES ((SELECT id FROM service_prices WHERE id = ?), ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$serviceId, $name, $salePrice, $expenseCost, $isActive, $hasDropdown, $category]);
    } else {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO service_prices (id, service_name, sale_price, expense_cost, is_active, has_dropdown, service_category) VALUES ((SELECT id FROM service_prices WHERE service_name = ?), ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $name, $salePrice, $expenseCost, $isActive, $hasDropdown, $category]);
    }
    json_response(['ok' => true]);
}

if ($action === 'api_delete_service_price' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_prices');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt tjänst-ID.'], 422);
    }
    $stmt = $pdo->prepare('DELETE FROM service_prices WHERE id = ?');
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}

if ($action === 'api_receipts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $sql = 'SELECT r.id, r.mechanic, COALESCE(u.full_name, r.mechanic) AS mechanic_name, r.work_type, r.styling_parts, r.performance_parts, r.amount, COALESCE(r.expense_total, 0) AS expense_total, r.discount_name, COALESCE(r.discount_percent, 0) AS discount_percent, r.customer, r.customer_personnummer, r.plate, r.order_comment, r.created_at, COALESCE(r.is_sent, 0) AS is_sent
        FROM receipts r
        LEFT JOIN users u ON u.' . $userLoginColumn . ' = r.mechanic
        ORDER BY r.id DESC';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['styling_parts'] = $row['styling_parts'] === null ? '' : (int) $row['styling_parts'];
        $row['performance_parts'] = $row['performance_parts'] === null ? '' : (int) $row['performance_parts'];
        $row['amount'] = (float) $row['amount'];
        $row['expense_total'] = (float) ($row['expense_total'] ?? 0);
        $row['is_sent'] = (int) ($row['is_sent'] ?? 0);
        $row['discount_percent'] = (float) ($row['discount_percent'] ?? 0);
        $row['customer_personnummer'] = (string) ($row['customer_personnummer'] ?? '');
        $row['work_order'] = "Redline Performance Arbetsorder - " . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT);
    }

    json_response(['ok' => true, 'receipts' => $rows]);
}

if ($action === 'api_create_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    $data = read_json_input();

    $workType = trim((string) ($data['work_type'] ?? ''));
    $stylingRaw = trim((string) ($data['styling_parts'] ?? ''));
    $performanceRaw = trim((string) ($data['performance_parts'] ?? ''));
    $amountRaw = trim((string) ($data['amount'] ?? ''));
    $customer = trim((string) ($data['customer'] ?? ''));
    $customerPersonnummerInput = trim((string) ($data['customer_personnummer'] ?? ''));
    $customerPersonnummer = normalize_personnummer($customerPersonnummerInput);
    $plate = normalize_plate((string) ($data['plate'] ?? ''));
    $expenseTotalInput = $data['expense_total'] ?? null;
    $discountNameInput = array_key_exists('discount_name', $data) ? trim((string) $data['discount_name']) : null;
    $discountPercentInput = $data['discount_percent'] ?? null;
    $expenseTotalInput = $data['expense_total'] ?? null;
    $expenseTotal = isset($data['expense_total']) && $data['expense_total'] !== '' ? (float) $data['expense_total'] : 0.0;
    $discountName = trim((string) ($data['discount_name'] ?? ''));
    $discountPercent = (float) ($data['discount_percent'] ?? 0);
    $orderComment = trim((string)($data['order_comment'] ?? ''));

    $errors = [];
    // allow combined work types like "Reperation & Prestanda"; we'll validate required part counts below

    $stylingParts = null;
    $performanceParts = null;
    if ($stylingRaw !== '') {
        if (!ctype_digit($stylingRaw)) {
            $errors[] = 'Styling-delar måste vara ett heltal.';
        } else {
            $stylingParts = (int) $stylingRaw;
        }
    }

    if ($performanceRaw !== '') {
        if (!ctype_digit($performanceRaw)) {
            $errors[] = 'Prestanda-delar måste vara ett heltal.';
        } else {
            $performanceParts = (int) $performanceRaw;
        }
    }

    // determine which work type components are present
    $partsInType = array_map('trim', preg_split('/\s*&\s*/', $workType));
    $hasStylingInType = in_array('Styling', $partsInType, true);
    $hasPerformanceInType = in_array('Prestanda', $partsInType, true);

    if ($hasStylingInType && $stylingParts === null) {
        $errors[] = 'Styling-delar krävs för valda arbetstyper.';
    }
    if ($hasPerformanceInType && $performanceParts === null) {
        $errors[] = 'Prestanda-delar krävs för valda arbetstyper.';
    }

    if ($customer === '') {
        $errors[] = 'Kund måste anges.';
    }
    if (!is_valid_plate($plate)) {
        $errors[] = 'Regplåt måste vara i formatet XXX-XXX (t.ex. RAO-121 eller MIX-15J).';
    }
    if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw < 0) {
        $errors[] = 'Summa måste vara ett positivt tal.';
    }
    if ($expenseTotal < 0) {
        $errors[] = 'Utgift måste vara 0 eller mer.';
    }
    if ($discountPercent < 0 || $discountPercent > 100) {
        $errors[] = 'Rabattprocent måste vara mellan 0 och 100.';
    }
    if ($customerPersonnummerInput !== '' && $customerPersonnummer === '') {
        $errors[] = 'Personnummer måste anges som ÅÅÅÅMMDD-XXXX.';
    }

    if ($errors) {
        json_response(['ok' => false, 'error' => implode(' ', $errors)], 422);
    }

    $stmt = $pdo->prepare('INSERT INTO receipts (mechanic, work_type, styling_parts, performance_parts, amount, expense_total, discount_name, discount_percent, customer, customer_personnummer, plate, order_comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$user['personnummer'], $workType, $stylingParts, $performanceParts, (float) $amountRaw, $expenseTotal, $discountName !== '' ? $discountName : null, $discountPercent, $customer, $customerPersonnummer !== '' ? $customerPersonnummer : null, $plate, $orderComment !== '' ? $orderComment : null,]);
    $receiptId = (int) $pdo->lastInsertId();
    log_activity(
        $pdo,
        $user,
        'receipt_created',
        'receipt',
        $receiptId,
        sprintf('Kvitto #%d sparades för %s.', $receiptId, $customer),
        [
            'work_type' => $workType,
            'amount' => (float) $amountRaw,
            'discount_percent' => $discountPercent,
        ]
    );
    json_response(['ok' => true]);
}

if ($action === 'api_update_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_edit_receipts');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    $workType = trim((string) ($data['work_type'] ?? ''));
    $stylingRaw = trim((string) ($data['styling_parts'] ?? ''));
    $performanceRaw = trim((string) ($data['performance_parts'] ?? ''));
    $amountRaw = trim((string) ($data['amount'] ?? ''));
    $customer = trim((string) ($data['customer'] ?? ''));
    $customerPersonnummerInput = trim((string) ($data['customer_personnummer'] ?? ''));
    $customerPersonnummer = normalize_personnummer($customerPersonnummerInput);
    $plate = normalize_plate((string) ($data['plate'] ?? ''));
    $expenseTotalInput = $data['expense_total'] ?? null;
    $discountNameInput = array_key_exists('discount_name', $data) ? trim((string) $data['discount_name']) : null;
    $discountPercentInput = $data['discount_percent'] ?? null;
    $orderComment = trim((string)($data['order_comment'] ?? ''));

    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt kvitto-ID.'], 422);
    }

    $stylingParts = $stylingRaw === '' ? null : (int) $stylingRaw;
    $performanceParts = $performanceRaw === '' ? null : (int) $performanceRaw;

    $partsInType = array_map('trim', preg_split('/\s*&\s*/', $workType));
    $hasStylingInType = in_array('Styling', $partsInType, true);
    $hasPerformanceInType = in_array('Prestanda', $partsInType, true);

    if ($hasStylingInType && $stylingParts === null) {
        json_response(['ok' => false, 'error' => 'Styling-delar krävs för valda arbetstyper.'], 422);
    }
    if ($hasPerformanceInType && $performanceParts === null) {
        json_response(['ok' => false, 'error' => 'Prestanda-delar krävs för valda arbetstyper.'], 422);
    }

    if (!is_valid_plate($plate) || $customer === '' || $amountRaw === '' || !is_numeric($amountRaw)) {
        json_response(['ok' => false, 'error' => 'Kontrollera kund, regplåt och summa.'], 422);
    }
    if ($customerPersonnummerInput !== '' && $customerPersonnummer === '') {
        json_response(['ok' => false, 'error' => 'Personnummer måste anges som ÅÅÅÅMMDD-XXXX.'], 422);
    }

    if ($expenseTotalInput === null || $expenseTotalInput === '') {
        $stmt = $pdo->prepare('SELECT expense_total, discount_name, discount_percent FROM receipts WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            json_response(['ok' => false, 'error' => 'Kvitto hittades inte.'], 404);
        }
        $expenseTotal = (float) ($existing['expense_total'] ?? 0);
        $discountName = (string) ($existing['discount_name'] ?? '');
        $discountPercent = (float) ($existing['discount_percent'] ?? 0);
    } else {
        $expenseTotal = (float) $expenseTotalInput;
        $discountName = $discountNameInput ?? '';
        $discountPercent = ($discountPercentInput === null || $discountPercentInput === '') ? 0.0 : (float) $discountPercentInput;
    }

    if ($discountNameInput !== null) {
        $discountName = $discountNameInput;
    }
    if ($discountPercentInput !== null && $discountPercentInput !== '') {
        $discountPercent = (float) $discountPercentInput;
    }
    if (!isset($discountPercent)) {
        $discountPercent = 0.0;
    }
    if ($discountPercent < 0 || $discountPercent > 100) {
        json_response(['ok' => false, 'error' => 'Rabattprocent måste vara mellan 0 och 100.'], 422);
    }
    if (!isset($discountName)) {
        $discountName = $discountNameInput ?? '';
    }
    if ($expenseTotal < 0) {
        json_response(['ok' => false, 'error' => 'Utgift måste vara 0 eller mer.'], 422);
    }

    $stmt = $pdo->prepare('UPDATE receipts SET work_type = ?, styling_parts = ?, performance_parts = ?, amount = ?, expense_total = ?, discount_name = ?, discount_percent = ?, customer = ?, customer_personnummer = ?, plate = ?, order_comment = ? WHERE id = ?');
    $stmt->execute([$workType, $stylingParts, $performanceParts, (float) $amountRaw, $expenseTotal, $discountName !== '' ? $discountName : null, $discountPercent, $customer, $customerPersonnummer !== '' ? $customerPersonnummer : null, $plate, $orderComment !== '' ? $orderComment : null, $id]);
    log_activity(
        $pdo,
        $user,
        'receipt_updated',
        'receipt',
        $id,
        sprintf('Kvitto #%d uppdaterades.', $id),
        [
            'customer' => $customer,
            'amount' => (float) $amountRaw,
        ]
    );
    json_response(['ok' => true]);
}

if ($action === 'api_delete_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_edit_receipts');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt kvitto-ID.'], 422);
    }
    $stmt = $pdo->prepare('SELECT customer FROM receipts WHERE id = ?');
    $stmt->execute([$id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receipt) {
        json_response(['ok' => false, 'error' => 'Kvitto hittades inte.'], 404);
    }
    $delete = $pdo->prepare('DELETE FROM receipts WHERE id = ?');
    $delete->execute([$id]);
    log_activity(
        $pdo,
        $user,
        'receipt_deleted',
        'receipt',
        $id,
        sprintf('Kvitto #%d raderades (%s).', $id, $receipt['customer'] ?? 'okänd')
    );
    json_response(['ok' => true]);
}

if ($action === 'api_mark_receipt_sent' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    $targetSent = (int) (($data['is_sent'] ?? 1) ? 1 : 0);

    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt kvitto-ID.'], 422);
    }

    $stmt = $pdo->prepare('SELECT is_sent FROM receipts WHERE id = ?');
    $stmt->execute([$id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receipt) {
        json_response(['ok' => false, 'error' => 'Kvitto hittades inte.'], 404);
    }

    $currentSent = (int) ($receipt['is_sent'] ?? 0);

    if ($targetSent === 0 && (int) ($user['permissions']['can_edit_receipts'] ?? 0) !== 1) {
        json_response(['ok' => false, 'error' => 'Endast ägare kan återställa skickade kvitton.'], 403);
    }

    if ($currentSent === $targetSent) {
        json_response(['ok' => true]);
    }

    $stmt = $pdo->prepare('UPDATE receipts SET is_sent = ? WHERE id = ?');
    $stmt->execute([$targetSent, $id]);
    $actionType = $targetSent === 1 ? 'receipt_marked_sent' : 'receipt_marked_unsent';
    log_activity(
        $pdo,
        $user,
        $actionType,
        'receipt',
        $id,
        $targetSent === 1 ? sprintf('Kvitto #%d markerades som skickat.', $id) : sprintf('Skickad-status återställd för kvitto #%d.', $id)
    );

    json_response(['ok' => true]);
}

if ($action === 'api_customers' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_customers');
    $sql = 'SELECT c.id, c.customer_name, c.personnummer, c.phone, c.discount_preset_id,
        dp.name AS discount_name, dp.percent AS discount_percent
        FROM customer_registry c
        LEFT JOIN discount_presets dp ON dp.id = c.discount_preset_id
        ORDER BY c.customer_name ASC';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'customers' => $rows]);
}

if ($action === 'api_create_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_view_customers');
    $data = read_json_input();
    $name = trim((string) ($data['customer_name'] ?? ''));
    $personnummerInput = trim((string) ($data['personnummer'] ?? ''));
    $personnummer = normalize_personnummer($personnummerInput);
    $phone = trim((string) ($data['phone'] ?? ''));
    $customerId = (int) ($data['id'] ?? 0);
    $discountPresetId = (int) ($data['discount_preset_id'] ?? 0);
    if ($discountPresetId <= 0) {
        $discountPresetId = null;
    }
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'Kundnamn måste anges.'], 422);
    }
    if ($personnummerInput !== '' && $personnummer === '') {
        json_response(['ok' => false, 'error' => 'Personnummer måste anges som ÅÅÅÅMMDD-XXXX.'], 422);
    }
    if ($discountPresetId !== null) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM discount_presets WHERE id = ?');
        $stmt->execute([$discountPresetId]);
        if ((int) $stmt->fetchColumn() === 0) {
            json_response(['ok' => false, 'error' => 'Ogiltig rabatt vald.'], 422);
        }
    }
    $existingId = null;
    if ($customerId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM customer_registry WHERE id = ?');
        $stmt->execute([$customerId]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);
        if ($existingId === 0) {
            $customerId = 0;
        }
    } elseif ($personnummer !== '') {
        $stmt = $pdo->prepare('SELECT id FROM customer_registry WHERE personnummer = ?');
        $stmt->execute([$personnummer]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM customer_registry WHERE customer_name = ? LIMIT 1');
        $stmt->execute([$name]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);
    }
    if ($customerId > 0) {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO customer_registry (id, customer_name, personnummer, phone, discount_preset_id) VALUES ((SELECT id FROM customer_registry WHERE id = ?), ?, ?, ?, ?)');
        $stmt->execute([$customerId, $name, $personnummer !== '' ? $personnummer : null, $phone, $discountPresetId]);
    } else {
        if ($personnummer !== '') {
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO customer_registry (id, customer_name, personnummer, phone, discount_preset_id) VALUES ((SELECT id FROM customer_registry WHERE personnummer = ?), ?, ?, ?, ?)');
            $stmt->execute([$personnummer, $name, $personnummer, $phone, $discountPresetId]);
        } else {
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO customer_registry (id, customer_name, personnummer, phone, discount_preset_id) VALUES ((SELECT id FROM customer_registry WHERE customer_name = ?), ?, ?, ?, ?)');
            $stmt->execute([$name, $name, null, $phone, $discountPresetId]);
        }
    }
    $savedId = (int) $pdo->lastInsertId();
    if ($savedId <= 0) {
        $savedId = $existingId ?? 0;
    }
    $actionType = ($existingId ?? 0) > 0 ? 'customer_updated' : 'customer_created';
    log_activity(
        $pdo,
        $user,
        $actionType,
        'customer',
        $savedId,
        sprintf('%s kund: %s%s', $actionType === 'customer_updated' ? 'Uppdaterade' : 'Skapade', $name, $personnummer !== '' ? ' (' . $personnummer . ')' : ''),
        [
            'phone' => $phone,
            'discount_preset_id' => $discountPresetId,
        ]
    );
    json_response(['ok' => true]);
}

if ($action === 'api_delete_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_view_customers');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt kund-ID.'], 422);
    }
    $stmt = $pdo->prepare('SELECT customer_name, personnummer FROM customer_registry WHERE id = ?');
    $stmt->execute([$id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        json_response(['ok' => false, 'error' => 'Kunden hittades inte.'], 404);
    }
    $delete = $pdo->prepare('DELETE FROM customer_registry WHERE id = ?');
    $delete->execute([$id]);
    log_activity(
        $pdo,
        $user,
        'customer_deleted',
        'customer',
        $id,
        sprintf('Kund %s raderades%s.', $customer['customer_name'] ?? 'okänd', ($customer['personnummer'] ?? '') !== '' ? ' (' . $customer['personnummer'] . ')' : '')
    );
    json_response(['ok' => true]);
}

if ($action === 'api_vehicles' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_vehicles');
    $rows = $pdo->query('SELECT id, plate, COALESCE(vehicle_model, vehicle_type) AS vehicle_model FROM vehicle_registry ORDER BY plate ASC')->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'vehicles' => $rows]);
}

if ($action === 'api_create_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_view_vehicles');
    $data = read_json_input();
    $vehicleId = (int) ($data['id'] ?? 0);
    $plate = normalize_plate((string) ($data['plate'] ?? ''));
    $model = trim((string) ($data['vehicle_model'] ?? ''));
    if (!is_valid_plate($plate)) {
        json_response(['ok' => false, 'error' => 'Regplåt måste vara i formatet XXX-XXX (t.ex. RAO-121 eller MIX-15J).'], 422);
    }
    if ($model === '') {
        json_response(['ok' => false, 'error' => 'Fordonsmodell måste anges.'], 422);
    }

    $vCols = $pdo->query('PRAGMA table_info(vehicle_registry)')->fetchAll(PDO::FETCH_ASSOC);
    $hasVehicleTypeCol = false;
    foreach ($vCols as $col) {
        if (($col['name'] ?? '') === 'vehicle_type') {
            $hasVehicleTypeCol = true;
            break;
        }
    }

    if ($hasVehicleTypeCol) {
        if ($vehicleId > 0) {
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO vehicle_registry (id, plate, vehicle_type, vehicle_model) VALUES ((SELECT id FROM vehicle_registry WHERE id = ?), ?, ?, ?)');
            $stmt->execute([$vehicleId, $plate, $model, $model]);
        } else {
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO vehicle_registry (id, plate, vehicle_type, vehicle_model) VALUES ((SELECT id FROM vehicle_registry WHERE plate = ?), ?, ?, ?)');
            $stmt->execute([$plate, $plate, $model, $model]);
        }
    } else {
        if ($vehicleId > 0) {
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO vehicle_registry (id, plate, vehicle_model) VALUES ((SELECT id FROM vehicle_registry WHERE id = ?), ?, ?)');
            $stmt->execute([$vehicleId, $plate, $model]);
        } else {
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO vehicle_registry (id, plate, vehicle_model) VALUES ((SELECT id FROM vehicle_registry WHERE plate = ?), ?, ?)');
            $stmt->execute([$plate, $plate, $model]);
        }
    }

    json_response(['ok' => true]);
}

if ($action === 'api_payroll_entries' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_admin');
    $sql = 'SELECT id, payee_name, amount, pay_date, created_at FROM payroll_entries ORDER BY DATE(pay_date) DESC, id DESC';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['amount'] = (float) ($row['amount'] ?? 0);
    }
    json_response(['ok' => true, 'entries' => $rows]);
}

if ($action === 'api_create_payroll_entry' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_view_admin');
    $data = read_json_input();
    $name = trim((string) ($data['payee_name'] ?? ''));
    $amountRaw = $data['amount'] ?? null;
    $amount = is_numeric($amountRaw) ? (float) $amountRaw : null;
    $payDate = trim((string) ($data['pay_date'] ?? ''));
    $entryId = (int) ($data['id'] ?? 0);

    if ($name === '') {
        json_response(['ok' => false, 'error' => 'Namn krävs.'], 422);
    }
    if ($amount === null || $amount <= 0) {
        json_response(['ok' => false, 'error' => 'Belopp måste vara större än 0.'], 422);
    }
    $dt = DateTime::createFromFormat('Y-m-d', $payDate);
    if (!$dt || $dt->format('Y-m-d') !== $payDate) {
        json_response(['ok' => false, 'error' => 'Datum måste vara i formatet ÅÅÅÅ-MM-DD.'], 422);
    }

    if ($entryId > 0) {
        $stmt = $pdo->prepare('UPDATE payroll_entries SET payee_name = ?, amount = ?, pay_date = ? WHERE id = ?');
        $stmt->execute([$name, $amount, $payDate, $entryId]);
        if ($stmt->rowCount() === 0) {
            json_response(['ok' => false, 'error' => 'Löneposten hittades inte.'], 404);
        }
    } else {
        $stmt = $pdo->prepare('INSERT INTO payroll_entries (payee_name, amount, pay_date) VALUES (?, ?, ?)');
        $stmt->execute([$name, $amount, $payDate]);
    }

    json_response(['ok' => true]);
}

if ($action === 'api_delete_payroll_entry' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_view_admin');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt löne-ID.'], 422);
    }
    $stmt = $pdo->prepare('DELETE FROM payroll_entries WHERE id = ?');
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}

if ($action === 'api_delete_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_view_vehicles');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt fordons-ID.'], 422);
    }
    $stmt = $pdo->prepare('DELETE FROM vehicle_registry WHERE id = ?');
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}

if ($action === 'api_ranks' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_manage_users');
    $rows = $pdo->query('SELECT id, name, can_view_admin, can_manage_users, can_manage_prices, can_edit_receipts, can_view_customers, can_view_vehicles, can_view_prices FROM ranks ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'ranks' => $rows]);
}

if ($action === 'api_save_rank' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_users');
    $data = read_json_input();
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'Ranknamn måste anges.'], 422);
    }
    $rankId = (int) ($data['id'] ?? 0);
    $canViewAdmin = (int) (($data['can_view_admin'] ?? 0) ? 1 : 0);
    $canManageUsers = (int) (($data['can_manage_users'] ?? 0) ? 1 : 0);
    $canManagePrices = (int) (($data['can_manage_prices'] ?? 0) ? 1 : 0);
    $canEditReceipts = (int) (($data['can_edit_receipts'] ?? 0) ? 1 : 0);
    $canViewCustomers = (int) (($data['can_view_customers'] ?? 0) ? 1 : 0);
    $canViewVehicles = (int) (($data['can_view_vehicles'] ?? 0) ? 1 : 0);
    $canViewPrices = (int) (($data['can_view_prices'] ?? 0) ? 1 : 0);

    if ($rankId > 0) {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO ranks (id, name, can_view_admin, can_manage_users, can_manage_prices, can_edit_receipts, can_view_customers, can_view_vehicles, can_view_prices) VALUES ((SELECT id FROM ranks WHERE id = ?), ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$rankId, $name, $canViewAdmin, $canManageUsers, $canManagePrices, $canEditReceipts, $canViewCustomers, $canViewVehicles, $canViewPrices]);
    } else {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO ranks (id, name, can_view_admin, can_manage_users, can_manage_prices, can_edit_receipts, can_view_customers, can_view_vehicles, can_view_prices) VALUES ((SELECT id FROM ranks WHERE name = ?), ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $name, $canViewAdmin, $canManageUsers, $canManagePrices, $canEditReceipts, $canViewCustomers, $canViewVehicles, $canViewPrices]);
    }
    json_response(['ok' => true]);
}

if ($action === 'api_activity_log' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_admin');
    $type = strtolower(trim((string) ($_GET['type'] ?? '')));
    $allowedTypes = ['receipt', 'customer'];
    $whereClause = '';
    if ($type !== '' && in_array($type, $allowedTypes, true)) {
        $whereClause = 'WHERE entity_type = ?';
    } else {
        $type = '';
    }
    $limit = (int) ($_GET['limit'] ?? 100);
    if ($limit <= 0) {
        $limit = 100;
    }
    if ($limit > 200) {
        $limit = 200;
    }
    $sql = 'SELECT id, created_at, actor_personnummer, actor_name, action_type, entity_type, entity_id, description FROM activity_log ' . $whereClause . ' ORDER BY id DESC LIMIT ?';
    $stmt = $pdo->prepare($sql);
    if ($whereClause !== '') {
        $stmt->bindValue(1, $type, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'entries' => $rows]);
}

if ($action === 'api_admin_summary' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_admin');

    $sales = (float) ($pdo->query('SELECT COALESCE(SUM(amount), 0) FROM receipts')->fetchColumn() ?: 0);

    $expenses = (float) ($pdo->query('SELECT COALESCE(SUM(expense_total), 0) FROM receipts')->fetchColumn() ?: 0);
    $profit = $sales - $expenses;
    $payrollTotal = (float) ($pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payroll_entries')->fetchColumn() ?: 0);
    $lastPayrollDate = (string) ($pdo->query('SELECT pay_date FROM payroll_entries ORDER BY DATE(pay_date) DESC, id DESC LIMIT 1')->fetchColumn() ?: '');

    $topSql = 'SELECT COALESCE(u.full_name, r.mechanic) AS mechanic_name, COUNT(*) AS receipt_count, COALESCE(SUM(r.amount), 0) AS total_sales
        FROM receipts r
        LEFT JOIN users u ON u.' . $userLoginColumn . ' = r.mechanic
        GROUP BY r.mechanic
        ORDER BY total_sales DESC
        LIMIT 10';
    $topMechanics = $pdo->query($topSql)->fetchAll(PDO::FETCH_ASSOC);

    $users = $pdo->query("SELECT u.id, u.$userLoginColumn AS personnummer, u.full_name, u.rank_id, COALESCE(r.name, '-') AS rank_name
        FROM users u LEFT JOIN ranks r ON r.id = u.rank_id ORDER BY u.id ASC")->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'ok' => true,
        'stats' => [
            'sales' => $sales,
            'expenses' => $expenses,
            'profit' => $profit,
            'receipt_count' => (int) ($pdo->query('SELECT COUNT(*) FROM receipts')->fetchColumn() ?: 0),
            'payroll_total' => $payrollTotal,
            'last_payroll_date' => $lastPayrollDate,
        ],
        'top_mechanics' => $topMechanics,
        'users' => $users,
    ]);
}

if ($action === 'api_admin_save_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_users');

    $data = read_json_input();
    $personnummer = trim((string) ($data['personnummer'] ?? ''));
    $fullName = trim((string) ($data['full_name'] ?? ''));
    $password = trim((string) ($data['password'] ?? ''));
    $rankId = (int) ($data['rank_id'] ?? 0);

    if ($personnummer === '' || $password === '' || $fullName === '') {
        json_response(['ok' => false, 'error' => 'Personnummer, namn och lösenord måste anges.'], 422);
    }

    $isAdmin = 0;
    if ($rankId > 0) {
        $stmt = $pdo->prepare('SELECT can_view_admin, can_manage_users, can_manage_prices, can_edit_receipts FROM ranks WHERE id = ?');
        $stmt->execute([$rankId]);
        $rank = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rank && (int) $rank['can_manage_users'] === 1 && (int) $rank['can_view_admin'] === 1) {
            $isAdmin = 1;
        }
    }

    $adminId = (int) ($data['id'] ?? 0);
    if ($adminId > 0) {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO users (id, $userLoginColumn, full_name, password, rank_id, is_admin) VALUES ((SELECT id FROM users WHERE id = ?), ?, ?, ?, ?, ?)");
        $stmt->execute([$adminId, $personnummer, $fullName, $password, $rankId > 0 ? $rankId : null, $isAdmin]);
    } else {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO users (id, $userLoginColumn, full_name, password, rank_id, is_admin) VALUES ((SELECT id FROM users WHERE $userLoginColumn = ?), ?, ?, ?, ?, ?)");
        $stmt->execute([$personnummer, $personnummer, $fullName, $password, $rankId > 0 ? $rankId : null, $isAdmin]);
    }

    json_response(['ok' => true]);
}

if ($action === 'api_layout_get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $stmt = $pdo->prepare('SELECT config_json, updated_at FROM layout_settings WHERE layout_key = ? LIMIT 1');
    $stmt->execute(['global_sections']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $layout = null;
    if ($row && isset($row['config_json']) && is_string($row['config_json'])) {
        $decoded = json_decode($row['config_json'], true);
        if (is_array($decoded) && !empty($decoded)) {
            $layout = $decoded;
        }
    }
    if (!is_array($layout) || empty($layout)) {
        $layout = normalize_layout_payload(DEFAULT_GLOBAL_LAYOUT_MAP);
    }
    json_response([
        'ok' => true,
        'layout' => $layout,
        'updated_at' => $row['updated_at'] ?? null,
        'preset_version' => DEFAULT_GLOBAL_LAYOUT_PRESET_VERSION,
    ]);
}

if ($action === 'api_layout_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_view_admin');
    $data = read_json_input();
    $layoutRaw = $data['layout'] ?? null;
    if (!is_array($layoutRaw) || empty($layoutRaw)) {
        json_response(['ok' => false, 'error' => 'Ogiltigt layout-format.'], 422);
    }
    $normalized = normalize_layout_payload($layoutRaw);
    if (empty($normalized)) {
        json_response(['ok' => false, 'error' => 'Kunde inte tolka layoutdata.'], 422);
    }
    $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare('INSERT INTO layout_settings (layout_key, config_json, updated_at)
        VALUES (:layout_key, :config_json, CURRENT_TIMESTAMP)
        ON CONFLICT(layout_key) DO UPDATE SET config_json = excluded.config_json, updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([
        ':layout_key' => 'global_sections',
        ':config_json' => $payload,
    ]);
    log_activity(
        $pdo,
        $user,
        'layout_saved',
        'layout',
        null,
        'Layouten uppdaterades.',
        ['layout_key' => 'global_sections']
    );
    json_response(['ok' => true, 'layout' => $normalized, 'preset_version' => DEFAULT_GLOBAL_LAYOUT_PRESET_VERSION]);
}

if ($action === 'api_layout_reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_view_admin');
    $normalizedDefault = normalize_layout_payload(DEFAULT_GLOBAL_LAYOUT_MAP);
    if (empty($normalizedDefault)) {
        json_response(['ok' => false, 'error' => 'Standardlayout saknas.'], 500);
    }
    $payload = json_encode($normalizedDefault, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare('INSERT INTO layout_settings (layout_key, config_json, updated_at)
        VALUES (:layout_key, :config_json, CURRENT_TIMESTAMP)
        ON CONFLICT(layout_key) DO UPDATE SET config_json = excluded.config_json, updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([
        ':layout_key' => 'global_sections',
        ':config_json' => $payload,
    ]);
    log_activity(
        $pdo,
        $user,
        'layout_reset',
        'layout',
        null,
        'Layouten återställdes till standard.',
        ['layout_key' => 'global_sections']
    );
    json_response(['ok' => true, 'layout' => $normalizedDefault, 'preset_version' => DEFAULT_GLOBAL_LAYOUT_PRESET_VERSION]);
}

if ($action !== '') {
    json_response(['ok' => false, 'error' => 'Ogiltig endpoint.'], 404);
}

$template = __DIR__ . '/index2.html';
if (!is_file($template)) {
    http_response_code(500);
    echo 'index2.html saknas.';
    exit;
}

header_remove('X-Frame-Options');
header('Content-Type: text/html; charset=utf-8');
readfile($template);

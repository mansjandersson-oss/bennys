<?php
declare(strict_types=1);

session_start();

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

$pdo->exec('CREATE TABLE IF NOT EXISTS ranks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    can_view_admin INTEGER NOT NULL DEFAULT 0,
    can_manage_users INTEGER NOT NULL DEFAULT 0,
    can_manage_prices INTEGER NOT NULL DEFAULT 0,
    can_edit_receipts INTEGER NOT NULL DEFAULT 0
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
    customer TEXT NOT NULL,
    plate TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS customer_registry (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_name TEXT UNIQUE NOT NULL,
    phone TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS vehicle_registry (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plate TEXT UNIQUE NOT NULL,
    vehicle_model TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS service_prices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_name TEXT UNIQUE NOT NULL,
    sale_price REAL NOT NULL DEFAULT 0,
    expense_cost REAL NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1
)');

$receiptColumns = $pdo->query('PRAGMA table_info(receipts)')->fetchAll(PDO::FETCH_ASSOC);
$hasStylingParts = false;
$hasPerformanceParts = false;
$hasLegacyPartsCount = false;
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

$userColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
$userColumnNames = array_map(static fn(array $c): string => (string) ($c['name'] ?? ''), $userColumns);
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

$rankSeed = [
    ['Admin', 1, 1, 1, 1],
    ['Chefmekaniker', 1, 0, 1, 1],
    ['Mekaniker', 0, 0, 0, 0],
];
$rankStmt = $pdo->prepare('INSERT OR IGNORE INTO ranks (name, can_view_admin, can_manage_users, can_manage_prices, can_edit_receipts) VALUES (?, ?, ?, ?, ?)');
foreach ($rankSeed as $rank) {
    $rankStmt->execute($rank);
}

$getRankId = static function (PDO $pdo, string $name): int {
    $stmt = $pdo->prepare('SELECT id FROM ranks WHERE name = ?');
    $stmt->execute([$name]);
    return (int) ($stmt->fetchColumn() ?: 0);
};

$adminRankId = $getRankId($pdo, 'Admin');
$mechRankId = $getRankId($pdo, 'Mekaniker');

$seedStmt = $pdo->prepare('INSERT OR IGNORE INTO users (personnummer, full_name, password, rank_id, is_admin) VALUES (?, ?, ?, ?, ?)');
$seedStmt->execute(['19900101-1234', 'Alex Benny', 'motor123', $adminRankId, 1]);
$seedStmt->execute(['19920202-5678', 'Robin Torque', 'garage123', $mechRankId, 0]);
$seedStmt->execute(['19950505-9012', 'Kim V8', 'bennys123', $mechRankId, 0]);

$serviceSeed = $pdo->prepare('INSERT OR IGNORE INTO service_prices (service_name, sale_price, expense_cost, is_active) VALUES (?, ?, ?, 1)');
$serviceSeed->execute(['Reperation', 1500, 700]);
$serviceSeed->execute(['Styling', 2500, 1200]);
$serviceSeed->execute(['Prestanda', 3500, 1800]);

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
    if (($permissions[$permission] ?? 0) !== 1) {
        json_response(['ok' => false, 'error' => 'Du saknar behörighet för detta.'], 403);
    }
}

$action = $_GET['action'] ?? '';

if ($action === 'api_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = read_json_input();
    $personnummer = trim((string) ($data['personnummer'] ?? ''));
    $password = trim((string) ($data['password'] ?? ''));

    $stmt = $pdo->prepare('SELECT u.personnummer, u.full_name, u.password, u.rank_id, u.is_admin, r.name AS rank_name,
        COALESCE(r.can_view_admin, 0) AS can_view_admin,
        COALESCE(r.can_manage_users, 0) AS can_manage_users,
        COALESCE(r.can_manage_prices, 0) AS can_manage_prices,
        COALESCE(r.can_edit_receipts, 0) AS can_edit_receipts
        FROM users u
        LEFT JOIN ranks r ON r.id = u.rank_id
        WHERE u.personnummer = ? AND u.password = ?');
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
    ];
    if ((int) ($user['is_admin'] ?? 0) === 1) {
        $permissions = [
            'can_view_admin' => 1,
            'can_manage_users' => 1,
            'can_manage_prices' => 1,
            'can_edit_receipts' => 1,
        ];
    }

    $_SESSION['personnummer'] = (string) ($user['personnummer'] ?? '');
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

if ($action === 'api_me') {
    $user = session_user();
    if ($user['personnummer'] === '') {
        json_response(['ok' => true, 'user' => null]);
    }
    json_response(['ok' => true, 'user' => $user]);
}

if ($action === 'api_service_prices' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $rows = $pdo->query('SELECT id, service_name, sale_price, expense_cost, is_active FROM service_prices ORDER BY service_name ASC')->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'services' => $rows]);
}

if ($action === 'api_save_service_price' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_prices');
    $data = read_json_input();
    $name = trim((string) ($data['service_name'] ?? ''));
    $salePrice = (float) ($data['sale_price'] ?? 0);
    $expenseCost = (float) ($data['expense_cost'] ?? 0);
    $isActive = (int) (($data['is_active'] ?? 0) ? 1 : 0);

    if ($name === '') {
        json_response(['ok' => false, 'error' => 'Tjänstens namn måste anges.'], 422);
    }

    $stmt = $pdo->prepare('INSERT OR REPLACE INTO service_prices (id, service_name, sale_price, expense_cost, is_active) VALUES ((SELECT id FROM service_prices WHERE service_name = ?), ?, ?, ?, ?)');
    $stmt->execute([$name, $name, $salePrice, $expenseCost, $isActive]);
    json_response(['ok' => true]);
}

if ($action === 'api_receipts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $rows = $pdo->query('SELECT r.id, r.mechanic, COALESCE(u.full_name, r.mechanic) AS mechanic_name, r.work_type, r.styling_parts, r.performance_parts, r.amount, r.customer, r.plate, r.created_at
        FROM receipts r
        LEFT JOIN users u ON u.personnummer = r.mechanic
        ORDER BY r.id DESC')->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['styling_parts'] = $row['styling_parts'] === null ? '' : (int) $row['styling_parts'];
        $row['performance_parts'] = $row['performance_parts'] === null ? '' : (int) $row['performance_parts'];
        $row['amount'] = (float) $row['amount'];
        $row['work_order'] = "Benny's Arbetsorder - " . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT);
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
    $plate = strtoupper(trim((string) ($data['plate'] ?? '')));

    $allowedWorkTypes = ['Reperation', 'Styling', 'Prestanda'];
    $errors = [];

    if (!in_array($workType, $allowedWorkTypes, true)) {
        $errors[] = 'Välj en giltig typ av arbete.';
    }

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

    if ($workType === 'Styling' && $stylingParts === null) {
        $errors[] = 'Styling-delar krävs för Styling.';
    }
    if ($workType === 'Prestanda' && $performanceParts === null) {
        $errors[] = 'Prestanda-delar krävs för Prestanda.';
    }
    if ($workType === 'Reperation' && ($stylingParts !== null || $performanceParts !== null)) {
        $errors[] = 'Reperation kan inte skickas med Styling/Prestanda-delar ifyllda.';
    }

    if ($customer === '') {
        $errors[] = 'Kund måste anges.';
    }
    if (!preg_match('/^[A-Z]{3}-\d{3}$/', $plate)) {
        $errors[] = 'Regplåt måste vara i formatet XXX-000.';
    }
    if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw < 0) {
        $errors[] = 'Summa måste vara ett positivt tal.';
    }

    if ($errors) {
        json_response(['ok' => false, 'error' => implode(' ', $errors)], 422);
    }

    $stmt = $pdo->prepare('INSERT INTO receipts (mechanic, work_type, styling_parts, performance_parts, amount, customer, plate) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$user['personnummer'], $workType, $stylingParts, $performanceParts, (float) $amountRaw, $customer, $plate]);
    json_response(['ok' => true]);
}

if ($action === 'api_update_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_edit_receipts');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    $workType = trim((string) ($data['work_type'] ?? ''));
    $stylingRaw = trim((string) ($data['styling_parts'] ?? ''));
    $performanceRaw = trim((string) ($data['performance_parts'] ?? ''));
    $amountRaw = trim((string) ($data['amount'] ?? ''));
    $customer = trim((string) ($data['customer'] ?? ''));
    $plate = strtoupper(trim((string) ($data['plate'] ?? '')));

    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt kvitto-ID.'], 422);
    }

    $allowedWorkTypes = ['Reperation', 'Styling', 'Prestanda'];
    if (!in_array($workType, $allowedWorkTypes, true)) {
        json_response(['ok' => false, 'error' => 'Välj en giltig typ av arbete.'], 422);
    }

    $stylingParts = $stylingRaw === '' ? null : (int) $stylingRaw;
    $performanceParts = $performanceRaw === '' ? null : (int) $performanceRaw;

    if ($workType === 'Reperation' && ($stylingParts !== null || $performanceParts !== null)) {
        json_response(['ok' => false, 'error' => 'Reperation kan inte ha styling/prestanda-delar.'], 422);
    }

    if (!preg_match('/^[A-Z]{3}-\d{3}$/', $plate) || $customer === '' || $amountRaw === '' || !is_numeric($amountRaw)) {
        json_response(['ok' => false, 'error' => 'Kontrollera kund, regplåt och summa.'], 422);
    }

    $stmt = $pdo->prepare('UPDATE receipts SET work_type = ?, styling_parts = ?, performance_parts = ?, amount = ?, customer = ?, plate = ? WHERE id = ?');
    $stmt->execute([$workType, $stylingParts, $performanceParts, (float) $amountRaw, $customer, $plate, $id]);
    json_response(['ok' => true]);
}

if ($action === 'api_delete_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_edit_receipts');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Ogiltigt kvitto-ID.'], 422);
    }
    $stmt = $pdo->prepare('DELETE FROM receipts WHERE id = ?');
    $stmt->execute([$id]);
    json_response(['ok' => true]);
}

if ($action === 'api_customers' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $rows = $pdo->query('SELECT id, customer_name, phone FROM customer_registry ORDER BY customer_name ASC')->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'customers' => $rows]);
}

if ($action === 'api_create_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $data = read_json_input();
    $name = trim((string) ($data['customer_name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    if ($name === '') {
        json_response(['ok' => false, 'error' => 'Kundnamn måste anges.'], 422);
    }
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO customer_registry (id, customer_name, phone) VALUES ((SELECT id FROM customer_registry WHERE customer_name = ?), ?, ?)');
    $stmt->execute([$name, $name, $phone]);
    json_response(['ok' => true]);
}

if ($action === 'api_vehicles' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $rows = $pdo->query('SELECT id, plate, COALESCE(vehicle_model, vehicle_type) AS vehicle_model FROM vehicle_registry ORDER BY plate ASC')->fetchAll(PDO::FETCH_ASSOC);
    json_response(['ok' => true, 'vehicles' => $rows]);
}

if ($action === 'api_create_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $data = read_json_input();
    $plate = strtoupper(trim((string) ($data['plate'] ?? '')));
    $model = trim((string) ($data['vehicle_model'] ?? ''));
    if (!preg_match('/^[A-Z]{3}-\d{3}$/', $plate)) {
        json_response(['ok' => false, 'error' => 'Regplåt måste vara i formatet XXX-000.'], 422);
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
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO vehicle_registry (id, plate, vehicle_type, vehicle_model) VALUES ((SELECT id FROM vehicle_registry WHERE plate = ?), ?, ?, ?)');
        $stmt->execute([$plate, $plate, $model, $model]);
    } else {
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO vehicle_registry (id, plate, vehicle_model) VALUES ((SELECT id FROM vehicle_registry WHERE plate = ?), ?, ?)');
        $stmt->execute([$plate, $plate, $model]);
    }

    json_response(['ok' => true]);
}

if ($action === 'api_ranks' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_manage_users');
    $rows = $pdo->query('SELECT id, name, can_view_admin, can_manage_users, can_manage_prices, can_edit_receipts FROM ranks ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
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
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO ranks (id, name, can_view_admin, can_manage_users, can_manage_prices, can_edit_receipts) VALUES ((SELECT id FROM ranks WHERE name = ?), ?, ?, ?, ?, ?)');
    $stmt->execute([
        $name,
        $name,
        (int) (($data['can_view_admin'] ?? 0) ? 1 : 0),
        (int) (($data['can_manage_users'] ?? 0) ? 1 : 0),
        (int) (($data['can_manage_prices'] ?? 0) ? 1 : 0),
        (int) (($data['can_edit_receipts'] ?? 0) ? 1 : 0),
    ]);
    json_response(['ok' => true]);
}

if ($action === 'api_admin_summary' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_admin');

    $sales = (float) ($pdo->query('SELECT COALESCE(SUM(amount), 0) FROM receipts')->fetchColumn() ?: 0);

    $expenseStmt = $pdo->query('SELECT COALESCE(SUM(COALESCE(sp.expense_cost, 0)), 0)
        FROM receipts r
        LEFT JOIN service_prices sp ON sp.service_name = r.work_type');
    $expenses = (float) ($expenseStmt->fetchColumn() ?: 0);
    $profit = $sales - $expenses;

    $topMechanics = $pdo->query('SELECT COALESCE(u.full_name, r.mechanic) AS mechanic_name, COUNT(*) AS receipt_count, COALESCE(SUM(r.amount), 0) AS total_sales
        FROM receipts r
        LEFT JOIN users u ON u.personnummer = r.mechanic
        GROUP BY r.mechanic
        ORDER BY total_sales DESC
        LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);

    $users = $pdo->query('SELECT u.id, u.personnummer, u.full_name, u.rank_id, COALESCE(r.name, "-") AS rank_name
        FROM users u LEFT JOIN ranks r ON r.id = u.rank_id ORDER BY u.id ASC')->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'ok' => true,
        'stats' => [
            'sales' => $sales,
            'expenses' => $expenses,
            'profit' => $profit,
            'receipt_count' => (int) ($pdo->query('SELECT COUNT(*) FROM receipts')->fetchColumn() ?: 0),
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

    $stmt = $pdo->prepare('INSERT OR REPLACE INTO users (id, personnummer, full_name, password, rank_id, is_admin) VALUES ((SELECT id FROM users WHERE personnummer = ?), ?, ?, ?, ?, ?)');
    $stmt->execute([$personnummer, $personnummer, $fullName, $password, $rankId > 0 ? $rankId : null, $isAdmin]);

    json_response(['ok' => true]);
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

header('Content-Type: text/html; charset=utf-8');
readfile($template);

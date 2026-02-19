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

$pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    personnummer TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
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

$columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
$userColumn = 'personnummer';
$hasPersonnummer = false;
foreach ($columns as $column) {
    if (($column['name'] ?? '') === 'personnummer') {
        $hasPersonnummer = true;
        break;
    }
}
if (!$hasPersonnummer) {
    $userColumn = 'username';
}

$seedStmt = $pdo->prepare("INSERT OR IGNORE INTO users ($userColumn, password) VALUES (?, ?)");
$seedStmt->execute(['19900101-1234', 'motor123']);
$seedStmt->execute(['19920202-5678', 'garage123']);
$seedStmt->execute(['19950505-9012', 'bennys123']);

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_login(): string
{
    $user = $_SESSION['personnummer'] ?? '';
    if ($user === '') {
        json_response(['ok' => false, 'error' => 'Ej inloggad.'], 401);
    }
    return $user;
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

$action = $_GET['action'] ?? '';

if ($action === 'api_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = read_json_input();
    $personnummer = trim((string) ($data['personnummer'] ?? ''));
    $password = trim((string) ($data['password'] ?? ''));

    $stmt = $pdo->prepare("SELECT $userColumn FROM users WHERE $userColumn = ? AND password = ?");
    $stmt->execute([$personnummer, $password]);
    $user = $stmt->fetchColumn();

    if (!$user) {
        json_response(['ok' => false, 'error' => 'Fel personnummer eller lösenord.'], 401);
    }

    $_SESSION['personnummer'] = (string) $user;
    json_response(['ok' => true, 'user' => (string) $user]);
}

if ($action === 'api_logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_unset();
    session_destroy();
    session_start();
    json_response(['ok' => true]);
}

if ($action === 'api_me') {
    $user = $_SESSION['personnummer'] ?? null;
    json_response(['ok' => true, 'user' => $user]);
}

if ($action === 'api_receipts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $rows = $pdo->query('SELECT id, mechanic, work_type, styling_parts, performance_parts, amount, customer, plate, created_at FROM receipts ORDER BY id DESC')
        ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['styling_parts'] = $row['styling_parts'] === null ? '' : (int) $row['styling_parts'];
        $row['performance_parts'] = $row['performance_parts'] === null ? '' : (int) $row['performance_parts'];
        $row['amount'] = (float) $row['amount'];
        $row['work_order'] = "Benny's Arbetsorder - " . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT);
    }

    json_response(['ok' => true, 'receipts' => $rows]);
}

if ($action === 'api_create_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mechanic = require_login();
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

    if ($workType === 'Styling') {
        if ($stylingRaw === '' || !ctype_digit($stylingRaw)) {
            $errors[] = 'Styling-delar krävs för Styling.';
        } else {
            $stylingParts = (int) $stylingRaw;
        }
    }

    if ($workType === 'Prestanda') {
        if ($performanceRaw === '' || !ctype_digit($performanceRaw)) {
            $errors[] = 'Prestanda-delar krävs för Prestanda.';
        } else {
            $performanceParts = (int) $performanceRaw;
        }
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
    $stmt->execute([$mechanic, $workType, $stylingParts, $performanceParts, (float) $amountRaw, $customer, $plate]);

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

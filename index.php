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
    parts_count INTEGER,
    amount REAL NOT NULL,
    customer TEXT NOT NULL,
    plate TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');


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
    $rows = $pdo->query('SELECT id, mechanic, work_type, parts_count, amount, customer, plate, created_at FROM receipts ORDER BY id DESC')
        ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['parts_count'] = $row['parts_count'] === null ? '' : (int) $row['parts_count'];
        $row['amount'] = (float) $row['amount'];
        $row['work_order'] = "Benny's Arbetsorder - " . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT);
    }

    json_response(['ok' => true, 'receipts' => $rows]);
}

if ($action === 'api_create_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mechanic = require_login();
    $data = read_json_input();

    $workType = trim((string) ($data['work_type'] ?? ''));
    $partsRaw = trim((string) ($data['parts_count'] ?? ''));
    $amountRaw = trim((string) ($data['amount'] ?? ''));
    $customer = trim((string) ($data['customer'] ?? ''));
    $plate = strtoupper(trim((string) ($data['plate'] ?? '')));

    $allowedWorkTypes = ['Reperation', 'Styling', 'Prestanda'];
    $errors = [];

    if (!in_array($workType, $allowedWorkTypes, true)) {
        $errors[] = 'Välj en giltig typ av arbete.';
    }

    $partsCount = null;
    if (in_array($workType, ['Styling', 'Prestanda'], true)) {
        if ($partsRaw === '' || !ctype_digit($partsRaw)) {
            $errors[] = 'Antal delar krävs för Styling/Prestanda.';
        } else {
            $partsCount = (int) $partsRaw;
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

    $stmt = $pdo->prepare('INSERT INTO receipts (mechanic, work_type, parts_count, amount, customer, plate) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$mechanic, $workType, $partsCount, (float) $amountRaw, $customer, $plate]);

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

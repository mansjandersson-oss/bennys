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

const TABLES = [
    'ranks',
    'users',
    'receipts',
    'customer_registry',
    'vehicle_registry',
    'discount_presets',
    'service_prices',
    'payroll_entries',
    'activity_log',
    'layout_settings',
];

final class JsonDb
{
    private string $dir;
    /** @var array<string,array{next_id:int,rows:array<int,array<string,mixed>>}> */
    private array $cache = [];

    public function __construct(string $dir)
    {
        $this->dir = $dir;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    public function path(string $table): string
    {
        return $this->dir . '/' . $table . '.json';
    }

    /** @return array{next_id:int,rows:array<int,array<string,mixed>>} */
    public function table(string $table): array
    {
        if (isset($this->cache[$table])) {
            return $this->cache[$table];
        }
        $path = $this->path($table);
        if (!is_file($path)) {
            $data = ['next_id' => 1, 'rows' => []];
            $this->cache[$table] = $data;
            return $data;
        }
        $raw = file_get_contents($path);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $rows = is_array($decoded['rows'] ?? null) ? array_values($decoded['rows']) : [];
        $nextId = (int) ($decoded['next_id'] ?? 1);
        if ($nextId < 1) {
            $nextId = 1;
        }
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['id'])) {
                $nextId = max($nextId, ((int) $row['id']) + 1);
            }
        }
        $data = ['next_id' => $nextId, 'rows' => $rows];
        $this->cache[$table] = $data;
        return $data;
    }

    public function saveTable(string $table, array $data): void
    {
        $this->cache[$table] = $data;
        $path = $this->path($table);
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** @return array<int,array<string,mixed>> */
    public function rows(string $table): array
    {
        return $this->table($table)['rows'];
    }

    /** @param array<int,array<string,mixed>> $rows */
    public function setRows(string $table, array $rows): void
    {
        $next = 1;
        foreach ($rows as $row) {
            $next = max($next, ((int) ($row['id'] ?? 0)) + 1);
        }
        $this->saveTable($table, ['next_id' => $next, 'rows' => array_values($rows)]);
    }

    /** @param array<string,mixed> $row */
    public function insert(string $table, array $row): array
    {
        $data = $this->table($table);
        if (!isset($row['id']) || (int) $row['id'] <= 0) {
            $row['id'] = $data['next_id'];
            $data['next_id']++;
        } else {
            $row['id'] = (int) $row['id'];
            $data['next_id'] = max($data['next_id'], $row['id'] + 1);
        }
        $data['rows'][] = $row;
        $this->saveTable($table, $data);
        return $row;
    }
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
    if ($trimmed === '') return '';
    $digits = preg_replace('/\D+/', '', $trimmed);
    if (!is_string($digits) || strlen($digits) !== 12) return '';
    return substr($digits, 0, 8) . '-' . substr($digits, 8);
}

function normalize_plate(?string $value): string
{
    $trimmed = strtoupper(trim((string) $value));
    if ($trimmed === '') return '';
    $compact = preg_replace('/[^A-Z0-9]/', '', $trimmed);
    if (!is_string($compact) || strlen($compact) !== 6) return $trimmed;
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
        if (!is_string($key) || $key === '') continue;
        if ($key[0] === '_' && !is_array($config)) {
            $normalized[$key] = $config;
            continue;
        }
        if (!is_array($config)) continue;
        $x = max(0, min(8000, (int) floor((float) ($config['x'] ?? 0))));
        $y = max(0, min(8000, (int) floor((float) ($config['y'] ?? 0))));
        $w = max(200, min(8000, (int) ceil((float) ($config['w'] ?? 0))));
        $h = max(200, min(8000, (int) ceil((float) ($config['h'] ?? 0))));
        $z = max(1, min(999, (int) ceil((float) ($config['z'] ?? 1))));
        $normalized[$key] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'z' => $z];
    }
    return $normalized;
}

function now_iso(): string
{
    return date('Y-m-d H:i:s');
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

function rank_permissions(?array $rank): array
{
    $keys = ['can_view_admin', 'can_manage_users', 'can_manage_prices', 'can_edit_receipts', 'can_view_customers', 'can_view_vehicles', 'can_view_prices'];
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = (int) (($rank[$k] ?? 0) ? 1 : 0);
    }
    return $out;
}

function find_by_id(array $rows, int $id): ?array
{
    foreach ($rows as $row) {
        if ((int) ($row['id'] ?? 0) === $id) return $row;
    }
    return null;
}

function log_activity(JsonDb $db, array $user, string $actionType, string $entityType, ?int $entityId, string $description, ?array $meta = null): void
{
    $db->insert('activity_log', [
        'created_at' => now_iso(),
        'actor_personnummer' => (string) ($user['personnummer'] ?? ''),
        'actor_name' => (string) ($user['full_name'] ?? ''),
        'action_type' => $actionType,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'description' => $description,
        'meta_json' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function sqlite_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$table]);
    return (int) ($stmt->fetchColumn() ?: 0) > 0;
}

function maybe_migrate_from_sqlite(JsonDb $db, string $sqlitePath): void
{
    $hasAnyJson = false;
    foreach (TABLES as $t) {
        if (is_file($db->path($t))) {
            $hasAnyJson = true;
            break;
        }
    }
    if ($hasAnyJson || !is_file($sqlitePath) || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        return;
    }

    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach (TABLES as $table) {
        if (!sqlite_table_exists($pdo, $table)) {
            $db->saveTable($table, ['next_id' => 1, 'rows' => []]);
            continue;
        }
        $rows = $pdo->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
        $next = 1;
        foreach ($rows as $r) {
            $next = max($next, ((int) ($r['id'] ?? 0)) + 1);
        }
        $db->saveTable($table, ['next_id' => $next, 'rows' => $rows]);
    }
}

function seed_if_empty(JsonDb $db): void
{
    $ranks = $db->rows('ranks');
    if (count($ranks) === 0) {
        $db->insert('ranks', ['name' => 'Ägare', 'can_view_admin' => 1, 'can_manage_users' => 1, 'can_manage_prices' => 1, 'can_edit_receipts' => 1, 'can_view_customers' => 1, 'can_view_vehicles' => 1, 'can_view_prices' => 1]);
        $db->insert('ranks', ['name' => 'Anställd', 'can_view_admin' => 0, 'can_manage_users' => 0, 'can_manage_prices' => 0, 'can_edit_receipts' => 0, 'can_view_customers' => 1, 'can_view_vehicles' => 1, 'can_view_prices' => 1]);
    }

    $ranks = $db->rows('ranks');
    $owner = null; $employee = null;
    foreach ($ranks as $r) {
        if (($r['name'] ?? '') === 'Ägare') $owner = $r;
        if (($r['name'] ?? '') === 'Anställd') $employee = $r;
    }

    $users = $db->rows('users');
    if (count($users) === 0) {
        $db->insert('users', ['personnummer' => '19900101-1234', 'full_name' => 'Stefan Örn', 'password' => 'motor123', 'rank_id' => (int) ($owner['id'] ?? 0), 'is_admin' => 1]);
        $db->insert('users', ['personnummer' => '19920202-5678', 'full_name' => 'Garage Anställd', 'password' => 'garage123', 'rank_id' => (int) ($employee['id'] ?? 0), 'is_admin' => 0]);
        $db->insert('users', ['personnummer' => '19950505-9012', 'full_name' => 'Benny Demo', 'password' => 'bennys123', 'rank_id' => (int) ($employee['id'] ?? 0), 'is_admin' => 0]);
    }

    if (count($db->rows('discount_presets')) === 0) {
        $db->insert('discount_presets', ['name' => 'Familj', 'percent' => 45]);
        $db->insert('discount_presets', ['name' => 'Anställd', 'percent' => 50]);
    }

    if (count($db->rows('service_prices')) === 0) {
        $db->insert('service_prices', ['service_name' => 'Service', 'sale_price' => 300, 'expense_cost' => 120, 'is_active' => 1, 'has_dropdown' => 0, 'service_category' => 'Övrigt']);
        $db->insert('service_prices', ['service_name' => 'Reparation', 'sale_price' => 1000, 'expense_cost' => 500, 'is_active' => 1, 'has_dropdown' => 0, 'service_category' => 'Övrigt']);
    }

    if (count($db->rows('layout_settings')) === 0) {
        $db->insert('layout_settings', ['layout_key' => 'global_sections', 'config_json' => json_encode(normalize_layout_payload(DEFAULT_GLOBAL_LAYOUT_MAP), JSON_UNESCAPED_UNICODE), 'updated_at' => now_iso()]);
    }
}

$dbDir = __DIR__ . '/data';
$db = new JsonDb($dbDir . '/json');
maybe_migrate_from_sqlite($db, $dbDir . '/bennys.sqlite');
foreach (TABLES as $tableName) {
    if (!is_file($db->path($tableName))) {
        $db->saveTable($tableName, ['next_id' => 1, 'rows' => []]);
    }
}
seed_if_empty($db);

$action = (string) ($_GET['action'] ?? '');

if ($action === 'api_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = read_json_input();
    $personnummer = trim((string) ($data['personnummer'] ?? ''));
    $password = trim((string) ($data['password'] ?? ''));

    $foundUser = null;
    foreach ($db->rows('users') as $u) {
        if ((string) ($u['personnummer'] ?? '') === $personnummer) {
            $foundUser = $u;
            break;
        }
    }

    if (!$foundUser || (string) ($foundUser['password'] ?? '') !== $password) {
        json_response(['ok' => false, 'error' => 'Fel personnummer eller lösenord.'], 401);
    }

    $rank = find_by_id($db->rows('ranks'), (int) ($foundUser['rank_id'] ?? 0));
    $permissions = rank_permissions($rank);

    $_SESSION['personnummer'] = (string) $foundUser['personnummer'];
    $_SESSION['full_name'] = (string) ($foundUser['full_name'] ?? '');
    $_SESSION['rank_id'] = (int) ($foundUser['rank_id'] ?? 0);
    $_SESSION['rank_name'] = (string) ($rank['name'] ?? '');
    $_SESSION['permissions'] = $permissions;

    json_response(['ok' => true]);
}

if ($action === 'api_logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_destroy();
    json_response(['ok' => true]);
}

if ($action === 'api_me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = session_user();
    if ($user['personnummer'] === '') {
        json_response(['ok' => true, 'user' => null]);
    }
    json_response(['ok' => true, 'user' => $user]);
}

if ($action === 'api_service_prices' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $rows = $db->rows('service_prices');
    usort($rows, static fn($a, $b) => strcmp((string) ($a['service_category'] ?? ''), (string) ($b['service_category'] ?? '')) ?: strcmp((string) ($a['service_name'] ?? ''), (string) ($b['service_name'] ?? '')));
    json_response(['ok' => true, 'services' => array_values($rows)]);
}

if ($action === 'api_discount_presets' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $rows = $db->rows('discount_presets');
    usort($rows, static fn($a, $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
    json_response(['ok' => true, 'discounts' => array_values($rows)]);
}

if ($action === 'api_save_discount_preset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_manage_prices');
    $data = read_json_input();
    $id = (int) ($data['id'] ?? 0);
    $name = trim((string) ($data['name'] ?? ''));
    $percent = (float) ($data['percent'] ?? 0);
    if ($name === '') json_response(['ok' => false, 'error' => 'Namn krävs.'], 422);

    $rows = $db->rows('discount_presets');
    $updated = false;
    foreach ($rows as &$row) {
        if (($id > 0 && (int) $row['id'] === $id) || ($id <= 0 && strcasecmp((string) ($row['name'] ?? ''), $name) === 0)) {
            $row['name'] = $name;
            $row['percent'] = $percent;
            $updated = true;
            $id = (int) $row['id'];
            break;
        }
    }
    unset($row);
    if ($updated) {
        $db->setRows('discount_presets', $rows);
    } else {
        $inserted = $db->insert('discount_presets', ['name' => $name, 'percent' => $percent]);
        $id = (int) $inserted['id'];
    }
    log_activity($db, $user, 'discount_saved', 'discount', $id, 'Rabatt sparad.', ['name' => $name, 'percent' => $percent]);
    json_response(['ok' => true]);
}

if ($action === 'api_delete_discount_preset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_manage_prices');
    $id = (int) (read_json_input()['id'] ?? 0);
    $rows = array_values(array_filter($db->rows('discount_presets'), static fn($r) => (int) ($r['id'] ?? 0) !== $id));
    $db->setRows('discount_presets', $rows);

    $customers = $db->rows('customer_registry');
    foreach ($customers as &$c) {
        if ((int) ($c['discount_preset_id'] ?? 0) === $id) $c['discount_preset_id'] = null;
    }
    unset($c);
    $db->setRows('customer_registry', $customers);
    log_activity($db, $user, 'discount_deleted', 'discount', $id, 'Rabatt raderad.');
    json_response(['ok' => true]);
}

if ($action === 'api_save_service_price' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_manage_prices');
    $d = read_json_input();
    $id = (int) ($d['id'] ?? 0);
    $name = trim((string) ($d['service_name'] ?? ''));
    if ($name === '') json_response(['ok' => false, 'error' => 'Tjänstnamn krävs.'], 422);
    $rows = $db->rows('service_prices');
    $updated = false;
    foreach ($rows as &$r) {
        if (($id > 0 && (int) $r['id'] === $id) || ($id <= 0 && strcasecmp((string) ($r['service_name'] ?? ''), $name) === 0)) {
            $r['service_name'] = $name;
            $r['sale_price'] = (float) ($d['sale_price'] ?? 0);
            $r['expense_cost'] = (float) ($d['expense_cost'] ?? 0);
            $r['is_active'] = (int) ($d['is_active'] ?? 1) === 1 ? 1 : 0;
            $r['has_dropdown'] = (int) ($d['has_dropdown'] ?? 0) === 1 ? 1 : 0;
            $r['service_category'] = trim((string) ($d['service_category'] ?? '')) ?: 'Övrigt';
            $id = (int) $r['id'];
            $updated = true;
            break;
        }
    }
    unset($r);
    if ($updated) {
        $db->setRows('service_prices', $rows);
    } else {
        $inserted = $db->insert('service_prices', [
            'service_name' => $name,
            'sale_price' => (float) ($d['sale_price'] ?? 0),
            'expense_cost' => (float) ($d['expense_cost'] ?? 0),
            'is_active' => (int) ($d['is_active'] ?? 1) === 1 ? 1 : 0,
            'has_dropdown' => (int) ($d['has_dropdown'] ?? 0) === 1 ? 1 : 0,
            'service_category' => trim((string) ($d['service_category'] ?? '')) ?: 'Övrigt',
        ]);
        $id = (int) $inserted['id'];
    }
    log_activity($db, $user, 'service_saved', 'service_price', $id, 'Tjänst sparad.');
    json_response(['ok' => true]);
}

if ($action === 'api_delete_service_price' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_prices');
    $id = (int) (read_json_input()['id'] ?? 0);
    $db->setRows('service_prices', array_values(array_filter($db->rows('service_prices'), static fn($r) => (int) ($r['id'] ?? 0) !== $id)));
    json_response(['ok' => true]);
}

if ($action === 'api_receipts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $usersByPnr = [];
    foreach ($db->rows('users') as $u) $usersByPnr[(string) ($u['personnummer'] ?? '')] = (string) ($u['full_name'] ?? '');
    $rows = $db->rows('receipts');
    usort($rows, static fn($a, $b) => (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));
    foreach ($rows as &$row) {
        $row['styling_parts'] = $row['styling_parts'] ?? '';
        $row['performance_parts'] = $row['performance_parts'] ?? '';
        $row['amount'] = (float) ($row['amount'] ?? 0);
        $row['expense_total'] = (float) ($row['expense_total'] ?? 0);
        $row['discount_percent'] = (float) ($row['discount_percent'] ?? 0);
        $row['is_sent'] = (int) ($row['is_sent'] ?? 0);
        $row['mechanic_name'] = $usersByPnr[(string) ($row['mechanic'] ?? '')] ?? (string) ($row['mechanic'] ?? '');
        $row['work_order'] = "Redline Performance Arbetsorder - " . str_pad((string) ($row['id'] ?? 0), 5, '0', STR_PAD_LEFT);
    }
    unset($row);
    json_response(['ok' => true, 'receipts' => $rows]);
}

if ($action === 'api_create_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    $d = read_json_input();
    $plate = normalize_plate((string) ($d['plate'] ?? ''));
    if (!is_valid_plate($plate)) json_response(['ok' => false, 'error' => 'Ogiltigt registreringsnummer.'], 422);

    $inserted = $db->insert('receipts', [
        'mechanic' => (string) ($user['personnummer'] ?? ''),
        'work_type' => trim((string) ($d['work_type'] ?? '')),
        'styling_parts' => ($d['styling_parts'] === '' || $d['styling_parts'] === null) ? '' : (int) $d['styling_parts'],
        'performance_parts' => ($d['performance_parts'] === '' || $d['performance_parts'] === null) ? '' : (int) $d['performance_parts'],
        'amount' => (float) ($d['amount'] ?? 0),
        'expense_total' => (float) ($d['expense_total'] ?? 0),
        'discount_name' => trim((string) ($d['discount_name'] ?? '')),
        'discount_percent' => (float) ($d['discount_percent'] ?? 0),
        'customer' => trim((string) ($d['customer'] ?? '')),
        'customer_personnummer' => normalize_personnummer((string) ($d['customer_personnummer'] ?? '')),
        'plate' => $plate,
        'order_comment' => trim((string) ($d['order_comment'] ?? '')),
        'created_at' => now_iso(),
        'is_sent' => 0,
    ]);
    log_activity($db, $user, 'receipt_created', 'receipt', (int) $inserted['id'], 'Kvitto skapades.');
    json_response(['ok' => true]);
}

if ($action === 'api_update_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_edit_receipts');
    $d = read_json_input();
    $id = (int) ($d['id'] ?? 0);
    $rows = $db->rows('receipts');
    foreach ($rows as &$r) {
        if ((int) ($r['id'] ?? 0) !== $id) continue;
        $plate = normalize_plate((string) ($d['plate'] ?? $r['plate'] ?? ''));
        if (!is_valid_plate($plate)) json_response(['ok' => false, 'error' => 'Ogiltigt registreringsnummer.'], 422);
        $r['work_type'] = trim((string) ($d['work_type'] ?? $r['work_type'] ?? ''));
        $r['styling_parts'] = ($d['styling_parts'] ?? '') === '' ? '' : (int) ($d['styling_parts'] ?? 0);
        $r['performance_parts'] = ($d['performance_parts'] ?? '') === '' ? '' : (int) ($d['performance_parts'] ?? 0);
        $r['amount'] = (float) ($d['amount'] ?? $r['amount'] ?? 0);
        $r['customer'] = trim((string) ($d['customer'] ?? $r['customer'] ?? ''));
        $r['plate'] = $plate;
        $r['order_comment'] = trim((string) ($d['order_comment'] ?? $r['order_comment'] ?? ''));
        $r['discount_name'] = trim((string) ($d['discount_name'] ?? $r['discount_name'] ?? ''));
        $r['discount_percent'] = (float) ($d['discount_percent'] ?? $r['discount_percent'] ?? 0);
        $db->setRows('receipts', $rows);
        log_activity($db, $user, 'receipt_updated', 'receipt', $id, 'Kvitto uppdaterades.');
        json_response(['ok' => true]);
    }
    unset($r);
    json_response(['ok' => false, 'error' => 'Kvitto hittades inte.'], 404);
}

if ($action === 'api_delete_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_edit_receipts');
    $id = (int) (read_json_input()['id'] ?? 0);
    $db->setRows('receipts', array_values(array_filter($db->rows('receipts'), static fn($r) => (int) ($r['id'] ?? 0) !== $id)));
    log_activity($db, $user, 'receipt_deleted', 'receipt', $id, 'Kvitto raderades.');
    json_response(['ok' => true]);
}

if ($action === 'api_mark_receipt_sent' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    $d = read_json_input();
    $id = (int) ($d['id'] ?? 0);
    $isSent = (int) ($d['is_sent'] ?? 0) === 1 ? 1 : 0;
    $rows = $db->rows('receipts');
    foreach ($rows as &$r) {
        if ((int) ($r['id'] ?? 0) === $id) {
            $r['is_sent'] = $isSent;
            $db->setRows('receipts', $rows);
            log_activity($db, $user, 'receipt_sent_state', 'receipt', $id, $isSent === 1 ? 'Kvitto markerades som skickat.' : 'Kvitto skickad-status återställdes.');
            json_response(['ok' => true]);
        }
    }
    unset($r);
    json_response(['ok' => false, 'error' => 'Kvitto hittades inte.'], 404);
}

if ($action === 'api_customers' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_customers');
    $rows = $db->rows('customer_registry');
    usort($rows, static fn($a, $b) => strcmp((string) ($a['customer_name'] ?? ''), (string) ($b['customer_name'] ?? '')));
    json_response(['ok' => true, 'customers' => $rows]);
}

if ($action === 'api_create_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_view_customers');
    $d = read_json_input();
    $id = (int) ($d['id'] ?? 0);
    $name = trim((string) ($d['customer_name'] ?? ''));
    if ($name === '') json_response(['ok' => false, 'error' => 'Kundnamn krävs.'], 422);
    $pnr = normalize_personnummer((string) ($d['personnummer'] ?? ''));
    $rows = $db->rows('customer_registry');
    foreach ($rows as &$c) {
        if (($id > 0 && (int) $c['id'] === $id) || ($id <= 0 && $pnr !== '' && (string) ($c['personnummer'] ?? '') === $pnr)) {
            $c['customer_name'] = $name;
            $c['personnummer'] = $pnr !== '' ? $pnr : null;
            $c['phone'] = trim((string) ($d['phone'] ?? ''));
            $c['discount_preset_id'] = (int) ($d['discount_preset_id'] ?? 0) ?: null;
            $db->setRows('customer_registry', $rows);
            json_response(['ok' => true]);
        }
    }
    unset($c);
    $db->insert('customer_registry', [
        'customer_name' => $name,
        'personnummer' => $pnr !== '' ? $pnr : null,
        'phone' => trim((string) ($d['phone'] ?? '')),
        'discount_preset_id' => (int) ($d['discount_preset_id'] ?? 0) ?: null,
        'created_at' => now_iso(),
    ]);
    log_activity($db, $user, 'customer_saved', 'customer', null, 'Kund sparades.');
    json_response(['ok' => true]);
}

if ($action === 'api_delete_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_view_customers');
    $id = (int) (read_json_input()['id'] ?? 0);
    $db->setRows('customer_registry', array_values(array_filter($db->rows('customer_registry'), static fn($r) => (int) ($r['id'] ?? 0) !== $id)));
    json_response(['ok' => true]);
}

if ($action === 'api_vehicles' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_vehicles');
    $rows = $db->rows('vehicle_registry');
    usort($rows, static fn($a, $b) => strcmp((string) ($a['plate'] ?? ''), (string) ($b['plate'] ?? '')));
    json_response(['ok' => true, 'vehicles' => $rows]);
}

if ($action === 'api_create_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_view_vehicles');
    $d = read_json_input();
    $id = (int) ($d['id'] ?? 0);
    $plate = normalize_plate((string) ($d['plate'] ?? ''));
    if (!is_valid_plate($plate)) json_response(['ok' => false, 'error' => 'Ogiltigt registreringsnummer.'], 422);
    $model = trim((string) ($d['vehicle_model'] ?? ''));
    if ($model === '') json_response(['ok' => false, 'error' => 'Modell krävs.'], 422);

    $rows = $db->rows('vehicle_registry');
    foreach ($rows as &$v) {
        if (($id > 0 && (int) $v['id'] === $id) || ($id <= 0 && (string) ($v['plate'] ?? '') === $plate)) {
            $v['plate'] = $plate;
            $v['vehicle_model'] = $model;
            $db->setRows('vehicle_registry', $rows);
            json_response(['ok' => true]);
        }
    }
    unset($v);
    $db->insert('vehicle_registry', ['plate' => $plate, 'vehicle_model' => $model, 'created_at' => now_iso()]);
    json_response(['ok' => true]);
}

if ($action === 'api_delete_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_view_vehicles');
    $id = (int) (read_json_input()['id'] ?? 0);
    $db->setRows('vehicle_registry', array_values(array_filter($db->rows('vehicle_registry'), static fn($r) => (int) ($r['id'] ?? 0) !== $id)));
    json_response(['ok' => true]);
}

if ($action === 'api_payroll_entries' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_admin');
    $rows = $db->rows('payroll_entries');
    usort($rows, static fn($a, $b) => strcmp((string) ($b['pay_date'] ?? ''), (string) ($a['pay_date'] ?? '')) ?: ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0)));
    json_response(['ok' => true, 'entries' => $rows]);
}

if ($action === 'api_create_payroll_entry' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_view_admin');
    $d = read_json_input();
    $id = (int) ($d['id'] ?? 0);
    $rows = $db->rows('payroll_entries');
    foreach ($rows as &$r) {
        if ((int) ($r['id'] ?? 0) !== $id) continue;
        $r['payee_name'] = trim((string) ($d['payee_name'] ?? ''));
        $r['amount'] = (float) ($d['amount'] ?? 0);
        $r['pay_date'] = trim((string) ($d['pay_date'] ?? ''));
        $db->setRows('payroll_entries', $rows);
        log_activity($db, $user, 'payroll_updated', 'payroll', $id, 'Lönepost uppdaterades.');
        json_response(['ok' => true]);
    }
    unset($r);
    $inserted = $db->insert('payroll_entries', [
        'payee_name' => trim((string) ($d['payee_name'] ?? '')),
        'amount' => (float) ($d['amount'] ?? 0),
        'pay_date' => trim((string) ($d['pay_date'] ?? '')),
        'created_at' => now_iso(),
    ]);
    log_activity($db, $user, 'payroll_created', 'payroll', (int) $inserted['id'], 'Lönepost skapades.');
    json_response(['ok' => true]);
}

if ($action === 'api_delete_payroll_entry' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_view_admin');
    $id = (int) (read_json_input()['id'] ?? 0);
    $db->setRows('payroll_entries', array_values(array_filter($db->rows('payroll_entries'), static fn($r) => (int) ($r['id'] ?? 0) !== $id)));
    json_response(['ok' => true]);
}

if ($action === 'api_ranks' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_manage_users');
    $rows = $db->rows('ranks');
    usort($rows, static fn($a, $b) => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));
    json_response(['ok' => true, 'ranks' => $rows]);
}

if ($action === 'api_save_rank' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_manage_users');
    $d = read_json_input();
    $id = (int) ($d['id'] ?? 0);
    $name = trim((string) ($d['name'] ?? ''));
    if ($name === '') json_response(['ok' => false, 'error' => 'Ranknamn krävs.'], 422);
    $rows = $db->rows('ranks');
    $payload = [
        'name' => $name,
        'can_view_admin' => (int) (($d['can_view_admin'] ?? false) ? 1 : 0),
        'can_manage_users' => (int) (($d['can_manage_users'] ?? false) ? 1 : 0),
        'can_manage_prices' => (int) (($d['can_manage_prices'] ?? false) ? 1 : 0),
        'can_edit_receipts' => (int) (($d['can_edit_receipts'] ?? false) ? 1 : 0),
        'can_view_customers' => (int) (($d['can_view_customers'] ?? false) ? 1 : 0),
        'can_view_vehicles' => (int) (($d['can_view_vehicles'] ?? false) ? 1 : 0),
        'can_view_prices' => (int) (($d['can_view_prices'] ?? false) ? 1 : 0),
    ];
    foreach ($rows as &$r) {
        if (($id > 0 && (int) $r['id'] === $id) || ($id <= 0 && strcasecmp((string) ($r['name'] ?? ''), $name) === 0)) {
            foreach ($payload as $k => $v) $r[$k] = $v;
            $db->setRows('ranks', $rows);
            log_activity($db, $user, 'rank_saved', 'rank', (int) $r['id'], 'Rank sparades.');
            json_response(['ok' => true]);
        }
    }
    unset($r);
    $inserted = $db->insert('ranks', $payload);
    log_activity($db, $user, 'rank_saved', 'rank', (int) $inserted['id'], 'Rank skapades.');
    json_response(['ok' => true]);
}

if ($action === 'api_delete_rank' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_users');
    $id = (int) (read_json_input()['id'] ?? 0);
    $db->setRows('ranks', array_values(array_filter($db->rows('ranks'), static fn($r) => (int) ($r['id'] ?? 0) !== $id)));
    $users = $db->rows('users');
    foreach ($users as &$u) {
        if ((int) ($u['rank_id'] ?? 0) === $id) $u['rank_id'] = null;
    }
    unset($u);
    $db->setRows('users', $users);
    json_response(['ok' => true]);
}

if ($action === 'api_admin_summary' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    require_permission('can_view_admin');
    $receipts = $db->rows('receipts');
    $payroll = $db->rows('payroll_entries');
    $users = $db->rows('users');

    $sales = 0.0; $expenses = 0.0;
    foreach ($receipts as $r) {
        $sales += (float) ($r['amount'] ?? 0);
        $expenses += (float) ($r['expense_total'] ?? 0);
    }
    $payrollTotal = 0.0; $lastPayDate = '';
    foreach ($payroll as $p) {
        $payrollTotal += (float) ($p['amount'] ?? 0);
        $d = (string) ($p['pay_date'] ?? '');
        if ($d > $lastPayDate) $lastPayDate = $d;
    }

    $names = [];
    foreach ($users as $u) $names[(string) ($u['personnummer'] ?? '')] = (string) ($u['full_name'] ?? '');
    $agg = [];
    foreach ($receipts as $r) {
        $mech = (string) ($r['mechanic'] ?? '');
        if (!isset($agg[$mech])) $agg[$mech] = ['mechanic_name' => $names[$mech] ?? $mech, 'receipt_count' => 0, 'total_sales' => 0.0];
        $agg[$mech]['receipt_count']++;
        $agg[$mech]['total_sales'] += (float) ($r['amount'] ?? 0);
    }
    $topMechanics = array_values($agg);
    usort($topMechanics, static fn($a, $b) => ((int) $b['receipt_count']) <=> ((int) $a['receipt_count']));

    $ranks = $db->rows('ranks');
    $rankById = [];
    foreach ($ranks as $r) $rankById[(int) ($r['id'] ?? 0)] = (string) ($r['name'] ?? '-');
    $adminUsers = [];
    foreach ($users as $u) {
        $adminUsers[] = [
            'id' => (int) ($u['id'] ?? 0),
            'personnummer' => (string) ($u['personnummer'] ?? ''),
            'full_name' => (string) ($u['full_name'] ?? ''),
            'rank_id' => (int) ($u['rank_id'] ?? 0),
            'rank_name' => $rankById[(int) ($u['rank_id'] ?? 0)] ?? '-',
        ];
    }

    json_response([
        'ok' => true,
        'stats' => [
            'sales' => $sales,
            'expenses' => $expenses,
            'profit' => $sales - $expenses,
            'receipt_count' => count($receipts),
            'payroll_total' => $payrollTotal,
            'last_payroll_date' => $lastPayDate,
        ],
        'top_mechanics' => $topMechanics,
        'users' => $adminUsers,
    ]);
}

if ($action === 'api_admin_save_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_users');
    $d = read_json_input();
    $id = (int) ($d['id'] ?? 0);
    $personnummer = trim((string) ($d['personnummer'] ?? ''));
    $fullName = trim((string) ($d['full_name'] ?? ''));
    $password = trim((string) ($d['password'] ?? ''));
    $rankId = (int) ($d['rank_id'] ?? 0);
    if ($personnummer === '' || $fullName === '' || $password === '') {
        json_response(['ok' => false, 'error' => 'Personnummer, namn och lösenord måste anges.'], 422);
    }
    $rank = find_by_id($db->rows('ranks'), $rankId);
    $isAdmin = ((int) ($rank['can_view_admin'] ?? 0) === 1 && (int) ($rank['can_manage_users'] ?? 0) === 1) ? 1 : 0;

    $rows = $db->rows('users');
    foreach ($rows as &$u) {
        if (($id > 0 && (int) $u['id'] === $id) || ($id <= 0 && (string) ($u['personnummer'] ?? '') === $personnummer)) {
            $u['personnummer'] = $personnummer;
            $u['full_name'] = $fullName;
            $u['password'] = $password;
            $u['rank_id'] = $rankId > 0 ? $rankId : null;
            $u['is_admin'] = $isAdmin;
            $db->setRows('users', $rows);
            json_response(['ok' => true]);
        }
    }
    unset($u);

    $db->insert('users', [
        'personnummer' => $personnummer,
        'full_name' => $fullName,
        'password' => $password,
        'rank_id' => $rankId > 0 ? $rankId : null,
        'is_admin' => $isAdmin,
    ]);
    json_response(['ok' => true]);
}

if ($action === 'api_delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_permission('can_manage_users');
    $id = (int) (read_json_input()['id'] ?? 0);
    $db->setRows('users', array_values(array_filter($db->rows('users'), static fn($r) => (int) ($r['id'] ?? 0) !== $id)));
    json_response(['ok' => true]);
}

if ($action === 'api_layout_get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $row = null;
    foreach ($db->rows('layout_settings') as $r) {
        if ((string) ($r['layout_key'] ?? '') === 'global_sections') {
            $row = $r;
            break;
        }
    }
    $layout = null;
    if ($row && is_string($row['config_json'] ?? null)) {
        $decoded = json_decode((string) $row['config_json'], true);
        if (is_array($decoded) && !empty($decoded)) $layout = $decoded;
    }
    if (!is_array($layout) || empty($layout)) {
        $layout = normalize_layout_payload(DEFAULT_GLOBAL_LAYOUT_MAP);
    }
    json_response(['ok' => true, 'layout' => $layout, 'updated_at' => $row['updated_at'] ?? null, 'preset_version' => DEFAULT_GLOBAL_LAYOUT_PRESET_VERSION]);
}

if ($action === 'api_layout_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_view_admin');
    $raw = read_json_input()['layout'] ?? null;
    if (!is_array($raw) || empty($raw)) json_response(['ok' => false, 'error' => 'Ogiltigt layout-format.'], 422);
    $normalized = normalize_layout_payload($raw);
    $rows = $db->rows('layout_settings');
    $saved = false;
    foreach ($rows as &$r) {
        if ((string) ($r['layout_key'] ?? '') === 'global_sections') {
            $r['config_json'] = json_encode($normalized, JSON_UNESCAPED_UNICODE);
            $r['updated_at'] = now_iso();
            $saved = true;
            break;
        }
    }
    unset($r);
    if ($saved) {
        $db->setRows('layout_settings', $rows);
    } else {
        $db->insert('layout_settings', ['layout_key' => 'global_sections', 'config_json' => json_encode($normalized, JSON_UNESCAPED_UNICODE), 'updated_at' => now_iso()]);
    }
    log_activity($db, $user, 'layout_saved', 'layout', null, 'Layouten uppdaterades.');
    json_response(['ok' => true, 'layout' => $normalized, 'preset_version' => DEFAULT_GLOBAL_LAYOUT_PRESET_VERSION]);
}

if ($action === 'api_layout_reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_login();
    require_permission('can_view_admin');
    $normalizedDefault = normalize_layout_payload(DEFAULT_GLOBAL_LAYOUT_MAP);
    $rows = $db->rows('layout_settings');
    $saved = false;
    foreach ($rows as &$r) {
        if ((string) ($r['layout_key'] ?? '') === 'global_sections') {
            $r['config_json'] = json_encode($normalizedDefault, JSON_UNESCAPED_UNICODE);
            $r['updated_at'] = now_iso();
            $saved = true;
            break;
        }
    }
    unset($r);
    if ($saved) {
        $db->setRows('layout_settings', $rows);
    } else {
        $db->insert('layout_settings', ['layout_key' => 'global_sections', 'config_json' => json_encode($normalizedDefault, JSON_UNESCAPED_UNICODE), 'updated_at' => now_iso()]);
    }
    log_activity($db, $user, 'layout_reset', 'layout', null, 'Layouten återställdes till standard.');
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

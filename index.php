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
    echo '<!doctype html><html lang="sv"><head><meta charset="utf-8" /><title>PDO SQLite saknas</title></head><body style="font-family:Arial,sans-serif;padding:20px;">';
    echo '<h1>PHP saknar SQLite-drivrutin</h1>';
    echo '<p>Appen kräver <strong>pdo_sqlite</strong> och <strong>sqlite3</strong>.</p>';
    echo '<p>Om du kör Windows-startscriptet: kör <code>start_app.bat</code> igen så försöker den aktivera drivrutiner i php.ini automatiskt.</p>';
    echo '<p>Om problemet kvarstår, öppna php.ini och kontrollera att dessa rader är aktiva:</p>';
    echo '<pre>extension=pdo_sqlite
extension=sqlite3</pre>';
    echo '</body></html>';
    exit;
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function init_db(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
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

    $pdo->exec('CREATE TABLE IF NOT EXISTS vehicle_registry (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        plate TEXT UNIQUE NOT NULL,
        vehicle_type TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS customer_registry (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_name TEXT UNIQUE NOT NULL,
        phone TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS work_types (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        default_price REAL NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    ensure_is_admin_column($pdo);

    $users = [
        ['19900101-1234', 'motor123', 1],
        ['19920202-5678', 'garage123', 0],
        ['19950505-9012', 'bennys123', 0],
    ];

    $userStmt = $pdo->prepare('INSERT OR IGNORE INTO users (username, password, is_admin) VALUES (?, ?, ?)');
    foreach ($users as $user) {
        $userStmt->execute($user);
    }

    $workTypes = [
        ['Reperation', 0, 1],
        ['Styling', 0, 1],
        ['Prestanda', 0, 1],
    ];
    $workStmt = $pdo->prepare('INSERT OR IGNORE INTO work_types (name, default_price, is_active) VALUES (?, ?, ?)');
    foreach ($workTypes as $workType) {
        $workStmt->execute($workType);
    }
}

function ensure_is_admin_column(PDO $pdo): void
{
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
    $hasIsAdmin = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'is_admin') {
            $hasIsAdmin = true;
            break;
        }
    }

    if (!$hasIsAdmin) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
    }
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_logged_in(): bool
{
    return !empty($_SESSION['personnummer']);
}

function current_user(): string
{
    return $_SESSION['personnummer'] ?? '';
}

function current_is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}

function redirect(string $target): never
{
    header('Location: ' . $target);
    exit;
}

function is_valid_phone(string $phone): bool
{
    return preg_match('/^[0-9+\- ]{6,20}$/', $phone) === 1;
}

function get_active_work_types(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name, default_price FROM work_types WHERE is_active = 1 ORDER BY name ASC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_all_work_types(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name, default_price, is_active FROM work_types ORDER BY id ASC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function render_page(string $title, string $body, ?string $username = null): void
{
    $templatePath = __DIR__ . '/index2.html';
    if (!file_exists($templatePath)) {
        http_response_code(500);
        echo 'Template index2.html saknas.';
        exit;
    }

    $template = file_get_contents($templatePath);
    if ($template === false) {
        http_response_code(500);
        echo 'Kunde inte läsa index2.html.';
        exit;
    }

    $auth = '';
    if ($username) {
        $adminLink = current_is_admin() ? ' · <a href="?action=admin">Admin</a>' : '';
        $auth = '<div class="user">Inloggad som <strong>' . esc($username) . '</strong>' . $adminLink . ' · <a href="?action=logout">Logga ut</a></div>';
    }

    $output = str_replace(
        ['{{TITLE}}', '{{AUTH}}', '{{BODY}}'],
        [esc($title), $auth, $body],
        $template
    );

    echo $output;
}

function render_admin_page(PDO $pdo, array $messages = [], array $errors = []): void
{
    if (!current_is_admin()) {
        redirect('?action=receipts');
    }

    $dateFrom = trim($_GET['from'] ?? '');
    $dateTo = trim($_GET['to'] ?? '');
    $workType = trim($_GET['work_type'] ?? '');
    $mechanic = trim($_GET['mechanic'] ?? '');

    $where = [];
    $params = [];

    if ($dateFrom !== '') {
        $where[] = 'date(created_at) >= date(?)';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'date(created_at) <= date(?)';
        $params[] = $dateTo;
    }
    if ($workType !== '') {
        $where[] = 'work_type = ?';
        $params[] = $workType;
    }
    if ($mechanic !== '') {
        $where[] = 'mechanic = ?';
        $params[] = $mechanic;
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $summaryStmt = $pdo->prepare('SELECT COUNT(*) AS total_receipts, COALESCE(SUM(amount), 0) AS total_amount FROM receipts' . $whereSql);
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_receipts' => 0, 'total_amount' => 0];

    $typeStmt = $pdo->prepare('SELECT work_type, COUNT(*) AS total FROM receipts' . $whereSql . ' GROUP BY work_type ORDER BY total DESC');
    $typeStmt->execute($params);
    $typeRows = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

    $mechanicStmt = $pdo->query('SELECT DISTINCT mechanic FROM receipts ORDER BY mechanic ASC');
    $mechanics = $mechanicStmt->fetchAll(PDO::FETCH_COLUMN);

    $users = $pdo->query('SELECT id, username, is_admin FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $vehicles = $pdo->query('SELECT id, plate, vehicle_type FROM vehicle_registry ORDER BY plate ASC')->fetchAll(PDO::FETCH_ASSOC);
    $customers = $pdo->query('SELECT id, customer_name, phone FROM customer_registry ORDER BY customer_name ASC')->fetchAll(PDO::FETCH_ASSOC);
    $workTypes = get_all_work_types($pdo);

    $messageHtml = '';
    foreach ($messages as $message) {
        $messageHtml .= '<div class="card"><div class="success">' . esc($message) . '</div></div>';
    }

    $errorHtml = '';
    if ($errors) {
        $errorHtml .= '<div class="card"><div class="error"><ul>';
        foreach ($errors as $error) {
            $errorHtml .= '<li>' . esc($error) . '</li>';
        }
        $errorHtml .= '</ul></div></div>';
    }

    $typeItems = '<li>Inga resultat för valt filter.</li>';
    if ($typeRows) {
        $typeItems = '';
        foreach ($typeRows as $row) {
            $typeItems .= '<li>' . esc($row['work_type']) . ': <strong>' . esc((string) $row['total']) . '</strong></li>';
        }
    }

    $workTypeOptions = '<option value="">Alla</option>';
    foreach ($workTypes as $workTypeRow) {
        $selected = ($workType === $workTypeRow['name']) ? ' selected' : '';
        $workTypeOptions .= '<option value="' . esc($workTypeRow['name']) . '"' . $selected . '>' . esc($workTypeRow['name']) . '</option>';
    }

    $mechanicOptions = '<option value="">Alla</option>';
    foreach ($mechanics as $m) {
        $selected = ($mechanic === $m) ? ' selected' : '';
        $mechanicOptions .= '<option value="' . esc((string) $m) . '"' . $selected . '>' . esc((string) $m) . '</option>';
    }

    $usersRows = '';
    foreach ($users as $user) {
        $badge = ((int) $user['is_admin'] === 1) ? '<span class="badge">Admin</span>' : '<span class="badge badge-gray">Mekaniker</span>';
        $usersRows .= '<tr>
          <td>' . esc((string) $user['id']) . '</td>
          <td>' . esc($user['username']) . '</td>
          <td>' . $badge . '</td>
          <td>
            <form method="post" action="?action=admin_update_user" class="inline-form">
              <input type="hidden" name="user_id" value="' . esc((string) $user['id']) . '" />
              <input type="text" name="password" placeholder="Nytt lösenord" required />
              <select name="is_admin">
                <option value="0">Mekaniker</option>
                <option value="1"' . (((int) $user['is_admin'] === 1) ? ' selected' : '') . '>Admin</option>
              </select>
              <button type="submit">Spara</button>
            </form>
          </td>
        </tr>';
    }

    $workTypeRows = '';
    if (!$workTypes) {
        $workTypeRows = '<tr><td colspan="5">Inga arbetstyper ännu.</td></tr>';
    }
    foreach ($workTypes as $wt) {
        $statusBadge = ((int) $wt['is_active'] === 1) ? '<span class="badge">Aktiv</span>' : '<span class="badge badge-gray">Inaktiv</span>';
        $workTypeRows .= '<tr>
          <td>' . esc((string) $wt['id']) . '</td>
          <td>' . esc($wt['name']) . '</td>
          <td>' . esc(number_format((float) $wt['default_price'], 2, ',', ' ')) . ' SEK</td>
          <td>' . $statusBadge . '</td>
          <td>
            <form method="post" action="?action=admin_update_work_type" class="inline-form">
              <input type="hidden" name="work_type_id" value="' . esc((string) $wt['id']) . '" />
              <input type="text" name="name" value="' . esc($wt['name']) . '" required />
              <input type="number" min="0" step="0.01" name="default_price" value="' . esc((string) $wt['default_price']) . '" required />
              <select name="is_active">
                <option value="1"' . (((int) $wt['is_active'] === 1) ? ' selected' : '') . '>Aktiv</option>
                <option value="0"' . (((int) $wt['is_active'] === 0) ? ' selected' : '') . '>Inaktiv</option>
              </select>
              <button type="submit">Spara</button>
            </form>
          </td>
        </tr>';
    }

    $vehicleRows = '';
    if (!$vehicles) {
        $vehicleRows = '<tr><td colspan="4">Inga registrerade fordon ännu.</td></tr>';
    }
    foreach ($vehicles as $vehicle) {
        $vehicleRows .= '<tr>
          <td>' . esc((string) $vehicle['id']) . '</td>
          <td>' . esc($vehicle['plate']) . '</td>
          <td>' . esc($vehicle['vehicle_type']) . '</td>
          <td>
            <form method="post" action="?action=admin_update_vehicle" class="inline-form">
              <input type="hidden" name="vehicle_id" value="' . esc((string) $vehicle['id']) . '" />
              <input type="text" name="vehicle_type" placeholder="Ny fordonstyp" required />
              <button type="submit">Uppdatera</button>
            </form>
          </td>
        </tr>';
    }

    $customerRows = '';
    if (!$customers) {
        $customerRows = '<tr><td colspan="4">Inga kunder registrerade ännu.</td></tr>';
    }
    foreach ($customers as $customerRow) {
        $customerRows .= '<tr>
          <td>' . esc((string) $customerRow['id']) . '</td>
          <td>' . esc($customerRow['customer_name']) . '</td>
          <td>' . esc($customerRow['phone']) . '</td>
          <td>
            <form method="post" action="?action=admin_update_customer" class="inline-form">
              <input type="hidden" name="customer_id" value="' . esc((string) $customerRow['id']) . '" />
              <input type="text" name="phone" placeholder="Nytt telefonnummer" required />
              <button type="submit">Uppdatera</button>
            </form>
          </td>
        </tr>';
    }

    $body = $messageHtml . $errorHtml . '<div class="card">
      <h1>Adminpanel</h1>
      <p class="muted">Statistik, kvittoinställningar och registerhantering för Benny\'s Motorworks.</p>
      <div class="buttons">
        <a class="btn btn-secondary" href="?action=receipts">Till kvitton</a>
      </div>
    </div>

    <div class="card">
      <h2>Filter för statistik</h2>
      <form method="get" action="">
        <input type="hidden" name="action" value="admin" />
        <label>Från datum <input type="date" name="from" value="' . esc($dateFrom) . '" /></label>
        <label>Till datum <input type="date" name="to" value="' . esc($dateTo) . '" /></label>
        <label>Typ av arbete
          <select name="work_type">' . $workTypeOptions . '</select>
        </label>
        <label>Mekaniker
          <select name="mechanic">' . $mechanicOptions . '</select>
        </label>
        <div class="buttons">
          <button type="submit">Filtrera</button>
          <a class="btn btn-secondary" href="?action=admin">Rensa filter</a>
        </div>
      </form>
    </div>

    <div class="card stats-grid">
      <div class="stat-box">
        <small>Antal kvitton</small>
        <strong>' . esc((string) $summary['total_receipts']) . '</strong>
      </div>
      <div class="stat-box">
        <small>Total omsättning</small>
        <strong>' . esc(number_format((float) $summary['total_amount'], 2, ',', ' ')) . ' SEK</strong>
      </div>
      <div class="stat-box">
        <small>Fördelning arbete</small>
        <ul>' . $typeItems . '</ul>
      </div>
    </div>

    <div class="card">
      <h2>Kvittodelen: Arbeten och standardpriser</h2>
      <p class="muted">Här kan du lägga till nya arbeten och sätta grundpris. Dessa syns direkt i kvittoformuläret.</p>
      <form method="post" action="?action=admin_create_work_type">
        <label>Namn på arbete <input name="name" placeholder="Ex. Lackering / Bärgning" required /></label>
        <label>Standardpris (SEK) <input type="number" min="0" step="0.01" name="default_price" value="0" required /></label>
        <label>Status
          <select name="is_active">
            <option value="1">Aktiv</option>
            <option value="0">Inaktiv</option>
          </select>
        </label>
        <button type="submit">Lägg till arbete</button>
      </form>
    </div>

    <div class="card">
      <h2>Befintliga arbeten</h2>
      <table>
        <thead>
          <tr><th>ID</th><th>Namn</th><th>Standardpris</th><th>Status</th><th>Redigera</th></tr>
        </thead>
        <tbody>' . $workTypeRows . '</tbody>
      </table>
    </div>

    <div class="card">
      <h2>Skapa ny användare</h2>
      <form method="post" action="?action=admin_create_user">
        <label>Personnummer <input name="personnummer" placeholder="ÅÅÅÅMMDD-XXXX" required /></label>
        <label>Lösenord <input type="text" name="password" required /></label>
        <label>Roll
          <select name="is_admin">
            <option value="0">Mekaniker</option>
            <option value="1">Admin</option>
          </select>
        </label>
        <button type="submit">Skapa användare</button>
      </form>
    </div>

    <div class="card">
      <h2>Redigera användare</h2>
      <table>
        <thead>
          <tr><th>ID</th><th>Personnummer</th><th>Roll</th><th>Ändra lösenord/roll</th></tr>
        </thead>
        <tbody>' . $usersRows . '</tbody>
      </table>
    </div>

    <div class="card">
      <h2>Fordonregister (nummerplåt + fordonstyp)</h2>
      <form method="post" action="?action=admin_create_vehicle">
        <label>Nummerplåt <input name="plate" placeholder="ABC-123" required /></label>
        <label>Fordonstyp <input name="vehicle_type" placeholder="Ex. Sultan RS / Tow Truck" required /></label>
        <button type="submit">Lägg till fordon</button>
      </form>
    </div>

    <div class="card">
      <h2>Registrerade fordon</h2>
      <table>
        <thead>
          <tr><th>ID</th><th>Nummerplåt</th><th>Fordon</th><th>Uppdatera fordon</th></tr>
        </thead>
        <tbody>' . $vehicleRows . '</tbody>
      </table>
    </div>

    <div class="card">
      <h2>Kundregister (namn + telefon)</h2>
      <form method="post" action="?action=admin_create_customer">
        <label>Kundnamn <input name="customer_name" placeholder="Namn" required /></label>
        <label>Telefonnummer <input name="phone" placeholder="070-123 45 67" required /></label>
        <button type="submit">Lägg till kund</button>
      </form>
    </div>

    <div class="card">
      <h2>Registrerade kunder</h2>
      <table>
        <thead>
          <tr><th>ID</th><th>Namn</th><th>Telefon</th><th>Uppdatera telefon</th></tr>
        </thead>
        <tbody>' . $customerRows . '</tbody>
      </table>
    </div>';

    render_page('Adminpanel', $body, current_user());
    exit;
}

init_db($pdo);

$action = $_GET['action'] ?? 'login';
$errors = [];
$messages = [];

if ($action === 'logout') {
    session_unset();
    session_destroy();
    session_start();
    redirect('?action=login');
}

if (!is_logged_in() && $action !== 'login') {
    redirect('?action=login');
}

if ($action === 'login' && is_logged_in() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('?action=receipts');
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $personnummer = trim($_POST['personnummer'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare('SELECT username, is_admin FROM users WHERE username = ? AND password = ?');
    $stmt->execute([$personnummer, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $errors[] = 'Fel personnummer eller lösenord.';
    } else {
        $_SESSION['personnummer'] = $user['username'];
        $_SESSION['is_admin'] = ((int) ($user['is_admin'] ?? 0) === 1) ? 1 : 0;
        redirect('?action=receipts');
    }
}

if ($action === 'admin_create_work_type' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!current_is_admin()) {
        redirect('?action=receipts');
    }

    $name = trim($_POST['name'] ?? '');
    $defaultPriceRaw = trim($_POST['default_price'] ?? '');
    $isActive = ((int) ($_POST['is_active'] ?? 1) === 1) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Namn på arbete måste anges.';
    }
    if ($defaultPriceRaw === '' || !is_numeric($defaultPriceRaw) || (float) $defaultPriceRaw < 0) {
        $errors[] = 'Standardpris måste vara 0 eller högre.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO work_types (name, default_price, is_active) VALUES (?, ?, ?)');
            $stmt->execute([$name, (float) $defaultPriceRaw, $isActive]);
            $messages[] = 'Arbete tillagt: ' . $name;
        } catch (Throwable $e) {
            $errors[] = 'Kunde inte lägga till arbete. Namnet kan redan finnas.';
        }
    }

    render_admin_page($pdo, $messages, $errors);
}

if ($action === 'admin_update_work_type' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!current_is_admin()) {
        redirect('?action=receipts');
    }

    $workTypeId = (int) ($_POST['work_type_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $defaultPriceRaw = trim($_POST['default_price'] ?? '');
    $isActive = ((int) ($_POST['is_active'] ?? 1) === 1) ? 1 : 0;

    if ($workTypeId <= 0) {
        $errors[] = 'Ogiltigt arbets-ID.';
    }
    if ($name === '') {
        $errors[] = 'Namn på arbete måste anges.';
    }
    if ($defaultPriceRaw === '' || !is_numeric($defaultPriceRaw) || (float) $defaultPriceRaw < 0) {
        $errors[] = 'Standardpris måste vara 0 eller högre.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE work_types SET name = ?, default_price = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$name, (float) $defaultPriceRaw, $isActive, $workTypeId]);
            $messages[] = 'Arbete uppdaterat.';
        } catch (Throwable $e) {
            $errors[] = 'Kunde inte uppdatera arbete. Namnet kan redan finnas.';
        }
    }

    render_admin_page($pdo, $messages, $errors);
}

if ($action === 'admin_create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!current_is_admin()) {
        redirect('?action=receipts');
    }

    $personnummer = trim($_POST['personnummer'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $isAdmin = ((int) ($_POST['is_admin'] ?? 0) === 1) ? 1 : 0;

    if (!preg_match('/^\d{8}-\d{4}$/', $personnummer)) {
        $errors[] = 'Personnummer måste vara i formatet ÅÅÅÅMMDD-XXXX.';
    }
    if ($password === '') {
        $errors[] = 'Lösenord måste anges.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)');
            $stmt->execute([$personnummer, $password, $isAdmin]);
            $messages[] = 'Ny användare skapad: ' . $personnummer;
        } catch (Throwable $e) {
            $errors[] = 'Kunde inte skapa användare. Kontrollera att personnummer inte redan finns.';
        }
    }

    render_admin_page($pdo, $messages, $errors);
}

if ($action === 'admin_update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!current_is_admin()) {
        redirect('?action=receipts');
    }

    $userId = (int) ($_POST['user_id'] ?? 0);
    $password = trim($_POST['password'] ?? '');
    $isAdmin = ((int) ($_POST['is_admin'] ?? 0) === 1) ? 1 : 0;

    if ($userId <= 0) {
        $errors[] = 'Ogiltigt användar-ID.';
    }
    if ($password === '') {
        $errors[] = 'Lösenord måste anges.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET password = ?, is_admin = ? WHERE id = ?');
        $stmt->execute([$password, $isAdmin, $userId]);
        $messages[] = 'Användare uppdaterad.';

        $myUserStmt = $pdo->prepare('SELECT is_admin FROM users WHERE username = ?');
        $myUserStmt->execute([current_user()]);
        $current = $myUserStmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['is_admin'] = ((int) ($current['is_admin'] ?? 0) === 1) ? 1 : 0;
    }

    render_admin_page($pdo, $messages, $errors);
}

if ($action === 'admin_create_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!current_is_admin()) {
        redirect('?action=receipts');
    }

    $plate = strtoupper(trim($_POST['plate'] ?? ''));
    $vehicleType = trim($_POST['vehicle_type'] ?? '');

    if (!preg_match('/^[A-Z]{3}-\d{3}$/', $plate)) {
        $errors[] = 'Nummerplåt måste vara i formatet XXX-000.';
    }
    if ($vehicleType === '') {
        $errors[] = 'Fordonstyp måste anges.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO vehicle_registry (plate, vehicle_type) VALUES (?, ?)');
            $stmt->execute([$plate, $vehicleType]);
            $messages[] = 'Fordon registrerat: ' . $plate;
        } catch (Throwable $e) {
            $errors[] = 'Kunde inte lägga till fordon. Nummerplåten kan redan finnas i registret.';
        }
    }

    render_admin_page($pdo, $messages, $errors);
}

if ($action === 'admin_update_vehicle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!current_is_admin()) {
        redirect('?action=receipts');
    }

    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $vehicleType = trim($_POST['vehicle_type'] ?? '');

    if ($vehicleId <= 0) {
        $errors[] = 'Ogiltigt fordons-ID.';
    }
    if ($vehicleType === '') {
        $errors[] = 'Fordonstyp måste anges.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE vehicle_registry SET vehicle_type = ? WHERE id = ?');
        $stmt->execute([$vehicleType, $vehicleId]);
        $messages[] = 'Fordon uppdaterat.';
    }

    render_admin_page($pdo, $messages, $errors);
}

if ($action === 'admin_create_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!current_is_admin()) {
        redirect('?action=receipts');
    }

    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($customerName === '') {
        $errors[] = 'Kundnamn måste anges.';
    }
    if (!is_valid_phone($phone)) {
        $errors[] = 'Telefonnummer är ogiltigt (använd siffror, +, -, mellanslag).';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO customer_registry (customer_name, phone) VALUES (?, ?)');
            $stmt->execute([$customerName, $phone]);
            $messages[] = 'Kund registrerad: ' . $customerName;
        } catch (Throwable $e) {
            $errors[] = 'Kunde inte lägga till kund. Namnet kan redan finnas i registret.';
        }
    }

    render_admin_page($pdo, $messages, $errors);
}

if ($action === 'admin_update_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!current_is_admin()) {
        redirect('?action=receipts');
    }

    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');

    if ($customerId <= 0) {
        $errors[] = 'Ogiltigt kund-ID.';
    }
    if (!is_valid_phone($phone)) {
        $errors[] = 'Telefonnummer är ogiltigt (använd siffror, +, -, mellanslag).';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE customer_registry SET phone = ? WHERE id = ?');
        $stmt->execute([$phone, $customerId]);
        $messages[] = 'Kund uppdaterad.';
    }

    render_admin_page($pdo, $messages, $errors);
}

if ($action === 'create_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $workType = trim($_POST['work_type'] ?? '');
    $partsCountRaw = trim($_POST['parts_count'] ?? '');
    $amountRaw = trim($_POST['amount'] ?? '');
    $customer = trim($_POST['customer'] ?? '');
    $plate = strtoupper(trim($_POST['plate'] ?? ''));

    $activeWorkTypes = get_active_work_types($pdo);
    $validWorkTypeMap = [];
    foreach ($activeWorkTypes as $activeWorkType) {
        $validWorkTypeMap[$activeWorkType['name']] = (float) $activeWorkType['default_price'];
    }

    if (!array_key_exists($workType, $validWorkTypeMap)) {
        $errors[] = 'Ogiltig typ av arbete.';
    }

    $partsCount = null;
    if (in_array($workType, ['Styling', 'Prestanda'], true)) {
        if ($partsCountRaw === '') {
            $errors[] = 'Antal delar krävs för Styling/Prestanda.';
        } elseif (!ctype_digit($partsCountRaw)) {
            $errors[] = 'Antal delar måste vara ett positivt heltal.';
        } else {
            $partsCount = (int) $partsCountRaw;
        }
    } elseif ($partsCountRaw !== '') {
        if (!ctype_digit($partsCountRaw)) {
            $errors[] = 'Antal delar måste vara ett heltal.';
        } else {
            $partsCount = (int) $partsCountRaw;
        }
    }

    if ($customer === '') {
        $errors[] = 'Kund måste anges.';
    }

    if (!preg_match('/^[A-Z]{3}-\d{3}$/', $plate)) {
        $errors[] = 'Regplåt måste vara i formatet XXX-000.';
    }

    if ($amountRaw === '') {
        if (array_key_exists($workType, $validWorkTypeMap)) {
            $amountRaw = (string) $validWorkTypeMap[$workType];
        }
    }

    if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw < 0) {
        $errors[] = 'Summa måste vara ett positivt tal.';
    }

    if (!$errors) {
        $amount = (float) $amountRaw;
        $stmt = $pdo->prepare('INSERT INTO receipts (mechanic, work_type, parts_count, amount, customer, plate) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([current_user(), $workType, $partsCount, $amount, $customer, $plate]);
        redirect('?action=receipts');
    }
}

if ($action === 'admin') {
    render_admin_page($pdo);
}

if ($action === 'new_receipt') {
    $vehicles = $pdo->query('SELECT plate FROM vehicle_registry ORDER BY plate ASC')->fetchAll(PDO::FETCH_COLUMN);
    $customers = $pdo->query('SELECT customer_name FROM customer_registry ORDER BY customer_name ASC')->fetchAll(PDO::FETCH_COLUMN);
    $activeWorkTypes = get_active_work_types($pdo);

    $plateOptions = '';
    foreach ($vehicles as $p) {
        $plateOptions .= '<option value="' . esc((string) $p) . '"></option>';
    }

    $customerOptions = '';
    foreach ($customers as $c) {
        $customerOptions .= '<option value="' . esc((string) $c) . '"></option>';
    }

    $workTypeOptions = '';
    $defaultPriceMap = [];
    foreach ($activeWorkTypes as $wt) {
        $name = (string) $wt['name'];
        $price = (float) $wt['default_price'];
        $defaultPriceMap[$name] = $price;
        $priceDisplay = number_format($price, 2, ',', ' ');
        $workTypeOptions .= '<option value="' . esc($name) . '">' . esc($name) . ' (' . esc($priceDisplay) . ' SEK)</option>';
    }
    $defaultPriceJson = esc(json_encode($defaultPriceMap) ?: '{}');

    $body = '<div class="card">
      <h1>Nytt kvitto</h1>
      <form method="post" action="?action=create_receipt" id="receipt-form">
        <label>Mekaniker <input value="' . esc(current_user()) . '" disabled /></label>
        <label>Typ av arbete
          <select name="work_type" id="work_type" required>
            ' . $workTypeOptions . '
          </select>
        </label>
        <label>Antal delar (för Styling/Prestanda) <input type="number" min="0" name="parts_count" /></label>
        <label>Summa (SEK) <input type="number" min="0" step="0.01" name="amount" id="amount" required /></label>
        <label>Kund <input name="customer" list="customer-registry" required /></label>
        <datalist id="customer-registry">' . $customerOptions . '</datalist>
        <label>Regplåt (XXX-000) <input name="plate" list="vehicle-registry" placeholder="ABC-123" required /></label>
        <datalist id="vehicle-registry">' . $plateOptions . '</datalist>
        <div class="buttons">
          <button type="submit">Skicka kvitto</button>
          <a class="btn btn-secondary" href="?action=receipts">Avbryt</a>
        </div>
      </form>
      <p class="muted">Tips: pris fylls automatiskt utifrån standardpris på vald arbetstyp (kan ändras manuellt).</p>
    </div>
    <script>
      (function() {
        const prices = JSON.parse(
          decodeURIComponent(
            encodeURIComponent("' . $defaultPriceJson . '")
          )
        );
        const workType = document.getElementById("work_type");
        const amount = document.getElementById("amount");
        if (workType && amount) {
          const setDefault = () => {
            const selected = workType.value;
            if (Object.prototype.hasOwnProperty.call(prices, selected)) {
              amount.value = Number(prices[selected]).toFixed(2);
            }
          };
          setDefault();
          workType.addEventListener("change", setDefault);
        }
      })();
    </script>';

    render_page('Nytt kvitto', $body, current_user());
    exit;
}

if ($action === 'receipts' || $action === 'home' || $action === 'create_receipt') {
    $stmt = $pdo->query('SELECT id, mechanic, work_type, parts_count, amount, customer, plate, created_at FROM receipts ORDER BY id DESC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $errorHtml = '';
    if ($errors) {
        $errorHtml .= '<div class="card"><div class="error"><ul>';
        foreach ($errors as $error) {
            $errorHtml .= '<li>' . esc($error) . '</li>';
        }
        $errorHtml .= '</ul></div></div>';
    }

    $tableRows = '';
    if (!$rows) {
        $tableRows = '<tr><td colspan="9">Inga kvitton skapade ännu.</td></tr>';
    }

    foreach ($rows as $row) {
        $workOrder = "Benny's Arbetsorder - " . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT);
        $partsDisplay = $row['parts_count'] === null ? '-' : (string) $row['parts_count'];

        $tableRows .= '<tr>
          <td>' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT) . '</td>
          <td>' . esc($row['mechanic']) . '</td>
          <td>' . esc($row['work_type']) . '</td>
          <td>' . esc($partsDisplay) . '</td>
          <td>' . number_format((float) $row['amount'], 2, ',', ' ') . ' SEK</td>
          <td>' . esc($row['customer']) . '</td>
          <td>' . esc($row['plate']) . '</td>
          <td>' . esc($row['created_at']) . '</td>
          <td><button type="button" onclick="copyWorkOrder(\'' . esc($workOrder) . '\')">Kopiera arbetsorder</button></td>
        </tr>';
    }

    $adminButton = current_is_admin() ? '<a class="btn btn-secondary" href="?action=admin">Adminpanel</a>' : '';

    $body = $errorHtml . '<div class="card">
      <h1>Alla kvitton</h1>
      <div class="buttons">
        <a class="btn" href="?action=new_receipt">Skapa kvitto</a>
        ' . $adminButton . '
      </div>
      <p class="muted">Arbetsorder följer formatet <strong>Benny\'s Arbetsorder - 00000</strong> och ökar dynamiskt.</p>
    </div>
    <div class="card">
      <table>
        <thead>
          <tr><th>#</th><th>Mekaniker</th><th>Typ</th><th>Antal delar</th><th>Summa</th><th>Kund</th><th>Regplåt</th><th>Skapad</th><th>Arbetsorder</th></tr>
        </thead>
        <tbody>' . $tableRows . '</tbody>
      </table>
    </div>
    <script>
      async function copyWorkOrder(text) {
        try {
          await navigator.clipboard.writeText(text);
          alert("Kopierat: " + text);
        } catch (e) {
          alert("Kunde inte kopiera automatiskt. Text: " + text);
        }
      }
    </script>';

    render_page('Kvitton', $body, current_user());
    exit;
}

$loginError = '';
if ($errors) {
    $loginError = '<div class="error">' . esc($errors[0]) . '</div>';
}

$body = '<div class="card">
  <h1>Dashboard</h1>
  <p class="muted">Logga in för att skapa och hantera kvitton för verkstaden.</p>
  ' . $loginError . '
  <form method="post" action="?action=login">
    <label>Personnummer <input name="personnummer" placeholder="ÅÅÅÅMMDD-XXXX" required /></label>
    <label>Lösenord <input type="password" name="password" required /></label>
    <button type="submit">Logga in</button>
  </form>
  <p class="muted">Admin: 19900101-1234/motor123</p>
  <p class="muted">Mekaniker: 19920202-5678/garage123, 19950505-9012/bennys123</p>
</div>';

render_page('Logga in', $body);

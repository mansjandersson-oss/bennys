<?php
declare(strict_types=1);

session_start();

$dbDir = __DIR__ . '/data';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}
$dbPath = $dbDir . '/bennys.sqlite';
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

    ensure_is_admin_column($pdo);

    $users = [
        ['19900101-1234', 'motor123', 1],
        ['19920202-5678', 'garage123', 0],
        ['19950505-9012', 'bennys123', 0],
    ];

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO users (username, password, is_admin) VALUES (?, ?, ?)');
    foreach ($users as $user) {
        $stmt->execute($user);
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
    if (in_array($workType, ['Reperation', 'Styling', 'Prestanda'], true)) {
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

    $usersStmt = $pdo->query('SELECT id, username, is_admin FROM users ORDER BY id ASC');
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

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

    $selectedRep = ($workType === 'Reperation') ? ' selected' : '';
    $selectedSty = ($workType === 'Styling') ? ' selected' : '';
    $selectedPre = ($workType === 'Prestanda') ? ' selected' : '';

    $body = $messageHtml . $errorHtml . '<div class="card">
      <h1>Adminpanel</h1>
      <p class="muted">Statistik, filter och användarhantering för Benny\'s Motorworks.</p>
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
          <select name="work_type">
            <option value="">Alla</option>
            <option value="Reperation"' . $selectedRep . '>Reperation</option>
            <option value="Styling"' . $selectedSty . '>Styling</option>
            <option value="Prestanda"' . $selectedPre . '>Prestanda</option>
          </select>
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

if ($action === 'create_receipt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $workType = trim($_POST['work_type'] ?? '');
    $partsCountRaw = trim($_POST['parts_count'] ?? '');
    $amountRaw = trim($_POST['amount'] ?? '');
    $customer = trim($_POST['customer'] ?? '');
    $plate = strtoupper(trim($_POST['plate'] ?? ''));

    if (!in_array($workType, ['Reperation', 'Styling', 'Prestanda'], true)) {
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
    $body = '<div class="card">
      <h1>Nytt kvitto</h1>
      <form method="post" action="?action=create_receipt">
        <label>Mekaniker <input value="' . esc(current_user()) . '" disabled /></label>
        <label>Typ av arbete
          <select name="work_type" required>
            <option value="Reperation">Reperation</option>
            <option value="Styling">Styling</option>
            <option value="Prestanda">Prestanda</option>
          </select>
        </label>
        <label>Antal delar (för Styling/Prestanda) <input type="number" min="0" name="parts_count" /></label>
        <label>Summa (SEK) <input type="number" min="0" step="0.01" name="amount" required /></label>
        <label>Kund <input name="customer" required /></label>
        <label>Regplåt (XXX-000) <input name="plate" placeholder="ABC-123" required /></label>
        <div class="buttons">
          <button type="submit">Skicka kvitto</button>
          <a class="btn btn-secondary" href="?action=receipts">Avbryt</a>
        </div>
      </form>
    </div>';

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

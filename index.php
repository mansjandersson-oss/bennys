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

    $users = [
        ['19900101-1234', 'motor123'],
        ['19920202-5678', 'garage123'],
        ['19950505-9012', 'bennys123'],
    ];

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO users (username, password) VALUES (?, ?)');
    foreach ($users as $user) {
        $stmt->execute($user);
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
        $auth = '<div class="user">Inloggad som <strong>' . esc($username) . '</strong> · <a href="?action=logout">Logga ut</a></div>';
    }

    $output = str_replace(
        ['{{TITLE}}', '{{AUTH}}', '{{BODY}}'],
        [esc($title), $auth, $body],
        $template
    );

    echo $output;
}

init_db($pdo);

$action = $_GET['action'] ?? 'login';
$errors = [];

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

    $stmt = $pdo->prepare('SELECT username FROM users WHERE username = ? AND password = ?');
    $stmt->execute([$personnummer, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $errors[] = 'Fel personnummer eller lösenord.';
    } else {
        $_SESSION['personnummer'] = $user['username'];
        redirect('?action=receipts');
    }
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

    $body = $errorHtml . '<div class="card">
      <h1>Alla kvitton</h1>
      <a class="btn" href="?action=new_receipt">Skapa kvitto</a>
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
  <p class="muted">Demo-konton: 19900101-1234/motor123, 19920202-5678/garage123, 19950505-9012/bennys123</p>
</div>';

render_page('Logga in', $body);

<?php
// Stratus marketing-site contact handler.
//
// Hardened 21 Jul 2026 after spam came through: added per-IP RATE LIMITING,
// moved logging OUT of the docroot, and stopped leaking errors to the visitor.
// Same discipline as the quote-relay endpoint (private/ storage, honeypot,
// rate-limit, log every attempt with its outcome, never echo internals).
//
// This file lives in the git-managed docroot (auto-deployed, wiped/reset every
// 60s), so its rate DB and log MUST live in private/ (a sibling of public_html,
// outside the docroot): git-reset never touches it, and nothing there is
// web-reachable — unlike the old logs/enquiries.log, which was public AND got
// wiped on every deploy.

error_reporting(E_ALL);
ini_set('display_errors', '0');   // log errors, never show them to the visitor
ini_set('log_errors', '1');

require_once('email_engine.php');

const PRIV      = __DIR__ . '/../private/contact';
const RATE_MAX  = 5;              // submissions per IP...
const RATE_MINS = 60;            // ...per this many minutes

function client_ip(): string {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return trim(explode(',', $ip)[0]);
}

function contact_log(string $outcome, string $ip, string $detail = ''): void {
    // One line per attempt, outside the docroot. This is the audit trail: it
    // shows honeypot hits and rate-limit blocks, not just delivered mail.
    if (!is_dir(PRIV)) { @mkdir(PRIV, 0750, true); }
    @file_put_contents(PRIV . '/contact.log',
        gmdate('c') . " {$outcome} ip={$ip}" . ($detail ? " {$detail}" : '') . "\n",
        FILE_APPEND);
}

function redirect(string $status): void {
    // Preserve the site's existing UX contract (index.html reads ?status=).
    echo '<script>window.location.href="index.html?status=' . $status . '#contact";</script>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }

$ip = client_ip();

// ── Honeypot — invisible field, only bots fill it. Look successful, send nothing.
if (!empty($_POST['website'])) {
    contact_log('honeypot', $ip);
    redirect('success');
}

// ── Rate limit (the protection that was missing). SQLite in private/, per IP.
try {
    if (!is_dir(PRIV)) { @mkdir(PRIV, 0750, true); }
    $db = new PDO('sqlite:' . PRIV . '/rate.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS rate (ip TEXT, ts TEXT)');
    $since = gmdate('Y-m-d H:i:s', time() - RATE_MINS * 60);
    $st = $db->prepare('SELECT COUNT(*) FROM rate WHERE ip = ? AND ts > ?');
    $st->execute([$ip, $since]);
    if ((int)$st->fetchColumn() >= RATE_MAX) {
        contact_log('rate-limited', $ip);
        redirect('error');
    }
    // Count this attempt toward the window (before validation, so a flood of
    // valid-looking spam is throttled too — that is what got through).
    $db->prepare('INSERT INTO rate (ip, ts) VALUES (?, ?)')->execute([$ip, gmdate('Y-m-d H:i:s')]);
} catch (Throwable $e) {
    // Never let a rate-store hiccup break the form or leak — log and continue.
    error_log('process.php rate store: ' . $e->getMessage());
}

// ── Collect + sanitise ────────────────────────────────────────────────────────
$name    = trim(strip_tags($_POST['name']    ?? ''));
$email   = trim($_POST['email']              ?? '');
$service = trim(strip_tags($_POST['service'] ?? ''));
$message = trim(strip_tags($_POST['message'] ?? ''));

// ── Validate ──────────────────────────────────────────────────────────────────
$errors = [];
if (strlen($name) < 2)                          $errors[] = 'name';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'email';
if (strlen($message) < 10)                      $errors[] = 'message';
if ($errors) {
    contact_log('invalid', $ip, 'fields=' . implode(',', $errors));
    redirect('error');
}

try {
    $service_labels = [
        'website'    => 'A website',
        'webapp'     => 'A web application',
        'software'   => 'Custom software',
        'marketing'  => 'SEO or digital advertising',
        'mobile'     => 'A mobile app',
        'hosting'    => 'Hosting or email setup',
        'everything' => 'All of the above',
    ];
    $service_readable = $service_labels[$service] ?? ($service ?: 'General Inquiry');

    $content  = "Name: {$name}\n";
    $content .= "Email: {$email}\n";
    $content .= "Service: {$service_readable}\n\n";
    $content .= "Message: {$message}\n\n";
    $content .= "------------------------------------------------\n";
    $content .= "Submitted: " . date('d M Y, H:i') . "\n";
    $content .= "IP Address: {$ip}\n";
    $content .= "\nReply directly to this email to respond to {$name}.";

    send_stratus_email('New Blueprint Submission: ' . $service_readable, $content);
    contact_log('sent', $ip, "email={$email} service={$service}");
    redirect('success');

} catch (\Exception $e) {
    // Log the detail; show the visitor a clean error, never the internals.
    error_log('process.php send error: ' . $e->getMessage());
    contact_log('send-failed', $ip, substr($e->getMessage(), 0, 120));
    redirect('error');
}

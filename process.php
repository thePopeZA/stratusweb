<?php
// Error reporting turned on to catch any issues immediately
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pull in the shared email engine
require_once('email_engine.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ── Honeypot — invisible field, only bots fill this in ───────────────
    if (!empty($_POST['website'])) {
        // Bot detected — pretend success, send nothing
        echo '<script type="text/javascript">';
        echo 'window.location.href = "index.html?status=success#contact";';
        echo '</script>';
        exit;
    }

    // ── Collect and sanitise input ─────────────────────────────────────────
    $name    = trim(strip_tags($_POST['name']    ?? ''));
    $email   = trim($_POST['email']              ?? '');
    $service = trim(strip_tags($_POST['service'] ?? ''));
    $message = trim(strip_tags($_POST['message'] ?? ''));

    // ── Validate ────────────────────────────────────────────────────────────
    $errors = [];
    if (strlen($name) < 2)                          $errors[] = 'name';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'email';
    if (strlen($message) < 10)                       $errors[] = 'message';

    if (!empty($errors)) {
        echo '<script type="text/javascript">';
        echo 'window.location.href = "index.html?status=error#contact";';
        echo '</script>';
        exit;
    }

    try {
        // ── Map service dropdown value to readable label ──────────────────
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

        // ── Build the email content ────────────────────────────────────────
        $content  = "Name: {$name}\n";
        $content .= "Email: {$email}\n";
        $content .= "Service: {$service_readable}\n\n";
        $content .= "Message: {$message}\n\n";
        $content .= "------------------------------------------------\n";
        $content .= "Submitted: " . date('d M Y, H:i') . "\n";
        $content .= "IP Address: " . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        $content .= "\nReply directly to this email to respond to {$name}.";

        // ── Send using the shared engine ───────────────────────────────────
        send_stratus_email('New Blueprint Submission: ' . $service_readable, $content);

        // ── Optional backup log ────────────────────────────────────────────
        $log_line = date('Y-m-d H:i:s') . " | {$name} | {$email} | {$service_readable}\n";
        @file_put_contents(__DIR__ . '/logs/enquiries.log', $log_line, FILE_APPEND);

        // ── Success redirect ───────────────────────────────────────────────
        echo '<script type="text/javascript">';
        echo 'window.location.href = "index.html?status=success#contact";';
        echo '</script>';
        exit;

    } catch (\Exception $e) {
        // Catch any server, PHP, or API errors
        error_log("process.php error: " . $e->getMessage());
        die("<h3>Submission Error</h3><pre>" . htmlspecialchars($e->getMessage()) . "</pre>");
    }
}
?>

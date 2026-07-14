<?php
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    die("Direct access not permitted.");
}

function stratus_env($key, $default = null) {
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $path = '/home/jurgsw/web/stratusnet.co.za/private/.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }
    return $vars[$key] ?? $default;
}

function send_stratus_email($subject, $body_content, $reply_to = null) {
    $api_key = stratus_env('RESEND_API_KEY');
    if (!$api_key) {
        error_log('Email Engine Error: RESEND_API_KEY not found');
        return false;
    }

    $payload = [
        'from'    => 'Stratus Net <noreply@stratusnet.co.za>',
        'to'      => ['info@stratusnet.co.za'],
        'subject' => $subject,
        'text'    => $body_content,
    ];

    if ($reply_to && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
        $payload['reply_to'] = $reply_to;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || $status < 200 || $status >= 300) {
        error_log("Email Engine Error: HTTP {$status} — {$response} {$err}");
        return false;
    }

    return true;
}
?>
<?php
/**
 * Stratus quote tool — logo upload receiver.
 *
 * Accepts ONE image via multipart POST field "logo", stores it under uploads/
 * with a random name, and returns JSON {"url": "https://.../uploads/xxx.png"}.
 * Image-only, size-capped, SVGs sanitised. Same-origin with quote.html.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('POST only', 405);
if (empty($_FILES['logo']) || !isset($_FILES['logo']['tmp_name'])) fail('no file received');

$f = $_FILES['logo'];
if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) fail('upload failed (code ' . ($f['error'] ?? '?') . ')');
if ($f['size'] <= 0 || $f['size'] > 8 * 1024 * 1024) fail('file too large (max 8 MB)');
if (!is_uploaded_file($f['tmp_name'])) fail('invalid upload');

// Trust the CONTENT, never the client-supplied name/extension.
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
$map = [
    'image/png'      => 'png',
    'image/jpeg'     => 'jpg',
    'image/gif'      => 'gif',
    'image/webp'     => 'webp',
    'image/svg+xml'  => 'svg',
    'text/plain'     => 'svg',   // some SVGs sniff as text/xml or text/plain
    'text/xml'       => 'svg',
    'application/xml'=> 'svg',
];
if (!isset($map[$mime])) fail('please upload an image (PNG, JPG, GIF, WEBP or SVG)');
$ext = $map[$mime];

// SVG can carry active content — sanitise hard, reject anything scriptable.
if ($ext === 'svg') {
    $svg = file_get_contents($f['tmp_name']);
    if ($svg === false) fail('could not read the file');
    if (stripos($svg, '<svg') === false) fail('that does not look like a valid SVG');
    if (preg_match('/<script|<foreignObject|<iframe|<!ENTITY|javascript:|on[a-z]+\s*=/i', $svg)) {
        fail('that SVG contains active content and was rejected — please export a plain SVG or send a PNG');
    }
}

$dir = __DIR__ . '/uploads';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
if (!is_dir($dir) || !is_writable($dir)) fail('storage unavailable — please attach your logo on WhatsApp instead', 500);

try {
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
} catch (Exception $e) {
    $name = 'logo_' . uniqid('', true) . '.' . $ext;
}
$dest = $dir . '/' . $name;

if (!move_uploaded_file($f['tmp_name'], $dest)) fail('could not save the file', 500);
@chmod($dest, 0644);

$host = $_SERVER['HTTP_HOST'] ?? 'stratusnet.co.za';
echo json_encode(['url' => 'https://' . $host . '/uploads/' . $name]);

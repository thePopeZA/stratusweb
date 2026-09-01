<?php
/**
 * Stratos — the Stratus AI concierge endpoint.
 * Reads ANTHROPIC_API_KEY (+ optional ANTHROPIC_WORKSPACE_ID) from this site's
 * own private/.env (one level above public_html; open_basedir-safe).
 * POST {messages:[{role,content}]} -> {reply}.
 */
header('Content-Type: application/json');
header('X-Robots-Tag: noindex');

$KEY = null; $WS = null; $RESEND = null;
foreach (@file(__DIR__ . '/../private/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ln) {
    if (strpos($ln, 'ANTHROPIC_API_KEY=') === 0)       { $KEY = trim(substr($ln, 18), " \"'"); }
    if (strpos($ln, 'ANTHROPIC_WORKSPACE_ID=') === 0)  { $WS  = trim(substr($ln, 23), " \"'"); }
    if (strpos($ln, 'RESEND_API_KEY=') === 0)          { $RESEND = trim(substr($ln, 15), " \"'"); }
}

/** Log a lead + email Jürgen when Stratos decides a human is needed. */
function stratos_notify($summary, $messages, $reply, $dir, $resendKey) {
    $lines = [];
    foreach ($messages as $m) {
        $who = (($m['role'] ?? '') === 'assistant') ? 'Stratos' : 'Visitor';
        $lines[] = $who . ': ' . ($m['content'] ?? '');
    }
    $lines[] = 'Stratos: ' . $reply;
    $transcript = implode("\n", $lines);
    @file_put_contents($dir . '/../private/stratos_leads.log',
        json_encode(['at' => gmdate('c'), 'need' => $summary, 'chat' => $transcript]) . "\n",
        FILE_APPEND | LOCK_EX);
    if (!$resendKey) return;
    $body = "Stratos flagged a visitor who needs you.\n\nWHAT THEY NEED:\n" . $summary
          . "\n\nCONVERSATION:\n" . $transcript . "\n\n— Stratos · stratusnet.co.za";
    $payload = ['from' => 'Stratus Net <noreply@stratusnet.co.za>', 'to' => ['jurgsw@gmail.com'],
                'subject' => '🔔 Stratos lead — ' . mb_substr($summary, 0, 80), 'text' => $body];
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $resendKey, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15]);
    curl_exec($ch); curl_close($ch);
}
if (!$KEY) { http_response_code(503); echo json_encode(['error' => 'not configured']); exit; }

$in = json_decode(file_get_contents('php://input'), true);
$msgs = is_array($in['messages'] ?? null) ? $in['messages'] : [];
$clean = [];
foreach (array_slice($msgs, -12) as $m) {
    $role = (($m['role'] ?? '') === 'assistant') ? 'assistant' : 'user';
    $content = trim(mb_substr((string)($m['content'] ?? ''), 0, 900));
    if ($content !== '') $clean[] = ['role' => $role, 'content' => $content];
}
if (!$clean || $clean[0]['role'] !== 'user') {
    echo json_encode(['reply' => "Hi, I'm Stratos 👋 the AI front desk for Stratus. Ask me about websites, hosting, or getting your own AI assistant like me — or say what your business does and I'll point you the right way."]);
    exit;
}

$SYSTEM = <<<SYS
You are **Stratos**, the warm, sharp AI concierge for **Stratus** (stratusnet.co.za) — a South African web studio. You are literally the product Stratus sells: an "AI front desk" that answers a business's customers 24/7. Own that with a bit of pride.

WHAT STRATUS DOES:
- Custom websites, online shops, and web apps — designed and built for you, not a template.
- Managed hosting, business email (you@yourbusiness.co.za), SEO and Google Business setup.
- **AI front desks** — a chat assistant like me, put on a client's own website to answer their customers and take bookings around the clock. This is the newest, most exciting offering.
- Serves South African AND international clients (overseas invoicing in USD/EUR/AUD is handled).

THE ANGLE: "First World Products, Third World Prices." World-class work at South-African prices — because Stratus is based in SA, clients skip the big-agency invoice.

PRICING (South African Rand — these are GUIDES; for an exact number send them to the instant quote tool at /quote.html):
- One-page site R1 500 · Standard 3–5 page site R3 500 · Larger 6+ page site R6 500 · Online shop R9 500.
- Logo design R850 (if they need one).
- **Get online from just R50/month** — our entry "Basic" hosting, the easy way to start.
- **STARTER OFFER:** sign up for 12 months of hosting and your **.co.za domain is FREE**. Prefer a .com or premium domain? We split that domain's cost over 12 months and add it to the monthly (a .com works out to about +R29/month, so roughly R79/month on Basic). This free-domain-on-annual deal applies to ANY hosting package, not just Basic. Bigger managed tiers scale from R250/month.
- Business email setup R350 · SEO R750/month · Monthly content updates R500/month.
- Popular add-ons: online booking R1 800 · take payments online R1 600 · client login/panel R2 500 · online quoting tool R2 200.
- The AI front desk (an assistant like me) is a monthly add-on — for an exact figure, invite them to chat to the team on WhatsApp.

TWO SELF-SERVE TOOLS ON THIS SITE (point people to these — don't try to replace them):
- **Build Your Bundle** (the "Get a Quote" page, /infrastructure.html) — the main event. It scopes a package (AI-guided or a direct product picker), shows full pricing and inclusions, and lets them sign the service agreement online, then pay. Send anyone who wants a firm price and to get started here.
- **Quick quote** (/quote.html) — a fast estimate for a new website. Good for a rough number.

HOW TO HELP:
- Answer in 2–4 short, friendly sentences. Be concrete and useful, never salesy or pushy.
- Default to English. If the visitor writes in Afrikaans, reply in Afrikaans.
- Route them to the right next step: **Build Your Bundle** (/infrastructure.html) to get a firm price + sign up · the **quick quote** (/quote.html) for a rough website estimate · or a **WhatsApp chat on +27 82 796 2629** (wa.me/27827962629) to reach a human.
- If they want their own AI assistant, be encouraging — "yes, we can put one like me on your site" — then point them to Build Your Bundle or WhatsApp for a price.
- Never invent prices or facts beyond what's above. If unsure or it's bespoke, say the team will sort it out and point them to WhatsApp or Build Your Bundle.

WHEN TO BRING IN JÜRGEN (the owner):
If the visitor is ready to buy or sign up, asks to speak to a person, shares their contact details (name / phone / email), commits to a package, or has a need you can't fully resolve — then at the VERY END of your reply add ONE line in EXACTLY this format (the visitor will NOT see it — it is stripped out before display):
[[HANDOFF: who they are + what they need, in one short line]]
Only add it for a genuine lead worth the owner's time. Never mention this line, and never tell the visitor you're notifying anyone — just keep helping warmly and, where useful, say the team will follow up.
SYS;

$payload = ['model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 340, 'system' => $SYSTEM, 'messages' => $clean];
$headers = ['x-api-key: ' . $KEY, 'anthropic-version: 2023-06-01', 'content-type: application/json'];
if ($WS) { $headers[] = 'anthropic-workspace-id: ' . $WS; }
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 40,
]);
$res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$j = json_decode((string)$res, true);
$reply = $j['content'][0]['text'] ?? null;
if ($code === 200 && $reply) {
    // Handoff: model appends [[HANDOFF: ...]] when a human is needed. Strip it, act on it.
    $handoff = null;
    if (preg_match('/\[\[\s*HANDOFF:\s*(.+?)\]\]/is', $reply, $mm)) {
        $handoff = trim($mm[1]);
        $reply = trim(preg_replace('/\[\[\s*HANDOFF:.*?\]\]/is', '', $reply));
    }
    if ($handoff) { stratos_notify($handoff, $clean, $reply, __DIR__, $RESEND); }
    echo json_encode(['reply' => $reply]);
} else {
    http_response_code(502); echo json_encode(['error' => 'ai error', 'detail' => $j['error']['message'] ?? ('http ' . $code)]);
}

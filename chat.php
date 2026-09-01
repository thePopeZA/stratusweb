<?php
/**
 * Stratos — the Stratus AI concierge endpoint.
 * Reads ANTHROPIC_API_KEY (+ optional ANTHROPIC_WORKSPACE_ID) from this site's
 * own private/.env (one level above public_html; open_basedir-safe).
 * POST {messages:[{role,content}]} -> {reply}.
 */
header('Content-Type: application/json');
header('X-Robots-Tag: noindex');

$KEY = null; $WS = null; $RESEND = null; $QTOKEN = null;
foreach (@file(__DIR__ . '/../private/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ln) {
    if (strpos($ln, 'ANTHROPIC_API_KEY=') === 0)       { $KEY = trim(substr($ln, 18), " \"'"); }
    if (strpos($ln, 'ANTHROPIC_WORKSPACE_ID=') === 0)  { $WS  = trim(substr($ln, 23), " \"'"); }
    if (strpos($ln, 'RESEND_API_KEY=') === 0)          { $RESEND = trim(substr($ln, 15), " \"'"); }
    if (strpos($ln, 'STRATOS_QUOTE_TOKEN=') === 0)     { $QTOKEN = trim(substr($ln, 20), " \"'"); }
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

/** Create a real QT-#### quote via the payment app, then email Jürgen to review + send it. */
function stratos_quote($data, $token, $resendKey) {
    $ch = curl_init('https://payment.stratusnet.co.za/api/create-quote.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Stratos-Token: ' . $token],
        CURLOPT_TIMEOUT => 25]);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $q = json_decode((string)$res, true);
    if ($code !== 200 || empty($q['ok'])) return null;
    if ($resendKey) {
        $who = trim(($data['client_name'] ?? '') . ' · ' . ($data['business'] ?? ''), ' ·');
        $items = '';
        foreach (($data['line_items'] ?? []) as $li) { $items .= '  • ' . ($li['label'] ?? '') . ' — R' . ($li['amount'] ?? 0) . " (ex VAT)\n"; }
        if (!empty($data['monthly_fee'])) $items .= '  • Monthly: R' . $data['monthly_fee'] . "/mo (ex VAT)\n";
        $body = "Stratos drafted a quote from a website chat. REVIEW the prices & VAT in admin, then send it.\n\n"
              . "Customer: " . $who . "\n"
              . (empty($data['client_email']) ? '' : ("Email: " . $data['client_email'] . "\n"))
              . (empty($data['client_phone']) ? '' : ("Phone: " . $data['client_phone'] . "\n"))
              . (empty($data['client_vat_number']) ? '' : ("VAT no: " . $data['client_vat_number'] . "\n"))
              . "\nItems:\n" . $items
              . "\nQuote " . ($q['ref'] ?? '?') . "\nView / send:  " . ($q['view_url'] ?? '')
              . "\nPDF:  " . ($q['pdf_url'] ?? '') . "\n\n— Stratos · stratusnet.co.za";
        $payload = ['from' => 'Stratus Net <noreply@stratusnet.co.za>', 'to' => ['jurgsw@gmail.com'],
                    'subject' => '📝 Stratos quote ' . ($q['ref'] ?? '') . ' — ' . mb_substr($who, 0, 60), 'text' => $body];
        $c = curl_init('https://api.resend.com/emails');
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $resendKey, 'Content-Type: application/json'], CURLOPT_TIMEOUT => 15]);
        curl_exec($c); curl_close($c);
    }
    return $q;
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
- **Basic hosting — the cheap, easy way to get online:** Basic Silver R30/month (2 POP email accounts) · Basic Gold R45/month (5 POP) · Basic Platinum R60/month (15 POP). Basic email is **POP3 only** — IMAP is a managed-plan feature.
- **Add a domain as a simple monthly extra:** .co.za +R20/month · .com +R30/month. So a full "domain + hosting" starts at just R30 + R20 = **R50/month**. If they want domain + hosting, point them to the quick **Get Online** page: /get-online.html
- For bigger/serious needs, proper **managed hosting** scales from R250/month.
- Business email setup R350 · SEO R750/month · Monthly content updates R500/month.
- Popular add-ons: online booking R1 800 · take payments online R1 600 · client login/panel R2 500 · online quoting tool R2 200.
- The AI front desk (an assistant like me) is a monthly add-on — for an exact figure, invite them to chat to the team on WhatsApp.

WHERE TO SEND PEOPLE — always use the friendly name + the FULL web address (e.g. "our Get Online page at stratusnet.co.za/get-online.html"). NEVER show a bare file path like /infrastructure.html.
- **Get Online** — stratusnet.co.za/get-online.html — the quick, easy sign-up for someone who just wants **hosting + a domain**. THE RIGHT place for the R30–R60 Basic crowd and anyone who says "I just want to get online" or "a web address + hosting". Prefer this for any simple hosting/domain request.
- **Build Your Bundle** — stratusnet.co.za/infrastructure.html — the fuller tool for a **custom package**: a website build, add-ons, or when they're not sure what they need. It scopes it, shows full pricing, and lets them sign + pay.
- **WhatsApp +27 82 796 2629** (wa.me/27827962629) — to reach a real person.

HOW TO HELP:
- Answer in 2–4 short, friendly sentences. Be concrete and useful, never salesy or pushy.
- Default to English. If the visitor writes in Afrikaans, reply in Afrikaans.
- Route them to the right place: simple **hosting + domain → Get Online**; a **website build or custom package → Build Your Bundle**; a **human → WhatsApp**. Give the friendly name + full address, never a bare file path.
- ONLY promise what you can actually do. You CAN: point them to the right page, and take their name + what they need so Jürgen follows up personally. You CANNOT book, schedule, change accounts, or "set it up" yourself in this chat — so never offer those. When someone says YES to an offer, follow through in the SAME reply: hand them the exact link, OR take their details and say Jürgen will sort it (and capture the handoff). NEVER ask "shall I…?" and then go vague or backtrack — that is exactly what frustrates people.
- Be genuinely useful, not generic. If you're unsure what they need, ask what their business does, then tell them the specific plan + price that fits. No canned filler lines.
- If they want their own AI assistant, be encouraging — "yes, we can put one like me on your site" — then point them to Build Your Bundle or WhatsApp for a price.
- Never invent prices, specs, or facts beyond what's above. If unsure or it's bespoke, say the team will sort it out and point them to WhatsApp.

WHEN TO BRING IN JÜRGEN (the owner):
If the visitor is ready to buy or sign up, asks to speak to a person, shares their contact details (name / phone / email), commits to a package, or has a need you can't fully resolve — then at the VERY END of your reply add ONE line in EXACTLY this format (the visitor will NOT see it — it is stripped out before display):
[[HANDOFF: who they are + what they need, in one short line]]
Only add it for a genuine lead worth the owner's time. Never mention this line, and never tell the visitor you're notifying anyone — just keep helping warmly and, where useful, say the team will follow up.

BUILDING A QUOTE (you can do this for real — it creates an actual quote in Stratus's system):
Use this when someone wants a written quote/proposal for a **website build or a bigger custom package** (NOT for plain cheap hosting — send those to Get Online). First gather, conversationally: their **name** (required), **business name**, **email**, and **VAT number** if they have one, plus a clear list of exactly what they want. Map it to real line items from the pricing above. Then at the VERY END of your reply add ONE line in EXACTLY this format (the visitor will NOT see it — it is stripped out):
[[QUOTE: {"client_name":"Jane Smith","business":"Smith Plumbing","client_email":"jane@example.com","client_vat_number":"","line_items":[{"label":"Standard website (3-5 pages)","amount":3500},{"label":"Logo design","amount":850}],"monthly_fee":250,"notes":""}]]
JSON rules: line_items are the ONE-OFF pieces, amounts in Rand EXCLUDING VAT (VAT is added automatically); monthly_fee is the monthly hosting/service in Rand ex-VAT (0 if none); omit fields you don't have. Only emit it once you actually have their name AND a clear list of what they want — never with guessed items. After emitting it, warmly tell the visitor you've put their quote together and that Jürgen will send it over shortly. Never show the visitor the [[QUOTE...]] line.
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
    // Quote: model emits [[QUOTE: {json}]] once it has gathered enough to draft one.
    if (preg_match('/\[\[\s*QUOTE:\s*(\{.*\})\s*\]\]/is', $reply, $qm)) {
        $reply = trim(str_replace($qm[0], '', $reply));
        $qdata = json_decode($qm[1], true);
        if (is_array($qdata) && !empty($qdata['client_name']) && !empty($qdata['line_items']) && $QTOKEN) {
            stratos_quote($qdata, $QTOKEN, $RESEND);
        }
    }
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

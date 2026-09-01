/* Stratos — the Stratus AI concierge widget. Self-contained; posts to /chat.php.
   Add to any page with: <script defer src="/stratos.js"></script> */
(function () {
  if (window.__stratosLoaded) return; window.__stratosLoaded = true;

  var CSS = ''
    + '#st-fab{position:fixed;right:20px;bottom:20px;z-index:99999;display:flex;align-items:center;gap:9px;'
    + 'background:#0A1C35;color:#fff;border:none;border-radius:100px;padding:13px 20px;font-family:inherit;'
    + 'font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 12px 30px rgba(10,28,53,.4);transition:transform .2s,box-shadow .2s}'
    + '#st-fab:hover{transform:translateY(-2px);box-shadow:0 16px 38px rgba(10,28,53,.5)}'
    + '#st-fab .st-dot{width:9px;height:9px;border-radius:50%;background:#00D2FF;box-shadow:0 0 0 0 rgba(0,210,255,.7);animation:stPulse 2.2s infinite}'
    + '@keyframes stPulse{0%{box-shadow:0 0 0 0 rgba(0,210,255,.6)}70%{box-shadow:0 0 0 10px rgba(0,210,255,0)}100%{box-shadow:0 0 0 0 rgba(0,210,255,0)}}'
    + '#st-panel{position:fixed;right:20px;bottom:80px;z-index:99999;width:374px;max-width:calc(100vw - 28px);height:540px;'
    + 'max-height:calc(100vh - 110px);background:#fff;border-radius:18px;box-shadow:0 40px 90px rgba(10,28,53,.4);'
    + 'display:none;flex-direction:column;overflow:hidden;font-family:inherit}'
    + '#st-panel.st-open{display:flex}'
    + '#st-panel .st-h{display:flex;align-items:center;gap:11px;padding:15px 18px;background:#0A1C35;color:#fff}'
    + '#st-panel .st-av{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#00D2FF,#0A1C35);'
    + 'display:flex;align-items:center;justify-content:center;font-weight:800;color:#06182F;flex:none;font-size:17px}'
    + '#st-panel .st-h b{font-size:15px;display:block;line-height:1.2}'
    + '#st-panel .st-h .st-s{font-size:12px;color:#9fb6d4;display:flex;align-items:center;gap:6px}'
    + '#st-panel .st-h .st-s i{width:7px;height:7px;border-radius:50%;background:#2fe38a;display:inline-block}'
    + '#st-panel .st-x{margin-left:auto;background:none;border:none;color:#fff;font-size:22px;line-height:1;cursor:pointer;opacity:.7}'
    + '#st-body{flex:1;overflow-y:auto;padding:16px;background:#F3F6FB;display:flex;flex-direction:column;gap:10px}'
    + '#st-body .st-m{max-width:84%;padding:11px 15px;font-size:14.5px;line-height:1.5;border-radius:15px}'
    + '#st-body .st-bot{background:#fff;border:1px solid #E3E8EF;color:#0A1C35;align-self:flex-start;border-bottom-left-radius:4px}'
    + '#st-body .st-me{background:#0A1C35;color:#fff;align-self:flex-end;border-bottom-right-radius:4px}'
    + '#st-body .st-tp{align-self:flex-start;background:#fff;border:1px solid #E3E8EF;border-radius:15px;border-bottom-left-radius:4px;padding:13px 16px;display:flex;gap:5px}'
    + '#st-body .st-tp span{width:7px;height:7px;border-radius:50%;background:#9fb6d4;animation:stBlink 1.2s infinite}'
    + '#st-body .st-tp span:nth-child(2){animation-delay:.2s}#st-body .st-tp span:nth-child(3){animation-delay:.4s}'
    + '@keyframes stBlink{0%,60%,100%{opacity:.3}30%{opacity:1}}'
    + '#st-chips{display:flex;gap:7px;flex-wrap:wrap;padding:0 14px 12px;background:#fff}'
    + '#st-chips button{background:#EEF3FA;border:1px solid #DDE6F1;color:#33465f;border-radius:100px;padding:8px 13px;font-family:inherit;font-size:12.5px;cursor:pointer}'
    + '#st-chips button:hover{border-color:#00A9CE;color:#0A1C35}'
    + '#st-foot{display:flex;gap:9px;padding:12px 14px;border-top:1px solid #E3E8EF;background:#fff}'
    + '#st-foot input{flex:1;border:1px solid #D6DEE9;border-radius:100px;padding:12px 16px;font-family:inherit;font-size:14px;outline:none;color:#0A1C35}'
    + '#st-foot input:focus{border-color:#00A9CE}'
    + '#st-foot button{flex:none;width:44px;height:44px;border-radius:50%;background:#00D2FF;color:#06182F;border:none;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:800}';

  var HTML = ''
    + '<button id="st-fab"><span class="st-dot"></span> Chat with Stratos</button>'
    + '<div id="st-panel">'
    + '<div class="st-h"><div class="st-av">S</div><div><b>Stratos</b>'
    + '<span class="st-s"><i></i> AI front desk · online now</span></div>'
    + '<button class="st-x" id="st-x">&times;</button></div>'
    + '<div id="st-body"></div>'
    + '<div id="st-chips">'
    + '<button data-q="What does an AI front desk cost?">AI front desk cost?</button>'
    + '<button data-q="I need a website for my business">I need a website</button>'
    + '<button data-q="Do you do online shops?">Online shops?</button>'
    + '</div>'
    + '<div id="st-foot"><input id="st-in" placeholder="Ask Stratos anything…" autocomplete="off">'
    + '<button id="st-send" aria-label="Send">&#10148;</button></div>'
    + '</div>';

  function init() {
    var style = document.createElement('style'); style.textContent = CSS; document.head.appendChild(style);
    var wrap = document.createElement('div'); wrap.innerHTML = HTML; document.body.appendChild(wrap);

    var hist = [], busy = false, greeted = false;
    var panel = document.getElementById('st-panel'), body = document.getElementById('st-body');
    var inp = document.getElementById('st-in'), chips = document.getElementById('st-chips');

    function bubble(t, who) { var d = document.createElement('div'); d.className = 'st-m ' + who; d.textContent = t; body.appendChild(d); body.scrollTop = body.scrollHeight; }
    function open() { panel.classList.add('st-open'); if (!greeted) { greeted = true; bubble("Hi, I'm Stratos 👋 the AI front desk for Stratus. Ask me about websites, hosting, or getting your own AI assistant like me — or tell me what your business does.", 'st-bot'); } inp.focus(); }
    function close() { panel.classList.remove('st-open'); }
    function send(text) {
      if (busy) return; text = (text || inp.value).trim(); if (!text) return;
      inp.value = ''; chips.style.display = 'none';
      bubble(text, 'st-me'); hist.push({ role: 'user', content: text }); busy = true;
      var tp = document.createElement('div'); tp.className = 'st-tp'; tp.innerHTML = '<span></span><span></span><span></span>';
      body.appendChild(tp); body.scrollTop = body.scrollHeight;
      fetch('/chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ messages: hist }) })
        .then(function (r) { return r.json(); })
        .then(function (j) { tp.remove(); var reply = j.reply || "Sorry, I had a hiccup — try me again?"; bubble(reply, 'st-bot'); hist.push({ role: 'assistant', content: reply }); busy = false; })
        .catch(function () { tp.remove(); bubble("Sorry, I couldn't reach the desk just now — please try again, or WhatsApp us on +27 82 796 2629.", 'st-bot'); busy = false; });
    }
    document.getElementById('st-fab').addEventListener('click', function () { panel.classList.contains('st-open') ? close() : open(); });
    document.getElementById('st-x').addEventListener('click', close);
    document.getElementById('st-send').addEventListener('click', function () { send(); });
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
    var cb = chips.querySelectorAll('button');
    for (var i = 0; i < cb.length; i++) cb[i].addEventListener('click', function () { send(this.getAttribute('data-q')); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

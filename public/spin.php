<?php
/**
 * SpinWheel Pro V2 — Public Spin Page
 * URL: https://yourdomain.com/spin/?p=TOKEN
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$token   = trim($_GET['p'] ?? '');
$project = $token ? getProjectByToken($token) : null;

if (!$project) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$projectId = (int)$project['id'];
$options   = getActiveWheelOptions($projectId);
$questions = getFormQuestions($projectId);

$db         = Database::getInstance();
$formConfig = $db->fetchOne("SELECT * FROM form_config WHERE project_id = ?", [$projectId]);
$formName   = $formConfig['form_name']   ?? $project['name'];
$formDesc   = $formConfig['description'] ?? 'Fill in your details to spin the wheel!';

$csrfToken  = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($project['name']) ?> — <?= APP_NAME ?></title>
<meta name="description" content="Spin to win amazing prizes in <?= htmlspecialchars($project['name']) ?>!">
<!-- Open Graph for social sharing -->
<meta property="og:title" content="<?= htmlspecialchars($project['name']) ?> — Spin & Win">
<meta property="og:description" content="Spin the wheel and win amazing prizes!">
<meta property="og:url" content="<?= APP_URL ?>/spin/?p=<?= htmlspecialchars($token) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ── BASE ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#04060c;--s2:#0d121e;--s3:#111828;--bd:#1a2235;--bd2:#243048;--a:#6366f1;--a2:#818cf8;--gold:#f59e0b;--gold2:#fbbf24;--g:#10b981;--r:#ef4444;--t:#e2e8f0;--t2:#94a3b8;--t3:#475569}
html,body{min-height:100vh;background:var(--bg);color:var(--t);font-family:'DM Sans',sans-serif;font-size:15px}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:4px}
/* ── LAYOUT ── */
.topbar{background:rgba(4,6,12,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd);padding:0 24px;height:52px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.brand{display:flex;align-items:center;gap:8px;font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:#fff}
.brand-dot{width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($project['color']) ?>;flex-shrink:0}
.page{max-width:1100px;margin:0 auto;padding:32px 20px 60px;display:grid;grid-template-columns:1fr 420px;gap:36px;align-items:start}
@media(max-width:860px){.page{grid-template-columns:1fr}}
/* ── WHEEL ── */
.wheel-col{display:flex;flex-direction:column;align-items:center;gap:18px}
.page-title{font-family:'Syne',sans-serif;font-size:clamp(30px,5vw,52px);font-weight:800;background:linear-gradient(135deg,var(--a2),var(--gold2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-align:center;line-height:1.1}
.page-sub{font-size:12px;letter-spacing:4px;text-transform:uppercase;color:var(--t3)}
.stats{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.stat{background:rgba(255,255,255,.04);border:1px solid var(--bd);border-radius:9px;padding:7px 16px;font-size:11px;text-align:center}
.stat strong{display:block;font-size:17px;color:var(--gold2);font-family:'Syne',sans-serif}
.wheel-wrap{position:relative;width:min(400px,86vw);height:min(400px,86vw)}
.wheel-glow{position:absolute;inset:-10px;border-radius:50%;background:conic-gradient(from 0deg,var(--a),var(--gold),var(--a2),var(--a));animation:gr 10s linear infinite;opacity:.2;filter:blur(8px)}
@keyframes gr{to{transform:rotate(360deg)}}
#wheelCanvas{position:relative;z-index:2;border-radius:50%;width:100%;height:100%}
.wheel-ptr{position:absolute;top:50%;right:-16px;transform:translateY(-50%);width:0;height:0;border-top:15px solid transparent;border-bottom:15px solid transparent;border-right:28px solid var(--gold2);filter:drop-shadow(0 0 6px rgba(251,191,36,.7));z-index:10}
.wheel-hub{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:42px;height:42px;background:radial-gradient(circle,#fff,var(--gold),var(--a));border-radius:50%;z-index:5;box-shadow:0 0 16px rgba(99,102,241,.6)}
.prizes{display:flex;flex-wrap:wrap;gap:6px;justify-content:center}
.prize-chip{display:flex;align-items:center;gap:5px;background:rgba(255,255,255,.04);border:1px solid var(--bd);border-radius:20px;padding:4px 11px;font-size:11px}
/* ── FORM ── */
.form-col{}
.form-card{background:var(--s2);border:1px solid var(--bd);border-radius:20px;overflow:hidden}
.form-card-top{height:4px;background:linear-gradient(90deg,<?= htmlspecialchars($project['color']) ?>,var(--gold))}
.form-card-body{padding:26px}
.form-title{font-family:'Syne',sans-serif;font-size:19px;font-weight:800;color:#fff;margin-bottom:4px}
.form-desc{font-size:13px;color:var(--t3);margin-bottom:22px;line-height:1.6}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:11px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--t3);margin-bottom:6px}
.fg label .req{color:var(--a)}
.fi{width:100%;background:rgba(255,255,255,.04);border:1px solid var(--bd);border-radius:8px;padding:10px 14px;color:var(--t);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:border-color .2s,box-shadow .2s;color-scheme:dark}
.fi:focus{border-color:var(--a);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.fi.err{border-color:var(--r)}
select.fi{cursor:pointer}
select.fi option{background:#0d121e;color:#e2e8f0}
textarea.fi{resize:vertical;min-height:80px}
.radio-opt,.check-opt{display:flex;align-items:center;gap:9px;padding:8px 12px;border-radius:8px;border:1px solid var(--bd);margin-bottom:6px;cursor:pointer;transition:all .15s;font-size:13px}
.radio-opt:hover,.check-opt:hover{border-color:var(--a);background:rgba(99,102,241,.05)}
.radio-opt input,.check-opt input{accent-color:var(--a)}
.stars{display:flex;gap:8px}
.star{font-size:26px;cursor:pointer;color:var(--t3);transition:color .1s;line-height:1}
.star.on,.star:hover{color:var(--gold)}
.file-area{border:2px dashed var(--bd);border-radius:9px;padding:22px 16px;text-align:center;cursor:pointer;position:relative;transition:all .2s}
.file-area:hover{border-color:var(--a);background:rgba(99,102,241,.04)}
.file-area input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.file-chosen{font-size:11px;color:var(--g);margin-top:5px;display:none}
.rank-item{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.04);border:1px solid var(--bd);border-radius:7px;padding:8px 12px;margin-bottom:5px;cursor:grab;font-size:13px}
.rank-handle{color:var(--t3)}
.rank-num{width:22px;height:22px;border-radius:50%;background:var(--a);color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ferr{font-size:11px;color:var(--r);margin-top:4px;display:none}
.ferr.show{display:block}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;display:none}
.alert.show{display:block}
.alert-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.btn-spin{width:100%;padding:14px;background:linear-gradient(135deg,var(--a),var(--gold));border:none;border-radius:11px;color:#000;font-family:'Syne',sans-serif;font-size:18px;font-weight:800;letter-spacing:2px;cursor:pointer;transition:all .2s;margin-top:8px}
.btn-spin:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 10px 28px rgba(99,102,241,.4)}
.btn-spin:disabled{opacity:.5;cursor:not-allowed}
.already-used{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:18px;text-align:center;display:none;margin-bottom:16px}
.already-used.show{display:block}
/* ── RESULT MODAL ── */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.82);backdrop-filter:blur(10px);z-index:500;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .3s}
.modal-bg.show{opacity:1;pointer-events:all}
.modal{background:var(--s2);border:1px solid var(--bd2);border-radius:24px;padding:44px 36px;text-align:center;max-width:440px;width:92%;transform:scale(.88);transition:transform .35s cubic-bezier(.34,1.4,.64,1);position:relative;overflow:hidden}
.modal-bg.show .modal{transform:scale(1)}
.modal::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--a),var(--gold))}
.m-emoji{font-size:54px;margin-bottom:14px;animation:mb .7s ease infinite alternate}
@keyframes mb{to{transform:scale(1.1)}}
.m-won{font-size:11px;letter-spacing:4px;text-transform:uppercase;color:var(--t3);margin-bottom:6px}
.m-prize{font-family:'Syne',sans-serif;font-size:clamp(24px,5vw,40px);font-weight:800;background:linear-gradient(135deg,var(--a2),var(--gold2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:12px;line-height:1.1}
.m-msg{font-size:14px;color:var(--t2);line-height:1.7;margin-bottom:24px}
.btn-close{background:transparent;border:1px solid var(--bd);color:var(--t);padding:10px 28px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;cursor:pointer;transition:all .2s}
.btn-close:hover{border-color:var(--a);color:var(--a)}
.spin-ico{display:inline-block;width:15px;height:15px;border:2px solid rgba(0,0,0,.2);border-top-color:#000;border-radius:50%;animation:sr .6s linear infinite;vertical-align:middle;margin-right:6px}
@keyframes sr{to{transform:rotate(360deg)}}
/* ── RIGHT PANEL ── */
.info-card{background:var(--s2);border:1px solid var(--bd);border-radius:14px;padding:20px;margin-bottom:16px}
.ic-title{font-size:10px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:var(--t3);margin-bottom:12px}
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-dot"></div>
    <?= htmlspecialchars($project['name']) ?>
  </div>
  <div style="font-size:12px;color:var(--t3)"><?= APP_NAME ?></div>
</div>

<div class="page">
  <!-- WHEEL COLUMN -->
  <div class="wheel-col">
    <h1 class="page-title"><?= htmlspecialchars($project['name']) ?></h1>
    <p class="page-sub">Spin to win amazing prizes</p>

    <div class="stats">
      <div class="stat"><strong><?= count($options) ?></strong>Prizes</div>
      <div class="stat"><strong>1×</strong>Spin Only</div>
      <div class="stat"><strong>FREE</strong>Entry</div>
    </div>

    <div class="wheel-wrap">
      <div class="wheel-glow"></div>
      <canvas id="wheelCanvas" width="400" height="400"></canvas>
      <div class="wheel-ptr"></div>
      <div class="wheel-hub"></div>
    </div>

    <div class="prizes" id="prizesList">
      <?php foreach ($options as $o): ?>
        <div class="prize-chip">
          <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($o['color']) ?>;flex-shrink:0"></span>
          <?= htmlspecialchars($o['name']) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- FORM COLUMN -->
  <div class="form-col">
    <div class="form-card">
      <div class="form-card-top"></div>
      <div class="form-card-body">

        <div class="already-used" id="alreadyUsed">
          <div style="font-size:28px;margin-bottom:8px">⚠️</div>
          <h3 style="color:#fca5a5;margin-bottom:6px;font-size:15px">Already Participated</h3>
          <p style="font-size:13px;color:var(--t3)">This email has already been used. Each email can only spin once per campaign.</p>
        </div>

        <div id="formWrap">
          <h2 class="form-title"><?= htmlspecialchars($formName) ?></h2>
          <p class="form-desc"><?= htmlspecialchars($formDesc) ?></p>
          <div class="alert alert-err" id="formAlert"></div>

          <form id="spinForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="project_token" value="<?= htmlspecialchars($token) ?>">

            <?php foreach ($questions as $q): ?>
              <?php
              $qid      = (int)$q['id'];
              $required = (bool)$q['is_required'];
              $optList  = $q['options_list'] ? explode('|||', $q['options_list']) : [];
              ?>
              <div class="fg" data-qid="<?= $qid ?>" data-type="<?= $q['field_type'] ?>">
                <label for="q_<?= $qid ?>">
                  <?= htmlspecialchars($q['question_text']) ?>
                  <?php if ($required): ?><span class="req"> *</span><?php endif; ?>
                </label>

                <?php if ($q['field_type'] === 'short'): ?>
                  <input id="q_<?= $qid ?>" name="q_<?= $qid ?>" type="text" class="fi" placeholder="Your answer" <?= $required ? 'required' : '' ?>>

                <?php elseif ($q['field_type'] === 'email'): ?>
                  <input id="q_<?= $qid ?>" name="q_<?= $qid ?>" type="email" class="fi" placeholder="your@company.com" <?= $required ? 'required' : '' ?>>

                <?php elseif ($q['field_type'] === 'paragraph'): ?>
                  <textarea id="q_<?= $qid ?>" name="q_<?= $qid ?>" class="fi" placeholder="Your answer…" <?= $required ? 'required' : '' ?>></textarea>

                <?php elseif ($q['field_type'] === 'date'): ?>
                  <input id="q_<?= $qid ?>" name="q_<?= $qid ?>" type="date" class="fi" <?= $required ? 'required' : '' ?>>

                <?php elseif ($q['field_type'] === 'dropdown'): ?>
                  <select id="q_<?= $qid ?>" name="q_<?= $qid ?>" class="fi" <?= $required ? 'required' : '' ?>>
                    <option value="">Select an option…</option>
                    <?php foreach ($optList as $opt): ?>
                      <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                  </select>

                <?php elseif ($q['field_type'] === 'radio'): ?>
                  <?php foreach ($optList as $opt): ?>
                    <label class="radio-opt">
                      <input type="radio" name="q_<?= $qid ?>" value="<?= htmlspecialchars($opt) ?>" <?= $required ? 'required' : '' ?>>
                      <span><?= htmlspecialchars($opt) ?></span>
                    </label>
                  <?php endforeach; ?>

                <?php elseif ($q['field_type'] === 'checkbox'): ?>
                  <?php foreach ($optList as $opt): ?>
                    <label class="check-opt">
                      <input type="checkbox" name="q_<?= $qid ?>[]" value="<?= htmlspecialchars($opt) ?>">
                      <span><?= htmlspecialchars($opt) ?></span>
                    </label>
                  <?php endforeach; ?>

                <?php elseif ($q['field_type'] === 'file'): ?>
                  <div class="file-area" id="fa_<?= $qid ?>">
                    <input type="file" id="q_<?= $qid ?>" name="q_<?= $qid ?>"
                           accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.gif"
                           onchange="showFileName(<?= $qid ?>,this)">
                    <div style="font-size:24px;margin-bottom:6px">📎</div>
                    <div style="font-size:13px;color:var(--t3)"><strong style="color:var(--a2)">Click to browse</strong> or drag & drop</div>
                    <div style="font-size:11px;color:var(--t3);margin-top:3px">PDF, DOC, DOCX, PNG, JPG — Max 10MB</div>
                    <div class="file-chosen" id="fc_<?= $qid ?>"></div>
                  </div>

                <?php elseif ($q['field_type'] === 'rating'): ?>
                  <div class="stars" id="stars_<?= $qid ?>" data-val="0">
                    <?php for ($i=1;$i<=5;$i++): ?>
                      <span class="star" data-n="<?= $i ?>" onclick="setRating(<?= $qid ?>,<?= $i ?>)">★</span>
                    <?php endfor; ?>
                  </div>
                  <input type="hidden" id="q_<?= $qid ?>" name="q_<?= $qid ?>" value="">

                <?php elseif ($q['field_type'] === 'ranking'): ?>
                  <div id="rank_<?= $qid ?>">
                    <?php foreach ($optList as $idx => $opt): ?>
                      <div class="rank-item" draggable="true" data-val="<?= htmlspecialchars($opt) ?>">
                        <span class="rank-handle">⠿</span>
                        <span class="rank-num"><?= $idx+1 ?></span>
                        <span><?= htmlspecialchars($opt) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <input type="hidden" id="q_<?= $qid ?>" name="q_<?= $qid ?>" value="<?= htmlspecialchars(implode(' > ', $optList)) ?>">

                <?php endif; ?>

                <div class="ferr" id="ferr_<?= $qid ?>">This field is required</div>
              </div>
            <?php endforeach; ?>

            <button type="button" class="btn-spin" id="spinBtn" onclick="handleSpin()">
              <?= htmlspecialchars(getSetting('spin_button_text', 'SPIN NOW!')) ?>
            </button>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- RESULT MODAL -->
<div class="modal-bg" id="resultModal">
  <div class="modal">
    <div class="m-emoji" id="mEmoji">🎉</div>
    <div class="m-won">You Won!</div>
    <div class="m-prize" id="mPrize">Prize Name</div>
    <p class="m-msg" id="mMsg">Congratulations!</p>
    <button class="btn-close" onclick="document.getElementById('resultModal').classList.remove('show')">Close</button>
  </div>
</div>

<script>
// ── WHEEL OPTIONS ──────────────────────────────────────
const WHEEL_OPTIONS = <?= json_encode(array_map(fn($o) => [
    'id'    => $o['id'],
    'name'  => $o['name'],
    'color' => $o['color'],
    'text_color' => $o['text_color'],
    'probability'=> (float)$o['probability'],
], $options)) ?>;

// ── WHEEL DRAW ─────────────────────────────────────────
const canvas = document.getElementById('wheelCanvas');
const ctx    = canvas.getContext('2d');
let angle = 0, spinning = false;

function drawWheel(rot = 0) {
    const W = canvas.width, CX = W/2, R = CX - 6;
    ctx.clearRect(0, 0, W, W);
    const n = WHEEL_OPTIONS.length;
    if (!n) return;
    const arc = (2 * Math.PI) / n;
    for (let i = 0; i < n; i++) {
        const o = WHEEL_OPTIONS[i], s = rot + i * arc, e = s + arc;
        ctx.beginPath(); ctx.moveTo(CX, CX); ctx.arc(CX, CX, R, s, e); ctx.closePath();
        ctx.fillStyle = o.color; ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.lineWidth = 1.5; ctx.stroke();
        ctx.save(); ctx.translate(CX, CX); ctx.rotate(s + arc / 2);
        ctx.textAlign = 'right'; ctx.fillStyle = o.text_color || '#fff';
        ctx.font = `bold ${Math.min(13, 280 / n)}px DM Sans`;
        ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 4;
        const t = o.name.length > 14 ? o.name.slice(0, 13) + '…' : o.name;
        ctx.fillText(t, R - 14, 5); ctx.restore();
    }
    ctx.beginPath(); ctx.arc(CX, CX, R, 0, 2 * Math.PI);
    ctx.strokeStyle = 'rgba(99,102,241,.4)'; ctx.lineWidth = 4; ctx.stroke();
}
drawWheel();

function easeOut(t) { return 1 - Math.pow(1 - t, 4); }

// ── FORM UTILS ─────────────────────────────────────────
function showFileName(qid, input) {
    const fc = document.getElementById('fc_' + qid);
    if (fc && input.files.length) { fc.textContent = '✓ ' + input.files[0].name; fc.style.display = 'block'; }
}
function setRating(qid, val) {
    document.querySelectorAll('#stars_' + qid + ' .star').forEach((s, i) => s.classList.toggle('on', i < val));
    document.getElementById('q_' + qid).value = val;
}
// Ranking drag-and-drop
document.querySelectorAll('[id^="rank_"]').forEach(el => {
    let drag = null;
    el.addEventListener('dragstart', e => { drag = e.target.closest('.rank-item'); drag.style.opacity = '.4'; });
    el.addEventListener('dragend',   e => { drag.style.opacity = '1'; updateRanks(el.id); });
    el.addEventListener('dragover',  e => {
        e.preventDefault();
        const over = e.target.closest('.rank-item');
        if (over && over !== drag) {
            const r = over.getBoundingClientRect();
            el.insertBefore(drag, e.clientY < r.top + r.height / 2 ? over : over.nextSibling);
        }
    });
});
function updateRanks(elId) {
    const el = document.getElementById(elId), qid = elId.replace('rank_', '');
    el.querySelectorAll('.rank-num').forEach((n, i) => n.textContent = i + 1);
    const vals = [...el.querySelectorAll('.rank-item')].map(x => x.dataset.val).join(' > ');
    const inp = document.getElementById('q_' + qid);
    if (inp) inp.value = vals;
}

// ── SPIN HANDLER ───────────────────────────────────────
function handleSpin() {
    if (spinning) return;
    const formAlert = document.getElementById('formAlert');
    formAlert.classList.remove('show');
    // Collect form data
    const fd = new FormData(document.getElementById('spinForm'));
    // AJAX submit
    const btn = document.getElementById('spinBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spin-ico"></span> Processing…';
    fetch('<?= APP_URL ?>/api/spin.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                btn.disabled = false;
                btn.textContent = '<?= addslashes(getSetting('spin_button_text', 'SPIN NOW!')) ?>';
                if (data.already_used) {
                    document.getElementById('alreadyUsed').classList.add('show');
                    document.getElementById('formWrap').style.display = 'none';
                    return;
                }
                formAlert.textContent = data.message || 'An error occurred. Please try again.';
                formAlert.classList.add('show');
                return;
            }
            // Animate wheel
            spinning = true;
            const winner = data.winner;
            const n = WHEEL_OPTIONS.length, arc = (2 * Math.PI) / n;
            const idx = WHEEL_OPTIONS.findIndex(o => o.id == winner.id);
            const targetMid = idx * arc + arc / 2;
            const extra = 8 * Math.PI + (2 * Math.PI - targetMid) - (angle % (2 * Math.PI));
            const spinAmt = extra + 2 * Math.PI;
            const startAngle = angle, t0 = performance.now();
            function frame(now) {
                const elapsed = now - t0, prog = Math.min(elapsed / 5000, 1);
                angle = startAngle + spinAmt * easeOut(prog);
                drawWheel(angle);
                if (prog < 1) { requestAnimationFrame(frame); return; }
                angle = startAngle + spinAmt;
                drawWheel(angle);
                spinning = false;
                btn.textContent = '✓ Spun!';
                showResult(winner);
            }
            requestAnimationFrame(frame);
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = '<?= addslashes(getSetting('spin_button_text', 'SPIN NOW!')) ?>';
            formAlert.textContent = 'Network error. Please check your connection and try again.';
            formAlert.classList.add('show');
        });
}

function showResult(winner) {
    const emojis = ['🎉','🏆','🎊','⭐','🎁','🥳'];
    document.getElementById('mEmoji').textContent = emojis[Math.floor(Math.random() * emojis.length)];
    document.getElementById('mPrize').textContent = winner.name;
    document.getElementById('mMsg').textContent   = winner.success_msg || 'Congratulations! Our team will contact you soon.';
    document.getElementById('resultModal').classList.add('show');
}
document.getElementById('resultModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) e.currentTarget.classList.remove('show');
});
</script>
</body>
</html>

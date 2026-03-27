<?php
/**
 * SpinWheel Pro V2 — Spin Page
 * Design: Direction B "Soft Event"
 * Forest green · Warm cream · Fraunces serif · Step progress
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

// ── TOKEN DETECTION ───────────────────────────────────
$token = '';
if (!$token && !empty($_GET['token']))       $token = trim($_GET['token']);
if (!$token && !empty($_GET['p']))           $token = trim($_GET['p']);
if (!$token && !empty($_SERVER['PATH_INFO'])) $token = trim($_SERVER['PATH_INFO'], '/');
if (!$token && !empty($_SERVER['REQUEST_URI'])) {
    $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if (preg_match('#^(?:.*/)?spin/(?!index\.php)([a-zA-Z0-9_-]+)#', $uri, $m)) $token = $m[1];
}
$token = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $token));

// ── LOOKUP ────────────────────────────────────────────
$project = $token ? getProjectByToken($token) : null;

if (!$project) {
    http_response_code(404);
    ?><!DOCTYPE html>
    <html lang="en"><head><meta charset="UTF-8"><title>Not Found</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Outfit:wght@400;500&display=swap" rel="stylesheet">
    <style>*{box-sizing:border-box;margin:0;padding:0}body{background:#fdf9f3;font-family:'Outfit',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px}h1{font-family:'Fraunces',serif;font-size:38px;color:#2d5016;margin-bottom:8px;letter-spacing:-.5px}p{color:#7a8070;line-height:1.6;margin-bottom:20px;font-size:14px}a{display:inline-block;padding:10px 24px;background:#2d5016;color:#fff;border-radius:8px;font-size:13px;font-weight:500;text-decoration:none}</style>
    </head><body><div><div style="font-size:52px;margin-bottom:14px">🎡</div><h1>Campaign Not Found</h1><p>This campaign has ended or the link is incorrect.<br>Please check the URL and try again.</p></div></body></html>
    <?php exit;
}

// ── DATA ──────────────────────────────────────────────
$projectId    = (int)$project['id'];
$options      = getActiveWheelOptions($projectId);
$questions    = getFormQuestions($projectId);
$db           = Database::getInstance();
$formConfig   = $db->fetchOne("SELECT * FROM form_config WHERE project_id = ?", [$projectId]);
$formName     = $formConfig['form_name']   ?? $project['name'];
$formDesc     = $formConfig['description'] ?? 'Fill in your details to claim your spin.';
$csrfToken    = generateCsrfToken();
$spinDuration = (int)($project['spin_duration_ms'] ?? 5000);
$projectColor = $project['color'] ?? '#2d5016';
$spinUrl      = APP_URL . '/spin/index.php?p=' . $project['token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($project['name']) ?> — Spin &amp; Win</title>
<meta name="description" content="Spin the wheel and win amazing prizes!">
<meta property="og:title"       content="<?= htmlspecialchars($project['name']) ?> — Spin &amp; Win">
<meta property="og:description" content="Spin the wheel and win amazing prizes!">
<meta property="og:url"         content="<?= htmlspecialchars($spinUrl) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,ital,wght@9..144,0,300;9..144,0,600;9..144,0,700;9..144,1,300&family=Outfit:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─────────────────────────────────────────────
   DESIGN B "Soft Event" — SpinWheel Pro
   Forest green · Cream · Fraunces + Outfit
───────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --forest:  #2d5016;
  --green:   #4a7c2f;
  --green-lt:#eef5e8;
  --green-bd:rgba(74,124,47,.2);
  --gold:    #c8923a;
  --gold-lt: #fdf3e3;
  --cream:   #fdf9f3;
  --paper:   #faf7f1;
  --white:   #ffffff;
  --ink:     #1a1f14;
  --ink2:    #2e3828;
  --muted:   #7a8070;
  --line:    rgba(26,31,20,.08);
  --line2:   rgba(26,31,20,.05);
  --rose:    #b85248;
  --rose-lt: #fceae8;
  --serif:   'Fraunces', Georgia, serif;
  --sans:    'Outfit', system-ui, sans-serif;
  --mono:    'DM Mono', monospace;
}

html,body{
  min-height:100vh;
  background:var(--paper);
  color:var(--ink);
  font-family:var(--sans);
  -webkit-font-smoothing:antialiased;
}

::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-thumb{background:rgba(26,31,20,.15);border-radius:2px}

/* ── TOPBAR ── */
.topbar{
  background:var(--white);
  border-bottom:1px solid var(--line);
  height:54px;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 28px;
  position:sticky;top:0;z-index:100;
}
.tb-brand{
  display:flex;align-items:center;gap:9px;
  font-family:var(--serif);font-size:15px;font-weight:600;
  color:var(--ink);letter-spacing:-.2px;
}
.tb-mark{
  width:28px;height:28px;border-radius:7px;
  background:var(--forest);
  display:flex;align-items:center;justify-content:center;
  font-size:14px;flex-shrink:0;
}
.tb-live{
  display:flex;align-items:center;gap:6px;
  font-size:11px;color:var(--muted);font-family:var(--mono);
}
.tb-dot{
  width:6px;height:6px;border-radius:50%;
  background:var(--green);
  animation:pulse 2s ease infinite;
}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* ── HERO BANNER ── */
.hero{
  background:var(--cream);
  padding:42px 28px 34px;
  text-align:center;
  border-bottom:1px solid var(--line2);
  position:relative;overflow:hidden;
}
.hero::before{
  content:'';position:absolute;top:-40px;left:50%;
  transform:translateX(-50%);
  width:600px;height:320px;
  background:radial-gradient(ellipse,rgba(74,124,47,.07) 0%,transparent 70%);
  pointer-events:none;
}
.hero-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:var(--green-lt);border:1px solid var(--green-bd);
  border-radius:20px;padding:5px 14px;
  font-size:11px;color:var(--green);
  font-family:var(--mono);letter-spacing:.5px;
  margin-bottom:14px;position:relative;
}
.hero-title{
  font-family:var(--serif);
  font-size:clamp(28px,5vw,48px);
  font-weight:700;color:var(--ink);
  letter-spacing:-1.2px;line-height:1.05;
  margin-bottom:10px;position:relative;
}
.hero-title em{color:var(--green);font-style:italic;font-weight:300}
.hero-sub{
  font-size:14px;color:var(--muted);
  max-width:440px;margin:0 auto;
  line-height:1.65;position:relative;
}

/* ── MAIN LAYOUT ── */
.page{
  max-width:1020px;margin:0 auto;
  display:grid;grid-template-columns:1fr 1fr;
  gap:0;
  background:var(--white);
  border-left:1px solid var(--line);
  border-right:1px solid var(--line);
  border-bottom:1px solid var(--line);
}
@media(max-width:760px){.page{grid-template-columns:1fr}}

/* ── WHEEL COLUMN ── */
.wheel-col{
  background:var(--cream);
  border-right:1px solid var(--line);
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  padding:40px 28px;
}
.wheel-wrap{
  position:relative;
  width:min(300px,85vw);
  aspect-ratio:1;
  margin-bottom:28px;
}
/* Decorative rings */
.w-ring1{
  position:absolute;inset:-8px;border-radius:50%;
  border:1px solid rgba(74,124,47,.14);
  pointer-events:none;
}
.w-ring2{
  position:absolute;inset:-20px;border-radius:50%;
  border:1px dashed rgba(74,124,47,.07);
  animation:ring-spin 24s linear infinite;
  pointer-events:none;
}
@keyframes ring-spin{to{transform:rotate(360deg)}}
.w-ring2::after{
  content:'';position:absolute;
  top:3px;left:50%;margin-left:-3px;
  width:6px;height:6px;border-radius:50%;
  background:rgba(74,124,47,.25);
}

#wheelCanvas{
  width:100%;height:100%;
  border-radius:50%;display:block;
  position:relative;z-index:2;
}
.w-pointer{
  position:absolute;top:50%;right:10px;
  transform:translateY(-50%);
  width:0;height:0;
  border-top:12px solid transparent;
  border-bottom:12px solid transparent;
  border-right:22px solid var(--forest);
  filter:drop-shadow(-2px 0 4px rgba(45,80,22,.4));
  z-index:10;
}
.w-hub{
  position:absolute;top:50%;left:50%;
  transform:translate(-50%,-50%);
  width:36px;height:36px;border-radius:50%;
  background:var(--white);
  border:2px solid rgba(74,124,47,.25);
  z-index:6;
  box-shadow:0 2px 10px rgba(45,80,22,.15);
  display:flex;align-items:center;justify-content:center;
}
.w-hub-inner{
  width:12px;height:12px;border-radius:50%;
  background:var(--forest);
}

/* Prize chips */
.prizes{
  display:flex;flex-wrap:wrap;gap:5px;
  justify-content:center;max-width:300px;
}
.prize-chip{
  display:flex;align-items:center;gap:5px;
  padding:4px 11px;
  background:var(--white);border:1px solid var(--line);
  border-radius:16px;font-size:11px;color:var(--muted);
  transition:border-color .15s;
}
.prize-chip:hover{border-color:rgba(74,124,47,.3);color:var(--ink)}
.chip-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}

/* ── FORM COLUMN ── */
.form-col{
  display:flex;flex-direction:column;
  justify-content:center;
  padding:36px 40px;
}

/* Step indicator */
.steps{
  display:flex;align-items:center;gap:0;
  margin-bottom:22px;
}
.step{
  display:flex;align-items:center;gap:6px;
  font-size:11px;color:var(--muted);font-family:var(--mono);
}
.step-num{
  width:22px;height:22px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:10px;font-weight:500;
  background:var(--green-lt);border:1px solid var(--green-bd);
  color:var(--green);flex-shrink:0;
  transition:all .2s;
}
.step-num.done{background:var(--green);color:#fff;border-color:var(--green)}
.step-num.active{background:var(--forest);color:#fff;border-color:var(--forest)}
.step-line{flex:1;height:1px;background:var(--line);margin:0 6px}

.form-title{
  font-family:var(--serif);font-size:20px;font-weight:600;
  color:var(--ink);letter-spacing:-.4px;margin-bottom:4px;
}
.form-desc{
  font-size:13px;color:var(--muted);
  margin-bottom:22px;line-height:1.6;
}

/* Already used */
.already-used{
  background:var(--rose-lt);border:1px solid rgba(184,82,72,.2);
  border-radius:10px;padding:16px;text-align:center;
  display:none;margin-bottom:16px;
}
.already-used.show{display:block}
.already-used h3{color:var(--rose);font-size:14px;margin-bottom:4px;font-family:var(--serif)}
.already-used p{font-size:12px;color:var(--muted)}

/* Form groups */
.fg{margin-bottom:14px}
.fg label{
  display:block;font-size:10px;font-weight:500;
  letter-spacing:.7px;text-transform:uppercase;
  color:var(--muted);margin-bottom:5px;font-family:var(--mono);
}
.req{color:var(--green)}
.fi{
  width:100%;padding:10px 13px;
  background:var(--cream);border:1px solid var(--line);
  border-radius:7px;color:var(--ink);
  font-family:var(--sans);font-size:13px;
  outline:none;transition:all .18s;
}
.fi:focus{
  border-color:var(--green);background:var(--white);
  box-shadow:0 0 0 3px rgba(74,124,47,.08);
}
.fi::placeholder{color:rgba(26,31,20,.28)}
select.fi{cursor:pointer}
select.fi option{background:var(--white);color:var(--ink)}
textarea.fi{resize:vertical;min-height:72px}

/* Choice options */
.radio-opt,.check-opt{
  display:flex;align-items:center;gap:9px;
  padding:8px 12px;border-radius:7px;
  border:1px solid var(--line);margin-bottom:5px;
  cursor:pointer;transition:all .15s;font-size:13px;
  background:var(--cream);
}
.radio-opt:hover,.check-opt:hover{
  border-color:var(--green);background:var(--green-lt);
}
.radio-opt input,.check-opt input{accent-color:var(--green)}

/* Stars */
.stars{display:flex;gap:8px;margin-top:4px}
.star{font-size:26px;cursor:pointer;color:rgba(26,31,20,.2);transition:color .1s}
.star.on{color:var(--gold)}
.star:hover{color:var(--gold)}

/* File upload */
.file-area{
  border:1.5px dashed rgba(26,31,20,.15);
  border-radius:8px;padding:20px;
  text-align:center;cursor:pointer;
  position:relative;transition:all .2s;background:var(--cream);
}
.file-area:hover{border-color:var(--green);background:var(--green-lt)}
.file-area input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.file-chosen{font-size:11px;color:var(--green);margin-top:5px;display:none}

/* Ranking */
.rank-item{
  display:flex;align-items:center;gap:8px;
  background:var(--cream);border:1px solid var(--line);
  border-radius:7px;padding:8px 12px;margin-bottom:5px;
  cursor:grab;font-size:13px;user-select:none;
  transition:border-color .15s;
}
.rank-item:hover{border-color:rgba(74,124,47,.3)}
.rank-handle{color:var(--muted);font-size:15px}
.rank-num{
  width:20px;height:20px;border-radius:50%;
  background:var(--green);color:#fff;
  font-size:10px;font-weight:500;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}

/* Validation */
.ferr{font-size:11px;color:var(--rose);margin-top:4px;display:none;font-family:var(--mono)}
.ferr.show{display:block}
.alert{padding:10px 13px;border-radius:7px;font-size:13px;margin-bottom:12px;display:none;line-height:1.5}
.alert.show{display:block}
.alert-err{background:var(--rose-lt);border:1px solid rgba(184,82,72,.2);color:var(--rose)}

/* CTA Button */
.btn-spin{
  width:100%;padding:14px;
  background:var(--forest);color:#fff;
  border:none;border-radius:9px;
  font-family:var(--serif);font-size:16px;font-weight:600;
  letter-spacing:.2px;cursor:pointer;
  transition:all .2s;margin-top:8px;
  display:flex;align-items:center;justify-content:center;gap:8px;
  position:relative;overflow:hidden;
}
.btn-spin::before{
  content:'';
  position:absolute;top:0;left:-100%;width:100%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.07),transparent);
  transition:left .5s;
}
.btn-spin:hover:not(:disabled)::before{left:100%}
.btn-spin:hover:not(:disabled){
  background:var(--green);
  transform:translateY(-2px);
  box-shadow:0 10px 28px rgba(45,80,22,.25);
}
.btn-spin:disabled{opacity:.5;cursor:not-allowed}
.spin-loader{
  display:inline-block;width:14px;height:14px;
  border:2px solid rgba(255,255,255,.3);border-top-color:#fff;
  border-radius:50%;animation:spin-ani .6s linear infinite;
}
@keyframes spin-ani{to{transform:rotate(360deg)}}

/* Footer bar */
.page-footer{
  max-width:1020px;margin:0 auto;
  padding:12px 28px;
  display:flex;align-items:center;justify-content:space-between;
  border-left:1px solid var(--line);
  border-right:1px solid var(--line);
  border-bottom:1px solid var(--line);
  background:var(--cream);
  border-radius:0 0 0 0;
}
.footer-left{font-size:11px;color:var(--muted);font-family:var(--mono);display:flex;align-items:center;gap:6px}
.footer-right{font-size:11px;color:var(--muted);font-family:var(--mono)}

/* ── WIN MODAL ── */
.modal-bg{
  position:fixed;inset:0;
  background:rgba(26,31,20,.25);
  z-index:500;
  display:flex;align-items:center;justify-content:center;
  padding:20px;
  opacity:0;pointer-events:none;
  transition:opacity .3s;
}
.modal-bg.show{opacity:1;pointer-events:all}
.modal{
  background:var(--white);
  border-radius:18px;
  padding:40px 32px;text-align:center;
  max-width:380px;width:100%;
  box-shadow:0 8px 40px rgba(26,31,20,.18),0 2px 8px rgba(26,31,20,.1);
  border:1px solid rgba(74,124,47,.2);
  transform:scale(.92) translateY(8px);
  transition:transform .35s cubic-bezier(.34,1.4,.64,1);
  position:relative;overflow:hidden;
  cursor:grab;user-select:none;
}
.modal:active{cursor:grabbing;}
.modal-bg.show .modal{transform:scale(1) translateY(0)}
.modal::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--green),var(--gold));
}
.m-icon{font-size:46px;margin-bottom:12px;display:block;animation:modal-bounce .8s ease infinite alternate}
@keyframes modal-bounce{to{transform:scale(1.1) rotate(4deg)}}
.m-badge{
  display:inline-block;
  background:var(--green-lt);border:1px solid var(--green-bd);
  color:var(--green);font-size:9px;font-family:var(--mono);
  letter-spacing:1.5px;text-transform:uppercase;
  padding:4px 12px;border-radius:12px;margin-bottom:10px;
}
.m-prize{
  font-family:var(--serif);font-size:clamp(22px,5vw,32px);
  font-weight:700;color:var(--ink);
  letter-spacing:-.5px;margin-bottom:8px;line-height:1.1;
}
.m-msg{
  font-size:13px;color:var(--muted);
  line-height:1.7;margin-bottom:22px;
}
.btn-close{
  width:100%;padding:12px;
  background:var(--forest);color:#fff;border:none;
  border-radius:9px;font-family:var(--serif);
  font-size:15px;font-weight:600;cursor:pointer;
  transition:all .18s;
}
.btn-close:hover{background:var(--green)}

/* ── RESPONSIVE ── */
@media(max-width:760px){
  .wheel-col{padding:32px 20px;border-right:none;border-bottom:1px solid var(--line)}
  .form-col{padding:28px 20px}
  .hero{padding:32px 20px 26px}
  .page-footer{flex-direction:column;gap:6px;text-align:center}
  .steps{display:none}
}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="tb-brand">
    <div class="tb-mark">🎡</div>
    <?= htmlspecialchars($project['name']) ?>
  </div>
  <div class="tb-live">
    <div class="tb-dot"></div>
    Campaign live
  </div>
</div>

<!-- HERO -->
<div style="max-width:1020px;margin:0 auto">
  <div class="hero">
    <div class="hero-badge">🎁 Free to enter · No purchase required</div>
    <h1 class="hero-title">Spin &amp; <em>Win</em></h1>
    <p class="hero-sub">Complete the form, spin the wheel, and instantly discover your prize.</p>
  </div>
</div>

<!-- MAIN PAGE -->
<div class="page">

  <!-- WHEEL COLUMN -->
  <div class="wheel-col">
    <div class="wheel-wrap">
      <div class="w-ring1"></div>
      <div class="w-ring2"></div>
      <canvas id="wheelCanvas" width="300" height="300"></canvas>
      <div class="w-pointer"></div>
      <div class="w-hub"><div class="w-hub-inner"></div></div>
    </div>

    <div class="prizes">
      <?php foreach ($options as $o): ?>
        <div class="prize-chip">
          <span class="chip-dot" style="background:<?= htmlspecialchars($o['color']) ?>"></span>
          <?= htmlspecialchars($o['name']) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- FORM COLUMN -->
  <div class="form-col">

    <!-- Already used notice -->
    <div class="already-used" id="alreadyUsed">
      <h3>Already Participated</h3>
      <p>This email has already been used for this campaign.<br>One spin per email address.</p>
    </div>

    <div id="formWrap">
      <!-- Steps -->
      <div class="steps">
        <div class="step"><div class="step-num active">1</div> Details</div>
        <div class="step-line"></div>
        <div class="step"><div class="step-num">2</div> Spin</div>
        <div class="step-line"></div>
        <div class="step"><div class="step-num">3</div> Win</div>
      </div>

      <h2 class="form-title"><?= htmlspecialchars($formName) ?></h2>
      <p class="form-desc"><?= htmlspecialchars($formDesc) ?></p>

      <div class="alert alert-err" id="formAlert"></div>

      <form id="spinForm" novalidate>
        <input type="hidden" name="csrf_token"    value="<?= $csrfToken ?>">
        <input type="hidden" name="project_token" value="<?= htmlspecialchars($token) ?>">

        <?php foreach ($questions as $q):
          $qid     = (int)$q['id'];
          $req     = (bool)$q['is_required'];
          $optList = !empty($q['options_list']) ? explode('|||', $q['options_list']) : [];
        ?>
        <div class="fg" data-qid="<?= $qid ?>">
          <label>
            <?= htmlspecialchars($q['question_text']) ?>
            <?php if ($req): ?><span class="req"> *</span><?php endif; ?>
          </label>

          <?php if ($q['field_type'] === 'short'): ?>
            <input id="q_<?= $qid ?>" name="q_<?= $qid ?>" type="text" class="fi" placeholder="Your answer" <?= $req?'required':'' ?>>

          <?php elseif ($q['field_type'] === 'email'): ?>
            <input id="q_<?= $qid ?>" name="q_<?= $qid ?>" type="email" class="fi" placeholder="your@company.com" <?= $req?'required':'' ?>>

          <?php elseif ($q['field_type'] === 'paragraph'): ?>
            <textarea id="q_<?= $qid ?>" name="q_<?= $qid ?>" class="fi" placeholder="Your answer…" <?= $req?'required':'' ?>></textarea>

          <?php elseif ($q['field_type'] === 'date'): ?>
            <input id="q_<?= $qid ?>" name="q_<?= $qid ?>" type="date" class="fi" <?= $req?'required':'' ?>>

          <?php elseif ($q['field_type'] === 'dropdown'): ?>
            <select id="q_<?= $qid ?>" name="q_<?= $qid ?>" class="fi" <?= $req?'required':'' ?>>
              <option value="">Select an option…</option>
              <?php foreach ($optList as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
              <?php endforeach; ?>
            </select>

          <?php elseif ($q['field_type'] === 'radio'): ?>
            <?php foreach ($optList as $opt): ?>
              <label class="radio-opt">
                <input type="radio" name="q_<?= $qid ?>" value="<?= htmlspecialchars($opt) ?>" <?= $req?'required':'' ?>>
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
            <div class="file-area">
              <input type="file" id="q_<?= $qid ?>" name="q_<?= $qid ?>" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.gif" onchange="showFileName(<?= $qid ?>,this)">
              <div style="font-size:22px;margin-bottom:6px">📎</div>
              <div style="font-size:13px;color:var(--muted)"><strong style="color:var(--green)">Click to browse</strong> or drag &amp; drop</div>
              <div style="font-size:11px;color:var(--muted);margin-top:3px">PDF, DOC, PNG, JPG — Max 10MB</div>
              <div class="file-chosen" id="fc_<?= $qid ?>"></div>
            </div>

          <?php elseif ($q['field_type'] === 'rating'): ?>
            <div class="stars" id="stars_<?= $qid ?>">
              <?php for ($i=1;$i<=5;$i++): ?>
                <span class="star" onclick="setRating(<?= $qid ?>,<?= $i ?>)">★</span>
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

        <?php if (empty($questions)): ?>
          <div style="background:var(--gold-lt);border:1px solid rgba(200,146,58,.2);border-radius:8px;padding:12px 14px;font-size:13px;color:#7a4e18;margin-bottom:14px">
            ⚠ No form questions configured yet. Add questions in Admin → Projects → Form Builder.
          </div>
        <?php endif; ?>

        <button type="button" id="spinBtn" class="btn-spin" onclick="handleSpin()">
          Spin the Wheel →
        </button>
      </form>
    </div>
  </div>

</div><!-- /page -->

<!-- FOOTER -->
<div style="max-width:1020px;margin:0 auto">
  <div class="page-footer">
    <div class="footer-left">
      <span>🔒</span>
      Corporate emails only · Data handled securely
    </div>
    <div class="footer-right">Powered by SpinWheel Pro</div>
  </div>
</div>

<!-- WIN MODAL -->
<div class="modal-bg" id="resultModal">
  <div class="modal">
    <div style="position:absolute;top:10px;right:12px;font-size:10px;color:rgba(26,31,20,.25);font-family:var(--mono);letter-spacing:.3px;pointer-events:none">drag to move</div>
    <span class="m-icon" id="mIcon">🎉</span>
    <div class="m-badge">You Won!</div>
    <div class="m-prize" id="mPrize">Prize Name</div>
    <p class="m-msg" id="mMsg">Congratulations! Our team will be in touch.</p>
    <button class="btn-close" onclick="closeModal()">Done</button>
  </div>
</div>

<script>
// ── CONFIG ────────────────────────────────────────────
const OPTS = <?= json_encode(array_values(array_map(fn($o) => [
    'id'         => (int)$o['id'],
    'name'       => $o['name'],
    'color'      => $o['color'],
    'text_color' => $o['text_color'],
    'probability'=> (float)$o['probability'],
], $options)), JSON_HEX_TAG) ?>;
const API_URL      = '<?= APP_URL ?>/api/spin.php';
const SPIN_DURATION = <?= $spinDuration ?>;

// ── WHEEL ─────────────────────────────────────────────
const canvas = document.getElementById('wheelCanvas');
const ctx    = canvas.getContext('2d');
let angle = 0, spinning = false;

function drawWheel(rot = 0) {
    const W = canvas.width, C = W/2, R = C - 4;
    ctx.clearRect(0,0,W,W);
    const n = OPTS.length;
    if (!n) {
        ctx.fillStyle = '#eef5e8';
        ctx.beginPath(); ctx.arc(C,C,R,0,2*Math.PI); ctx.fill();
        ctx.fillStyle = '#7a8070'; ctx.font = '13px Outfit,sans-serif';
        ctx.textAlign = 'center'; ctx.fillText('No prizes yet',C,C+5);
        return;
    }
    const arc = (2*Math.PI)/n;
    for (let i=0; i<n; i++) {
        const o = OPTS[i];
        const s = -Math.PI/2 + rot + i*arc;
        const e = s + arc;
        // Segment fill
        ctx.beginPath(); ctx.moveTo(C,C); ctx.arc(C,C,R,s,e); ctx.closePath();
        ctx.fillStyle = o.color; ctx.fill();
        // Separator
        ctx.beginPath(); ctx.moveTo(C,C);
        ctx.lineTo(C + R*Math.cos(s), C + R*Math.sin(s));
        ctx.strokeStyle = 'rgba(255,255,255,.3)'; ctx.lineWidth = 1.5; ctx.stroke();
        // Label
        ctx.save(); ctx.translate(C,C); ctx.rotate(s + arc/2);
        ctx.textAlign = 'right';
        ctx.fillStyle = o.text_color || '#fff';
        ctx.font = `600 ${Math.min(12, 220/n)}px Outfit, sans-serif`;
        ctx.shadowColor = 'rgba(0,0,0,.25)'; ctx.shadowBlur = 3;
        const label = o.name.length > 13 ? o.name.slice(0,12)+'…' : o.name;
        ctx.fillText(label, R-10, 5);
        ctx.restore();
    }
    // Rim
    ctx.beginPath(); ctx.arc(C,C,R,0,2*Math.PI);
    ctx.strokeStyle = 'rgba(255,255,255,.2)'; ctx.lineWidth = 3; ctx.stroke();
}
drawWheel();
function easeOut(t) { return 1 - Math.pow(1-t, 4); }

// ── FORM HELPERS ──────────────────────────────────────
function showFileName(qid, inp) {
    const fc = document.getElementById('fc_'+qid);
    if (fc && inp.files.length) { fc.textContent = '✓ ' + inp.files[0].name; fc.style.display = 'block'; }
}
function setRating(qid, val) {
    document.querySelectorAll('#stars_'+qid+' .star').forEach((s,i) => s.classList.toggle('on', i < val));
    document.getElementById('q_'+qid).value = val;
}
document.querySelectorAll('[id^="rank_"]').forEach(el => {
    let drag = null;
    el.addEventListener('dragstart', e => { drag = e.target.closest('.rank-item'); drag.style.opacity = '.4'; });
    el.addEventListener('dragend',   e => { drag.style.opacity = '1'; updateRanks(el.id); });
    el.addEventListener('dragover',  e => {
        e.preventDefault();
        const over = e.target.closest('.rank-item');
        if (over && over !== drag) {
            const r = over.getBoundingClientRect();
            el.insertBefore(drag, e.clientY < r.top + r.height/2 ? over : over.nextSibling);
        }
    });
});
function updateRanks(elId) {
    const el = document.getElementById(elId), qid = elId.replace('rank_','');
    el.querySelectorAll('.rank-num').forEach((n,i) => n.textContent = i+1);
    const v = [...el.querySelectorAll('.rank-item')].map(x => x.dataset.val).join(' > ');
    const inp = document.getElementById('q_'+qid);
    if (inp) inp.value = v;
}

// ── STEP UPDATER ──────────────────────────────────────
function setStep(n) {
    document.querySelectorAll('.step-num').forEach((el, i) => {
        el.classList.remove('active', 'done');
        if (i + 1 < n) el.classList.add('done');
        else if (i + 1 === n) el.classList.add('active');
    });
}

// ── SPIN ──────────────────────────────────────────────
function handleSpin() {
    if (spinning) return;
    const btn     = document.getElementById('spinBtn');
    const alertEl = document.getElementById('formAlert');
    alertEl.classList.remove('show');
    btn.disabled = true;
    btn.innerHTML = '<span class="spin-loader"></span> Checking details…';

    fetch(API_URL, { method:'POST', body: new FormData(document.getElementById('spinForm')) })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            btn.disabled = false; btn.innerHTML = 'Spin the Wheel →';
            if (data.already_used) {
                document.getElementById('alreadyUsed').classList.add('show');
                document.getElementById('formWrap').style.display = 'none';
                return;
            }
            alertEl.textContent = data.message || 'Something went wrong. Please try again.';
            alertEl.classList.add('show');
            return;
        }
        // ── SPIN ANIMATION ────────────────────────────────
        // drawWheel(rot): segment i midpoint = -PI/2 + rot + i*arc + arc/2
        // Pointer is at the RIGHT side (angle = 0).
        // For pointer to land on winner (idx), we need:
        //   -PI/2 + finalRot + idx*arc + arc/2 = 0
        //   finalRot = PI/2 - idx*arc - arc/2
        spinning = true;
        setStep(2);
        btn.innerHTML = '<span class="spin-loader"></span> Spinning…';
        const winner    = data.winner;
        const idx       = OPTS.findIndex(o => o.id == winner.id);
        const n         = OPTS.length;
        const arc       = (2*Math.PI)/n;

        // Target: pointer (angle=0) aligns with CENTER of winner segment.
        // Formula derived from drawWheel: segment i midpoint = -PI/2 + rot + i*arc + arc/2
        // For midpoint to be at angle=0: rot = PI/2 - i*arc - arc/2
        // Subtract one full arc to correct for observed 1-segment clockwise offset.
        // Add small fixed nudge so wheel always stops visibly inside segment, never on edge.
        const nudge     = arc * 0.15; // 15% from center toward leading edge
        let targetRot   = Math.PI/2 - idx*arc - arc/2 - arc - nudge;
        // Ensure minimum 8 full forward rotations from current angle
        while (targetRot - angle < 8 * 2*Math.PI) targetRot += 2*Math.PI;
        const spinAmt   = targetRot - angle;
        const startAngle = angle, t0 = performance.now();

        function frame(now) {
            const elapsed = now - t0, prog = Math.min(elapsed / SPIN_DURATION, 1);
            angle = startAngle + spinAmt * easeOut(prog);
            drawWheel(angle);
            if (prog < 1) { requestAnimationFrame(frame); return; }
            spinning = false;
            setStep(3);
            btn.innerHTML = '✓ Done!';
            showResult(winner);
        }
        requestAnimationFrame(frame);
    })
    .catch(() => {
        btn.disabled = false; btn.innerHTML = 'Spin the Wheel →';
        alertEl.textContent = 'Network error. Please check your connection.';
        alertEl.classList.add('show');
    });
}

// ── RESULT MODAL ──────────────────────────────────────
function showResult(w) {
    const icons = ['🎉','🏆','🎊','⭐','🎁','🥳'];
    document.getElementById('mIcon').textContent  = icons[Math.floor(Math.random()*icons.length)];
    document.getElementById('mPrize').textContent = w.name;
    document.getElementById('mMsg').textContent   = w.success_msg || 'Congratulations! Our team will be in touch soon.';
    document.getElementById('resultModal').classList.add('show');
    // Reset modal position to center on each open
    const m = document.getElementById('resultModal').querySelector('.modal');
    m.style.transform = '';
    m.style.left = '';
    m.style.top  = '';
    m.style.position = '';
}
function closeModal() {
    document.getElementById('resultModal').classList.remove('show');
}
document.getElementById('resultModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeModal();
});

// ── DRAGGABLE MODAL ───────────────────────────────────
(function() {
    const bg  = document.getElementById('resultModal');
    const modal = bg.querySelector('.modal');
    let isDragging = false, startX, startY, origLeft, origTop;

    modal.addEventListener('mousedown', e => {
        // Don't drag when clicking the button
        if (e.target.closest('button')) return;
        isDragging = true;
        const rect = modal.getBoundingClientRect();
        startX = e.clientX; startY = e.clientY;
        origLeft = rect.left; origTop = rect.top;
        modal.style.position = 'fixed';
        modal.style.margin   = '0';
        modal.style.left     = origLeft + 'px';
        modal.style.top      = origTop  + 'px';
        modal.style.transform = 'none';
        // Remove from flex layout
        bg.style.alignItems    = 'flex-start';
        bg.style.justifyContent = 'flex-start';
        e.preventDefault();
    });

    document.addEventListener('mousemove', e => {
        if (!isDragging) return;
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        modal.style.left = (origLeft + dx) + 'px';
        modal.style.top  = (origTop  + dy) + 'px';
    });

    document.addEventListener('mouseup', () => { isDragging = false; });

    // Touch support
    modal.addEventListener('touchstart', e => {
        if (e.target.closest('button')) return;
        const t = e.touches[0];
        const rect = modal.getBoundingClientRect();
        isDragging = true;
        startX = t.clientX; startY = t.clientY;
        origLeft = rect.left; origTop = rect.top;
        modal.style.position = 'fixed';
        modal.style.margin   = '0';
        modal.style.left     = origLeft + 'px';
        modal.style.top      = origTop  + 'px';
        modal.style.transform = 'none';
        bg.style.alignItems    = 'flex-start';
        bg.style.justifyContent = 'flex-start';
    }, {passive:true});

    document.addEventListener('touchmove', e => {
        if (!isDragging) return;
        const t = e.touches[0];
        modal.style.left = (origLeft + t.clientX - startX) + 'px';
        modal.style.top  = (origTop  + t.clientY - startY) + 'px';
    }, {passive:true});

    document.addEventListener('touchend', () => { isDragging = false; });
})();
</script>
</body>
</html>

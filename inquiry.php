<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/inquiry-form.php';

tylilo_session_start();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $preview = tylilo_is_preview();

    // Preview deploy check: log in to the mail server and quit without
    // sending or storing anything. Needs the one-time preview token.
    if ($preview && ($_POST['preview_smtp_check'] ?? '') === '1') {
        $ok = tylilo_inquiry_selftest_ok(is_string($_POST['preview_selftest'] ?? null) ? $_POST['preview_selftest'] : '');
        header('Cache-Control: no-store');
        if (!$ok) {
            http_response_code(403);
            exit;
        }
        header('X-Inquiry-SMTP: ' . (tylilo_smtp_check() ? 'login-ok' : 'failed'));
        http_response_code(204);
        exit;
    }
    $done = static function (array $flash, int $storedId = 0): void {
        $_SESSION['inquiry_flash'] = $flash;
        if ($storedId > 0) {
            header('X-Inquiry-Stored: ' . $storedId);
        }
        header('Location: /inquiry' . (empty($flash['success']) ? '#inquiry-form' : '#inquiry-thanks'), true, 303);
        exit;
    };

    $expected = is_string($_SESSION['inquiry_csrf'] ?? null) ? $_SESSION['inquiry_csrf'] : '';
    $result = tylilo_inquiry_validate($_POST, $expected);
    $clean = $result['clean'];

    if ($result['spam']) {
        $done(['success' => true]);
    }

    $selftest = tylilo_inquiry_selftest_ok(is_string($_POST['preview_selftest'] ?? null) ? $_POST['preview_selftest'] : '');
    if ($result['errors'] === [] && !$selftest) {
        $token = is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '';
        $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';
        $check = tylilo_turnstile_verify($token, $ip, 'inquiry');
        if (!$check['ok']) {
            $result['errors']['form'] = $check['error'];
        }
    }

    if ($result['errors'] !== []) {
        $done(['success' => false, 'errors' => $result['errors'], 'old' => $clean]);
    }

    $hits = [];
    foreach ($_SESSION['inquiry_hits'] ?? [] as $hit) {
        if (is_int($hit) && (time() - $hit) < 1800) {
            $hits[] = $hit;
        }
    }
    if (count($hits) >= 5) {
        $done(['success' => false, 'errors' => ['form' => 'Please wait a little while before sending another inquiry.'], 'old' => $clean]);
    }

    $fit = tylilo_inquiry_fit($clean);
    $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';
    try {
        $saved = tylilo_inquiry_save($clean, $fit, $ip, $preview);
    } catch (Throwable $e) {
        error_log('Inquiry: storage failed.');
        $saved = ['ok' => false, 'id' => 0, 'limited' => false];
    }
    if ($saved['limited']) {
        $done(['success' => false, 'errors' => ['form' => 'Please wait a little while before sending another inquiry.'], 'old' => $clean]);
    }
    if (!$saved['ok']) {
        $done(['success' => false, 'errors' => ['form' => 'Something went wrong on our side, and your answers were not saved. Please try again in a few minutes.'], 'old' => $clean]);
    }

    $mail = tylilo_inquiry_send($clean, $fit, $saved['id'], $preview);
    try {
        tylilo_inquiry_mark_mail($saved['id'], $mail['visitor'], $mail['owner']);
    } catch (Throwable $e) {
        error_log('Inquiry: could not record mail status.');
    }
    if ($selftest) {
        header('X-Inquiry-Mail: visitor=' . ($mail['visitor'] ? 'sent' : 'failed') . '; owner=' . ($mail['owner'] ? 'sent' : 'failed'));
    }

    $hits[] = time();
    $_SESSION['inquiry_hits'] = $hits;
    unset($_SESSION['inquiry_csrf']);
    $done(['success' => true], $selftest ? $saved['id'] : 0);
}

$flash = is_array($_SESSION['inquiry_flash'] ?? null) ? $_SESSION['inquiry_flash'] : [];
unset($_SESSION['inquiry_flash']);
$success = !empty($flash['success']);
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$old = is_array($flash['old'] ?? null) ? $flash['old'] : [];

if (empty($_SESSION['inquiry_csrf']) || !is_string($_SESSION['inquiry_csrf'])) {
    $_SESSION['inquiry_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['inquiry_csrf'];

$pageTitle = 'Start an inquiry — Tylilo, LLC';
$pageDescription = 'Tell Tylilo, LLC about your business or organization in a few short questions. Ryan replies personally by email within one business day.';
$pageCanonical = TYLILO_URL . '/inquiry';
$pageTurnstile = !$success;
$pageInquiry = !$success;

require __DIR__ . '/includes/head.php';
?>
<body>
  <script nonce="<?= tylilo_e($cspNonce) ?>">document.documentElement.classList.add("js");</script>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="hero">
<?php require __DIR__ . '/includes/topbar.php'; ?>
  </header>

  <main id="main" tabindex="-1">
    <section class="band inq-band" aria-labelledby="inq-heading">
      <div class="signal-inner inq-layout">
        <div class="inq-intro">
          <p class="eyebrow">Consulting</p>
          <h1 id="inq-heading">Start an inquiry</h1>
<?php if ($success): ?>
          <div class="form-status success inq-thanks" id="inquiry-thanks" tabindex="-1" role="status" aria-live="polite">
            <p><?= tylilo_e(TYLILO_INQUIRY_THANKS) ?></p>
          </div>
          <p class="lede-quiet">A copy of your answers is on its way to your inbox. If it does not arrive in a few minutes, check your spam folder.</p>
          <p><a class="btn" href="/">Back to the home page</a></p>
<?php else: ?>
          <p class="lede-quiet">A few short questions, one at a time. It takes about five minutes, and nothing sensitive is asked. Ryan reads every inquiry and replies personally by email.</p>
          <p class="inq-note"><?= tylilo_e(TYLILO_INQUIRY_REMOTE_NOTE) ?></p>
<?php endif; ?>
        </div>
<?php if (!$success): ?>
        <div class="inq-panel" id="inquiry-form">
<?php if ($errors !== []): ?>
          <div class="form-status error" id="form-status" tabindex="-1" role="alert">
<?php if (!empty($errors['form'])): ?>
            <p><?= tylilo_e((string) $errors['form']) ?></p>
<?php endif; ?>
<?php $fieldErrors = array_diff_key($errors, ['form' => true]); ?>
<?php if ($fieldErrors !== []): ?>
            <p>Please check these answers:</p>
            <ul>
<?php foreach ($fieldErrors as $key => $message): ?>
<?php $label = $key === 'consent' ? 'Confirmation' : (string) (tylilo_inquiry_questions()[$key]['label'] ?? $key); ?>
              <li><a href="#<?= tylilo_e($key === 'consent' ? 'inq-consent' : 'inq-' . str_replace('_', '-', (string) $key)) ?>"><?= tylilo_e($label) ?></a>: <?= tylilo_e((string) $message) ?></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
          </div>
<?php endif; ?>
          <?= tylilo_inquiry_form_html($old, $errors, $csrf, tylilo_turnstile_sitekey()) ?>
        </div>
<?php endif; ?>
      </div>
    </section>
  </main>
<?php require __DIR__ . '/includes/footer.php'; ?>

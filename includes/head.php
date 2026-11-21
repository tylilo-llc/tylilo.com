<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$pageTitle = $pageTitle ?? TYLILO_SITE_NAME;
$pageDescription = $pageDescription ?? 'Tylilo, LLC. Placeholder homepage for a company and studio site.';
$pageRobots = $pageRobots ?? 'index, follow';
$pageCanonical = $pageCanonical ?? '';
$nonce = tylilo_e($cspNonce);
?>
<!DOCTYPE html>
<html lang="en" data-theme="auto">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= tylilo_e($pageTitle) ?></title>
  <meta name="description" content="<?= tylilo_e($pageDescription) ?>">
  <meta name="robots" content="<?= tylilo_e($pageRobots) ?>">
  <meta name="theme-color" content="#0A3161">
  <meta name="color-scheme" content="light dark">
  <meta name="application-name" content="<?= tylilo_e(TYLILO_SITE_NAME) ?>">
<?php if ($pageCanonical !== ''): ?>
  <link rel="canonical" href="<?= tylilo_e($pageCanonical) ?>">
  <meta property="og:url" content="<?= tylilo_e($pageCanonical) ?>">
<?php endif; ?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= tylilo_e(TYLILO_SITE_NAME) ?>">
  <meta property="og:title" content="<?= tylilo_e($pageTitle) ?>">
  <meta property="og:description" content="<?= tylilo_e($pageDescription) ?>">
  <meta property="og:image" content="<?= tylilo_e(TYLILO_URL) ?>/assets/img/og.png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="Tylilo logo on a navy background">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= tylilo_e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= tylilo_e($pageDescription) ?>">
  <meta name="twitter:image" content="<?= tylilo_e(TYLILO_URL) ?>/assets/img/og.png">
  <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16.png">
  <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
  <link rel="preload" as="image" href="/assets/img/logo-round.webp" type="image/webp">
  <script nonce="<?= $nonce ?>">
    (function () {
      var theme = "auto";
      try {
        theme = localStorage.getItem("tylilo-theme") || "auto";
      } catch (e) {}
      if (theme !== "light" && theme !== "dark" && theme !== "auto") {
        theme = "auto";
      }
      document.documentElement.setAttribute("data-theme", theme);
      var dark = false;
      try {
        dark = theme === "dark" || (theme !== "light" && window.matchMedia("(prefers-color-scheme: dark)").matches);
      } catch (e) {}
      var meta = document.querySelector('meta[name="theme-color"]');
      if (meta) {
        meta.setAttribute("content", dark ? "#071526" : "#0A3161");
      }
    })();
  </script>
  <link rel="stylesheet" href="/assets/css/styles.css">
<?php if (!empty($pageStylesheets) && is_array($pageStylesheets)): ?>
<?php foreach ($pageStylesheets as $pageStylesheet): ?>
<?php if (is_string($pageStylesheet) && $pageStylesheet !== ''): ?>
  <link rel="stylesheet" href="<?= tylilo_e($pageStylesheet) ?>">
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
</head>

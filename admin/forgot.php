<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$html = '<div class="card"><h2>Forgot password?</h2>';
$html .= '<p>Ask the site owner for a password reset link.</p>';
$html .= '<p><a href="/admin/">Back to sign in</a></p></div>';
tylilo_admin_layout('Forgot password', $html);

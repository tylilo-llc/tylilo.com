<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/admin.php';

tylilo_admin_boot();

function tylilo_admin_here(string $href, string $path): bool
{
    if ($href === '/admin/') {
        return $path === '/admin' || $path === '/admin/' || $path === '/admin/index.php';
    }

    return str_starts_with($path, $href);
}

/**
 * Grouped admin navigation. A group with no visible links is omitted,
 * including its heading and the divider that would have sat above it.
 *
 * @param array<string, mixed> $user
 */
function tylilo_admin_menu_html(array $user, string $path): string
{
    $groups = [
        ['Home', [['/admin/', 'Home']], false],
    ];

    $workspace = [];
    if (tylilo_admin_can($user, 'notes')) {
        $workspace[] = ['/admin/notes.php', 'Notes'];
        $workspace[] = ['/admin/todos.php', 'To-dos'];
    }
    if (tylilo_admin_can($user, 'ideas')) {
        $workspace[] = ['/admin/ideas.php', 'Ideas'];
    }
    if (tylilo_admin_can($user, 'feedback')) {
        $workspace[] = ['/admin/feedback.php', 'Feedback'];
    }
    if ($workspace !== []) {
        $groups[] = ['Workspace', $workspace, false];
    }

    $consulting = [];
    if (tylilo_admin_can($user, 'inquiries.view')) {
        $consulting[] = ['/admin/inquiries.php', 'Inquiries'];
    }
    if (tylilo_admin_can($user, 'contacts.view')) {
        $consulting[] = ['/admin/contacts.php', 'Contacts'];
    }
    if ($consulting !== []) {
        $groups[] = ['Consulting', $consulting, false];
    }

    $games = [];
    if (tylilo_admin_is_owner($user)) {
        $games[] = ['/admin/games.php', 'Games'];
    }
    if (tylilo_admin_can($user, 'beta')) {
        $games[] = ['/admin/beta/', 'Beta'];
    }
    if (tylilo_admin_can($user, 'leaderboards')) {
        $games[] = ['/admin/scores.php', 'Scores'];
    }
    if ($games !== []) {
        $groups[] = ['Games', $games, false];
    }

    if (tylilo_admin_is_owner($user)) {
        $groups[] = ['Team', [['/admin/users.php', 'Users'], ['/admin/activity.php', 'Activity']], false];
    }

    $groups[] = ['Account', [['/admin/settings.php', 'Settings']], true];

    $html = '';
    foreach ($groups as $index => $group) {
        if ($index > 0) {
            $html .= '<hr class="admin-menu-divider">';
        }
        $html .= '<div class="admin-menu-group">';
        $html .= '<h2 class="admin-menu-label">' . tylilo_e((string) $group[0]) . '</h2>';
        foreach ($group[1] as $link) {
            $href = (string) $link[0];
            $label = (string) $link[1];
            $here = tylilo_admin_here($href, $path);
            $html .= '<a href="' . tylilo_e($href) . '"' . ($here ? ' aria-current="page"' : '') . '>' . tylilo_e($label) . '</a>';
        }
        if ($group[2] === true) {
            $html .= '<form method="post" action="/admin/logout.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><button type="submit">Log out</button></form>';
        }
        $html .= '</div>';
    }

    return $html;
}

function tylilo_admin_layout(string $title, string $main): void
{
    global $cspNonce;
    $pageTitle = $title . ' — Admin';
    $pageDescription = 'Private admin area.';
    $pageCanonical = TYLILO_URL . '/admin/';
    $pageRobots = 'noindex, nofollow';
    require dirname(__DIR__) . '/includes/head.php';
    $user = tylilo_admin_current();
    echo '<body>';
    echo '<script nonce="' . tylilo_e($cspNonce) . '">document.documentElement.classList.add("js");</script>';
    echo '<a class="skip-link" href="#main">Skip to content</a>';
    echo '<header class="hero">';
    require dirname(__DIR__) . '/includes/topbar.php';
    echo '<div class="admin-subhead">';
    if ($user !== null) {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        echo '<div class="admin-menu">';
        echo '<div class="admin-menu-bar">';
        echo '<button type="button" class="admin-menu-toggle" id="admin-menu-toggle" aria-expanded="false" aria-controls="admin-menu-panel">';
        echo '<svg class="admin-menu-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="22" height="22">';
        echo '<path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';
        echo '</svg> Menu</button>';
        echo '<h1 class="tagline">' . tylilo_e($title) . '</h1>';
        echo '</div>';
        echo '<nav id="admin-menu-panel" class="admin-menu-panel" aria-label="Admin">';
        echo tylilo_admin_menu_html($user, $path);
        echo '</nav></div>';
    } else {
        echo '<h1 class="tagline">' . tylilo_e($title) . '</h1>';
    }
    echo '</div></header>';
    echo '<main id="main" tabindex="-1"><div class="wrap">';
    echo $main;
    echo '</div></main>';
    if ($user !== null) {
        $now = time();
        $loginAt = $_SESSION['admin_login_at'] ?? 0;
        $loginAt = is_int($loginAt) ? $loginAt : $now;
        $session = [
            'idleLeft' => TYLILO_ADMIN_IDLE,
            'absoluteLeft' => max(0, TYLILO_ADMIN_ABSOLUTE - ($now - $loginAt)),
            'warn' => TYLILO_ADMIN_WARN,
            'csrf' => tylilo_admin_csrf(),
        ];
        echo '<dialog id="session-warn" class="card session-warn" role="alertdialog" aria-modal="true" aria-labelledby="session-warn-title" aria-describedby="session-warn-text session-countdown session-unsaved">';
        echo '<h2 id="session-warn-title">You will be signed out</h2>';
        echo '<p id="session-warn-text"></p>';
        echo '<p id="session-countdown"></p>';
        echo '<p id="session-live" class="visually-hidden" aria-live="polite"></p>';
        echo '<p id="session-unsaved" hidden>You have unsaved text on this page. Stay signed in to keep it.</p>';
        echo '<button type="button" class="btn send" id="session-stay">Stay signed in</button>';
        echo '<button type="button" id="session-leave" hidden>Go to sign in</button>';
        echo '</dialog>';
        echo '<script type="application/json" id="admin-session-data">' . json_encode($session, JSON_UNESCAPED_SLASHES) . '</script>';
        echo '<script src="' . tylilo_e(tylilo_asset('/assets/js/admin-session.js')) . '" defer></script>';
        echo '<script src="' . tylilo_e(tylilo_asset('/assets/js/admin-menu.js')) . '" defer></script>';
    }
    require dirname(__DIR__) . '/includes/footer.php';
}

function tylilo_admin_guard(): array
{
    $user = tylilo_admin_current();
    if ($user === null || (int) ($user['disabled'] ?? 0) === 1) {
        header('Location: /admin/', true, 303);
        exit;
    }

    return $user;
}

function tylilo_admin_forbid(): void
{
    http_response_code(403);
    tylilo_admin_layout('Not allowed', '<div class="card" role="alert"><h2>You do not have access to that page.</h2></div>');
    exit;
}

function tylilo_admin_field(string $name): string
{
    return is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
}

<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/admin.php';

tylilo_admin_boot();

function tylilo_admin_layout(string $title, string $main): void
{
    global $cspNonce;
    $pageTitle = $title . ' — Admin';
    $pageDescription = 'Private admin area.';
    $pageCanonical = TYLILO_URL . '/admin/';
    $pageRobots = 'noindex, nofollow';
    require dirname(__DIR__) . '/includes/head.php';
    echo '<body>';
    echo '<a class="skip-link" href="#main">Skip to content</a>';
    echo '<header class="hero"><div class="hero-shapes" aria-hidden="true"><span class="blob a"></span><span class="blob b"></span><span class="blob c"></span></div>';
    require dirname(__DIR__) . '/includes/topbar.php';
    echo '<div class="hero-inner"><h1 class="tagline">' . tylilo_e($title) . '</h1></div></header>';
    echo '<main id="main" tabindex="-1"><div class="wrap">';
    $user = tylilo_admin_current();
    if ($user !== null) {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $links = ['/admin/' => 'Home'];
        if (tylilo_admin_can($user, 'notes')) {
            $links['/admin/notes.php'] = 'Notes';
            $links['/admin/todos.php'] = 'To-dos';
        }
        if (tylilo_admin_can($user, 'feedback')) {
            $links['/admin/feedback.php'] = 'Feedback';
        }
        if (tylilo_admin_can($user, 'ideas')) {
            $links['/admin/ideas.php'] = 'Ideas';
        }
        if (tylilo_admin_can($user, 'beta')) {
            $links['/admin/beta/'] = 'Beta';
        }
        if (tylilo_admin_is_owner($user)) {
            $links['/admin/games.php'] = 'Games';
            $links['/admin/users.php'] = 'Users';
            $links['/admin/activity.php'] = 'Activity';
        }
        if (tylilo_admin_can($user, 'leaderboards')) {
            $links['/admin/scores.php'] = 'Scores';
        }
        $links['/admin/settings.php'] = 'Settings';
        echo '<nav class="admin-nav" aria-label="Admin">';
        foreach ($links as $href => $label) {
            $here = $href === '/admin/'
                ? ($path === '/admin' || $path === '/admin/' || $path === '/admin/index.php')
                : str_starts_with($path, $href);
            echo '<a href="' . tylilo_e($href) . '"' . ($here ? ' aria-current="page"' : '') . '>' . tylilo_e($label) . '</a>';
        }
        echo '<form method="post" action="/admin/logout.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><button type="submit">Log out</button></form>';
        echo '</nav>';
    }
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

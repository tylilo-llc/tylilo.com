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
        echo '<nav class="admin-nav" aria-label="Admin">';
        foreach ([
            '/admin/' => 'Home',
            '/admin/notes.php' => 'Notes',
            '/admin/todos.php' => 'To-dos',
            '/admin/games.php' => 'Games',
            '/admin/settings.php' => 'Settings',
        ] as $href => $label) {
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
    require dirname(__DIR__) . '/includes/footer.php';
}

function tylilo_admin_guard(): array
{
    $user = tylilo_admin_current();
    if ($user === null) {
        header('Location: /admin/', true, 303);
        exit;
    }

    return $user;
}

function tylilo_admin_field(string $name): string
{
    return is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
}

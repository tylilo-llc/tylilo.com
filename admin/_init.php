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
 * Admin navigation groups for the current person.
 * Empty groups are omitted, except Software, which the owner sees
 * with a coming-later note until those tools exist.
 *
 * @param array<string, mixed> $user
 * @return list<array{id: string, label: string, links: list<array{0: string, 1: string, 2?: string}>, logout: bool}>
 */
function tylilo_admin_menu_groups(array $user): array
{
    $groups = [
        ['id' => 'home', 'label' => '', 'links' => [['/admin/', 'Home']], 'logout' => false],
    ];

    $consulting = [];
    if (tylilo_admin_can($user, 'inquiries.view')) {
        $consulting[] = ['/admin/inquiries.php', 'Inquiries'];
    }
    if (tylilo_admin_can($user, 'contacts.view')) {
        $consulting[] = ['/admin/contacts.php', 'Contacts'];
    }
    if ($consulting !== []) {
        $groups[] = ['id' => 'consulting', 'label' => 'Consulting', 'links' => $consulting, 'logout' => false];
    }

    if (tylilo_admin_is_owner($user)) {
        $groups[] = ['id' => 'software', 'label' => 'Software', 'links' => [['', 'Coming later', 'note']], 'logout' => false];
    }

    $arcade = [];
    if (tylilo_admin_is_owner($user)) {
        $arcade[] = ['/admin/games.php', 'Games'];
    }
    if (tylilo_admin_can($user, 'beta')) {
        $arcade[] = ['/admin/beta/', 'Beta'];
    }
    if (tylilo_admin_can($user, 'leaderboards')) {
        $arcade[] = ['/admin/scores.php', 'Scores'];
    }
    if (tylilo_admin_can($user, 'feedback')) {
        $arcade[] = ['/admin/feedback.php', 'Feedback'];
    }
    if ($arcade !== []) {
        $groups[] = ['id' => 'arcade', 'label' => 'Arcade', 'links' => $arcade, 'logout' => false];
    }

    $site = [];
    if (tylilo_admin_is_owner($user)) {
        $site[] = ['/admin/users.php', 'Users'];
        $site[] = ['/admin/activity.php', 'Activity'];
    }
    if (tylilo_admin_can($user, 'ideas')) {
        $site[] = ['/admin/ideas.php', 'Ideas'];
    }
    if (tylilo_admin_can($user, 'notes')) {
        $site[] = ['/admin/notes.php', 'Notes'];
        $site[] = ['/admin/todos.php', 'To-dos'];
    }
    $site[] = ['/admin/settings.php', 'Settings'];
    $groups[] = ['id' => 'site', 'label' => 'Site', 'links' => $site, 'logout' => true];

    return $groups;
}


/**
 * Unread consulting inquiries for this person, or 0 without permission.
 *
 * @param array<string, mixed> $user
 */
function tylilo_admin_inquiries_unread(array $user): int
{
    if (!tylilo_admin_can($user, 'inquiries.view')) {
        return 0;
    }
    try {
        require_once dirname(__DIR__) . '/includes/inquiry.php';

        return tylilo_inquiry_unread_count((int) ($user['id'] ?? 0));
    } catch (Throwable $e) {
        return 0;
    }
}

function tylilo_admin_unread_badge(int $count, string $label): string
{
    if ($count < 1) {
        return '';
    }
    $shown = $count > 99 ? '99+' : (string) $count;

    return '<span class="admin-unread-badge" aria-hidden="true">' . tylilo_e($shown) . '</span>';
}

/**
 * Grouped admin navigation. A group with no visible links is omitted.
 *
 * @param array<string, mixed> $user
 */
function tylilo_admin_menu_html(array $user, string $path): string
{
    $html = '';
    foreach (tylilo_admin_menu_groups($user) as $group) {
        $html .= '<div class="admin-menu-group" data-group="' . tylilo_e($group['id']) . '">';
        if ($group['label'] !== '') {
            $html .= '<h2 class="admin-menu-label">' . tylilo_e($group['label']) . '</h2>';
        }
        foreach ($group['links'] as $link) {
            $href = (string) $link[0];
            $label = (string) $link[1];
            if (($link[2] ?? '') === 'note') {
                $html .= '<p class="admin-menu-note">' . tylilo_e($label) . '</p>';
                continue;
            }
            $here = tylilo_admin_here($href, $path);
            $unread = 0;
            $aria = '';
            if ($href === '/admin/inquiries.php') {
                $unread = tylilo_admin_inquiries_unread($user);
                if ($unread > 0) {
                    $aria = ' aria-label="' . tylilo_e($label . ', ' . $unread . ' unread') . '"';
                }
            }
            $html .= '<a href="' . tylilo_e($href) . '"' . ($here ? ' aria-current="page"' : '') . $aria . '>' . tylilo_e($label) . tylilo_admin_unread_badge($unread, $label) . '</a>';
        }
        if ($group['logout'] === true) {
            $html .= '<form method="post" action="/admin/logout.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><button type="submit">Log out</button></form>';
        }
        $html .= '</div>';
    }

    return $html;
}

/**
 * Dashboard summary cards, one per menu group the person can see.
 *
 * @param array<string, mixed> $user
 */
function tylilo_admin_dashboard_html(array $user): string
{
    $html = '<div class="card"><h2>Hello, ' . tylilo_e((string) ($user['username'] ?? '')) . '</h2>';
    $html .= '<p>Your role is ' . tylilo_e((string) ($user['role'] ?? 'member')) . '. Open a section from the cards below or from the menu.</p></div>';
    $html .= '<div class="admin-dash">';
    foreach (tylilo_admin_menu_groups($user) as $group) {
        if ($group['id'] === 'home') {
            continue;
        }
        $html .= tylilo_admin_dashboard_card($user, $group);
    }
    $html .= '</div>';

    return $html;
}

/**
 * @param array<string, mixed> $user
 * @param array{id: string, label: string, links: list<array{0: string, 1: string, 2?: string}>, logout: bool} $group
 */
function tylilo_admin_dashboard_card(array $user, array $group): string
{
    $id = $group['id'];
    $title = $group['label'] !== '' ? $group['label'] : 'Home';
    $body = '';
    $actions = [];
    if ($id === 'consulting') {
        $total = 0;
        $people = 0;
        try {
            if (tylilo_admin_can($user, 'inquiries.view')) {
                require_once dirname(__DIR__) . '/includes/inquiry.php';
                $total = count(tylilo_inquiry_all());
            }
            if (tylilo_admin_can($user, 'contacts.view')) {
                require_once dirname(__DIR__) . '/includes/contacts.php';
                $people = count(tylilo_contacts_all());
            }
        } catch (Throwable $e) {
            $total = 0;
        }
        if (tylilo_admin_can($user, 'inquiries.view')) {
            $unread = tylilo_admin_inquiries_unread($user);
            if ($unread === 0) {
                $body .= '<p>No unread inquiries, ' . $total . ' in total.</p>';
            } else {
                $body .= '<p>' . ($unread === 1 ? '1 unread inquiry' : $unread . ' unread inquiries') . ', ' . $total . ' in total.</p>';
            }
            $actions[] = ['/admin/inquiries.php', 'View inquiries', $unread];
        }
        if (tylilo_admin_can($user, 'contacts.view')) {
            $body .= '<p>' . ($people === 0 ? 'No contact profiles yet.' : ($people === 1 ? '1 person in Contacts.' : $people . ' people in Contacts.')) . '</p>';
            $actions[] = ['/admin/contacts.php', 'View contacts'];
        }
    } elseif ($id === 'software') {
        $body = '<p>Castmaker and other tools will appear here.</p>';
    } elseif ($id === 'arcade') {
        $scores = 0;
        $latest = '';
        $feedback = 0;
        try {
            if (tylilo_admin_can($user, 'leaderboards')) {
                require_once dirname(__DIR__) . '/includes/scores.php';
                $data = tylilo_scores_with(static fn (array $stored): array => $stored);
                $bestAt = 0;
                foreach ($data['scores'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $scores++;
                    $at = (int) ($row['at'] ?? 0);
                    if ($at >= $bestAt) {
                        $bestAt = $at;
                        $name = trim((string) ($row['name'] ?? ''));
                        $score = (string) ($row['score'] ?? '');
                        $game = (string) ($row['game'] ?? '');
                        $latest = trim($name . ($score !== '' ? ', ' . $score : '') . ($game !== '' ? ' on ' . $game : ''));
                    }
                }
            }
            if (tylilo_admin_can($user, 'feedback')) {
                $data = tylilo_admin_with(static fn (array $stored): array => $stored);
                $feedback = count($data['feedback'] ?? []);
            }
        } catch (Throwable $e) {
            $scores = 0;
        }
        if (tylilo_admin_can($user, 'leaderboards')) {
            $body .= $scores === 0
                ? '<p>No scores on file.</p>'
                : '<p>' . $scores . ' ' . ($scores === 1 ? 'score' : 'scores') . ' on file' . ($latest !== '' ? '. Latest: ' . tylilo_e($latest) : '') . '.</p>';
            $actions[] = ['/admin/scores.php', 'View scores'];
        }
        if (tylilo_admin_can($user, 'feedback')) {
            $body .= '<p>' . ($feedback === 0 ? 'No feedback yet.' : ($feedback === 1 ? '1 feedback item.' : $feedback . ' feedback items.')) . '</p>';
            $actions[] = ['/admin/feedback.php', 'View feedback'];
        }
        if (tylilo_admin_can($user, 'beta')) {
            $actions[] = ['/admin/beta/', 'Open beta'];
        }
        if ($body === '') {
            $body = '<p>Arcade tools you can use are in the menu.</p>';
        }
    } elseif ($id === 'site') {
        $open = 0;
        $total = 0;
        try {
            if (tylilo_admin_can($user, 'ideas')) {
                $data = tylilo_admin_with(static fn (array $stored): array => $stored);
                foreach ($data['ideas'] ?? [] as $idea) {
                    if (!is_array($idea)) {
                        continue;
                    }
                    $total++;
                    if ((string) ($idea['status'] ?? '') !== 'published') {
                        $open++;
                    }
                }
            }
        } catch (Throwable $e) {
            $open = 0;
        }
        if (tylilo_admin_can($user, 'ideas')) {
            $body .= '<p>' . ($open === 0 ? 'No open ideas' : ($open === 1 ? '1 open idea' : $open . ' open ideas')) . ($total > 0 ? ' (' . $total . ' in total)' : '') . '.</p>';
            $actions[] = ['/admin/ideas.php', 'View ideas'];
        }
        if (tylilo_admin_is_owner($user)) {
            $actions[] = ['/admin/users.php', 'Manage users'];
        }
        if (tylilo_admin_can($user, 'notes')) {
            $actions[] = ['/admin/notes.php', 'Open notes'];
        }
        $actions[] = ['/admin/settings.php', 'Settings'];
        if ($body === '') {
            $body = '<p>Change your password and sign-in options, or open the other site tools in the menu.</p>';
        }
    } else {
        $body = '<p>Open this section from the menu.</p>';
    }
    if ($body === '') {
        $body = '<p>Nothing to report yet.</p>';
    }
    $html = '<article class="card admin-dash-card" data-dash="' . tylilo_e($id) . '">';
    $consultingUnread = $id === 'consulting' ? tylilo_admin_inquiries_unread($user) : 0;
    $html .= '<h2>' . tylilo_e($title) . tylilo_admin_unread_badge($consultingUnread, $title) . '</h2>' . $body;
    if ($actions !== []) {
        $html .= '<p class="admin-dash-actions">';
        foreach ($actions as $item) {
            $href = (string) $item[0];
            $label = (string) $item[1];
            $count = (int) ($item[2] ?? 0);
            $aria = $count > 0 ? ' aria-label="' . tylilo_e($label . ', ' . $count . ' unread') . '"' : '';
            $html .= '<a href="' . tylilo_e($href) . '"' . $aria . '>' . tylilo_e($label) . tylilo_admin_unread_badge($count, $label) . '</a>';
        }
        $html .= '</p>';
    }
    $html .= '</article>';

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
    echo '</header>';
    if ($user !== null) {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        echo '<div class="admin-shell">';
        echo '<div class="admin-menu">';
        echo '<div class="admin-menu-bar">';
        $menuUnread = tylilo_admin_inquiries_unread($user);
        $menuLabel = $menuUnread > 0 ? 'Menu, ' . $menuUnread . ' unread inquiries' : 'Menu';
        echo '<button type="button" class="admin-menu-toggle" id="admin-menu-toggle" aria-expanded="false" aria-controls="admin-menu-panel"' . ($menuUnread > 0 ? ' aria-label="' . tylilo_e($menuLabel) . '"' : '') . '>';
        echo '<svg class="admin-menu-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="22" height="22">';
        echo '<path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';
        echo '</svg> Menu' . tylilo_admin_unread_badge($menuUnread, 'Menu') . '</button>';
        echo '<h1 class="tagline">' . tylilo_e($title) . '</h1>';
        echo '</div>';
        echo '<nav id="admin-menu-panel" class="admin-menu-panel" aria-label="Admin">';
        echo tylilo_admin_menu_html($user, $path);
        echo '</nav></div>';
        echo '<main id="main" tabindex="-1"><div class="wrap">';
        echo $main;
        echo '</div></main></div>';
    } else {
        echo '<main id="main" tabindex="-1"><div class="wrap">';
        echo '<h1 class="tagline">' . tylilo_e($title) . '</h1>';
        echo $main;
        echo '</div></main>';
    }
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

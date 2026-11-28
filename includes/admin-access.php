<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

const TYLILO_ADMIN_IDLE = 1800;
const TYLILO_ADMIN_ABSOLUTE = 43200;
const TYLILO_ADMIN_WARN = 120;
const TYLILO_ADMIN_INVITE_TTL = 604800;
const TYLILO_ADMIN_RESET_TTL = 86400;

/**
 * Grouped permissions shown on the Users page. Future consulting or
 * software admin tools get their own key in the matching group, off by
 * default. The owner can always open every section.
 *
 * @return array<string, array<string, array{label: string, short: string, invite_default: bool}>>
 */
function tylilo_admin_permission_catalog(): array
{
    return [
        'Workspace' => [
            'notes' => ['label' => 'Own private notes and to-dos', 'short' => 'Notes', 'invite_default' => true],
            'feedback' => ['label' => 'Game feedback', 'short' => 'Feedback', 'invite_default' => true],
            'ideas' => ['label' => 'Shared ideas board', 'short' => 'Ideas', 'invite_default' => true],
        ],
        'Consulting' => [
            'inquiries.view' => ['label' => 'View consulting inquiries', 'short' => 'Inquiries', 'invite_default' => false],
            'contacts.view' => ['label' => 'View private contact profiles', 'short' => 'Contacts', 'invite_default' => false],
        ],
        'Games' => [
            'leaderboards' => ['label' => 'Leaderboard moderation', 'short' => 'Leaderboards', 'invite_default' => false],
            'beta' => ['label' => 'Play beta games', 'short' => 'Beta games', 'invite_default' => false],
        ],
    ];
}

function tylilo_admin_permission_post_name(string $key): string
{
    return 'perm_' . str_replace('.', '_', $key);
}

function tylilo_admin_default_permissions(bool $leaderboards = false): array
{
    $out = [];
    foreach (tylilo_admin_permission_catalog() as $group) {
        foreach ($group as $key => $meta) {
            $out[$key] = !empty($meta['invite_default']) ? 1 : 0;
        }
    }
    if ($leaderboards) {
        $out['leaderboards'] = 1;
    }

    return $out;
}

function tylilo_admin_permissions_from_post(): array
{
    $out = [];
    foreach (tylilo_admin_permission_catalog() as $group) {
        foreach (array_keys($group) as $key) {
            $out[$key] = isset($_POST[tylilo_admin_permission_post_name($key)]) ? 1 : 0;
        }
    }

    return $out;
}

/**
 * Permission checkboxes for an invite (defaults) or an existing member.
 *
 * @param array<string, mixed>|null $current
 */
function tylilo_admin_permissions_fields_html(?array $current, bool $short = false): string
{
    $html = '';
    foreach (tylilo_admin_permission_catalog() as $group => $perms) {
        $html .= '<fieldset class="perm-group"><legend>' . tylilo_e($group) . '</legend>';
        foreach ($perms as $key => $meta) {
            $name = tylilo_admin_permission_post_name($key);
            $label = $short ? (string) $meta['short'] : (string) $meta['label'];
            $on = $current === null ? !empty($meta['invite_default']) : !empty($current[$key]);
            $html .= '<label><input type="checkbox" name="' . tylilo_e($name) . '"' . ($on ? ' checked' : '') . '> ' . tylilo_e($label) . '</label>';
        }
        $html .= '</fieldset>';
    }

    return $html;
}

function tylilo_admin_migrate(array $data): array
{
    $data += tylilo_admin_blank();
    if (!is_array($data['password_resets'] ?? null)) {
        $data['password_resets'] = [];
    }
    $ownerId = 0;
    foreach ($data['users'] as &$user) {
        if (($user['role'] ?? '') === 'admin') {
            $user['role'] = 'owner';
        }
        if (($user['role'] ?? '') === 'owner' && $ownerId === 0) {
            $ownerId = (int) $user['id'];
        }
        $user['disabled'] = (int) ($user['disabled'] ?? 0);
        // Existing permission maps are left as stored. New keys such as
        // inquiries.view are not added or granted. Missing maps get the
        // current defaults, where consulting and software keys stay off.
        if (!isset($user['permissions']) || !is_array($user['permissions'])) {
            $user['permissions'] = tylilo_admin_default_permissions(($user['role'] ?? '') === 'owner');
        }
    }
    unset($user);
    foreach (['notes', 'todos'] as $key) {
        foreach ($data[$key] as &$row) {
            if (!isset($row['user_id'])) {
                $row['user_id'] = $ownerId;
            }
        }
        unset($row);
    }
    foreach ($data['ideas'] as &$idea) {
        if (is_array($idea)) {
            $idea = tylilo_idea_normalize($idea);
        }
    }
    unset($idea);

    return $data;
}

/**
 * @return array<string, string>
 */
function tylilo_idea_help_texts(): array
{
    return [
        'title' => 'The name you are using while the game is still an idea. It can change later. Example: Harbor Match.',
        'pitch' => 'One sentence that says what the player does. Example: Slide boats into port, three at a time.',
        'genre' => 'The kind of game it is. Example: Puzzle.',
        'style' => 'How the game should look. Example: Retro pixel.',
        'inspired' => 'A game, place, or feeling this reminds you of. It is a starting point, not a copy. Example: A quiet afternoon at the docks.',
        'controls' => 'How the player gives commands. Tick every way that fits. Example: Keyboard and mouse.',
        'devices' => 'Where someone should be able to play. Example: Phone and desktop.',
        'session' => 'How long one sitting should take. Example: Under 2 min.',
        'players' => 'Who plays at the same time. Example: Solo.',
        'difficulty' => 'How demanding a normal game should feel. Example: Casual.',
        'scoring' => 'What the player is trying to beat. Example: High score.',
        'leaderboard' => 'Tick this if that result should be able to appear on a public Tylilo leaderboard later. Example: on, for a high-score puzzle.',
        'audience' => 'Who the game is for. Example: Everyone.',
        'effort' => 'A rough guess of how big the build is. It is not a promise. Example: A weekend.',
        'status' => 'Where the idea is right now. Anyone can mark it Idea or Discussing. Only the owner can mark it Approved, In beta, or Published. Example: Discussing.',
        'priority' => 'How soon the owner wants to look at this. Only the owner sets it. Example: High.',
        'notes' => 'Extra detail that does not fit the other boxes. Example: Keep the palette cream and navy.',
        'image' => 'An optional picture, such as a napkin sketch or a reference. Use a JPEG, PNG, WebP, or GIF under 2 MB. Example: a photo of a paper sketch.',
    ];
}

function tylilo_idea_info_button(string $helpId, string $label): string
{
    return '<button type="button" class="idea-info" aria-expanded="false" aria-controls="' . tylilo_e($helpId) . '" aria-describedby="' . tylilo_e($helpId) . '" aria-label="About ' . tylilo_e($label) . '"><span aria-hidden="true">(i)</span></button>';
}

function tylilo_idea_info_panel(string $helpId, string $key): string
{
    $text = tylilo_idea_help_texts()[$key] ?? '';

    return '<p id="' . tylilo_e($helpId) . '" class="idea-help" role="tooltip" hidden>' . tylilo_e($text) . '</p>';
}

function tylilo_idea_info(string $fieldId, string $label, string $key): string
{
    $helpId = $fieldId . '-help';

    return '<span class="idea-label"><label for="' . tylilo_e($fieldId) . '">' . tylilo_e($label) . '</label>'
        . tylilo_idea_info_button($helpId, $label) . '</span>'
        . tylilo_idea_info_panel($helpId, $key);
}

/**
 * @return array<string, array<string, string>>
 */
function tylilo_idea_choices(): array
{
    return [
        'genre' => [
            'arcade' => 'Arcade',
            'puzzle' => 'Puzzle',
            'platformer' => 'Platformer',
            'shooter' => 'Shooter',
            'racing' => 'Racing',
            'card' => 'Card/board',
            'strategy' => 'Strategy',
            'sports' => 'Sports',
            'rhythm' => 'Rhythm',
            'adventure' => 'Adventure',
            'other' => 'Other',
        ],
        'style' => [
            'pixel' => 'Retro pixel',
            '8-bit' => '8-bit',
            'neon' => 'Vector/neon',
            'cartoon' => 'Cartoon',
            'low-poly' => 'Low-poly 3D',
            'hand-drawn' => 'Hand-drawn',
            'minimal' => 'Minimal',
            'other' => 'Other',
        ],
        'controls' => [
            'keyboard' => 'Keyboard',
            'mouse' => 'Mouse/tap',
            'swipe' => 'Touch swipe',
            'gamepad' => 'Gamepad',
        ],
        'devices' => [
            'phone' => 'Phone',
            'tablet' => 'Tablet',
            'desktop' => 'Desktop',
        ],
        'session' => [
            'short' => 'Under 2 min',
            'medium' => '5–10 min',
            'long' => '30+ min',
        ],
        'players' => [
            'solo' => 'Solo',
            'local' => 'Local 2-player',
            'turns' => 'Take turns',
        ],
        'difficulty' => [
            'casual' => 'Casual',
            'medium' => 'Medium',
            'hardcore' => 'Hardcore',
        ],
        'scoring' => [
            'score' => 'High score',
            'time' => 'Time',
            'levels' => 'Levels cleared',
            'none' => 'None',
        ],
        'audience' => [
            'everyone' => 'Everyone',
            'kids' => 'Kids',
            'teens' => 'Teens and up',
        ],
        'effort' => [
            'weekend' => 'Weekend',
            'weeks' => 'A few weeks',
            'big' => 'Big project',
        ],
        'status' => [
            'idea' => 'Idea',
            'discussing' => 'Discussing',
            'approved' => 'Approved',
            'beta' => 'In beta',
            'published' => 'Published',
        ],
        'priority' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
        ],
    ];
}

function tylilo_idea_owner_statuses(): array
{
    return ['approved', 'beta', 'published'];
}

function tylilo_idea_choice(string $group, string $value): string
{
    $choices = tylilo_idea_choices();

    return $choices[$group][$value] ?? '';
}

function tylilo_idea_list(string $group, mixed $values): array
{
    $allowed = array_keys(tylilo_idea_choices()[$group] ?? []);
    $values = is_array($values) ? $values : [];
    $picked = [];
    foreach ($values as $value) {
        if (is_string($value) && in_array($value, $allowed, true) && !in_array($value, $picked, true)) {
            $picked[] = $value;
        }
    }

    return $picked;
}

function tylilo_idea_normalize(array $idea): array
{
    $status = (string) ($idea['status'] ?? '');
    if ($status === 'proposed' || $status === '') {
        $status = 'idea';
    }
    if (tylilo_idea_choice('status', $status) === '') {
        $status = 'idea';
    }
    $priority = (string) ($idea['priority'] ?? '');
    if ($priority !== '' && tylilo_idea_choice('priority', $priority) === '') {
        $priority = '';
    }
    $votes = [];
    foreach (is_array($idea['votes'] ?? null) ? $idea['votes'] : [] as $vote) {
        $vote = (int) $vote;
        if ($vote > 0 && !in_array($vote, $votes, true)) {
            $votes[] = $vote;
        }
    }

    return [
        'id' => (int) ($idea['id'] ?? 0),
        'user_id' => (int) ($idea['user_id'] ?? 0),
        'title' => (string) ($idea['title'] ?? ''),
        'pitch' => (string) ($idea['pitch'] ?? ''),
        'notes' => (string) ($idea['notes'] ?? ''),
        'inspired' => (string) ($idea['inspired'] ?? ''),
        'genre' => tylilo_idea_choice('genre', (string) ($idea['genre'] ?? '')) === '' ? '' : (string) $idea['genre'],
        'style' => tylilo_idea_choice('style', (string) ($idea['style'] ?? '')) === '' ? '' : (string) $idea['style'],
        'controls' => tylilo_idea_list('controls', $idea['controls'] ?? []),
        'devices' => tylilo_idea_list('devices', $idea['devices'] ?? []),
        'session' => tylilo_idea_choice('session', (string) ($idea['session'] ?? '')) === '' ? '' : (string) $idea['session'],
        'players' => tylilo_idea_choice('players', (string) ($idea['players'] ?? '')) === '' ? '' : (string) $idea['players'],
        'difficulty' => tylilo_idea_choice('difficulty', (string) ($idea['difficulty'] ?? '')) === '' ? '' : (string) $idea['difficulty'],
        'scoring' => tylilo_idea_choice('scoring', (string) ($idea['scoring'] ?? '')) === '' ? '' : (string) $idea['scoring'],
        'leaderboard' => !empty($idea['leaderboard']) ? 1 : 0,
        'audience' => tylilo_idea_choice('audience', (string) ($idea['audience'] ?? '')) === '' ? '' : (string) $idea['audience'],
        'effort' => tylilo_idea_choice('effort', (string) ($idea['effort'] ?? '')) === '' ? '' : (string) $idea['effort'],
        'status' => $status,
        'priority' => $priority,
        'image' => (string) ($idea['image'] ?? ''),
        'votes' => $votes,
        'created_at' => (int) ($idea['created_at'] ?? time()),
    ];
}

function tylilo_idea_posted_list(string $name, string $group): array
{
    $raw = $_POST[$name] ?? [];

    return tylilo_idea_list($group, is_array($raw) ? $raw : []);
}

function tylilo_idea_post_field(string $name): string
{
    return is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
}

function tylilo_idea_from_post(bool $owner, array $previous): array
{
    $previous = tylilo_idea_normalize($previous);
    $posted = tylilo_idea_post_field('status');
    $memberStatus = in_array($posted, ['idea', 'discussing'], true) ? $posted : '';
    if ($owner) {
        $status = tylilo_idea_choice('status', $posted) === '' ? $previous['status'] : $posted;
        $priority = tylilo_idea_post_field('priority');
        $priority = tylilo_idea_choice('priority', $priority) === '' ? '' : $priority;
    } else {
        $status = $memberStatus !== '' && !in_array($previous['status'], tylilo_idea_owner_statuses(), true)
            ? $memberStatus
            : $previous['status'];
        $priority = $previous['priority'];
    }

    return tylilo_idea_normalize([
        'id' => $previous['id'],
        'user_id' => $previous['user_id'],
        'title' => tylilo_idea_post_field('title'),
        'pitch' => tylilo_idea_post_field('pitch'),
        'notes' => is_string($_POST['notes'] ?? null) ? trim($_POST['notes']) : '',
        'inspired' => tylilo_idea_post_field('inspired'),
        'genre' => tylilo_idea_post_field('genre'),
        'style' => tylilo_idea_post_field('style'),
        'controls' => tylilo_idea_posted_list('controls', 'controls'),
        'devices' => tylilo_idea_posted_list('devices', 'devices'),
        'session' => tylilo_idea_post_field('session'),
        'players' => tylilo_idea_post_field('players'),
        'difficulty' => tylilo_idea_post_field('difficulty'),
        'scoring' => tylilo_idea_post_field('scoring'),
        'leaderboard' => isset($_POST['leaderboard']) ? 1 : 0,
        'audience' => tylilo_idea_post_field('audience'),
        'effort' => tylilo_idea_post_field('effort'),
        'status' => $status,
        'priority' => $priority,
        'image' => $previous['image'],
        'votes' => $previous['votes'],
        'created_at' => $previous['created_at'],
    ]);
}

function tylilo_idea_toggle_vote(array $votes, int $userId): array
{
    $next = [];
    $had = false;
    foreach ($votes as $id) {
        $id = (int) $id;
        if ($id <= 0 || in_array($id, $next, true)) {
            continue;
        }
        if ($id === $userId) {
            $had = true;
            continue;
        }
        $next[] = $id;
    }
    if (!$had && $userId > 0) {
        $next[] = $userId;
    }

    return $next;
}

function tylilo_admin_is_owner(array $user): bool
{
    return ($user['role'] ?? '') === 'owner' || ($user['role'] ?? '') === 'admin';
}

function tylilo_admin_can(array $user, string $permission): bool
{
    if ((int) ($user['disabled'] ?? 0) === 1) {
        return false;
    }
    if (tylilo_admin_is_owner($user)) {
        return true;
    }
    $permissions = $user['permissions'] ?? [];

    return is_array($permissions) && !empty($permissions[$permission]);
}

function tylilo_admin_touch_session(): void
{
    tylilo_session_start();
    $now = time();
    $last = $_SESSION['admin_last_seen'] ?? 0;
    $last = is_int($last) ? $last : 0;
    $login = $_SESSION['admin_login_at'] ?? 0;
    $login = is_int($login) ? $login : 0;
    $signedIn = !empty($_SESSION['admin_user_id']);
    $idleEnded = $signedIn && $last > 0 && ($now - $last) > TYLILO_ADMIN_IDLE;
    $capEnded = $signedIn && ($login <= 0 || ($now - $login) > TYLILO_ADMIN_ABSOLUTE);
    if ($idleEnded || $capEnded) {
        $who = (int) $_SESSION['admin_user_id'];
        unset($_SESSION['admin_user_id'], $_SESSION['admin_pending_id'], $_SESSION['admin_login_at'], $_SESSION['admin_session_version'], $_SESSION['admin_last_seen']);
        $_SESSION['admin_notice'] = 'signed-out';
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        tylilo_admin_log($who, '', $capEnded ? 'session-cap' : 'session-idle', 'Signed out');

        return;
    }
    if ($signedIn || !empty($_SESSION['admin_pending_id'])) {
        $_SESSION['admin_last_seen'] = $now;
    }
}

function tylilo_admin_begin_session(array $account): void
{
    tylilo_session_start();
    session_regenerate_id(true);
    $now = time();
    unset($_SESSION['admin_pending_id'], $_SESSION['admin_notice']);
    $_SESSION['admin_user_id'] = (int) $account['id'];
    $_SESSION['admin_last_seen'] = $now;
    $_SESSION['admin_login_at'] = $now;
    $_SESSION['admin_session_version'] = (int) ($account['session_version'] ?? 1);
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function tylilo_admin_end_session(string $notice = ''): void
{
    tylilo_session_start();
    $_SESSION = [];
    if ($notice !== '') {
        $_SESSION['admin_notice'] = $notice;
    }
    session_regenerate_id(true);
}

function tylilo_admin_bump_session(array &$user): void
{
    $user['session_version'] = (int) ($user['session_version'] ?? 1) + 1;
}

function tylilo_admin_log(int $userId, string $username, string $action, string $detail): void
{
    tylilo_admin_with(static function (array $data) use ($userId, $username, $action, $detail): array {
        $data['activity'][] = [
            'id' => tylilo_admin_next_id($data['activity'] ?? []),
            'at' => time(),
            'user_id' => $userId,
            'username' => $username,
            'action' => $action,
            'detail' => $detail,
        ];
        if (count($data['activity']) > 400) {
            $data['activity'] = array_slice($data['activity'], -400);
        }

        return $data;
    });
}

function tylilo_admin_owner_id(array $data): int
{
    foreach ($data['users'] as $user) {
        if (tylilo_admin_is_owner($user)) {
            return (int) $user['id'];
        }
    }

    return 0;
}

function tylilo_admin_current_game_version(string $slug): string
{
    if (preg_match('/^[a-z0-9-]{1,40}$/', $slug) !== 1) {
        return '';
    }
    $file = dirname(__DIR__) . '/arcade/' . $slug . '/index.html';
    if (!is_file($file)) {
        return '';
    }
    $text = file_get_contents($file, false, null, 0, 300000);
    if (!is_string($text)) {
        return '';
    }
    if (preg_match("/VERSION\\s*=\\s*['\"]([0-9A-Za-z._-]{1,16})['\"]/", $text, $match) === 1) {
        return $match[1];
    }
    if (preg_match('/"version"\\s*:\\s*"([0-9A-Za-z._-]{1,16})"/', $text, $match) === 1) {
        return $match[1];
    }

    return '';
}

function tylilo_admin_idea_image_extension(string $path, int $size): string
{
    if ($size < 1 || $size > 2000000 || !is_file($path)) {
        return '';
    }
    $info = @getimagesize($path);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!is_array($info) || !isset($types[$info[2] ?? 0])) {
        return '';
    }

    return $types[$info[2]];
}

function tylilo_admin_store_idea_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return '';
    }
    $tmp = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
    $ext = tylilo_admin_idea_image_extension($tmp, (int) ($file['size'] ?? 0));
    if ($ext === '') {
        return '';
    }
    $dir = tylilo_turnstile_dir() . '/idea-images';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        return '';
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!is_uploaded_file($tmp) || !tylilo_admin_idea_image_write($tmp, $dir . '/' . $name, $ext)) {
        return '';
    }

    return $name;
}

function tylilo_admin_idea_image_write(string $src, string $dest, string $ext): bool
{
    $loaders = [
        'jpg' => 'imagecreatefromjpeg',
        'png' => 'imagecreatefrompng',
        'webp' => 'imagecreatefromwebp',
        'gif' => 'imagecreatefromgif',
    ];
    $loader = $loaders[$ext] ?? '';
    if ($loader === '' || !function_exists($loader)) {
        return false;
    }
    $image = @$loader($src);
    if ($image === false) {
        return false;
    }
    $ok = match ($ext) {
        'jpg' => imagejpeg($image, $dest, 90),
        'png' => imagepng($image, $dest),
        'webp' => function_exists('imagewebp') && imagewebp($image, $dest, 90),
        'gif' => imagegif($image, $dest),
        default => false,
    };
    imagedestroy($image);

    return $ok;
}

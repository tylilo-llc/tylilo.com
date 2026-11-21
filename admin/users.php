<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_is_owner($me)) {
    tylilo_admin_forbid();
}

$error = '';
$freshLink = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
    } else {
        $action = tylilo_admin_field('action');
        $id = (int) tylilo_admin_field('id');
        if ($action === 'invite') {
            $token = bin2hex(random_bytes(16));
            $permissions = tylilo_admin_permissions_from_post();
            tylilo_admin_with(static function (array $data) use ($token, $permissions): array {
                $data['invites'][] = [
                    'id' => tylilo_admin_next_id($data['invites']),
                    'token_hash' => hash('sha256', $token),
                    'permissions' => $permissions,
                    'expires_at' => time() + TYLILO_ADMIN_INVITE_TTL,
                    'used_at' => 0,
                    'revoked' => 0,
                    'created_at' => time(),
                ];

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'invite', 'Created an invite link');
            $freshLink = TYLILO_URL . '/admin/invite.php?code=' . $token;
        } elseif ($action === 'revoke') {
            tylilo_admin_with(static function (array $data) use ($id): array {
                foreach ($data['invites'] as &$invite) {
                    if ((int) $invite['id'] === $id) {
                        $invite['revoked'] = 1;
                    }
                }

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'invite', 'Revoked invite ' . $id);
        } elseif ($action === 'permissions') {
            $permissions = tylilo_admin_permissions_from_post();
            tylilo_admin_with(static function (array $data) use ($id, $permissions): array {
                foreach ($data['users'] as &$user) {
                    if ((int) $user['id'] === $id && !tylilo_admin_is_owner($user)) {
                        $user['permissions'] = $permissions;
                        tylilo_admin_bump_session($user);
                    }
                }

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'permissions', 'Updated permissions and ended sessions for user ' . $id);
        } elseif ($action === 'disable' || $action === 'enable') {
            $disabled = $action === 'disable' ? 1 : 0;
            tylilo_admin_with(static function (array $data) use ($id, $disabled): array {
                foreach ($data['users'] as &$user) {
                    if ((int) $user['id'] === $id && !tylilo_admin_is_owner($user)) {
                        $user['disabled'] = $disabled;
                        if ($disabled === 1) {
                            tylilo_admin_bump_session($user);
                        }
                    }
                }

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], $action, 'User ' . $id);
        } elseif ($action === 'remove') {
            tylilo_admin_with(static function (array $data) use ($id): array {
                $data['users'] = array_values(array_filter($data['users'], static fn (array $user): bool => (int) $user['id'] !== $id || tylilo_admin_is_owner($user)));
                $data['notes'] = array_values(array_filter($data['notes'], static fn (array $row): bool => (int) ($row['user_id'] ?? 0) !== $id));
                $data['todos'] = array_values(array_filter($data['todos'], static fn (array $row): bool => (int) ($row['user_id'] ?? 0) !== $id));

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'remove', 'Removed user ' . $id . ' and ended their sessions');
        } elseif ($action === 'signout') {
            $targetName = '';
            tylilo_admin_with(static function (array $data) use ($id, &$targetName): array {
                foreach ($data['users'] as &$user) {
                    if ((int) $user['id'] === $id) {
                        tylilo_admin_bump_session($user);
                        $targetName = (string) $user['username'];
                    }
                }

                return $data;
            });
            tylilo_admin_log((int) $me['id'], (string) $me['username'], 'sign-out-everywhere', $targetName !== '' ? $targetName : ('User ' . $id));
            if ($id === (int) $me['id']) {
                tylilo_admin_end_session('signed-out');
                header('Location: /admin/?out=1', true, 303);
                exit;
            }
        }
        if ($freshLink === '' && $error === '') {
            header('Location: /admin/users.php', true, 303);
            exit;
        }
    }
}

$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$html = '<div class="card"><h2>Users</h2>';
$html .= '<p>You are the owner. Invite links work once and expire after 7 days. The site does not email them.</p>';
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}
if ($freshLink !== '') {
    $html .= '<div class="form-status success" role="status"><p>Copy this invite link now. It is not shown again.</p><p class="invite-copy"><label for="invite-link" class="visually-hidden">Invite link</label><input id="invite-link" type="text" readonly value="' . tylilo_e($freshLink) . '" style="flex:1;min-width:0"> <button type="button" class="btn send" id="copy-invite">Copy link</button></p><p id="copy-invite-status" role="status" aria-live="polite"></p></div>';
}
$html .= '<button type="button" class="btn send" id="open-invite">Invite someone</button>';
$html .= '<dialog id="invite-dialog" class="card" aria-labelledby="invite-title">';
$html .= '<h2 id="invite-title">Invite someone</h2>';
$html .= '<form method="post" action="/admin/users.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="invite">';
foreach (['perm_notes' => 'Own private notes and to-dos', 'perm_feedback' => 'Game feedback', 'perm_ideas' => 'Shared ideas board', 'perm_leaderboards' => 'Leaderboard moderation', 'perm_beta' => 'Play beta games'] as $name => $label) {
    $checked = $name === 'perm_leaderboards' || $name === 'perm_beta' ? '' : ' checked';
    $html .= '<label><input type="checkbox" name="' . $name . '"' . $checked . '> ' . tylilo_e($label) . '</label>';
}
$html .= '<button class="btn send" type="submit">Create invite link</button> ';
$html .= '<button type="button" id="close-invite">Cancel</button></form></dialog></div>';

foreach ($data['users'] as $user) {
    $html .= '<article class="card"><h2>' . tylilo_e((string) $user['username']) . '</h2>';
    $html .= '<p>' . (tylilo_admin_is_owner($user) ? 'Owner' : 'Team member') . ((int) ($user['disabled'] ?? 0) === 1 ? ' · turned off' : '') . '</p>';
    $html .= '<form method="post" action="/admin/users.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="signout"><input type="hidden" name="id" value="' . (int) $user['id'] . '"><button type="submit">Sign out everywhere</button></form>';
    if (!tylilo_admin_is_owner($user)) {
        $html .= '<p><a href="/admin/notes.php?user=' . (int) $user['id'] . '">View notes</a> · <a href="/admin/todos.php?user=' . (int) $user['id'] . '">View to-dos</a></p>';
        $perms = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
        $html .= '<form method="post" action="/admin/users.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="permissions"><input type="hidden" name="id" value="' . (int) $user['id'] . '">';
        foreach (['perm_notes' => 'Notes', 'perm_feedback' => 'Feedback', 'perm_ideas' => 'Ideas', 'perm_leaderboards' => 'Leaderboards', 'perm_beta' => 'Beta games'] as $name => $label) {
            $key = str_replace('perm_', '', $name);
            $checked = !empty($perms[$key]) ? ' checked' : '';
            $html .= '<label><input type="checkbox" name="' . $name . '"' . $checked . '> ' . tylilo_e($label) . '</label>';
        }
        $html .= '<button type="submit">Save permissions</button></form>';
        $toggle = (int) ($user['disabled'] ?? 0) === 1 ? 'enable' : 'disable';
        $html .= '<form method="post" action="/admin/users.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="' . $toggle . '"><input type="hidden" name="id" value="' . (int) $user['id'] . '"><button type="submit">' . ($toggle === 'enable' ? 'Turn on' : 'Turn off') . '</button></form>';
        $html .= '<form method="post" action="/admin/users.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="' . (int) $user['id'] . '"><button type="submit">Remove</button></form>';
    }
    $html .= '</article>';
}
$html .= '<div class="card"><h2>Open invites</h2>';
$open = 0;
foreach ($data['invites'] as $invite) {
    if ((int) $invite['revoked'] === 1 || (int) $invite['used_at'] > 0) {
        continue;
    }
    $open++;
    $html .= '<p>Expires ' . tylilo_e(gmdate('Y-m-d', (int) $invite['expires_at'])) . '</p>';
    $html .= '<form method="post" action="/admin/users.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="' . (int) $invite['id'] . '"><button type="submit">Revoke invite</button></form>';
}
if ($open === 0) {
    $html .= '<p>No open invites.</p>';
}
$html .= '</div>';
$html .= '<script nonce="' . tylilo_e($cspNonce) . '">document.getElementById("open-invite").addEventListener("click",function(){document.getElementById("invite-dialog").showModal();});document.getElementById("close-invite").addEventListener("click",function(){document.getElementById("invite-dialog").close();});(function(){var b=document.getElementById("copy-invite"),i=document.getElementById("invite-link"),st=document.getElementById("copy-invite-status");if(!b||!i){return;}function done(ok){st.textContent=ok?"Copied to the clipboard.":"Could not copy. Select the link and copy it.";if(ok){b.textContent="Copied";setTimeout(function(){b.textContent="Copy link";},2500);}}i.addEventListener("focus",function(){i.select();});b.addEventListener("click",function(){if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(i.value).then(function(){done(true);},function(){i.select();done(document.execCommand&&document.execCommand("copy"));});}else{i.select();done(document.execCommand&&document.execCommand("copy"));}});})();</script>';
tylilo_admin_layout('Users', $html);

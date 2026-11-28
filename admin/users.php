<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$me = tylilo_admin_guard();
if (!tylilo_admin_is_owner($me)) {
    tylilo_admin_forbid();
}

$error = '';
$freshLink = '';
$freshKind = '';
$openId = 0;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!tylilo_admin_csrf_ok(tylilo_admin_field('csrf'))) {
        $error = 'That request was not accepted. Please try again.';
        $openId = (int) tylilo_admin_field('id');
    } else {
        $action = tylilo_admin_field('action');
        $id = (int) tylilo_admin_field('id');
        $openId = $id;
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
            $freshKind = 'invite';
        } elseif ($action === 'reset') {
            $token = tylilo_admin_issue_reset((int) $me['id'], $id);
            if (!is_string($token)) {
                $error = 'A reset link could not be created.';
            } else {
                $freshLink = TYLILO_URL . '/admin/reset.php?token=' . $token;
                $freshKind = 'reset';
            }
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
                $targetIsOwner = false;
                foreach ($data['users'] as $user) {
                    if ((int) ($user['id'] ?? 0) === $id && tylilo_admin_is_owner($user)) {
                        $targetIsOwner = true;
                    }
                }
                $data['users'] = array_values(array_filter($data['users'], static fn (array $user): bool => (int) $user['id'] !== $id || tylilo_admin_is_owner($user)));
                $data['notes'] = array_values(array_filter($data['notes'], static fn (array $row): bool => (int) ($row['user_id'] ?? 0) !== $id));
                $data['todos'] = array_values(array_filter($data['todos'], static fn (array $row): bool => (int) ($row['user_id'] ?? 0) !== $id));
                if (!$targetIsOwner) {
                    $data['password_resets'] = array_values(array_filter(tylilo_admin_reset_rows($data), static fn (array $row): bool => (int) ($row['user_id'] ?? 0) !== $id));
                }

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
            $to = '/admin/users.php';
            if ($action !== 'invite' && $action !== 'remove' && $id > 0) {
                $to .= '#user-' . $id;
            }
            header('Location: ' . $to, true, 303);
            exit;
        }
    }
}

$data = tylilo_admin_with(static fn (array $stored): array => $stored);
$html = '<div class="card"><h2>Users</h2>';
$html .= '<p>You are the owner. Invite links work once and expire after 7 days. Password reset links work once and expire after 24 hours. The site does not email them.</p>';
if ($error !== '') {
    $html .= '<div class="form-status error" role="alert"><p>' . tylilo_e($error) . '</p></div>';
}
if ($freshLink !== '') {
    $freshLabel = $freshKind === 'reset' ? 'Password reset link' : 'Invite link';
    $freshText = $freshKind === 'reset'
        ? 'Copy this password reset link now. It is not shown again. It works once and expires after 24 hours.'
        : 'Copy this invite link now. It is not shown again.';
    $html .= '<div class="form-status success" role="status"><p>' . tylilo_e($freshText) . '</p><p class="invite-copy"><label for="fresh-link" class="visually-hidden">' . tylilo_e($freshLabel) . '</label><input id="fresh-link" type="text" readonly value="' . tylilo_e($freshLink) . '"> <button type="button" class="btn send" id="copy-fresh">Copy link</button></p><p id="copy-fresh-status" role="status" aria-live="polite"></p></div>';
}
$html .= '<button type="button" class="btn send" id="open-invite">Invite someone</button>';
$html .= '<dialog id="invite-dialog" class="card" aria-labelledby="invite-title">';
$html .= '<h2 id="invite-title">Invite someone</h2>';
$html .= '<form method="post" action="/admin/users.php"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="invite">';
foreach (['perm_notes' => 'Own private notes and to-dos', 'perm_feedback' => 'Game feedback', 'perm_ideas' => 'Shared ideas board', 'perm_leaderboards' => 'Leaderboard moderation', 'perm_beta' => 'Play alpha and beta games', 'perm_arcade_publish' => 'Show, hide, or set the stage of pre-release games in the Arcade'] as $name => $label) {
    $checked = $name === 'perm_leaderboards' || $name === 'perm_beta' || $name === 'perm_arcade_publish' ? '' : ' checked';
    $html .= '<label><input type="checkbox" name="' . $name . '"' . $checked . '> ' . tylilo_e($label) . '</label>';
}
$html .= '<button class="btn send" type="submit">Create invite link</button> ';
$html .= '<button type="button" id="close-invite">Cancel</button></form></dialog></div>';

foreach ($data['users'] as $user) {
    $uid = (int) $user['id'];
    $role = tylilo_admin_is_owner($user) ? 'Owner' : 'Team member';
    $off = (int) ($user['disabled'] ?? 0) === 1;
    $status = $role . ($off ? ' · turned off' : '');
    $html .= '<article class="card user-card" id="user-' . $uid . '">';
    $html .= '<details' . ($openId === $uid ? ' open' : '') . '>';
    $html .= '<summary><h2>' . tylilo_e((string) $user['username']) . '</h2>';
    $html .= '<span class="user-status">' . tylilo_e($status) . '</span></summary>';
    $html .= '<div class="user-card-body">';
    $html .= '<form method="post" action="/admin/users.php#user-' . $uid . '"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="signout"><input type="hidden" name="id" value="' . $uid . '"><button type="submit">Sign out everywhere</button></form>';
    $html .= '<form method="post" action="/admin/users.php#user-' . $uid . '"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="' . $uid . '"><button type="submit">Reset password<span class="visually-hidden"> for ' . tylilo_e((string) $user['username']) . '</span></button></form>';
    if (!tylilo_admin_is_owner($user)) {
        $html .= '<p><a href="/admin/notes.php?user=' . $uid . '">View notes</a> · <a href="/admin/todos.php?user=' . $uid . '">View to-dos</a></p>';
        $perms = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
        $html .= '<form method="post" action="/admin/users.php#user-' . $uid . '"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="permissions"><input type="hidden" name="id" value="' . $uid . '">';
        foreach (['perm_notes' => 'Notes', 'perm_feedback' => 'Feedback', 'perm_ideas' => 'Ideas', 'perm_leaderboards' => 'Leaderboards', 'perm_beta' => 'Alpha/Beta games', 'perm_arcade_publish' => 'Arcade visibility'] as $name => $label) {
            $key = str_replace('perm_', '', $name);
            $checked = !empty($perms[$key]) ? ' checked' : '';
            $html .= '<label><input type="checkbox" name="' . $name . '"' . $checked . '> ' . tylilo_e($label) . '</label>';
        }
        $html .= '<button type="submit">Save permissions</button></form>';
        $toggle = $off ? 'enable' : 'disable';
        $html .= '<form method="post" action="/admin/users.php#user-' . $uid . '"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="' . $toggle . '"><input type="hidden" name="id" value="' . $uid . '"><button type="submit">' . ($toggle === 'enable' ? 'Turn on' : 'Turn off') . '</button></form>';
        $html .= '<form method="post" action="/admin/users.php#user-' . $uid . '"><input type="hidden" name="csrf" value="' . tylilo_e(tylilo_admin_csrf()) . '"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="' . $uid . '"><button type="submit">Remove</button></form>';
    }
    $html .= '</div></details></article>';
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
$html .= '<script nonce="' . tylilo_e($cspNonce) . '">document.getElementById("open-invite").addEventListener("click",function(){document.getElementById("invite-dialog").showModal();});document.getElementById("close-invite").addEventListener("click",function(){document.getElementById("invite-dialog").close();});(function(){var b=document.getElementById("copy-fresh"),i=document.getElementById("fresh-link"),st=document.getElementById("copy-fresh-status");if(!b||!i||!st){return;}function done(ok){st.textContent=ok?"Copied to the clipboard.":"Could not copy. Select the link and copy it.";if(ok){b.textContent="Copied";setTimeout(function(){b.textContent="Copy link";},2500);}}i.addEventListener("focus",function(){i.select();});b.addEventListener("click",function(){if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(i.value).then(function(){done(true);},function(){i.select();done(document.execCommand&&document.execCommand("copy"));});}else{i.select();done(document.execCommand&&document.execCommand("copy"));}});})();(function(){var id=location.hash;if(!id||id.indexOf("#user-")!==0){return;}var card=document.getElementById(id.slice(1));var panel=card&&card.querySelector("details");if(!panel){return;}panel.open=true;var summary=panel.querySelector("summary");if(summary){summary.focus();}})();</script>';
tylilo_admin_layout('Users', $html);

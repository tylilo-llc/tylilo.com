<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/inquiry.php';

/**
 * Private contact profiles.
 *
 * A profile is one person, keyed by their normalized email. Everything on
 * it comes from what that person submitted through the inquiry form, plus
 * Ryan's own notes and status. Nothing is fetched from anywhere else, and
 * nothing is tracked. Inquiries stay in inquiries.json. Notes and status
 * live in contacts.json, in the same private data folder.
 */

/** Profiles with no activity for this long are due for deletion. */
const TYLILO_CONTACT_RETENTION = 94694400; // 3 years (1096 days)

function tylilo_contact_statuses(): array
{
    return [
        'new' => 'New',
        'replied' => 'Replied',
        'call' => 'Call scheduled',
        'engaged' => 'Engaged',
        'not_fit' => 'Not a fit',
        'closed' => 'Closed',
    ];
}

function tylilo_contact_email_key(string $email): string
{
    return strtolower(trim($email));
}

function tylilo_contact_id(string $emailKey): string
{
    return substr(hash('sha256', 'tylilo-contact|' . tylilo_contact_email_key($emailKey)), 0, 24);
}

function tylilo_contact_store_path(): string
{
    $dir = tylilo_turnstile_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Contact storage is not available.');
    }

    return $dir . '/contacts.json';
}

/**
 * Locked read-modify-write of the contact notes and status store.
 *
 * @return array{contacts: array<string, array<string, mixed>>, purges: list<array{at: int, count: int}>}
 */
function tylilo_contacts_with(callable $change): array
{
    $path = tylilo_contact_store_path();
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Contact storage is not available.');
    }
    @chmod($path, 0600);
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Contact storage is not available.');
        }
        $raw = stream_get_contents($handle);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $data = [];
        }
        $data += ['contacts' => [], 'purges' => []];
        if (!is_array($data['contacts'])) {
            $data['contacts'] = [];
        }
        $data = $change($data);
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Contact storage is not available.');
        }
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $encoded);
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    return $data;
}

/**
 * @param list<array<string, mixed>> $inquiries newest first
 */
function tylilo_contact_pick(array $inquiries, string $key): string
{
    foreach ($inquiries as $row) {
        $a = is_array($row['answers'] ?? null) ? $row['answers'] : [];
        $v = $a[$key] ?? '';
        if (is_string($v) && trim($v) !== '') {
            return trim($v);
        }
    }

    return '';
}

/**
 * Every profile, built from the inquiries and the notes store. Newest
 * activity first.
 *
 * @return list<array<string, mixed>>
 */
function tylilo_contacts_all(?int $now = null): array
{
    $now ??= time();
    $meta = tylilo_contacts_with(static fn (array $d): array => $d)['contacts'];
    $groups = [];
    foreach (tylilo_inquiry_all() as $row) {
        $a = is_array($row['answers'] ?? null) ? $row['answers'] : [];
        $email = tylilo_contact_email_key((string) ($a['email'] ?? ''));
        if ($email === '') {
            continue;
        }
        $groups[$email][] = $row;
    }
    foreach ($meta as $id => $m) {
        if (!is_array($m)) {
            continue;
        }
        $email = tylilo_contact_email_key((string) ($m['email'] ?? ''));
        if ($email !== '' && !isset($groups[$email])) {
            $groups[$email] = [];
        }
    }

    $profiles = [];
    foreach ($groups as $email => $inquiries) {
        usort($inquiries, static fn (array $x, array $y): int => ((int) ($y['created_at'] ?? 0)) <=> ((int) ($x['created_at'] ?? 0)));
        $id = tylilo_contact_id($email);
        $m = is_array($meta[$id] ?? null) ? $meta[$id] : [];
        $notes = is_array($m['notes'] ?? null) ? $m['notes'] : [];
        usort($notes, static fn (array $x, array $y): int => [(int) ($y['at'] ?? 0), (int) ($y['id'] ?? 0)] <=> [(int) ($x['at'] ?? 0), (int) ($x['id'] ?? 0)]);
        $status = (string) ($m['status'] ?? 'new');
        if (!isset(tylilo_contact_statuses()[$status])) {
            $status = 'new';
        }
        $latest = $inquiries[0] ?? [];
        $activity = (int) ($m['created_at'] ?? 0);
        foreach ($inquiries as $row) {
            $activity = max($activity, (int) ($row['created_at'] ?? 0));
        }
        foreach ($notes as $note) {
            $activity = max($activity, (int) ($note['at'] ?? 0));
        }
        $activity = max($activity, (int) ($m['status_at'] ?? 0));
        $stage = tylilo_contact_pick($inquiries, 'stage');
        $size = tylilo_contact_pick($inquiries, 'size');
        $location = tylilo_contact_pick($inquiries, 'location');
        $profiles[] = [
            'id' => $id,
            'email' => $email,
            'name' => tylilo_contact_pick($inquiries, 'name'),
            'business' => tylilo_contact_pick($inquiries, 'business'),
            'website' => tylilo_contact_pick($inquiries, 'website'),
            'location' => $location !== '' ? tylilo_inquiry_label('location', $location) : '',
            'stage' => $stage !== '' ? tylilo_inquiry_label('stage', $stage) : '',
            'size' => $size !== '' ? tylilo_inquiry_label('size', $size) : '',
            'industry' => tylilo_contact_pick($inquiries, 'industry'),
            'fit' => (string) ($latest['fit'] ?? ''),
            'fit_reasons' => is_array($latest['fit_reasons'] ?? null) ? $latest['fit_reasons'] : [],
            'status' => $status,
            'status_at' => (int) ($m['status_at'] ?? 0),
            'notes' => $notes,
            'inquiries' => $inquiries,
            'first_contact' => $inquiries !== [] ? (int) ($inquiries[count($inquiries) - 1]['created_at'] ?? 0) : (int) ($m['created_at'] ?? 0),
            'last_activity' => $activity,
            'due' => $status !== 'engaged' && $activity > 0 && ($now - $activity) > TYLILO_CONTACT_RETENTION,
        ];
    }
    usort($profiles, static fn (array $x, array $y): int => $y['last_activity'] <=> $x['last_activity']);

    return $profiles;
}

/**
 * @return array<string, mixed>|null
 */
function tylilo_contact_get(string $id): ?array
{
    if (preg_match('/\A[a-f0-9]{24}\z/', $id) !== 1) {
        return null;
    }
    foreach (tylilo_contacts_all() as $profile) {
        if ($profile['id'] === $id) {
            return $profile;
        }
    }

    return null;
}

function tylilo_contact_id_for_email(string $email): string
{
    return tylilo_contact_id(tylilo_contact_email_key($email));
}

function tylilo_contact_ensure(array $data, string $emailKey, int $now): array
{
    $id = tylilo_contact_id($emailKey);
    if (!is_array($data['contacts'][$id] ?? null)) {
        $data['contacts'][$id] = ['email' => $emailKey, 'status' => 'new', 'status_at' => 0, 'notes' => [], 'created_at' => $now];
    }

    return $data;
}

function tylilo_contact_add_note(string $emailKey, string $text): bool
{
    $text = trim(str_replace("\r\n", "\n", $text));
    if ($text === '' || tylilo_inquiry_len($text) > 4000) {
        return false;
    }
    $emailKey = tylilo_contact_email_key($emailKey);
    $now = time();
    tylilo_contacts_with(static function (array $data) use ($emailKey, $text, $now): array {
        $data = tylilo_contact_ensure($data, $emailKey, $now);
        $id = tylilo_contact_id($emailKey);
        $max = 0;
        foreach ($data['contacts'][$id]['notes'] as $n) {
            $max = max($max, (int) ($n['id'] ?? 0));
        }
        $data['contacts'][$id]['notes'][] = ['id' => $max + 1, 'at' => $now, 'text' => $text];

        return $data;
    });

    return true;
}

function tylilo_contact_set_status(string $emailKey, string $status): bool
{
    if (!isset(tylilo_contact_statuses()[$status])) {
        return false;
    }
    $emailKey = tylilo_contact_email_key($emailKey);
    $now = time();
    tylilo_contacts_with(static function (array $data) use ($emailKey, $status, $now): array {
        $data = tylilo_contact_ensure($data, $emailKey, $now);
        $id = tylilo_contact_id($emailKey);
        if (($data['contacts'][$id]['status'] ?? '') !== $status) {
            $data['contacts'][$id]['status'] = $status;
            $data['contacts'][$id]['status_at'] = $now;
        }

        return $data;
    });

    return true;
}

/**
 * Remove a person: every inquiry with their email, their notes, and status.
 *
 * @return int number of inquiries removed
 */
function tylilo_contact_delete(string $emailKey): int
{
    $emailKey = tylilo_contact_email_key($emailKey);
    $removed = 0;
    tylilo_inquiry_with(static function (array $data) use ($emailKey, &$removed): array {
        $keep = [];
        foreach ($data['inquiries'] as $row) {
            $a = is_array($row['answers'] ?? null) ? $row['answers'] : [];
            if (tylilo_contact_email_key((string) ($a['email'] ?? '')) === $emailKey) {
                $removed++;
                continue;
            }
            $keep[] = $row;
        }
        $data['inquiries'] = $keep;

        return $data;
    });
    $id = tylilo_contact_id($emailKey);
    tylilo_contacts_with(static function (array $data) use ($id): array {
        unset($data['contacts'][$id]);

        return $data;
    });

    return $removed;
}

/**
 * Delete profiles with no activity for over three years. Engaged clients
 * are kept. Only a count is logged, never who.
 *
 * @return int profiles removed
 */
function tylilo_contacts_purge_expired(?int $now = null): int
{
    $now ??= time();
    $count = 0;
    foreach (tylilo_contacts_all($now) as $profile) {
        if (!empty($profile['due'])) {
            tylilo_contact_delete((string) $profile['email']);
            $count++;
        }
    }
    if ($count > 0) {
        tylilo_contacts_with(static function (array $data) use ($count, $now): array {
            $data['purges'][] = ['at' => $now, 'count' => $count];
            $data['purges'] = array_slice($data['purges'], -20);

            return $data;
        });
    }

    return $count;
}

function tylilo_contact_when(int $ts): string
{
    $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('America/New_York'));

    return $dt->format('M j, Y, g:i A') . ' ET';
}

/**
 * One person's data as plain text, for an access request.
 *
 * @param array<string, mixed> $p
 */
function tylilo_contact_export_text(array $p): string
{
    $statuses = tylilo_contact_statuses();
    $out = "Tylilo, LLC: record for " . ($p['name'] !== '' ? $p['name'] . ' ' : '') . '<' . $p['email'] . ">\n";
    $out .= "Exported " . tylilo_contact_when(time()) . "\n\n";
    $out .= "PROFILE\n";
    foreach (['name' => 'Name', 'email' => 'Email', 'business' => 'Business', 'website' => 'Website', 'location' => 'Location', 'stage' => 'Stage', 'size' => 'Team size', 'industry' => 'Industry'] as $k => $label) {
        $out .= $label . ': ' . ($p[$k] !== '' ? $p[$k] : '-') . "\n";
    }
    $out .= 'Status: ' . ($statuses[$p['status']] ?? 'New') . "\n";
    $out .= 'Latest suggested fit (private): ' . ($p['fit'] !== '' ? $p['fit'] : '-') . "\n\n";
    $out .= "NOTES\n";
    if ($p['notes'] === []) {
        $out .= "None.\n";
    }
    foreach ($p['notes'] as $n) {
        $out .= '[' . tylilo_contact_when((int) $n['at']) . '] ' . $n['text'] . "\n";
    }
    $out .= "\nINQUIRIES\n";
    foreach ($p['inquiries'] as $row) {
        $out .= "\n#" . (int) $row['id'] . ' · ' . tylilo_contact_when((int) $row['created_at']) . "\n";
        $a = is_array($row['answers'] ?? null) ? $row['answers'] : [];
        foreach (tylilo_inquiry_summary($a) as [$label, $value]) {
            $out .= $label . "\n" . $value . "\n\n";
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $p
 */
function tylilo_contact_export_json(array $p): string
{
    $statuses = tylilo_contact_statuses();
    $inquiries = [];
    foreach ($p['inquiries'] as $row) {
        $a = is_array($row['answers'] ?? null) ? $row['answers'] : [];
        $inquiries[] = [
            'id' => (int) $row['id'],
            'received' => gmdate('c', (int) $row['created_at']),
            'suggested_fit' => (string) ($row['fit'] ?? ''),
            'answers' => $a,
        ];
    }
    $notes = [];
    foreach ($p['notes'] as $n) {
        $notes[] = ['at' => gmdate('c', (int) $n['at']), 'text' => (string) $n['text']];
    }
    $data = [
        'exported' => gmdate('c'),
        'profile' => [
            'name' => $p['name'],
            'email' => $p['email'],
            'business' => $p['business'],
            'website' => $p['website'],
            'location' => $p['location'],
            'stage' => $p['stage'],
            'size' => $p['size'],
            'industry' => $p['industry'],
            'status' => $statuses[$p['status']] ?? 'New',
            'latest_suggested_fit' => $p['fit'],
        ],
        'notes' => $notes,
        'inquiries' => $inquiries,
    ];

    return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

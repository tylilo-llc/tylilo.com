<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/turnstile.php';
require_once __DIR__ . '/smtp.php';

/**
 * Consulting inquiry form: questions, validation, private fit, storage, email.
 * The suggested fit is for the admin only. It is never shown to the visitor
 * and never included in the visitor's copy of the email.
 */

const TYLILO_INQUIRY_OWNER_EMAIL = 'ryan@tylilo.com';
const TYLILO_INQUIRY_RATE_MAX = 3;
const TYLILO_INQUIRY_RATE_WINDOW = 3600;
const TYLILO_INQUIRY_DISCLAIMER = 'Tylilo, LLC provides general business consulting. We are not attorneys, accountants, or licensed financial, investment, or tax advisors, and nothing we provide is legal, financial, or tax advice. For those matters, please consult a qualified professional. Results are not guaranteed.';
const TYLILO_INQUIRY_THANKS = 'Thanks! I\'ll personally reply by email within one business day.';
const TYLILO_INQUIRY_REMOTE_NOTE = 'Everything we offer works remotely, wherever you are. At this time, in-person visits are available in East Tennessee.';

/**
 * Every question. type: radio, checkbox, text, textarea, email, url.
 * 'when' limits a question to some answers. Questions outside their
 * condition are dropped on the server even if a browser sends them.
 *
 * @return array<string, array<string, mixed>>
 */
function tylilo_inquiry_questions(): array
{
    return [
        'stage' => [
            'label' => 'Where are things today?',
            'type' => 'radio',
            'required' => true,
            'options' => [
                'idea' => 'Just an idea, not started yet',
                'first_year' => 'In my first year',
                'stuck' => 'Established but stuck',
                'growing' => 'Growing fast',
                'change' => 'Preparing for change or sale',
            ],
        ],
        'idea_what' => [
            'label' => 'What is the idea?',
            'type' => 'textarea',
            'max' => 1000,
            'when' => ['stage' => ['idea']],
        ],
        'idea_tested' => [
            'label' => 'Has it been tested with real customers yet?',
            'type' => 'radio',
            'options' => [
                'not_yet' => 'Not yet',
                'talked' => 'I have talked with possible customers',
                'early' => 'Some early sales or a pilot',
            ],
            'when' => ['stage' => ['idea']],
        ],
        'idea_launch' => [
            'label' => 'When would you like to launch?',
            'type' => 'radio',
            'options' => [
                'soon' => 'Within 3 months',
                'year' => 'In 3 to 12 months',
                'open' => 'No date yet',
            ],
            'when' => ['stage' => ['idea']],
        ],
        'goals' => [
            'label' => 'What would success look like?',
            'hint' => 'Choose all that apply.',
            'type' => 'checkbox',
            'required' => true,
            'options' => [
                'income' => 'More income',
                'time' => 'More time away from the business',
                'stability' => 'More stability',
                'growth' => 'Growth',
                'team' => 'A stronger team',
                'exit' => 'Succession or exit',
                'financing' => 'Ready for financing',
                'mission' => 'Serve our mission or community',
            ],
            'option_hints' => [
                'mission' => 'For charities, ministries, and nonprofits.',
            ],
        ],
        'time_hours' => [
            'label' => 'About how many hours a week do you work?',
            'type' => 'radio',
            'options' => [
                'under40' => 'Under 40',
                '40to55' => '40 to 55',
                '55to70' => '55 to 70',
                'over70' => 'Over 70',
            ],
            'when' => ['goals' => ['time']],
        ],
        'time_only_me' => [
            'label' => 'What can only you do right now?',
            'type' => 'textarea',
            'max' => 1000,
            'when' => ['goals' => ['time']],
        ],
        'growth_bottleneck' => [
            'label' => 'What limits how much more you can take on?',
            'type' => 'radio',
            'options' => [
                'leads' => 'Finding enough customers',
                'delivery' => 'Delivering the work',
                'people' => 'People and hiring',
                'cash' => 'Cash flow',
                'systems' => 'Systems and tools',
                'other' => 'Something else',
            ],
            'when' => ['goals' => ['growth']],
        ],
        'money_purpose' => [
            'label' => 'What is the financing or sale for?',
            'type' => 'textarea',
            'max' => 1000,
            'when' => ['goals' => ['financing', 'exit']],
        ],
        'money_timeline' => [
            'label' => 'What is the timeline?',
            'type' => 'radio',
            'options' => [
                'under6' => 'Under 6 months',
                '6to12' => '6 to 12 months',
                '1to3' => '1 to 3 years',
                'unsure' => 'Not sure yet',
            ],
            'when' => ['goals' => ['financing', 'exit']],
        ],
        'team_unclear' => [
            'label' => 'Where do things feel unclear?',
            'hint' => 'Choose all that apply.',
            'type' => 'checkbox',
            'options' => [
                'roles' => 'Who is responsible for what',
                'processes' => 'How the work gets done',
                'decisions' => 'Who makes which decisions',
                'handoffs' => 'Handoffs and communication',
            ],
            'when' => ['goals' => ['team']],
        ],
        'mission_focus' => [
            'label' => 'Where would help matter most?',
            'hint' => 'Choose all that apply.',
            'type' => 'checkbox',
            'options' => [
                'reach' => 'Reach',
                'people' => 'Volunteers and staff',
                'funding' => 'Funding and donors',
                'operations' => 'Operations',
            ],
            'when' => ['goals' => ['mission']],
        ],
        'obstacle' => [
            'label' => 'What is the biggest obstacle right now?',
            'type' => 'textarea',
            'max' => 1000,
            'when' => ['goals_none_of' => ['time', 'growth', 'financing', 'exit', 'team', 'mission']],
        ],
        'size' => [
            'label' => 'How many people work in it, including you?',
            'type' => 'radio',
            'required' => true,
            'options' => [
                'none' => 'Not yet',
                'solo' => 'Just me',
                '2to10' => '2 to 10',
                '11to25' => '11 to 25',
                '25plus' => 'More than 25',
            ],
        ],
        'industry' => [
            'label' => 'Industry or type of organization',
            'hint' => 'Optional. For example: landscaping, dental office, church.',
            'type' => 'text',
            'max' => 120,
        ],
        'timeline' => [
            'label' => 'When would you like to start?',
            'type' => 'radio',
            'required' => true,
            'options' => [
                'now' => 'Now',
                '3months' => 'Within 3 months',
                'exploring' => 'Just exploring',
            ],
        ],
        'help' => [
            'label' => 'What kind of help sounds right?',
            'type' => 'radio',
            'required' => true,
            'options' => [
                'diagnosis' => 'A one-time diagnosis',
                'alongside' => 'Work alongside me for a few months',
                'advisor' => 'An ongoing advisor',
                'unsure' => 'Not sure yet',
            ],
            'option_hints' => [
                'diagnosis' => 'A written 90-day plan.',
                'alongside' => 'One or two priorities over 8 to 12 weeks, with weekly check-ins, and processes and roles written down.',
                'advisor' => 'A monthly planning note, decision support by email, and a quarterly check-in.',
                'unsure' => 'Ryan will recommend one.',
            ],
        ],
        'location' => [
            'label' => 'Where are you located?',
            'hint' => TYLILO_INQUIRY_REMOTE_NOTE,
            'type' => 'radio',
            'required' => true,
            'options' => [
                'east_tn' => 'East Tennessee',
                'us' => 'Elsewhere in the US',
                'outside_us' => 'Outside the US',
                'prefer_not' => 'Prefer not to say',
            ],
        ],
        'name' => [
            'label' => 'Your name',
            'type' => 'text',
            'required' => true,
            'max' => 100,
            'autocomplete' => 'name',
        ],
        'business' => [
            'label' => 'Business or organization name',
            'hint' => 'Optional. Leave it blank if there is no name yet.',
            'type' => 'text',
            'max' => 120,
            'autocomplete' => 'organization',
        ],
        'email' => [
            'label' => 'Email',
            'type' => 'email',
            'required' => true,
            'max' => 254,
            'autocomplete' => 'email',
        ],
        'website' => [
            'label' => 'Website or social link',
            'hint' => 'Optional.',
            'type' => 'text',
            'max' => 300,
            'autocomplete' => 'url',
        ],
        'heard' => [
            'label' => 'How did you hear about us?',
            'hint' => 'Optional.',
            'type' => 'text',
            'max' => 200,
        ],
        'message' => [
            'label' => 'Anything else?',
            'hint' => 'Optional.',
            'type' => 'textarea',
            'max' => 2000,
        ],
    ];
}

/**
 * The steps, in order, with the questions on each.
 *
 * @return list<array{id: string, title: string, fields: list<string>}>
 */
function tylilo_inquiry_steps(): array
{
    return [
        ['id' => 'stage', 'title' => 'Where things stand', 'fields' => ['stage', 'idea_what', 'idea_tested', 'idea_launch']],
        ['id' => 'goals', 'title' => 'What success looks like', 'fields' => ['goals']],
        ['id' => 'detail', 'title' => 'A little more detail', 'fields' => ['time_hours', 'time_only_me', 'growth_bottleneck', 'money_purpose', 'money_timeline', 'team_unclear', 'mission_focus', 'obstacle']],
        ['id' => 'size', 'title' => 'Size', 'fields' => ['size', 'industry']],
        ['id' => 'timeline', 'title' => 'Timing', 'fields' => ['timeline']],
        ['id' => 'help', 'title' => 'Kind of help', 'fields' => ['help']],
        ['id' => 'location', 'title' => 'Location', 'fields' => ['location']],
        ['id' => 'contact', 'title' => 'How to reach you', 'fields' => ['name', 'business', 'email', 'website', 'heard', 'message']],
    ];
}

function tylilo_inquiry_clean_line(string $value): string
{
    $value = str_replace(["\r", "\n", "\t", "\0"], ' ', $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    $value = preg_replace('/\s{2,}/u', ' ', $value) ?? '';

    return trim($value);
}

function tylilo_inquiry_clean_text(string $value): string
{
    $value = str_replace("\0", '', $value);
    $value = preg_replace("/\r\n?/", "\n", $value) ?? '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? '';

    return trim($value);
}

function tylilo_inquiry_len(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    $count = preg_match_all('/./us', $value);

    return $count === false ? strlen($value) : $count;
}

/**
 * True when a question applies to these answers.
 *
 * @param array<string, mixed> $question
 * @param array<string, mixed> $answers
 */
function tylilo_inquiry_applies(array $question, array $answers): bool
{
    $when = $question['when'] ?? null;
    if (!is_array($when)) {
        return true;
    }
    $goals = is_array($answers['goals'] ?? null) ? $answers['goals'] : [];
    foreach ($when as $key => $values) {
        if ($key === 'goals_none_of') {
            if (array_intersect($goals, $values) !== []) {
                return false;
            }
            continue;
        }
        if ($key === 'goals') {
            if (array_intersect($goals, $values) === []) {
                return false;
            }
            continue;
        }
        $current = $answers[$key] ?? '';
        if (!is_string($current) || !in_array($current, $values, true)) {
            return false;
        }
    }

    return true;
}

/**
 * Validate a POST. Nothing outside the question list is kept.
 *
 * @return array{spam: bool, errors: array<string, string>, clean: array<string, mixed>}
 */
function tylilo_inquiry_validate(array $post, string $expectedCsrf): array
{
    $questions = tylilo_inquiry_questions();
    $clean = [];
    $errors = [];

    // Choice questions first, because they decide which follow-ups apply.
    foreach ($questions as $key => $q) {
        if ($q['type'] === 'radio') {
            $value = is_string($post[$key] ?? null) ? $post[$key] : '';
            $clean[$key] = array_key_exists($value, $q['options']) ? $value : '';
        } elseif ($q['type'] === 'checkbox') {
            $raw = is_array($post[$key] ?? null) ? $post[$key] : [];
            $picked = [];
            foreach (array_keys($q['options']) as $option) {
                if (in_array($option, $raw, true)) {
                    $picked[] = (string) $option;
                }
            }
            $clean[$key] = $picked;
        }
    }

    foreach ($questions as $key => $q) {
        if (in_array($q['type'], ['radio', 'checkbox'], true)) {
            continue;
        }
        $raw = is_string($post[$key] ?? null) ? $post[$key] : '';
        $clean[$key] = $q['type'] === 'textarea' ? tylilo_inquiry_clean_text($raw) : tylilo_inquiry_clean_line($raw);
    }

    foreach ($questions as $key => $q) {
        if (!tylilo_inquiry_applies($q, $clean)) {
            $clean[$key] = $q['type'] === 'checkbox' ? [] : '';
            continue;
        }
        $value = $clean[$key];
        $empty = $value === '' || $value === [];
        if (!empty($q['required']) && $empty) {
            $errors[$key] = match ($q['type']) {
                'checkbox' => 'Please choose at least one.',
                'radio' => 'Please choose one.',
                'email' => 'Please enter your email address.',
                default => 'Please fill this in.',
            };
            continue;
        }
        if (is_string($value) && isset($q['max']) && tylilo_inquiry_len($value) > (int) $q['max']) {
            $errors[$key] = 'Please keep this to ' . number_format((int) $q['max']) . ' characters or fewer.';
        }
    }

    if (!isset($errors['email']) && $clean['email'] !== '' && filter_var($clean['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'That email address does not look right.';
    }

    $clean['consent'] = isset($post['consent']) && $post['consent'] === 'yes';
    if (!$clean['consent']) {
        $errors['consent'] = 'Please confirm that you have read this.';
    }

    $honeypot = is_string($post['ty_extra'] ?? null) ? trim($post['ty_extra']) : '';
    if ($honeypot !== '') {
        return ['spam' => true, 'errors' => [], 'clean' => $clean];
    }

    $token = is_string($post['csrf'] ?? null) ? $post['csrf'] : '';
    if ($expectedCsrf === '' || !hash_equals($expectedCsrf, $token)) {
        $errors = ['form' => 'This page was open for a while, so we could not confirm the submission. Please try again.'] + $errors;
    }

    return ['spam' => false, 'errors' => $errors, 'clean' => $clean];
}

/**
 * Private suggestion for Ryan. Never shown to the person who sent it.
 *
 * @param array<string, mixed> $a
 * @return array{fit: string, reasons: list<string>}
 */
function tylilo_inquiry_fit(array $a): array
{
    $goals = is_array($a['goals'] ?? null) ? $a['goals'] : [];
    $stage = (string) ($a['stage'] ?? '');
    $help = (string) ($a['help'] ?? '');
    $size = (string) ($a['size'] ?? '');
    $timeline = (string) ($a['timeline'] ?? '');
    $tested = (string) ($a['idea_tested'] ?? '');

    if ($stage === 'idea' && ($tested === '' || $tested === 'not_yet')) {
        return [
            'fit' => 'Not a fit',
            'reasons' => [
                'The idea has not been tested with customers yet.',
                'Suggest the free help at the Small Business Development Center (SBDC) or SCORE first.',
            ],
        ];
    }

    $map = ['diagnosis' => 'Assessment', 'alongside' => 'Improvement', 'advisor' => 'Advisory'];
    if (isset($map[$help])) {
        return ['fit' => $map[$help], 'reasons' => ['They chose "' . tylilo_inquiry_label('help', $help) . '".']];
    }

    $reasons = ['They were not sure which kind of help.'];
    if ($stage === 'change' || array_intersect($goals, ['exit', 'financing']) !== []) {
        $reasons[] = 'Change, sale, or financing is on the table, which benefits from steady advice over time.';

        return ['fit' => 'Advisory', 'reasons' => $reasons];
    }
    $team = in_array($size, ['2to10', '11to25', '25plus'], true);
    if (($stage === 'growing' || array_intersect($goals, ['team', 'time', 'growth']) !== []) && $team && $timeline !== 'exploring') {
        $reasons[] = 'A team is in place and the goals are about capacity, time, or people.';

        return ['fit' => 'Improvement', 'reasons' => $reasons];
    }
    $reasons[] = 'A diagnosis and written plan is the usual starting point.';

    return ['fit' => 'Assessment', 'reasons' => $reasons];
}

function tylilo_inquiry_label(string $key, string $value): string
{
    $q = tylilo_inquiry_questions()[$key] ?? null;
    if (!is_array($q) || !isset($q['options'][$value])) {
        return $value;
    }

    return (string) $q['options'][$value];
}

/**
 * Answered questions as label => text, in form order.
 *
 * @param array<string, mixed> $answers
 * @return list<array{0: string, 1: string}>
 */
function tylilo_inquiry_summary(array $answers): array
{
    $rows = [];
    foreach (tylilo_inquiry_questions() as $key => $q) {
        $value = $answers[$key] ?? null;
        if ($q['type'] === 'checkbox') {
            if (!is_array($value) || $value === []) {
                continue;
            }
            $labels = array_map(static fn ($v): string => tylilo_inquiry_label($key, (string) $v), $value);
            $rows[] = [(string) $q['label'], implode('; ', $labels)];
        } elseif ($q['type'] === 'radio') {
            if (!is_string($value) || $value === '') {
                continue;
            }
            $rows[] = [(string) $q['label'], tylilo_inquiry_label($key, $value)];
        } else {
            if (!is_string($value) || $value === '') {
                continue;
            }
            $rows[] = [(string) $q['label'], $value];
        }
    }

    return $rows;
}

function tylilo_inquiry_store_path(): string
{
    $dir = tylilo_turnstile_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Inquiry storage is not available.');
    }

    return $dir . '/inquiries.json';
}

/**
 * Locked read-modify-write of the inquiry store.
 *
 * @return array{inquiries: list<array<string, mixed>>, rates: list<array{h: string, at: int}>}
 */
function tylilo_inquiry_with(callable $change): array
{
    $path = tylilo_inquiry_store_path();
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Inquiry storage is not available.');
    }
    @chmod($path, 0600);
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Inquiry storage is not available.');
        }
        $raw = stream_get_contents($handle);
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $data = [];
        }
        $data += ['inquiries' => [], 'rates' => []];
        $data = $change($data);
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Inquiry storage is not available.');
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
 * @return list<array<string, mixed>>
 */
function tylilo_inquiry_all(): array
{
    $data = tylilo_inquiry_with(static fn (array $d): array => $d);
    $rows = $data['inquiries'];
    usort($rows, static fn (array $x, array $y): int => ((int) ($y['created_at'] ?? 0)) <=> ((int) ($x['created_at'] ?? 0)));

    return $rows;
}

function tylilo_inquiry_delete(int $id): void
{
    tylilo_inquiry_with(static function (array $data) use ($id): array {
        $data['inquiries'] = array_values(array_filter(
            $data['inquiries'],
            static fn (array $row): bool => (int) ($row['id'] ?? 0) !== $id
        ));

        return $data;
    });
}

function tylilo_inquiry_ip_hash(string $ip): string
{
    $dir = tylilo_turnstile_dir();
    $saltFile = $dir . '/inquiry-ip.salt';
    $salt = is_file($saltFile) ? (string) file_get_contents($saltFile) : '';
    if (strlen($salt) < 32) {
        $salt = random_bytes(32);
        @file_put_contents($saltFile, $salt, LOCK_EX);
        @chmod($saltFile, 0600);
    }

    return hash_hmac('sha256', $ip, $salt);
}

/**
 * Store one inquiry if this address is under the hourly limit.
 * The raw address is never stored. A keyed hash is kept for one hour.
 *
 * @param array<string, mixed> $answers
 * @return array{ok: bool, id: int, limited: bool}
 */
function tylilo_inquiry_save(array $answers, array $fit, string $ip, bool $preview): array
{
    $hash = tylilo_inquiry_ip_hash($ip);
    $now = time();
    $result = ['ok' => false, 'id' => 0, 'limited' => false];
    tylilo_inquiry_with(static function (array $data) use ($answers, $fit, $hash, $now, $preview, &$result): array {
        $rates = [];
        $mine = 0;
        foreach ($data['rates'] as $rate) {
            if (!is_array($rate) || ($now - (int) ($rate['at'] ?? 0)) >= TYLILO_INQUIRY_RATE_WINDOW) {
                continue;
            }
            $rates[] = $rate;
            if (hash_equals((string) ($rate['h'] ?? ''), $hash)) {
                $mine++;
            }
        }
        if ($mine >= TYLILO_INQUIRY_RATE_MAX) {
            $data['rates'] = $rates;
            $result['limited'] = true;

            return $data;
        }
        $max = 0;
        foreach ($data['inquiries'] as $row) {
            $max = max($max, (int) ($row['id'] ?? 0));
        }
        $id = $max + 1;
        unset($answers['consent']);
        $data['inquiries'][] = [
            'id' => $id,
            'created_at' => $now,
            'answers' => $answers,
            'fit' => $fit['fit'],
            'fit_reasons' => $fit['reasons'],
            'preview' => $preview,
            'mail_visitor' => 'pending',
            'mail_owner' => 'pending',
        ];
        $rates[] = ['h' => $hash, 'at' => $now];
        $data['rates'] = $rates;
        $result = ['ok' => true, 'id' => $id, 'limited' => false];

        return $data;
    });

    return $result;
}

function tylilo_inquiry_mark_mail(int $id, bool $visitorOk, bool $ownerOk): void
{
    tylilo_inquiry_with(static function (array $data) use ($id, $visitorOk, $ownerOk): array {
        foreach ($data['inquiries'] as $i => $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                $data['inquiries'][$i]['mail_visitor'] = $visitorOk ? 'sent' : 'failed';
                $data['inquiries'][$i]['mail_owner'] = $ownerOk ? 'sent' : 'failed';
            }
        }

        return $data;
    });
}

function tylilo_inquiry_subject(string $subject, bool $preview): string
{
    return ($preview ? '[Preview] ' : '') . $subject;
}

/**
 * Plain text and HTML bodies for both emails.
 *
 * @param array<string, mixed> $answers
 * @return array{visitor_text: string, visitor_html: string, owner_text: string, owner_html: string}
 */
function tylilo_inquiry_bodies(array $answers, array $fit, int $id, bool $preview): array
{
    $rows = tylilo_inquiry_summary($answers);
    $name = (string) ($answers['name'] ?? '');
    $first = trim(explode(' ', $name)[0] ?? '');
    $greeting = $first !== '' ? 'Hi ' . $first . ',' : 'Hello,';

    $textRows = '';
    $htmlRows = '';
    foreach ($rows as [$label, $value]) {
        $textRows .= $label . "\n" . $value . "\n\n";
        $htmlRows .= '<p style="margin:0 0 16px"><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong><br>'
            . nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    $disclaimer = TYLILO_INQUIRY_DISCLAIMER;
    $wrap = static function (string $inner): string {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>'
            . '<body style="margin:0;padding:24px;background:#F4F6F8;color:#122033;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5">'
            . '<div style="max-width:600px;margin:0 auto;background:#FFFFFF;border:1px solid #D5DCE5;padding:24px">'
            . $inner
            . '</div></body></html>';
    };

    $previewNote = 'This is a test message from the private preview site, not tylilo.com.';
    $visitorText = ($preview ? '[Preview] ' . $previewNote . "\n\n" : '') . $greeting . "\n\n"
        . "Thank you for reaching out to Tylilo, LLC. I'll personally reply by email within one business day.\n\n"
        . "Here is a copy of what you sent:\n\n"
        . $textRows
        . "If anything changes, just reply to this email.\n\n"
        . "Ryan Thorpe\nTylilo, LLC\nhttps://tylilo.com\n\n"
        . "--\n" . $disclaimer . "\n";
    $visitorHtml = $wrap(
        ($preview ? '<p style="margin:0 0 16px;padding:8px 12px;background:#FFF4D6;font-size:14px"><strong>[Preview]</strong> ' . $previewNote . '</p>' : '')
        . '<p style="margin:0 0 16px">' . htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="margin:0 0 16px">Thank you for reaching out to Tylilo, LLC. I\'ll personally reply by email within one business day.</p>'
        . '<p style="margin:0 0 16px">Here is a copy of what you sent:</p>'
        . '<div style="border-left:3px solid #0A3161;padding-left:16px;margin:0 0 16px">' . $htmlRows . '</div>'
        . '<p style="margin:0 0 16px">If anything changes, just reply to this email.</p>'
        . '<p style="margin:0 0 24px">Ryan Thorpe<br>Tylilo, LLC<br><a href="https://tylilo.com" style="color:#0A3161">tylilo.com</a></p>'
        . '<p style="margin:0;font-size:13px;color:#4A5B70">' . htmlspecialchars($disclaimer, ENT_QUOTES, 'UTF-8') . '</p>'
    );

    $email = (string) ($answers['email'] ?? '');
    $fitText = $fit['fit'] . "\n- " . implode("\n- ", $fit['reasons']);
    $ownerText = "New consulting inquiry #" . $id . ($preview ? ' (sent from the preview site)' : '') . "\n\n"
        . "From: " . $name . " <" . $email . ">\n\n"
        . "Suggested fit (private): " . $fitText . "\n\n"
        . $textRows
        . "Reply to them at " . $email . ". Full details are in the admin under Inquiries.\n";
    $fitHtml = '<p style="margin:0 0 16px;padding:12px;background:#EEF2F7"><strong>Suggested fit (private):</strong> '
        . htmlspecialchars($fit['fit'], ENT_QUOTES, 'UTF-8') . '<br>'
        . implode('<br>', array_map(static fn (string $r): string => htmlspecialchars($r, ENT_QUOTES, 'UTF-8'), $fit['reasons'])) . '</p>';
    $ownerHtml = $wrap(
        '<p style="margin:0 0 16px"><strong>New consulting inquiry #' . $id . '</strong>' . ($preview ? ' (sent from the preview site)' : '') . '</p>'
        . '<p style="margin:0 0 16px">From ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        . ' &lt;<a href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '" style="color:#0A3161">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</a>&gt;</p>'
        . $fitHtml
        . $htmlRows
        . '<p style="margin:0">Full details are in the admin under Inquiries.</p>'
    );

    return [
        'visitor_text' => $visitorText,
        'visitor_html' => $visitorHtml,
        'owner_text' => $ownerText,
        'owner_html' => $ownerHtml,
    ];
}

/**
 * Send the visitor's copy and Ryan's notice.
 *
 * @param array<string, mixed> $answers
 * @return array{visitor: bool, owner: bool}
 */
function tylilo_inquiry_send(array $answers, array $fit, int $id, bool $preview): array
{
    $bodies = tylilo_inquiry_bodies($answers, $fit, $id, $preview);
    $visitor = tylilo_smtp_send(
        (string) $answers['email'],
        tylilo_inquiry_subject('Your inquiry with Tylilo, LLC', $preview),
        $bodies['visitor_text'],
        $bodies['visitor_html']
    );
    $who = tylilo_inquiry_clean_line((string) ($answers['name'] ?? ''));
    $owner = tylilo_smtp_send(
        TYLILO_INQUIRY_OWNER_EMAIL,
        tylilo_inquiry_subject('New inquiry #' . $id . ': ' . ($who !== '' ? $who : 'website visitor'), $preview),
        $bodies['owner_text'],
        $bodies['owner_html']
    );

    return ['visitor' => $visitor, 'owner' => $owner];
}

/**
 * Preview only: a one-time token the preview deploy writes to private
 * storage so its automated check can submit without solving Turnstile.
 * Never active on the live site. The token file is deleted when used.
 */
function tylilo_inquiry_selftest_ok(string $given): bool
{
    if (!function_exists('tylilo_is_preview') || !tylilo_is_preview()) {
        return false;
    }
    $given = trim($given);
    if (strlen($given) < 32 || strlen($given) > 128) {
        return false;
    }
    $ok = false;
    foreach (tylilo_private_file_candidates('inquiry-selftest.php') as $file) {
        if (!is_file($file)) {
            continue;
        }
        $fresh = (time() - (int) @filemtime($file)) < 1800;
        $value = include $file;
        if ($fresh && is_string($value) && strlen($value) >= 32 && hash_equals($value, $given)) {
            $ok = true;
        }
        @unlink($file);
    }

    return $ok;
}

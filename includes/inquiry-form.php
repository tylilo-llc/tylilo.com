<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/inquiry.php';

/**
 * The full inquiry form as one long page. inquiry.js turns it into one
 * step at a time. Without JavaScript every step shows and one Send button
 * submits it all.
 */

function tylilo_inquiry_when_note(array $when): string
{
    $parts = [];
    foreach ($when as $key => $values) {
        if ($key === 'goals_none_of') {
            $parts[] = 'If none of the goals above have their own questions here';
            continue;
        }
        if (!is_array($values) || $values === []) {
            continue;
        }
        $labels = array_map(static fn (string $v): string => '“' . tylilo_inquiry_label((string) $key, $v) . '”', $values);
        $parts[] = 'If you chose ' . implode(' or ', $labels);
    }
    if ($parts === []) {
        return '';
    }

    return implode(', and ', $parts) . ':';
}


/**
 * Questions rendered under a parent option, in form order.
 *
 * @return array<string, array<string, list<string>>>
 */
function tylilo_inquiry_under_children(): array
{
    $map = [];
    foreach (tylilo_inquiry_questions() as $key => $q) {
        $under = $q['under'] ?? null;
        if (!is_array($under) || !is_string($under['field'] ?? null) || $under['field'] === '') {
            continue;
        }
        $opts = $under['option'] ?? [];
        $opts = is_array($opts) ? $opts : [$opts];
        foreach ($opts as $opt) {
            if (!is_string($opt) && !is_int($opt)) {
                continue;
            }
            $map[$under['field']][(string) $opt][] = $key;
        }
    }

    return $map;
}

/**
 * True when this option is the last trigger for the child in the parent options list.
 *
 * @param array<string, mixed> $parent
 */
function tylilo_inquiry_under_here(array $parent, string $option, string $childKey): bool
{
    $child = tylilo_inquiry_questions()[$childKey] ?? null;
    if (!is_array($child) || !is_array($child['under'] ?? null)) {
        return false;
    }
    $opts = $child['under']['option'] ?? [];
    $opts = is_array($opts) ? $opts : [$opts];
    $opts = array_map('strval', $opts);
    if (!in_array($option, $opts, true)) {
        return false;
    }
    $last = null;
    foreach (array_keys($parent['options'] ?? []) as $value) {
        if (in_array((string) $value, $opts, true)) {
            $last = (string) $value;
        }
    }

    return $last === $option;
}

/**
 * @param array<string, mixed> $q
 * @param array<string, mixed> $old
 * @param array<string, string> $errors
 */
function tylilo_inquiry_field_html(string $key, array $q, array $old, array $errors): string
{
    $id = 'inq-' . str_replace('_', '-', $key);
    // A text follow-up such as goals_other would otherwise share
    // inq-goals-other with the parent option that reveals it.
    if (isset($q['under']) && ($q['type'] ?? '') !== 'radio' && ($q['type'] ?? '') !== 'checkbox') {
        $id .= '-text';
    }
    $required = !empty($q['required']);
    $error = $errors[$key] ?? '';
    $hint = (string) ($q['hint'] ?? '');
    $describedby = [];
    if ($hint !== '') {
        $describedby[] = $id . '-hint';
    }
    if ($error !== '') {
        $describedby[] = $id . '-error';
    }
    $req = $required ? ' <span class="inq-req">(required)</span>' : '';
    $html = '';

    if ($q['type'] === 'radio' || $q['type'] === 'checkbox') {
        $html .= '<fieldset class="inq-q" id="' . $id . '" data-field="' . tylilo_e($key) . '"'
            . ($required ? ' data-required="1"' : '')
            . ($describedby !== [] ? ' aria-describedby="' . implode(' ', $describedby) . '"' : '') . '>';
        $html .= '<legend class="inq-label">' . tylilo_e((string) $q['label']) . $req . '</legend>';
        if ($hint !== '') {
            $html .= '<p class="inq-hint" id="' . $id . '-hint">' . tylilo_e($hint) . '</p>';
        }
        if ($error !== '') {
            $html .= '<p class="field-error" id="' . $id . '-error">' . tylilo_e($error) . '</p>';
        }
        $html .= '<div class="inq-options">';
        $current = $old[$key] ?? ($q['type'] === 'checkbox' ? [] : '');
        $children = tylilo_inquiry_under_children()[$key] ?? [];
        $renderedReveal = [];
        foreach ($q['options'] as $value => $label) {
            $value = (string) $value;
            $optId = $id . '-' . str_replace('_', '-', $value);
            $checked = $q['type'] === 'checkbox'
                ? (is_array($current) && in_array($value, $current, true))
                : $current === $value;
            $optHint = (string) ($q['option_hints'][$value] ?? '');
            $name = $q['type'] === 'checkbox' ? $key . '[]' : $key;
            $revealIds = [];
            foreach ($children[$value] ?? [] as $childKey) {
                $revealIds[] = 'inq-reveal-' . str_replace('_', '-', $childKey);
            }
            $controls = $revealIds !== [] ? ' aria-controls="' . implode(' ', array_unique($revealIds)) . '"' : '';
            $html .= '<div class="inq-option-block">';
            $html .= '<label class="inq-option" for="' . $optId . '">'
                . '<input type="' . $q['type'] . '" id="' . $optId . '" name="' . tylilo_e($name) . '" value="' . tylilo_e($value) . '"'
                . ($checked ? ' checked' : '')
                . ($required && $q['type'] === 'radio' && empty($q['when']) ? ' required' : '')
                . ($error !== '' ? ' aria-invalid="true"' : '')
                . $controls
                . ($optHint !== '' ? ' aria-describedby="' . $optId . '-hint"' : '')
                . '>'
                . '<span class="inq-option-text"><span class="inq-option-label">' . tylilo_e((string) $label) . '</span>'
                . ($optHint !== '' ? '<span class="inq-option-hint" id="' . $optId . '-hint">' . tylilo_e($optHint) . '</span>' : '')
                . '</span></label>';
            foreach ($children[$value] ?? [] as $childKey) {
                if (isset($renderedReveal[$childKey]) || !tylilo_inquiry_under_here($q, $value, $childKey)) {
                    continue;
                }
                $renderedReveal[$childKey] = true;
                $child = tylilo_inquiry_questions()[$childKey];
                $when = is_array($child['when'] ?? null) ? $child['when'] : [];
                $revealId = 'inq-reveal-' . str_replace('_', '-', $childKey);
                $html .= '<div class="inq-reveal" id="' . $revealId . '" data-reveal-when="' . tylilo_e((string) json_encode($when, JSON_UNESCAPED_SLASHES)) . '">';
                $note = tylilo_inquiry_when_note($when);
                if ($note !== '') {
                    $html .= '<p class="inq-when">' . tylilo_e($note) . '</p>';
                }
                $html .= tylilo_inquiry_field_html($childKey, $child, $old, $errors);
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div></fieldset>';

        return $html;
    }

    $value = is_string($old[$key] ?? null) ? $old[$key] : '';
    $attrs = ' id="' . $id . '" name="' . tylilo_e($key) . '"';
    if (isset($q['max'])) {
        $attrs .= ' maxlength="' . (int) $q['max'] . '"';
    }
    // Conditional questions get a required check in JS and on the server.
    // A static required attribute would block a no-JS submit when the
    // follow-up does not apply.
    if ($required && empty($q['when'])) {
        $attrs .= ' required aria-required="true"';
    }
    if (isset($q['autocomplete'])) {
        $attrs .= ' autocomplete="' . tylilo_e((string) $q['autocomplete']) . '"';
    }
    if ($describedby !== []) {
        $attrs .= ' aria-describedby="' . implode(' ', $describedby) . '"';
    }
    if ($error !== '') {
        $attrs .= ' aria-invalid="true"';
    }
    $html .= '<div class="inq-q" data-field="' . tylilo_e($key) . '"' . ($required ? ' data-required="1"' : '') . '>';
    $html .= '<label class="inq-label" for="' . $id . '">' . tylilo_e((string) $q['label']) . $req . '</label>';
    if ($hint !== '') {
        $html .= '<p class="inq-hint" id="' . $id . '-hint">' . tylilo_e($hint) . '</p>';
    }
    if ($error !== '') {
        $html .= '<p class="field-error" id="' . $id . '-error">' . tylilo_e($error) . '</p>';
    }
    if ($q['type'] === 'textarea') {
        $html .= '<textarea' . $attrs . ' rows="4">' . tylilo_e($value) . '</textarea>';
    } else {
        $type = $q['type'] === 'email' ? 'email' : 'text';
        $extra = $type === 'email' ? ' inputmode="email" spellcheck="false"' : '';
        if ($key === 'website') {
            $extra = ' inputmode="url" spellcheck="false"';
        }
        $html .= '<input type="' . $type . '"' . $attrs . $extra . ' value="' . tylilo_e($value) . '">';
    }
    if ($key === 'email') {
        $html .= '<p class="inq-hint inq-privacy">We use your email only to reply and to send you a copy of your answers. See the <a href="/privacy#inquiries" target="_blank" rel="noopener">privacy policy<span class="visually-hidden"> (opens in a new window)</span></a>.</p>';
    }
    $html .= '</div>';

    return $html;
}

/**
 * @param array<string, mixed> $old
 * @param array<string, string> $errors
 */
function tylilo_inquiry_form_html(array $old, array $errors, string $csrf, string $sitekey): string
{
    $questions = tylilo_inquiry_questions();
    $steps = tylilo_inquiry_steps();
    $total = count($steps);
    $html = '<form class="inq-form" action="/inquiry" method="post" accept-charset="UTF-8" autocomplete="on" data-inquiry>';
    $html .= '<div class="hp" aria-hidden="true"><label for="inq-extra">Leave this field empty</label>'
        . '<input id="inq-extra" type="text" name="ty_extra" tabindex="-1" autocomplete="off" value=""></div>';
    $html .= '<input type="hidden" name="csrf" value="' . tylilo_e($csrf) . '">';

    foreach ($steps as $index => $step) {
        $n = $index + 1;
        $titleId = 'inq-step-' . $step['id'] . '-title';
        $html .= '<section class="inq-step" id="inq-step-' . tylilo_e($step['id']) . '" data-step="' . $n . '" aria-labelledby="' . $titleId . '">';
        $html .= '<p class="inq-count">Step ' . $n . ' of ' . $total . '</p>';
        $html .= '<h2 class="inq-step-title" id="' . $titleId . '" tabindex="-1">' . tylilo_e($step['title']) . '</h2>';

        $group = null;
        $groupWhen = null;
        foreach ($step['fields'] as $key) {
            $q = $questions[$key];
            if (isset($q['under'])) {
                continue;
            }
            $when = $q['when'] ?? null;
            $whenJson = $when === null ? null : json_encode($when, JSON_UNESCAPED_SLASHES);
            if ($whenJson !== $groupWhen) {
                if ($group !== null) {
                    $html .= '</div>';
                    $group = null;
                }
                if ($whenJson !== null) {
                    $html .= '<div class="inq-branch" data-when="' . tylilo_e($whenJson) . '">';
                    $html .= '<p class="inq-when">' . tylilo_e(tylilo_inquiry_when_note($when)) . '</p>';
                    $group = $whenJson;
                }
                $groupWhen = $whenJson;
            }
            $html .= tylilo_inquiry_field_html($key, $q, $old, $errors);
        }
        if ($group !== null) {
            $html .= '</div>';
        }

        if ($n === $total) {
            $consentError = $errors['consent'] ?? '';
            $html .= '<div class="inq-consent">';
            $html .= '<p class="inq-retain" id="inq-retain">We keep your answers so we can follow up and understand your business. You can ask us to see or delete them at any time. <a href="/privacy#inquiries" target="_blank" rel="noopener">Read how we keep them<span class="visually-hidden"> (opens in a new window)</span></a>.</p>';
            $html .= '<p class="inq-disclaimer" id="inq-disclaimer">' . tylilo_e(TYLILO_INQUIRY_DISCLAIMER) . '</p>';
            if ($consentError !== '') {
                $html .= '<p class="field-error" id="inq-consent-error">' . tylilo_e($consentError) . '</p>';
            }
            $html .= '<label class="inq-check" for="inq-consent"><input type="checkbox" id="inq-consent" name="consent" value="yes" required'
                . (!empty($old['consent']) ? ' checked' : '')
                . ' aria-describedby="inq-disclaimer' . ($consentError !== '' ? ' inq-consent-error' : '') . '"'
                . ($consentError !== '' ? ' aria-invalid="true"' : '')
                . '><span>I have read and understand this. <span class="inq-req">(required)</span></span></label>';
            $html .= '</div>';
            $html .= '<div class="turnstile-slot"><div class="cf-turnstile" data-contact-turnstile data-sitekey="' . tylilo_e($sitekey)
                . '" data-action="inquiry" data-appearance="interaction-only" data-size="flexible" data-theme="auto"></div></div>';
            $html .= '<div class="inq-actions inq-submit-row"><button class="btn send" type="submit" data-inq-submit>Send inquiry</button></div>';
        }
        $html .= '</section>';
    }
    $html .= '</form>';

    return $html;
}

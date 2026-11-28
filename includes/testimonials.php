<?php

declare(strict_types=1);

if (!defined('TYLILO_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

/**
 * Quotes shown on the homepage.
 *
 * Leave this empty until someone gives Tylilo a real quote. Each entry is
 * an array with quote, name, and an optional role:
 *
 *     ['quote' => 'What they said.', 'name' => 'Their name', 'role' => 'Optional role']
 *
 * Do not add a quote, a name, or a role that was not actually given.
 *
 * @return list<array{quote: string, name: string, role?: string}>
 */
function tylilo_testimonials(): array
{
    return [];
}

/**
 * HTML for the testimonial list. An empty list, or entries missing a quote
 * or a name, renders the empty state and no quotation.
 */
function tylilo_testimonials_html(array $items): string
{
    $cards = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $quote = trim((string) ($item['quote'] ?? ''));
        $name = trim((string) ($item['name'] ?? ''));
        if ($quote === '' || $name === '') {
            continue;
        }
        $role = trim((string) ($item['role'] ?? ''));
        $byline = tylilo_e($name);
        if ($role !== '') {
            $byline .= ', ' . tylilo_e($role);
        }
        $cards[] = '<li><blockquote><p>' . tylilo_e($quote) . '</p><footer>' . $byline . '</footer></blockquote></li>';
    }

    if ($cards === []) {
        return '<p class="testimonials-empty">No testimonials yet.</p>';
    }

    return '<ul class="testimonials">' . implode('', $cards) . '</ul>';
}

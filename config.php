<?php
/**
 * Tylilo site settings.
 *
 * Loaded by the pages. Not a public URL (see .htaccess).
 */

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

/**
 * ============================================================
 *  CONTACT EMAIL — change this one line
 * ============================================================
 * This is the only place the address is defined. It is the mailbox
 * that receives the contact form. It is not shown on the site.
 * hello@tylilo.com is a placeholder until the real mailbox exists.
 * ============================================================
 */
const TYLILO_CONTACT_EMAIL = 'hello@tylilo.com';

/**
 * Public Turnstile sitekey for the contact form. The secret is not here.
 */
const TYLILO_TURNSTILE_SITEKEY = '0x4AAAAAAGLxGfk9two8K9sp';

const TYLILO_SITE_NAME = 'Tylilo';
const TYLILO_LEGAL_NAME = 'Tylilo, LLC';
const TYLILO_YEAR = '2026';
const TYLILO_URL = 'https://tylilo.com';

/**
 * OTRadio stays in otradio/ either way. false hides /otradio/ behind the
 * normal 404 page and drops it from the footer. The script, stylesheet,
 * and fonts stay on disk. Set this to true and add the URL back to
 * sitemap.xml when the player should be public again.
 */
const TYLILO_OTRADIO_ENABLED = false;

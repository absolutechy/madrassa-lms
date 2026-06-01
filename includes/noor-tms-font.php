<?php
/**
 * Site-wide Urdu font (Jameel Noori Nastaleeq Kasheeda).
 *
 * The @font-face family name must match CSS font-family exactly.
 * Spaced names like "Jameel Noori Nastaleeq Kasheeda" will NOT work unless
 * the CDN defines them that way — the bundled face uses JameelNooriNastaliqKashida.
 *
 * @package Noor_TMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exact font-family string from @font-face (no spaces).
 */
function noor_tms_font_family(): string {
	return 'JameelNooriNastaliqKashida';
}

/**
 * Enqueue Kasheeda webfont and apply it site-wide (front + admin).
 *
 * Loads late so theme styles do not override the font.
 */
function noor_tms_enqueue_font(): void {
	static $enqueued = false;
	if ( $enqueued ) {
		return;
	}
	$enqueued = true;

	$handle = 'noor-tms-jameel-noori-kasheeda';
	$family = noor_tms_font_family();

	// Self-contained @font-face (no external stylesheet body rules).
	wp_register_style( $handle, false, [], NOOR_TMS_VERSION );
	wp_enqueue_style( $handle );

	$woff2 = 'https://cdn.jsdelivr.net/npm/jameel-noori-nastaliq-kasheeda@1.1.0/fonts/JameelNooriNastaliqKasheeda3.woff2';

	$css = "@font-face {
		font-family: \"{$family}\";
		font-style: normal;
		font-weight: 400;
		font-display: swap;
		src: url(\"{$woff2}\") format(\"woff2\");
	}
	:root {
		--tms-font-family: \"{$family}\", \"Jameel Noori Nastaleeq Kasheeda\", serif;
	}
	html,
	body,
	.noor-tms-app,
	.noor-tms-login-wrap,
	#wpwrap,
	#wpbody-content {
		font-family: var(--tms-font-family) !important;
	}
	.noor-tms-app *,
	.noor-tms-login-wrap * {
		font-family: inherit;
	}
	.dashicons,
	.dashicons:before,
	[class*=\"dashicons-\"]:before,
	.fa,
	[class*=\"fa-\"] {
		font-family: dashicons, \"Font Awesome 6 Free\", \"Font Awesome 5 Free\" !important;
	}
	input,
	textarea,
	select,
	button {
		font-family: inherit !important;
	}";

	wp_add_inline_style( $handle, $css );
}

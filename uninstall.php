<?php
/**
 * Uninstall routine: clean up plugin options and transient data.
 *
 * @package Botelix
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove this plugin's options from the current site.
 */
function botelix_uninstall_site(): void {
	delete_option( 'botelix_settings' );
}

// Options are per site, so a network uninstall has to visit every site.
if ( is_multisite() ) {
	$botelix_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $botelix_site_ids as $botelix_site_id ) {
		switch_to_blog( (int) $botelix_site_id );
		botelix_uninstall_site();
		restore_current_blog();
	}
	unset( $botelix_site_ids, $botelix_site_id );
} else {
	botelix_uninstall_site();
}

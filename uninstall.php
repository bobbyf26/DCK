<?php
/**
 * Uninstall cleanup. Only runs when the plugin is deleted from wp-admin.
 * Conservative: removes the custom role and cached page lookups but LEAVES
 * contractor content in place so listings are not lost on accidental delete.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

remove_role( 'dck_contractor' );

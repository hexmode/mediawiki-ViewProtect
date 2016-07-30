<?php
/**
 * Hooks for ViewProtect extension
 *
 * @file
 * @ingroup Extensions
 */

class ViewProtectHooks {
	/**
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		// Always return true, indicating that parser initialization should
		// continue normally.
		return true;
	}

	/**
	 * This registers our database schema update(s)
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'viewprotect', __DIR__ . '/sql/add-viewprotect.sql' );

		return true;
	}

}

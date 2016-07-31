<?php
/**
 * Hooks for ViewProtect extension
 *
 * @file
 * @ingroup Extensions
 */

class ViewProtectHooks {
	static public function onCoPPageGroups(
		Title $title, $readGroup, $editGroup, $copOnly = false
	) {
		if ( !$copOnly ) {
			if ( $editGroup === null ) {
				$editGroup = $readGroup;
			}
			ViewProtect::setPageProtection( $title, 'read', $readGroup );
			ViewProtect::setPageProtection( $title, 'edit', $editGroup );
		} else {
			ViewProtect::setPageProtection( $title, 'read', 'employees-only' );
			ViewProtect::setPageProtection( $title, 'edit', 'employees-only' );
		}
	}

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
		$updater->addExtensionTable( 'viewprotect', __DIR__ . '/../sql/add-viewprotect.sql' );

		return true;
	}

	/**
	 * This checks the permissions to see if they're allowed.
	 */
	static public function onGetUserPermissionsErrors(
		Title $title, User $user, $action, &$result
	) {
		$available = array(		// For later reference, these are the
								// possible actions
			"read", "edit", "patrol", "deletedhistory",
			"delete", "move", "protect",
		);
		$result = ViewProtect::checkPermission( $title, $user, $action );
		wfDebugLog( __METHOD__, "Result of $title/$user/$action: " . var_export( $result, true ) );

		return true;
	}
}

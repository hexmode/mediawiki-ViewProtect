<?php
/**
 * ViewProtect extension
 *
 * @file
 * @ingroup Extensions
 */

class ViewProtect {
	static protected $cache = null;

	static public function checkPermission( Title $title, User $user, $action ) {
		if ( self::userIsEditor( $user ) ) {
			return [];
		}
		$allowedGroups = self::getPageProtections( $title, $action );
		wfDebugLog( __METHOD__, "Checking for $user/$title/$action ..." );
		if ( count( $allowedGroups ) > 0 ) {
			$groupList = [];

			foreach ( $allowedGroups as $group ) {
				if ( self::inGroup( $user, $group ) ) {
					wfDebugLog( __METHOD__, "Result for $user/$title/$action: ok" );
					return [];
				}
				$groupList[] = $group;
			}
			wfDebugLog( __METHOD__, "Result for $user/$title/$action: no" );
			return [ [ "viewprotect-denied", $groupList ] ];
		}
		wfDebugLog( __METHOD__, "Result for $user/$title/$action: ok" );
		return [];
	}

	static public function setPageProtection( Title $title, $action, $group ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'viewprotect',
					  [ 'viewprotect_page' => $title->getArticleID(),
						'viewprotect_group' => $group,
						'viewprotect_permission' => $action ],
					  __METHOD__, [ 'IGNORE' ] );
	}

	static protected function getPageProtections( Title $title, $action ) {
		wfDebugLog( __METHOD__, "Checking $action for $title" );
		$dbkey = $title->getArticleID();
		if ( self::$cache === null || !isset( self::$cache[ $dbkey ] ) ) {
			$dbr = wfGetDB( DB_MASTER );
			$res = $dbr->select( 'viewprotect',
								 [ 'viewprotect_group', 'viewprotect_permission' ],
								 [ 'viewprotect_page' => $dbkey ],
								 __METHOD__ );
			self::$cache[$dbkey] = [];
			foreach ( $res as $row ) {
				self::$cache[$dbkey][$row->viewprotect_permission][$row->viewprotect_group] = 1;
			}
		}

		if ( !isset( self::$cache[$dbkey][$action] ) ) {
			return [];
		}
		return array_keys( self::$cache[$dbkey][$action] );
	}

	static protected function inGroup( User $user, $group ) {
		if ( $group === 'employees-only' ) {
			return in_array( 'employee', $user->getGroups() );
		}

		wfDebugLog( __METHOD__, "Checking if $user is in $group ..." );
		// Ugh, this should be in my group management extension
		global $wgAuth;
		if ( !method_exists( $wgAuth, "isInCoPGroup" ) ) {
			throw new MWException(
				"Expected isInCoPGroup method for \$wgAuth, " .
				"but none was found.  " .
				"Is this a CoP-modified Auth_remoteuser?" );
		}
		$r = $wgAuth->isInCoPGroup( $group, $user );
		wfDebugLog( __METHOD__, "$user is in $group: " . ( $r ? "yes" : "no" ) );
		return $r;
	}

	static protected function userIsEditor( User $user ) {
		return in_array( 'Editor', $user->getGroups() );
	}
}

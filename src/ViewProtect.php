<?php
/**
 * ViewProtect extension
 *
 * Copyright (C) 2017  Mark A. Hershberger
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup Extensions
 */

namespace ViewProtect;

use ConfigFactory;
use Title;
use User;

class ViewProtect {
	static protected $cache = null;
	static protected $pagePermissionWriteCache = [];

	/**
	 *
	 */
	public static function clearPagePermissions( $title ) {
		if ( is_object( $title ) ) {
			$title = [ $title->getArticleId() ];
		}

		$dbw = wfGetDB( DB_MASTER );
		foreach ( (array)$title as $page ) {
			$dbw->delete( 'viewprotect',
						  [ 'viewprotect_page' => $page ],
						  __METHOD__ );
		}
	}

	/**
	 *
	 */
	public static function checkPermission(
		Title $title, User $user, $action
	) {
		if ( self::userIsVIP( $user ) ) {
			return [];
		}
		$allowedGroups = self::getPageProtections( $title, $action );
		wfDebugLog( __METHOD__, "Checking for $user/$title/$action ..." );
		if ( count( $allowedGroups ) > 0 ) {
			$groupList = [];

			foreach ( $allowedGroups as $group ) {
				if ( self::inGroup( $user, $group ) ) {
					wfDebugLog( __METHOD__,
								"Result for $user/$title/$action: ok" );
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

	/**
	 *
	 */
	public static function setPageProtection( Title $title, $action, $group ) {
		self::$pagePermissionWriteCache[$title->getArticleID()][$action][$group] = 1;
	}

	/**
	 *
	 */
	public static function flushPageProtections() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );
		self::clearPagePermissions( array_keys( self::$pagePermissionWriteCache ) );
		foreach ( self::$pagePermissionWriteCache as $pageId => $actionGroup ) {
			foreach ( $actionGroup as $action => $group ) {
				if ( is_array( $group ) ) {
					$group = $dbw->makeList( $group );
				}
				$dbw->insert( 'viewprotect',
							  [ 'viewprotect_page' => $pageId,
								'viewprotect_permission' => $action,
								'viewprotect_group' => $group ],
							  __METHOD__ );
			}
		}
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 *
	 */
	protected static function getPageProtections( Title $title, $action ) {
		wfDebugLog( __METHOD__, "Checking $action for $title" );
		$dbkey = $title->getArticleID();
		if ( $dbkey !== 0 && ( self::$cache === null || !isset( self::$cache[ $dbkey ] ) ) ) {
			$dbr = wfGetDB( DB_MASTER );
			$res = $dbr->select( 'viewprotect',
								 [ 'viewprotect_group',
								   'viewprotect_permission' ],
								 [ 'viewprotect_page' => $dbkey ],
								 __METHOD__ );
			self::$cache[$dbkey] = [];
			foreach ( $res as $row ) {
				$perm = $row->viewprotect_permission;
				$group = $row->viewprotect_group;
				self::$cache[$dbkey][$perm][$group] = 1;
			}
		}

		if ( !isset( self::$cache[$dbkey][$action] ) ) {
			return [];
		}
		return array_keys( self::$cache[$dbkey][$action] );
	}

	/**
	 *
	 */
	protected static function inGroup( User $user, $group ) {
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
		$result = $wgAuth->isInCoPGroup( $group, $user );
		wfDebugLog( __METHOD__, "$user is in $group: " .
					( $result ? "yes" : "no" ) );
		return $result;
	}

	/**
	 *
	 */
	protected static function userIsVIP( User $user ) {
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'main' );
		return in_array( $config->get( 'VIPUserGroup' ), $user->getGroups() );
	}
}

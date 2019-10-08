<?php
/**
 * ViewProtect extension
 *
 * Copyright (C) 2017, 2019  NicheWork, LLC
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

namespace MediaWiki\Extension\ViewProtect;

use ConfigFactory;
use ManualLogEntry;
use Title;
use User;

class ViewProtect {
	/** @param ?array */
	static protected $cache;
	/** @param array */
	static protected $pagePermissionWriteCache = [];

	/**
	 * See if user has access to perform a given action on a page
	 *
	 * @param Title $title title being checked
	 * @param User $user to check
	 * @param string $action being checked
	 * @return true|array true if allowed, otherwise a list of groups that have access
	 */
	public static function hasPermission(
		Title $title, User $user, $action
	) {
		if ( $user->isAllowed( "viewprotectmanage" ) ) {
			return true;
		}
		$allowedGroups = self::getPageRestrictions( $title, $action );
		wfDebugLog( __METHOD__, "Checking for $user/$title/$action ..." );
		$groupList = [];
		if ( count( $allowedGroups ) === 0 ) {
			wfDebugLog( __METHOD__,
						"Result for $user/$title/$action: everyone allowed" );
			return true;
		}

		foreach ( $allowedGroups as $group ) {
			if ( self::inGroup( $user, $group ) ) {
				wfDebugLog( __METHOD__,
							"Result for $user/$title/$action: ok" );
				return true;
			}
			$groupList[] = $group;
		}
		wfDebugLog( __METHOD__, "Result for $user/$title/$action: no" );
		return array_merge( [ "viewprotect-denied" ], $groupList );
	}

	/**
	 * Set the page permission cache
	 *
	 * @param User $user the user
	 * @param Title $title title
	 * @param string $action restricted action
	 * @param string $group group allowed
	 * @return void
	 */
	public static function setPageProtection( User $user, Title $title, $action, $group ) {
		self::$pagePermissionWriteCache[$title->getArticleID()][$action][$group] = $user;
	}

	/**
	 * Log an action
	 *
	 * @param User $user performing action
	 * @param string $action being performed
	 * @param int $pageId that is the target
	 * @param string $oldGroups to which it was restricted
	 * @param string $newGroups to which it is now restricted
	 * @return bool
	 */
	protected static function log( User $user, $action, $pageId, $oldGroups, $newGroups ) {
		$logEntry = new ManualLogEntry( 'viewprotect', $action );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( Title::newFromId( $pageId ) );
		$logEntry->setParameters( [
			'4::oldgroup' => $oldGroups,
			'5::oldgroup' => $newGroups
		] );
		return $logEntry->insert();
	}

	/**
	 * Remove all permission protections
	 *
	 * @param int[] $pageIDs list of the page id or title object for
	 *     which to clear permissions
	 * @return array<string, array<string, array<string, bool>>>
	 *     permissions that were cleared
	 */
	public static function clearPagePermissions( array $pageIDs ) {
		$result = [];
		$dbw = wfGetDB( DB_MASTER );
		foreach ( $pageIDs as $pageID ) {
			$res = $dbw->select( 'viewprotect',
								 [
									 'viewprotect_page as page',
									 'viewprotect_group as grp',
									 'viewprotect_permission as permission'
								 ],
								 [ 'viewprotect_page' => $pageID ], __METHOD__,
								 [ 'DISTINCT' ] );
			foreach ( $res as $row ) {
				$result[$row->page][$row->permission][$row->grp] = true;
			}
			$dbw->delete( 'viewprotect',
						  [ 'viewprotect_page' => $pageID ],
						  __METHOD__ );
		}
		return $result;
	}

	/**
	 * Write cached page permissions to disk.
	 *
	 * @return void
	 */
	public static function flushPageProtections() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );

		$pUser = '';
		$oPerm = self::clearPagePermissions(
			array_keys( self::$pagePermissionWriteCache )
		);
		$nPerm = [];
		foreach ( self::$pagePermissionWriteCache as $pageId => $actionGroup ) {
			foreach ( $actionGroup as $action => $groups ) {
				foreach ( $groups as $group => $user ) {
					$nPerm[$pageId][$action][$group] = 1;
					$pUser = $user; // It'll only be one user making changes
					$dbw->insert( 'viewprotect',
								  [ 'viewprotect_page' => $pageId,
									'viewprotect_permission' => $action,
									'viewprotect_group' => $group ],
								  __METHOD__ );
				}
			}
		}
		foreach ( $nPerm as $pageId => $actions ) {
			foreach ( $actions as $perm => $groups ) {
				$nGroups = implode( ", ", array_keys( $groups ) );
				$oGroups = '';
				if ( isset( $oPerm[$pageId][$perm] ) ) {
					$oGroups = implode( ", ", array_keys( $oPerm[$pageId][$perm] ) );
				}
				wfDebugLog( __METHOD__, "$pUser, $perm, $pageId, $oGroups, $nGroups" );
				if ( $oGroups != $nGroups ) {
					self::log( $pUser, $perm, $pageId, $oGroups, $nGroups );
				}
			}
		}
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Get the groups allowed
	 *
	 * @param Title $title title
	 * @param string $action restricted action, default 'read'
	 * @return array list of allowed groups, empty if everyone is allowed
	 */
	public static function getPageRestrictions( Title $title, $action = 'read' ) {
		wfDebugLog( __METHOD__, "Checking $action for $title" );
		$dbkey = $title->getArticleID();
		if ( $dbkey !== 0 &&
			 ( self::$cache === null || !isset( self::$cache[ $dbkey ] ) )
		) {
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
		return array_filter( array_keys( self::$cache[$dbkey][$action] ) );
	}

	/**
	 * Check if user is in the group
	 *
	 * @param User $user being checked
	 * @param string $group to check
	 * @return bool true if user is in the group
	 */
	protected static function inGroup( User $user, $group ) {
		$result = in_array( $group, $user->getGroups() );

		wfDebugLog( __METHOD__, "$user is in $group: " .
					( $result ? "yes" : "no" ) );
		return $result;
	}
}

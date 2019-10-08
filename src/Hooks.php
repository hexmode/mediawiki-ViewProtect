<?php
/**
 * Hooks for ViewProtect extension
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

use DatabaseUpdater;
use OutputPage;
use Skin;
use Title;
use User;

class Hooks {
	/**
	 * Use this hook to add whatever we need to the page.
	 *
	 * @param OutputPage $out for anything we need to display
	 * @param Skin $skin for the skin
	 * @return bool
	 *
	 * @SuppressWarnings(UnusedFormalParameter)
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		// Always return true, indicating that parser initialization should
		// continue normally.

		// Only checking read restrictions right now
		$restrictions = [ 'read' ];

		global $wgFullyInitialised;
		if ( $wgFullyInitialised === true ) {
			$title = $out->getTitle();
			if ( $title !== null ) {
				foreach ( $restrictions as $restrict ) {
					$allowed = ViewProtect::getPageRestrictions( $title, $restrict );
					if ( count( $allowed ) > 0 ) {
						$msg = $out->msg( "viewprotect-$restrict-indicator", $allowed );
						$out->setIndicators( [ "viewprotect-$restrict" => $msg->plain() ] );
					}
				}
			}
		}
		return true;
	}

	/**
	 * This registers our database schema update(s)
	 *
	 * @param DatabaseUpdater $updater pump updates through here
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable(
			'viewprotect', __DIR__ . '/../sql/add-viewprotect.sql'
		);

		return true;
	}

	/**
	 * This checks the permissions to see if they're allowed.
	 *
	 * @param Title $title of page to check
	 * @param User $user to check perms for
	 * @param string $action being performed
	 * @param bool|string|array &$result (array of) Permissions error
	 *        message keys or true if no errror.
	 * @return bool
	 */
	public static function onGetUserPermissionsErrors(
		Title $title, User $user, $action, &$result
	) {
		// For later reference, these are the possible actions
		// $available = array(
		// "read", "edit", "patrol", "deletedhistory",
		// "delete", "move", "protect",
		// );
		global $wgFullyInitialised;
		if ( $wgFullyInitialised === true ) {
			$result = ViewProtect::hasPermission( $title, $user, $action );

			if ( $result !== true && count( $result ) !== 0 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check for any read restrictions
	 *
	 * @param Title $title file page
	 * @param string &$path don't know why I'd want this
	 * @param string &$baseName isn't this in title?
	 * @param array &$result parameters for wfForbidden
	 * @return bool false if restricted and current user isn't a member
	 */
	public static function onImgAuthBeforeStream( Title $title, &$path, &$baseName,
												  &$result ) {
		global $wgResourceBasePath, $wgUser;

		$groups = ViewProtect::hasPermission( $title, $wgUser, 'read' );
		if ( $groups === true ) {
			return true;
		}
		$path = $wgResourceBasePath . "/extensions/ViewProtect/resources/Stop_Sign.svg";
		$result = [ 'img-auth-accessdenied', 'img-auth-badtitle', $baseName ];
		return false;
	}
}

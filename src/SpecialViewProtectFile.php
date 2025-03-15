<?php
/**
 * ViewProtect SpecialPage for protecting files with the ViewProtect
 * extension
 *
 * Copyright (C) 2017-2025  NicheWork, LLC
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

use HTMLForm;
use ImageListPager;
use Iterator;
use MWException;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class SpecialViewProtectFile extends SpecialPage {

	protected $mostRecent;
	protected $uploadedFiles;
	protected $submittedName;
	protected $submittedGroup;

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		// A special page should at least have a name.
		parent::__construct( 'ViewProtectFile', 'upload' );
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub The subpage string argument
	 *       (if any: [[Special:ViewProtect/subpage]]).
	 */
	public function execute( $sub ) {
		parent::execute( $sub );

		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'viewprotectfile' ) );
		$out->addModules( "ext.ViewProtectFile" );

		$files = $this->getRecentFiles();
		if ( $files === null ) {
			$this->getOutput()->addWikiText( wfMessage( 'viewprotectfile-nofiles' ) );
			return;
		}

		$request = $this->getRequest();
		$this->submittedName = $request->getVal( 'viewprotectfile', $sub );
		if ( $request->wasPosted() ) {
			$check = $request->getVal( 'selectedFile' );
			if ( $check && $this->submittedName !== $check ) {
				throw new MWException( "Something funky happened!" );
			}
			$this->submittedGroup = $request->getVal( 'groupRestriction' );
		}

		$fields = $this->getFormFields( $files );
		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$form
			->setMethod( 'post' )
			->setAction( $this->getPageTitle()->getLocalURL() )
			->setSubmitTextMsg( 'viewprotectfile-submit' )
			->setWrapperLegendMsg( 'viewprotectfile-legend' )
			->setSubmitCallback( [ $this, 'submitSend' ] )
			->prepareForm();

		$tried = "";
		if ( $request->wasPosted() ) {
			$tried = $form->trySubmit();
		}
		$form->displayForm( $tried );
	}

	/**
	 * Return form fields
	 *
	 * @param array $files to pick from
	 * @return array for making a form
	 */
	public function getFormFields( array $files ) {
		$selectType = 'combobox';
		$optionsName = 'options';
		$otherName = '';
		if ( !class_exists( "HTMLComboBoxField" ) ) {
			$selectType = 'autocompleteselect';
			$optionsName = 'autocomplete';
			$otherName = 'options';
		}
		$field = [
			'viewprotectfile' => [
				'type' => $selectType,
				'name' => 'viewprotectfile',
				$optionsName => $files,
				$otherName => [],
				'require-match' => false,
				'default' => $this->mostRecent,
				'label-message' => 'viewprotectfile-name-enter',
				'validation-callback' => [ $this, 'userCanModify' ],
				'required' => true
			]
		];

		if ( $this->submittedName && $this->userCanModify( $this->submittedName ) ) {
			$title = Title::newFromText( $this->submittedName, NS_FILE );
			if ( !$title || !$title->exists() ) {
				$field['warning'] = [
					'default' => $this->showNote( 'viewprotectfile-notexist' ),
					'type' => 'info',
					'raw' => true,
				];
			}
			$field['viewprotectfile']['label-message'] = 'viewprotectfile-name';
			$field['viewprotectfile']['default'] = $this->submittedName;
			$field['viewprotectfile']['readonly'] = true;
			$field['viewprotectfile']['type'] = 'text';

			$field['selected-file'] = [
				'type' => 'hidden',
				'name' => 'selectedFile',
				'default' => $this->submittedName,
			];
			$field['groups'] = $this->fillGroupsField();
		}

		return $field;
	}

	/**
	 * Populate the groups field
	 *
	 * @return array
	 */
	protected function fillGroupsField() {
		$group = $this->getCurrentRestriction( $this->submittedName );
		return [
			'type' => 'select',
			'name' => 'groupRestriction',
			'options' => $this->getAvailableGroups(),
			'require-match' => true,
			'default' => $group,
			'label-message' => [ 'viewprotectfile-group', $this->submittedName ]
		];
	}

	/**
	 * Get HTML for an info message.
	 *
	 * @param string $msg to show
	 * @return string html
	 */
	protected function showNote( $msg ) {
		return '<ul class="oo-ui-fieldLayout-messages">'
			. '<li class="oo-ui-fieldLayout-messages-error">'
			. '<span aria-disabled="false" class="oo-ui-widget oo-ui-widget-enabled'
			. ' oo-ui-iconElement oo-ui-iconElement-icon oo-ui-icon-alert'
			. ' oo-ui-flaggedElement-warning oo-ui-iconWidget oo-ui-image-warning">'
			. '</span><span aria-disabled="false"'
			. ' class="oo-ui-widget oo-ui-widget-enabled oo-ui-labelElement'
			. ' oo-ui-labelElement-label oo-ui-labelWidget">'
			. $this->msg( $msg )->parse() . '</span></li></ul>';
	}

	/**
	 * Return the group that the file is currently restricted to
	 *
	 * @param string $file name
	 * @return string group
	 */
	public function getCurrentRestriction( $file ) {
		$group = ViewProtect::getPageRestrictions(
			Title::newFromText( $file, NS_FILE ), 'read'
		);

		if ( count( $group ) >= 1 ) {
			return $group[0];
		}
		return "";
	}

	/**
	 * Get the list of explicit group memberships this user has.
	 * The implicit * and user groups are not included.
	 *
	 * Copied from removed User::getGroups()
	 *
	 * @return string[] Array of internal group names
	 */
    public function getUserGroups() {
        return MediaWikiServices::getInstance()
			->getUserGroupManager()
			->getUserGroups( $this->getUser(), $this->getUser()->queryFlagsUsed );
    }

	/**
	 * Return a list of groups that the current user is a member of
	 *
	 * @return array list of groups
	 */
	public function getAvailableGroups() {
		// <none> so group restriction can be removed
		$groups = [ '<none>' => '' ];

		if ( $this->getUser()->isAllowed( "viewprotectmanage" ) ) {
			$groupList = User::getAllGroups();
		} else {
			$groupList = $this->getUserGroups();
		}
		array_map(
			function( $name ) use ( &$groups ) {
				$groups[$name] = $name;
			}, $groupList );
		return $groups;
	}

	/**
	 * Return true if the user uploaded the file.
	 *
	 * @param string $file name to check
	 * @return bool
	 */
	public function userCanModify( $file ) {
		if ( $this->getUser()->isAllowed( "viewprotectmanage" ) ) {
			return true;
		}
		$files = $this->getRecentFiles();
		return isset( $files[$file] );
	}

	/**
	 * Submit send
	 *
	 * @return bool
	 */
	public function submitSend() {
		if ( $this->submittedGroup === null ) {
			$this->getOutput()->redirect(
				$this->getPageTitle( $this->submittedName )->getLocalURL()
			);
			return false;
		}
		$title = Title::newFromText( $this->submittedName, NS_FILE );
		ViewProtect::setPageProtection( $this->getUser(), $title, 'read',
										$this->submittedGroup );
		ViewProtect::setPageProtection( $this->getUser(), $title, 'upload',
										$this->submittedGroup );
		ViewProtect::flushPageProtections();
		$this->getOutput()->redirect(
			$this->getPageTitle()->getLocalURL() .  '?restrict=' .
			urlencode( $this->submittedName ) . '&group=' .
			urlencode( $this->submittedGroup )
		);
		return "";
	}

	/**
	 * Why would you cache this?
	 *
	 * @return bool false
	 */
	public function isCacheable() {
		return false;
	}

	/**
	 * Get the section to show this page on in the list of special
	 * pages.
	 *
	 * @return string 'other'
	 */
	protected function getGroupName() {
		return 'other';
	}

	/**
	 * Return a list of the logged-in user's files
	 *
	 * @param string $search what to look for
	 * @return array list of titles
	 */
	protected function getRecentFiles( $search = "" ) {
		if ( !is_array( $this->uploadedFiles ) ) {
			$user = $this->getUser();
			$isMgr = $user->isAllowed( "viewprotectmanage" )
				   ? null
				   : $user->getName();
			$pager = new ImageListPager(
				$this->getContext(),
				$isMgr,
				$search,
				$this->including(),
				false,
				$this->getLinkRenderer()
			);
			// Only offer the most recent 200 files for completion
			$pager->setLimit( 200 );
			$pager->doQuery();
			$fileIterator = $pager->getResult();
			if ( $fileIterator->numRows() > 0 ) {
				$current = $fileIterator->current();
				if ( is_object( $current ) && property_exists( $current, 'img_name' ) ) {
					$this->mostRecent = $current->img_name;
					$this->uploadedFiles = [];

					iterator_apply(
						$fileIterator,
						function ( Iterator $iter ) {
							$name = $iter->current()->img_name;
							$this->uploadedFiles[$name] = $name;
							return true;
						}, [ $fileIterator ] );
				}
			}
		}
		return $this->uploadedFiles;
	}

}

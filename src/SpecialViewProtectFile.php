<?php
/**
 * ViewProtect SpecialPage for protecting files with the ViewProtect
 * extension
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

use HTMLForm;
use ImageListPager;
use MWException;
use SpecialPage;
use Title;

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
		$out->setPageTitle( $this->msg( 'viewprotect' ) );
		$out->addModules( "ext.ViewProtectFile" );

		$request = $this->getRequest();
		$this->submittedName = $request->getVal( 'viewprotectfile', $sub );
		$check = $request->getVal( 'selectedFile' );
		if ( $check && $this->submittedName !== $check ) {
			throw new MWException( "Something funky happened!" );
		}
		$this->submittedGroup = $request->getVal( 'groupRestriction' );
		$field = $this->getFormFields();
		$form = HTMLForm::factory( 'ooui', $field, $this->getContext() );
		$form
			->setMethod( 'post' )
			->setAction( $this->getPageTitle()->getLocalURL() )
			->setSubmitTextMsg( 'viewprotectfile-submit' )
			->setWrapperLegendMsg( 'viewprotectfile-legend' )
			->setSubmitCallback( [ $this, 'submitSend' ] )
			->prepareForm();

		$tried = false;

		if ( $this->submittedName &&
			 ( $sub == null || $this->submittedGroup !== null )
		) {
			$tried = $form->trySubmit();
		}
		$form->displayForm( $tried );
	}

	/**
	 * Return form fields
	 *
	 * @return array for making a form
	 */
	public function getFormFields() {
		$files = $this->getUserFiles();
		$field = [
			'viewprotectfile' => [
				'type' => 'combobox',
				'name' => 'viewprotectfile',
				'options' => $files,
				'default' => $this->mostRecent,
				'label-message' => 'viewprotectfile-name',
				'validation-callback' => [ $this, 'userUploaded' ],
				'required' => true,
			]
		];

		if ( $this->submittedName &&
			 $this->userUploaded( $this->submittedName )
		) {
			$group = $this->getCurrentRestriction( $this->submittedName );
			$field['viewprotectfile']['default'] = $this->submittedName;
			$field['viewprotectfile']['readonly'] = true;
			$field['viewprotectfile']['type'] = 'text';

			$field['selected-file'] = [
				'type' => 'hidden',
				'name' => 'selectedFile',
				'default' => $this->submittedName,
			];
			$field['groups'] = [
				'type' => 'combobox',
				'name' => 'groupRestriction',
				'options' => $this->getGroupMemberships(),
				'default' => $group,
				'label-message' => [ 'viewprotectfile-group', $this->submittedName ]
			];
		}
		return $field;
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
	 * Return a list of groups that the current user is a member of
	 *
	 * @return array list of groups
	 */
	public function getGroupMemberships() {
		// <none> so group restriction can be removed
		$groups = [ '<none>' => '' ];
		array_map(
			function( $name ) use ( &$groups ) {
				$groups[$name] = $name;
			}, $this->getUser()->getGroups() );
		return $groups;
	}

	/**
	 * Return true if the user uploaded the file.
	 *
	 * @param string $file name to check
	 * @return bool
	 */
	public function userUploaded( $file ) {
		$files = $this->getUserFiles();
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
			return true;
		}
		$title = Title::newFromText( $this->submittedName, NS_FILE );
		ViewProtect::setPageProtection( $title, 'read', $this->submittedGroup );
		ViewProtect::setPageProtection( $title, 'upload', $this->submittedGroup );
		ViewProtect::flushPageProtections();
		$this->getOutput()->redirect(
			$this->getPageTitle()->getLocalURL() .  '?restrict=' .
			urlencode( $this->submittedName ) . '&group=' .
			urlencode( $this->submittedGroup )
		);
		return true;
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
	protected function getUserFiles( $search = "" ) {
		if ( !is_array( $this->uploadedFiles ) ) {
			$pager = new ImageListPager(
				$this->getContext(),
				$this->getUser()->getName(),
				$search,
				$this->including(),
				false
			);
			$pager->doQuery();
			// Only offer the most recent 200 files for completion
			$pager->setLimit( 200 );

			$this->mostRecent = $pager->getResult()->current()->img_name;
			$fileIterator = $pager->getResult();
			$this->uploadedFiles = [];

			iterator_apply( $fileIterator,
							function ( $iter ) {
								$name = $iter->current()->img_name;
								$this->uploadedFiles[$name] = $name;
								return true;
							}, [ $fileIterator ] );
		}
		return $this->uploadedFiles;
	}

}

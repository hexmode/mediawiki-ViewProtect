<?php
/**
 * ViewProtect SpecialPage for ViewProtect extension
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

use SpecialPage;

class SpecialViewProtect extends SpecialPage {

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		// A special page should at least have a name.
		parent::__construct( 'ViewProtect', 'viewprotectmanage' );
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub The subpage string argument (if any).
	 *  [[Special:ViewProtect/subpage]].
	 */
	public function execute( $sub ) {
		parent::execute( $sub );
		$out = $this->getOutput();

		$out->setPageTitle( $this->msg( 'viewprotect' ) );

		// Parses message from .i18n.php as wikitext and adds it to the
		// page output.
		$out->addWikiMsg( 'viewprotect-intro' );
	}

	/**
	 * Get the section to show this page on in the list of special
	 * pages.
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'other';
	}
}

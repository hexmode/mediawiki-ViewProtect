<?php
/*
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
 */

namespace MediaWiki\Extension\ViewProtect;

use ApiQueryBase;

class ApiQuery extends ApiQueryBase {

	/**
	 * Constructor is optional. Only needed if we give
	 * this module properties a prefix (in this case we're using
	 * "ex" as the prefix for the module's properties.
	 * Query modules have the convention to use a property prefix.
	 * Base modules generally don't use a prefix, and as such don't
	 * need the constructor in most cases.
	 */
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'ex' );
	}

	/**
	 * In this example we're returning one ore more properties
	 * of wgViewProtectFooStuff. In a more realistic example, this
	 * method would probably
	 */
	public function execute() {
		global $wgViewProtectFooStuff;
		$params = $this->extractRequestParams();

		$stuff = [];

		// This is a filtered request, only show this key if it exists,
		// (or none, if it doesn't exist)
		if ( isset( $params['key'] ) ) {
			$key = $params['key'];
			if ( isset( $wgViewProtectFooStuff[$key] ) ) {
				$stuff[$key] = $wgViewProtectFooStuff[$key];
			}

		// This is an unfiltered request, replace the array with the total
		// set of properties instead.
		} else {
			$stuff = $wgViewProtectFooStuff;
		}

		$result = [ 'stuff' => $stuff ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 *
	 */
	public function getAllowedParams() {
		return [
			'key' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	/**
	 *
	 */
	protected function getViewProtectsMessages() {
		return [
			'action=query&list=viewprotect'
				=> 'apihelp-query+viewprotect-example-1',
			'action=query&list=viewprotect&key=do'
				=> 'apihelp-query+viewprotect-example-2',
		];
	}
}

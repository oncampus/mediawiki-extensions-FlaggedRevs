<?php

/**
 * Created on November 6, 2009
 *
 * API module for MediaWiki's FlaggedRevs extension
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * API module to get flag config info
 *
 * @ingroup FlaggedRevs
 */
class ApiFlagConfig extends ApiBase {

	public function execute() {
		$this->getMain()->setCacheMode( 'public' );
		$minQLTags = FlaggedRevs::quickTags( FR_QUALITY );
		$minPLTags = FlaggedRevs::quickTags( FR_PRISTINE );
		$data = [];
		foreach ( FlaggedRevs::getDimensions() as $tag => $levels ) {
			$data[] = [
				'name'   => $tag,
				'levels' => count( $levels ) - 1, // exclude '0' level
				'tier1'  => 1,
				'tier2'  => $minQLTags[$tag],
				'tier3'  => $minPLTags[$tag]
			];
		}
		$result = $this->getResult();
		$result->setIndexedTagName( $data, 'tag' );
		$result->addValue( null, $this->getModuleName(), $data );
	}

	public function mustBePosted() {
		return false;
	}

	public function isWriteMode() {
			return false;
	}

	public function getAllowedParams() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=flagconfig'
				=> 'apihelp-flagconfig-example-1',
		];
	}
}

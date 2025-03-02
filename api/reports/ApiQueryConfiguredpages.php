<?php

/**
 * Created on April 8, 2011
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
 * Query module to list pages with custom review configurations
 *
 * @ingroup FlaggedRevs
 */
class ApiQueryConfiguredpages extends ApiQueryGeneratorBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'cp' );
	}

	public function execute() {
		$this->run();
	}

	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	private function run( $resultPageSet = null ) {
		$params = $this->extractRequestParams();

		// Construct SQL Query
		$this->addTables( [ 'page', 'flaggedpage_config', 'flaggedpages' ] );
		if ( isset( $params['namespace'] ) ) {
			$this->addWhereFld( 'page_namespace', $params['namespace'] );
		}
		if ( isset( $params['default'] ) ) {
			// Convert readable 'stable'/'latest' to 0/1 (DB format)
			$override = ( $params['default'] === 'stable' ) ? 1 : 0;
			$this->addWhereFld( 'fpc_override', $override );
		}
		if ( isset( $params['autoreview'] ) ) {
			// Convert readable 'none' to '' (DB format)
			$level = ( $params['autoreview'] === 'none' ) ? '' : $params['autoreview'];
			$this->addWhereFld( 'fpc_level', $level );
		}

		$this->addWhereRange(
			'fpc_page_id',
			$params['dir'],
			$params['start'],
			$params['end']
		);
		$this->addJoinConds( [
			'flaggedpage_config' => [ 'INNER JOIN', 'page_id=fpc_page_id' ],
			'flaggedpages' 		 => [ 'LEFT JOIN', 'page_id=fp_page_id' ]
		] );

		if ( is_null( $resultPageSet ) ) {
			$this->addFields( [
				'page_id',
				'page_namespace',
				'page_title',
				'page_len',
				'page_latest',
				'fpc_page_id',
				'fpc_override',
				'fpc_level',
				'fpc_expiry',
				'fp_stable'
			] );
		} else {
			$this->addFields( $resultPageSet->getPageTableFields() );
			$this->addFields( 'fpc_page_id' );
		}

		$limit = $params['limit'];
		$this->addOption( 'LIMIT', $limit + 1 );
		$res = $this->select( __METHOD__ );

		$data = [];
		$count = 0;
		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				// We've reached the one extra which shows that there are
				// additional pages to be had. Stop here...
				$this->setContinueEnumParameter( 'start', $row->fpc_page_id );
				break;
			}

			if ( is_null( $resultPageSet ) ) {
				$title = Title::newFromRow( $row );
				$data[] = [
					'pageid' 			 => intval( $row->page_id ),
					'ns' 				 => intval( $row->page_namespace ),
					'title' 			 => $title->getPrefixedText(),
					'last_revid' 		 => intval( $row->page_latest ),
					'stable_revid' 		 => intval( $row->fp_stable ),
					'stable_is_default'	 => intval( $row->fpc_override ),
					'autoreview'		 => $row->fpc_level,
					'expiry'			 => ( $row->fpc_expiry === 'infinity' ) ?
						'infinity' : wfTimestamp( TS_ISO_8601, $row->fpc_expiry ),
				];
			} else {
				$resultPageSet->processDbRow( $row );
			}
		}

		if ( is_null( $resultPageSet ) ) {
			$result = $this->getResult();
			$result->setIndexedTagName( $data, 'p' );
			$result->addValue( 'query', $this->getModuleName(), $data );
		}
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams() {
		// Replace '' with more readable 'none' in autoreview restiction levels
		$autoreviewLevels = FlaggedRevs::getRestrictionLevels();
		$autoreviewLevels[] = 'none';
		return [
			'start' => [
				ApiBase::PARAM_TYPE 	=> 'integer'
			],
			'end' => [
				ApiBase::PARAM_TYPE 	=> 'integer'
			],
			'dir' => [
				ApiBase::PARAM_DFLT 	=> 'newer',
				ApiBase::PARAM_TYPE 	=> [ 'newer', 'older' ],
				/** @todo Once support for MediaWiki < 1.25 is dropped,
				 * just use ApiBase::PARAM_HELP_MSG directly */
				constant( 'ApiBase::PARAM_HELP_MSG' ) ?: '' => 'api-help-param-direction',
			],
			'namespace' => [
				ApiBase::PARAM_DFLT 	=> null,
				ApiBase::PARAM_TYPE 	=> 'namespace',
				ApiBase::PARAM_ISMULTI 	=> true,
			],
			'default' => [
				ApiBase::PARAM_DFLT 	=> null,
				ApiBase::PARAM_TYPE 	=> [ 'latest', 'stable' ],
			],
			'autoreview' => [
				ApiBase::PARAM_DFLT 	=> null,
				ApiBase::PARAM_TYPE 	=> $autoreviewLevels,
			],
			'limit' => [
				ApiBase::PARAM_DFLT 	=> 10,
				ApiBase::PARAM_TYPE 	=> 'limit',
				ApiBase::PARAM_MIN  	=> 1,
				ApiBase::PARAM_MAX  	=> ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 	=> ApiBase::LIMIT_BIG2
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=configuredpages&cpnamespace=0'
				=> 'apihelp-query+configuredpages-example-1',
			'action=query&generator=configuredpages&gcplimit=4&prop=info'
				=> 'apihelp-query+configuredpages-example-2',
		];
	}
}

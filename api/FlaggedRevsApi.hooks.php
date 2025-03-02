<?php

abstract class FlaggedRevsApiHooks extends ApiQueryBase {

	public static function addApiRevisionParams( &$module, &$params ) {
		if ( !$module instanceof ApiQueryRevisions ) {
			return true;
		}
		$params['prop'][ApiBase::PARAM_TYPE][] = 'flagged';
		return true;
	}

	public static function addApiRevisionData( &$module ) {
		if ( !$module instanceof ApiQueryRevisions ) {
			return true;
		}
		$params = $module->extractRequestParams( false );
		if ( empty( $params['prop'] ) || !in_array( 'flagged', $params['prop'] ) ) {
			return true;
		}
		if ( !in_array( 'ids', $params['prop'] ) ) {
			$module->dieWithError(
				[ 'apierror-invalidparammix-mustusewith', 'rvprop=flagged', 'rvprop=ids' ], 'missingparam'
			);
		}
		// Get all requested pageids/revids in a mapping:
		// pageid => revid => array_index of the revision
		// we will need this later to add data to the result array
		$result = $module->getResult();
		if ( defined( 'ApiResult::META_CONTENT' ) ) {
			$data = (array)$result->getResultData( [ 'query', 'pages' ], [ 'Strip' => 'all' ] );
		} else {
			$data = $result->getData();
			if ( !isset( $data['query'] ) || !isset( $data['query']['pages'] ) ) {
				return true;
			}
			$data = $data['query']['pages'];
		}
		foreach ( $data as $pageid => $page ) {
			if ( array_key_exists( 'revisions', (array)$page ) ) {
				foreach ( $page['revisions'] as $index => $rev ) {
					if ( array_key_exists( 'revid', (array)$rev ) ) {
						$pageids[$pageid][$rev['revid']] = $index;
					}
				}
			}
		}
		if ( empty( $pageids ) ) {
			return true;
		}

		// Construct SQL Query
		$db = $module->getDB();
		$module->resetQueryParams();
		$module->addTables( [ 'flaggedrevs', 'user' ] );
		$module->addFields( [
			'fr_page_id',
			'fr_rev_id',
			'fr_timestamp',
			'fr_quality',
			'fr_tags',
			'user_name'
		] );
		$module->addWhere( 'fr_user=user_id' );

		$where = [];
		// Construct WHERE-clause to avoid multiplying the number of scanned rows
		// as flaggedrevs table has composite primary key (fr_page_id,fr_rev_id)
		foreach ( $pageids as $pageid => $revids ) {
			$where[] = $db->makeList( [ 'fr_page_id' => $pageid,
				'fr_rev_id' => array_keys( $revids ) ], LIST_AND );
		}
		$module->addWhere( $db->makeList( $where, LIST_OR ) );

		$res = $module->select( __METHOD__ );

		// Add flagging data to result array
		foreach ( $res as $row ) {
			$index = $pageids[$row->fr_page_id][$row->fr_rev_id];
			$data = [
				'user' 			=> $row->user_name,
				'timestamp' 	=> wfTimestamp( TS_ISO_8601, $row->fr_timestamp ),
				'level' 		=> intval( $row->fr_quality ),
				'level_text' 	=> FlaggedRevs::getQualityLevelText( $row->fr_quality ),
				'tags' 			=> FlaggedRevision::expandRevisionTags( $row->fr_tags )
			];
			$result->addValue(
				[ 'query', 'pages', $row->fr_page_id, 'revisions', $index ],
				'flagged',
				$data
			);
		}
		return true;
	}
}

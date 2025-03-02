<?php

class PendingChanges extends SpecialPage {
	/*
	 * @var $pages PendingChangesPager
	 */
	protected $pager = null;

	public function __construct() {
		parent::__construct( 'PendingChanges' );
		$this->mIncludable = true;
	}

	public function execute( $par ) {
		$request = $this->getRequest();

		$this->setHeaders();
		$this->currentUnixTS = wfTimestamp( TS_UNIX ); // now

		$this->namespace = $request->getIntOrNull( 'namespace' );
		$this->level = $request->getInt( 'level', -1 );
		$category = trim( $request->getVal( 'category' ) );
		$catTitle = Title::makeTitleSafe( NS_CATEGORY, $category );
		$this->category = is_null( $catTitle ) ? '' : $catTitle->getText();
		$this->size = $request->getIntOrNull( 'size' );
		$this->watched = $request->getCheck( 'watched' );
		$this->stable = $request->getCheck( 'stable' );
		$feedType = $request->getVal( 'feed' );

		$incLimit = 0;
		if ( $this->including() ) {
			$incLimit = $this->parseParams( $par ); // apply non-URL params
		}

		$this->pager = new PendingChangesPager( $this, $this->namespace,
			$this->level, $this->category, $this->size, $this->watched, $this->stable );

		# Output appropriate format...
		if ( $feedType != null ) {
			$this->feed( $feedType );
		} else {
			if ( $this->including() ) {
				if ( $incLimit ) { // limit provided
					$this->pager->setLimit( $incLimit ); // apply non-URL limit
				}
			} else {
				$this->setSyndicated();
				$this->showForm();
			}
			$this->showPageList();
		}
	}

	protected function setSyndicated() {
		$request = $this->getRequest();
		$queryParams = [
			'namespace' => $request->getIntOrNull( 'namespace' ),
			'level'     => $request->getIntOrNull( 'level' ),
			'category'  => $request->getVal( 'category' ),
		];
		$this->getOutput()->setSyndicated( true );
		$this->getOutput()->setFeedAppendQuery( wfArrayToCgi( $queryParams ) );
	}

	public function showForm() {
		global $wgScript;

		# Explanatory text
		$this->getOutput()->addWikiMsg( 'pendingchanges-list',
			$this->getLanguage()->formatNum( $this->pager->getNumRows() ) );

		$form = Html::openElement( 'form', [ 'name' => 'pendingchanges',
			'action' => $wgScript, 'method' => 'get' ] ) . "\n";
		$form .= "<fieldset><legend>" . $this->msg( 'pendingchanges-legend' )->escaped() . "</legend>\n";
		$form .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBKey() ) . "\n";

		$items = [];
		if ( count( FlaggedRevs::getReviewNamespaces() ) > 1 ) {
			$items[] = "<span style='white-space: nowrap;'>" .
				FlaggedRevsXML::getNamespaceMenu( $this->namespace, '' ) . '</span>';
		}
		if ( FlaggedRevs::qualityVersions() ) {
			$items[] = "<span style='white-space: nowrap;'>" .
				FlaggedRevsXML::getLevelMenu( $this->level, 'revreview-filter-stable' ) .
				'</span>';
		}
		if ( !FlaggedRevs::isStableShownByDefault() && !FlaggedRevs::useOnlyIfProtected() ) {
			$items[] = "<span style='white-space: nowrap;'>" .
				Xml::check( 'stable', $this->stable, [ 'id' => 'wpStable' ] ) .
				Xml::label( $this->msg( 'pendingchanges-stable' )->text(), 'wpStable' ) . '</span>';
		}
		if ( $items ) {
			$form .= implode( ' ', $items ) . '<br />';
		}

		$items = [];
		$items[] =
			Xml::label( $this->msg( "pendingchanges-category" )->text(), 'wpCategory' ) . '&#160;' .
			Xml::input( 'category', 30, $this->category, [ 'id' => 'wpCategory' ] );
		if ( $this->getUser()->getId() ) {
			$items[] = Xml::check( 'watched', $this->watched, [ 'id' => 'wpWatched' ] ) .
				Xml::label( $this->msg( 'pendingchanges-onwatchlist' )->text(), 'wpWatched' );
		}
		$form .= implode( ' ', $items ) . '<br />';
		$form .=
			Xml::label( $this->msg( 'pendingchanges-size' )->text(), 'wpSize' ) .
			Xml::input( 'size', 4, $this->size, [ 'id' => 'wpSize' ] ) . ' ' .
			Xml::submitButton( $this->msg( 'allpagessubmit' )->text() ) . "\n";
		$form .= "</fieldset>";
		$form .= Html::closeElement( 'form' ) . "\n";

		$this->getOutput()->addHTML( $form );
	}

	public function showPageList() {
		$out = $this->getOutput();
		if ( $this->pager->getNumRows() ) {
			// To style output of ChangesList::showCharacterDifference
			$out->addModuleStyles( 'mediawiki.special.changeslist' );
		}
		// Viewing the list normally...
		if ( !$this->including() ) {
			if ( $this->pager->getNumRows() ) {
				$out->addHTML( $this->pager->getNavigationBar() );
				$out->addHTML( $this->pager->getBody() );
				$out->addHTML( $this->pager->getNavigationBar() );
			} else {
				$out->addWikiMsg( 'pendingchanges-none' );
			}
		// If this list is transcluded...
		} else {
			if ( $this->pager->getNumRows() ) {
				$out->addHTML( $this->pager->getBody() );
			} else {
				$out->addWikiMsg( 'pendingchanges-none' );
			}
		}
	}

	/**
	 * Set pager parameters from $par, return pager limit
	 * @param string $par
	 * @return bool|int
	 */
	protected function parseParams( $par ) {
		$bits = preg_split( '/\s*,\s*/', trim( $par ) );
		$limit = false;
		foreach ( $bits as $bit ) {
			if ( is_numeric( $bit ) ) {
				$limit = intval( $bit );
			}
			$m = [];
			if ( preg_match( '/^limit=(\d+)$/', $bit, $m ) ) {
				$limit = intval( $m[1] );
			}
			if ( preg_match( '/^namespace=(.*)$/', $bit, $m ) ) {
				$ns = $this->getLanguage()->getNsIndex( $m[1] );
				if ( $ns !== false ) {
					$this->namespace = $ns;
				}
			}
			if ( preg_match( '/^category=(.+)$/', $bit, $m ) ) {
				$this->category = $m[1];
			}
		}
		return $limit;
	}

	/**
	 * Output a subscription feed listing recent edits to this page.
	 * @param string $type
	 */
	protected function feed( $type ) {
		global $wgFeed, $wgFeedClasses, $wgFeedLimit;

		if ( !$wgFeed ) {
			$this->getOutput()->addWikiMsg( 'feed-unavailable' );
			return;
		}
		if ( !isset( $wgFeedClasses[$type] ) ) {
			$this->getOutput()->addWikiMsg( 'feed-invalid' );
			return;
		}
		$feed = new $wgFeedClasses[$type](
			$this->feedTitle(),
			$this->msg( 'tagline' )->text(),
			$this->getPageTitle()->getFullUrl()
		);
		$this->pager->mLimit = min( $wgFeedLimit, $this->pager->mLimit );

		$feed->outHeader();
		if ( $this->pager->getNumRows() > 0 ) {
			foreach ( $this->pager->mResult as $row ) {
				$feed->outItem( $this->feedItem( $row ) );
			}
		}
		$feed->outFooter();
	}

	protected function feedTitle() {
		global $wgContLanguageCode, $wgSitename;

		$page = SpecialPageFactory::getPage( 'PendingChanges' );
		$desc = $page->getDescription();
		return "$wgSitename - $desc [$wgContLanguageCode]";
	}

	protected function feedItem( $row ) {
		$title = Title::MakeTitle( $row->page_namespace, $row->page_title );
		if ( $title ) {
			$date = $row->pending_since;
			$comments = $title->getTalkPage()->getFullURL();
			$curRev = Revision::newFromTitle( $title );
			return new FeedItem(
				$title->getPrefixedText(),
				FeedUtils::formatDiffRow( $title, $row->stable, $curRev->getId(),
					$row->pending_since, $curRev->getComment() ),
				$title->getFullURL(),
				$date,
				$curRev->getUserText(),
				$comments );
		} else {
			return null;
		}
	}

	public function formatRow( $row ) {
		$css = $quality = $underReview = '';
		$title = Title::newFromRow( $row );
		$stxt = ChangesList::showCharacterDifference( $row->rev_len, $row->page_len );
		# Page links...
		$linkRenderer = $this->getLinkRenderer();
		$link = $linkRenderer->makeLink( $title );
		$hist = $linkRenderer->makeKnownLink(
			$title,
			$this->msg( 'hist' )->text(),
			[],
			[ 'action' => 'history' ]
		);
		$review = $linkRenderer->makeKnownLink(
			$title,
			$this->msg( 'pendingchanges-diff' )->text(),
			[],
			[ 'diff' => 'cur', 'oldid' => $row->stable ] + FlaggedRevs::diffOnlyCGI()
		);
		# Show quality level if there are several
		if ( FlaggedRevs::qualityVersions() ) {
			$quality = $row->quality
				? $this->msg( 'revreview-lev-quality' )->escaped()
				: $this->msg( 'revreview-lev-basic' )->escaped();
			$quality = " <b>[{$quality}]</b>";
		}
		# Is anybody watching?
		if ( !$this->including() && $this->getUser()->isAllowed( 'unreviewedpages' ) ) {
			$uw = FRUserActivity::numUsersWatchingPage( $title );
			$watching = $uw
				? $this->msg( 'pendingchanges-watched' )->numParams( $uw )->escaped()
				: $this->msg( 'pendingchanges-unwatched' )->escaped();
			$watching = " {$watching}";
		} else {
			$uw = -1;
			$watching = ''; // leave out data
		}
		# Get how long the first unreviewed edit has been waiting...
		if ( $row->pending_since ) {
			$firstPendingTime = wfTimestamp( TS_UNIX, $row->pending_since );
			$hours = ( $this->currentUnixTS - $firstPendingTime ) / 3600;
			// After three days, just use days
			if ( $hours > ( 3 * 24 ) ) {
				$days = round( $hours / 24, 0 );
				$age = $this->msg( 'pendingchanges-days' )->numParams( $days )->escaped();
			// If one or more hours, use hours
			} elseif ( $hours >= 1 ) {
				$hours = round( $hours, 0 );
				$age = $this->msg( 'pendingchanges-hours' )->numParams( $hours )->escaped();
			} else {
				$age = $this->msg( 'pendingchanges-recent' )->escaped(); // hot off the press :)
			}
			// Oh-noes!
			$css = self::getLineClass( $hours, $uw );
			$css = $css ? " class='$css'" : "";
		} else {
			$age = ""; // wtf?
		}
		# Show if a user is looking at this page
		list( $u, $ts ) = FRUserActivity::getUserReviewingDiff( $row->stable, $row->page_latest );
		if ( $u !== null ) {
			$underReview = ' <span class="fr-under-review">' .
				$this->msg( 'pendingchanges-viewing' )->escaped() . '</span>';
		}

		return ( "<li{$css}>{$link} ({$hist}) {$stxt} ({$review}) <i>{$age}</i>" .
			"{$quality}{$watching}{$underReview}</li>" );
	}

	protected static function getLineClass( $hours, $uw ) {
		if ( $uw == 0 ) {
			return 'fr-unreviewed-unwatched';
		} else {
			return "";
		}
	}

	protected function getGroupName() {
		return 'quality';
	}
}

/**
 * Query to list out outdated reviewed pages
 */
class PendingChangesPager extends AlphabeticPager {
	public $mForm;
	protected $category, $namespace;

	const PAGE_LIMIT = 100; // Don't get too expensive

	public function __construct( $form, $namespace, $level = -1, $category = '',
		$size = null, $watched = false, $stable = false
	) {
		$this->mForm = $form;
		# Must be a content page...
		$vnamespaces = FlaggedRevs::getReviewNamespaces();
		if ( is_null( $namespace ) ) {
			$namespace = $vnamespaces;
		} else {
			$namespace = intval( $namespace );
		}
		# Sanity check
		if ( !in_array( $namespace, $vnamespaces ) ) {
			$namespace = $vnamespaces;
		}
		$this->namespace = $namespace;
		# Sanity check level: 0 = checked; 1 = quality; 2 = pristine
		$this->level = ( $level >= 0 && $level <= 2 ) ? $level : -1;
		$this->category = $category ? str_replace( ' ', '_', $category ) : null;
		$this->size = ( $size !== null ) ? intval( $size ) : null;
		$this->watched = (bool)$watched;
		$this->stable = $stable && !FlaggedRevs::isStableShownByDefault()
			&& !FlaggedRevs::useOnlyIfProtected();

		parent::__construct();
		# Don't get too expensive
		$this->mLimitsShown = [ 20, 50, 100 ];
		$this->setLimit( $this->mLimit ); // apply max limit
	}

	public function setLimit( $limit ) {
		$this->mLimit = min( $limit, self::PAGE_LIMIT );
	}

	public function formatRow( $row ) {
		return $this->mForm->formatRow( $row );
	}

	public function getDefaultQuery() {
		$query = parent::getDefaultQuery();
		$query['category'] = $this->category;
		return $query;
	}

	public function getDefaultDirections() {
		return false;
	}

	public function getQueryInfo() {
		$tables = [ 'page', 'revision' ];
		$fields = [ 'page_namespace', 'page_title', 'page_len', 'rev_len', 'page_latest' ];
		# Show outdated "stable" versions
		if ( $this->level < 0 ) {
			$tables[] = 'flaggedpages';
			$fields[] = 'fp_stable AS stable';
			$fields[] = 'fp_quality AS quality';
			$fields[] = 'fp_pending_since AS pending_since';
			$conds[] = 'page_id = fp_page_id';
			$conds[] = 'rev_id = fp_stable'; // PK
			$conds[] = 'fp_pending_since IS NOT NULL';
			# Filter by pages configured to be stable
			if ( $this->stable ) {
				$tables[] = 'flaggedpage_config';
				$conds[] = 'fp_page_id = fpc_page_id';
				$conds['fpc_override'] = 1;
			}
			# Filter by category
			if ( $this->category != '' ) {
				$tables[] = 'categorylinks';
				$conds[] = 'cl_from = fp_page_id';
				$conds['cl_to'] = $this->category;
			}
			$this->mIndexField = 'fp_pending_since';
		# Show outdated version for a specific review level
		} else {
			$tables[] = 'flaggedpage_pending';
			$fields[] = 'fpp_rev_id AS stable';
			$fields[] = 'fpp_quality AS quality';
			$fields[] = 'fpp_pending_since AS pending_since';
			$conds[] = 'page_id = fpp_page_id';
			$conds[] = 'rev_id = fpp_rev_id'; // PK
			$conds[] = 'fpp_pending_since IS NOT NULL';
			# Filter by review level
			$conds['fpp_quality'] = $this->level;
			# Filter by pages configured to be stable
			if ( $this->stable ) {
				$tables[] = 'flaggedpage_config';
				$conds[] = 'fpp_page_id = fpc_page_id';
				$conds['fpc_override'] = 1;
			}
			# Filter by category
			if ( $this->category != '' ) {
				$tables[] = 'categorylinks';
				$conds[] = 'cl_from = fpp_page_id';
				$conds['cl_to'] = $this->category;
			}
			$this->mIndexField = 'fpp_pending_since';
		}
		$fields[] = $this->mIndexField; // Pager needs this
		# Filter namespace
		if ( $this->namespace !== null ) {
			$conds['page_namespace'] = $this->namespace;
		}
		# Filter by watchlist
		if ( $this->watched ) {
			$uid = (int)$this->getUser()->getId();
			if ( $uid ) {
				$tables[] = 'watchlist';
				$conds[] = "wl_user = '$uid'";
				$conds[] = 'page_namespace = wl_namespace';
				$conds[] = 'page_title = wl_title';
			}
		}
		# Filter by bytes changed
		if ( $this->size !== null && $this->size >= 0 ) {
			# Note: ABS(x-y) is broken due to mysql unsigned int design.
			$conds[] = 'GREATEST(page_len,rev_len)-LEAST(page_len,rev_len) <= ' .
				intval( $this->size );
		}
		return [
			'tables'  => $tables,
			'fields'  => $fields,
			'conds'   => $conds
		];
	}

	public function getIndexField() {
		return $this->mIndexField;
	}

	public function doBatchLookups() {
		$lb = new LinkBatch();
		foreach ( $this->mResult as $row ) {
			$lb->add( $row->page_namespace, $row->page_title );
		}
		$lb->execute();
	}

	public function getStartBody() {
		return '<ul>';
	}

	public function getEndBody() {
		return '</ul>';
	}
}

<?php

// Assumes $wgFlaggedRevsProtection is off
class ConfiguredPages extends SpecialPage {
	/*
	 * @var $pager ConfiguredPagesPager
	 */
	protected $pager = null;

	public function __construct() {
		parent::__construct( 'ConfiguredPages' );
	}

	public function execute( $par ) {
		$request = $this->getRequest();

		$this->setHeaders();

		$this->namespace = $request->getIntOrNull( 'namespace' );
		$this->override = $request->getIntOrNull( 'stable' );
		$this->autoreview = $request->getVal( 'restriction', '' );

		$this->pager = new ConfiguredPagesPager(
			$this, [], $this->namespace, $this->override, $this->autoreview );

		$this->showForm();
		$this->showPageList();
	}

	protected function showForm() {
		global $wgScript;

		# Explanatory text
		$this->getOutput()->addWikiMsg( 'configuredpages-list',
			$this->getLanguage()->formatNum( $this->pager->getNumRows() ) );

		$fields = [];
		# Namespace selector
		if ( count( FlaggedRevs::getReviewNamespaces() ) > 1 ) {
			$fields[] = FlaggedRevsXML::getNamespaceMenu( $this->namespace, '' );
		}
		# Default version selector
		$fields[] = FlaggedRevsXML::getDefaultFilterMenu( $this->override );
		# Restriction level selector
		if ( FlaggedRevs::getRestrictionLevels() ) {
			$fields[] = FlaggedRevsXML::getRestrictionFilterMenu( $this->autoreview );
		}

		$form = Html::openElement( 'form',
			[ 'name' => 'configuredpages', 'action' => $wgScript, 'method' => 'get' ] );
		$form .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedDBKey() );
		$form .= "<fieldset><legend>" . $this->msg( 'configuredpages' )->escaped() . "</legend>\n";
		$form .= implode( '&#160;', $fields ) . '<br/>';
		$form .= Xml::submitButton( $this->msg( 'go' )->text() );
		$form .= "</fieldset>\n";
		$form .= Html::closeElement( 'form' ) . "\n";

		$this->getOutput()->addHTML( $form );
	}

	protected function showPageList() {
		if ( $this->pager->getNumRows() ) {
			$this->getOutput()->addHTML( $this->pager->getNavigationBar() );
			$this->getOutput()->addHTML( $this->pager->getBody() );
			$this->getOutput()->addHTML( $this->pager->getNavigationBar() );
		} else {
			$this->getOutput()->addWikiMsg( 'configuredpages-none' );
		}
	}

	public function formatRow( $row ) {
		$title = Title::newFromRow( $row );
		# Link to page
		$linkRenderer = $this->getLinkRenderer();
		$link = $linkRenderer->makeLink( $title );
		# Link to page configuration
		$config = $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Stabilization' ),
			$this->msg( 'configuredpages-config' )->text(),
			[],
			[ 'page' => $title->getPrefixedUrl() ]
		);
		# Show which version is the default (stable or draft)
		if ( intval( $row->fpc_override ) ) {
			$default = $this->msg( 'configuredpages-def-stable' )->escaped();
		} else {
			$default = $this->msg( 'configuredpages-def-draft' )->escaped();
		}
		# Autoreview/review restriction level
		$restr = '';
		if ( $row->fpc_level != '' ) {
			$restr = 'autoreview=' . htmlspecialchars( $row->fpc_level );
			$restr = "[$restr]";
		}
		# When these configuration settings expire
		if ( $row->fpc_expiry != 'infinity' && strlen( $row->fpc_expiry ) ) {
			$expiry_description = " (" . $this->msg(
				'protect-expiring',
				$this->getLanguage()->timeanddate( $row->fpc_expiry ),
				$this->getLanguage()->date( $row->fpc_expiry ),
				$this->getLanguage()->time( $row->fpc_expiry )
			)->inContentLanguage()->text() . ")";
		} else {
			$expiry_description = "";
		}
		return "<li>{$link} ({$config}) <b>[$default]</b> " .
			"{$restr}<i>{$expiry_description}</i></li>";
	}

	protected function getGroupName() {
		return 'quality';
	}
}

/**
 * Query to list out stable versions for a page
 */
class ConfiguredPagesPager extends AlphabeticPager {
	public $mForm, $mConds, $namespace, $override, $autoreview;

	/**
	 * @param ConfiguredPages $form
	 * @param array $conds
	 * @param int $namespace (null for "all")
	 * @param int $override (null for "either")
	 * @param string $autoreview ('' for "all", 'none' for no restriction)
	 */
	public function __construct( $form, $conds, $namespace, $override, $autoreview ) {
		$this->mForm = $form;
		$this->mConds = $conds;
		# Must be content pages...
		$validNS = FlaggedRevs::getReviewNamespaces();
		if ( is_int( $namespace ) ) {
			if ( !in_array( $namespace, $validNS ) ) {
				$namespace = $validNS; // fallback to "all"
			}
		} else {
			$namespace = $validNS; // "all"
		}
		$this->namespace = $namespace;
		if ( !is_int( $override ) ) {
			$override = null; // "all"
		}
		$this->override = $override;
		if ( $autoreview === 'none' ) {
			$autoreview = ''; // 'none' => ''
		} elseif ( $autoreview === '' ) {
			$autoreview = null; // '' => null
		}
		$this->autoreview = $autoreview;
		parent::__construct();
	}

	public function formatRow( $row ) {
		return $this->mForm->formatRow( $row );
	}

	public function getQueryInfo() {
		$conds = $this->mConds;
		$conds[] = 'page_id = fpc_page_id';
		if ( $this->override !== null ) {
			$conds['fpc_override'] = $this->override;
		}
		if ( $this->autoreview !== null ) {
			$conds['fpc_level'] = $this->autoreview;
		}
		$conds['page_namespace'] = $this->namespace;
		# Be sure not to include expired items
		$encCutoff = $this->mDb->addQuotes( $this->mDb->timestamp() );
		$conds[] = "fpc_expiry > {$encCutoff}";
		return [
			'tables' => [ 'flaggedpage_config', 'page' ],
			'fields' => [ 'page_namespace', 'page_title', 'fpc_override',
				'fpc_expiry', 'fpc_page_id', 'fpc_level' ],
			'conds'  => $conds,
			'options' => []
		];
	}

	public function getIndexField() {
		return 'fpc_page_id';
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

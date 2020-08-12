<?php

/**
 * Query to list out stable versions for a page
 */
class ConfiguredPagesPager extends AlphabeticPager {
	/** @var ConfiguredPages */
	private $mForm;

	/** @var array */
	private $mConds;

	/** @var int|null */
	private $namespace;

	/** @var int|null */
	private $override;

	/** @var string */
	private $autoreview;

	/**
	 * @param ConfiguredPages $form
	 * @param array $conds
	 * @param int|null $namespace (null for "all")
	 * @param int|null $override (null for "either")
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

	protected function doBatchLookups() {
		$lb = new LinkBatch();
		foreach ( $this->mResult as $row ) {
			$lb->add( $row->page_namespace, $row->page_title );
		}
		$lb->execute();
	}

	protected function getStartBody() {
		return '<ul>';
	}

	protected function getEndBody() {
		return '</ul>';
	}
}

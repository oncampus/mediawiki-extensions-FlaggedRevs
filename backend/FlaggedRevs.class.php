<?php

use MediaWiki\Edit\PreparedEdit;

/**
 * Class containing utility functions for a FlaggedRevs environment
 *
 * Class is lazily-initialized, calling load() as needed
 */
class FlaggedRevs {
	# Tag name/level config
	protected static $dimensions = [];
	protected static $minSL = [];
	protected static $minQL = [];
	protected static $minPL = [];
	protected static $qualityVersions = false;
	protected static $pristineVersions = false;
	protected static $tagRestrictions = [];
	protected static $binaryFlagging = true;
	# Namespace config
	protected static $reviewNamespaces = [];
	# Restriction levels/config
	protected static $restrictionLevels = [];
	# Autoreview config
	protected static $autoReviewConfig = 0;

	protected static $loaded = false;

	protected static function load() {
		if ( self::$loaded ) {
			return true;
		}
		if ( !FlaggedRevsSetup::isReady() ) { // sanity
			throw new Exception( 'FlaggedRevs config loaded too soon! Possibly before LocalSettings.php!' );
		}
		self::$loaded = true;

		# Make sure that the restriction levels are unique
		global $wgFlaggedRevsRestrictionLevels;
		self::$restrictionLevels = array_unique( $wgFlaggedRevsRestrictionLevels );
		self::$restrictionLevels = array_filter( self::$restrictionLevels, 'strlen' );

		# Make sure no talk namespaces are in review namespace
		global $wgFlaggedRevsNamespaces;
		foreach ( $wgFlaggedRevsNamespaces as $ns ) {
			if ( MWNamespace::isTalk( $ns ) ) {
				throw new Exception( 'FlaggedRevs given talk namespace in $wgFlaggedRevsNamespaces!' );
			} elseif ( $ns == NS_MEDIAWIKI ) {
				throw new Exception( 'FlaggedRevs given NS_MEDIAWIKI in $wgFlaggedRevsNamespaces!' );
			}
		}
		self::$reviewNamespaces = $wgFlaggedRevsNamespaces;

		# Handle $wgFlaggedRevsAutoReview settings
		global $wgFlaggedRevsAutoReview, $wgFlaggedRevsAutoReviewNew;
		if ( is_int( $wgFlaggedRevsAutoReview ) ) {
			self::$autoReviewConfig = $wgFlaggedRevsAutoReview;
		} else { // b/c
			if ( $wgFlaggedRevsAutoReview ) {
				self::$autoReviewConfig = FR_AUTOREVIEW_CHANGES;
			}
			wfWarn( '$wgFlaggedRevsAutoReview is now a bitfield instead of a boolean.' );
		}
		if ( isset( $wgFlaggedRevsAutoReviewNew ) ) { // b/c
			self::$autoReviewConfig = ( $wgFlaggedRevsAutoReviewNew )
				? self::$autoReviewConfig |= FR_AUTOREVIEW_CREATION
				: self::$autoReviewConfig & ~FR_AUTOREVIEW_CREATION;
			wfWarn( '$wgFlaggedRevsAutoReviewNew is deprecated; use $wgFlaggedRevsAutoReview.' );
		}

		// When using a simple config, we don't need to initialize the other settings
		if ( self::useSimpleConfig() ) {
			return true;
		}

		# Handle levelled tags
		global $wgFlaggedRevsTags, $wgFlaggedRevTags;
		$flaggedRevsTags = null;
		if ( isset( $wgFlaggedRevTags ) ) {
			$flaggedRevsTags = $wgFlaggedRevTags; // b/c
			wfWarn( 'Please use $wgFlaggedRevsTags instead of $wgFlaggedRevTags in config.' );
		} elseif ( isset( $wgFlaggedRevsTags ) ) {
			$flaggedRevsTags = $wgFlaggedRevsTags;
		}
		# Assume true, then set to false if needed
		if ( !empty( $flaggedRevsTags ) ) {
			self::$qualityVersions = true;
			self::$pristineVersions = true;
			self::$binaryFlagging = ( count( $flaggedRevsTags ) <= 1 );
		}
		foreach ( $flaggedRevsTags as $tag => $levels ) {
			# Sanity checks
			$safeTag = htmlspecialchars( $tag );
			if ( !preg_match( '/^[a-zA-Z]{1,20}$/', $tag ) || $safeTag !== $tag ) {
				throw new Exception( 'FlaggedRevs given invalid tag name!' );
			}
			# Define "quality" and "pristine" reqs
			if ( is_array( $levels ) ) {
				$minQL = $levels['quality'];
				$minPL = $levels['pristine'];
				$ratingLevels = $levels['levels'];
			# B/C, $levels is just an integer (minQL)
			} else {
				global $wgFlaggedRevPristine, $wgFlaggedRevValues;
				$ratingLevels = $wgFlaggedRevValues ?? 1;
				$minQL = $levels; // an integer
				$minPL = $wgFlaggedRevPristine ?? $ratingLevels + 1;
				wfWarn( 'Please update the format of $wgFlaggedRevsTags in config.' );
			}
			# Set FlaggedRevs tags
			self::$dimensions[$tag] = [];
			for ( $i = 0; $i <= $ratingLevels; $i++ ) {
				self::$dimensions[$tag][$i] = "{$tag}-{$i}";
			}
			if ( $ratingLevels > 1 ) {
				self::$binaryFlagging = false; // more than one level
			}
			# Sanity checks
			if ( !is_int( $minQL ) || !is_int( $minPL ) ) {
				throw new Exception( 'FlaggedRevs given invalid tag value!' );
			}
			if ( $minQL > $ratingLevels ) {
				self::$qualityVersions = false;
				self::$pristineVersions = false;
			}
			if ( $minPL > $ratingLevels ) {
				self::$pristineVersions = false;
			}
			self::$minQL[$tag] = max( $minQL, 1 );
			self::$minPL[$tag] = max( $minPL, 1 );
			self::$minSL[$tag] = 1;
		}

		# Handle restrictions on tags
		global $wgFlaggedRevsTagsRestrictions, $wgFlagRestrictions;
		if ( isset( $wgFlagRestrictions ) ) {
			self::$tagRestrictions = $wgFlagRestrictions; // b/c
			wfWarn( 'Please use $wgFlaggedRevsTagsRestrictions instead of $wgFlagRestrictions in config.' );
		} else {
			self::$tagRestrictions = $wgFlaggedRevsTagsRestrictions;
		}

		return true;
	}

	# ################ Basic config accessors #################

	/**
	 * Is there only one tag and it has only one level?
	 * @return bool
	 */
	public static function binaryFlagging() {
		self::load();
		return self::$binaryFlagging;
	}

	/**
	 * If there only one tag and it has only one level, return it
	 * @return string
	 */
	public static function binaryTagName() {
		self::load();
		if ( !self::binaryFlagging() ) {
			return null;
		}
		$tags = array_keys( self::$dimensions );
		return empty( $tags ) ? null : $tags[0];
	}

	/**
	 * Are quality versions enabled?
	 * @return bool
	 */
	public static function qualityVersions() {
		self::load();
		return self::$qualityVersions;
	}

	/**
	 * Are pristine versions enabled?
	 * @return bool
	 */
	public static function pristineVersions() {
		self::load();
		return self::$pristineVersions;
	}

	/**
	 * Get the highest review tier that is enabled
	 * @return int One of FR_PRISTINE,FR_QUALITY,FR_CHECKED
	 */
	public static function highestReviewTier() {
		self::load();
		if ( self::$pristineVersions ) {
			return FR_PRISTINE;
		} elseif ( self::$qualityVersions ) {
			return FR_QUALITY;
		}
		return FR_CHECKED;
	}

	/**
	 * Allow auto-review edits directly to the stable version by reviewers?
	 * @return bool
	 */
	public static function autoReviewEdits() {
		self::load();
		return ( self::$autoReviewConfig & FR_AUTOREVIEW_CHANGES ) != 0;
	}

	/**
	 * Auto-review new pages with the minimal level?
	 * @return bool
	 */
	public static function autoReviewNewPages() {
		self::load();
		return ( self::$autoReviewConfig & FR_AUTOREVIEW_CREATION ) != 0;
	}

	/**
	 * Auto-review of new pages or edits to pages enabled?
	 * @return bool
	 */
	public static function autoReviewEnabled() {
		return self::autoReviewEdits() || self::autoReviewNewPages();
	}

	/**
	 * Get the maximum level that $tag can be autoreviewed to
	 * @param string $tag
	 * @return int
	 */
	public static function maxAutoReviewLevel( $tag ) {
		global $wgFlaggedRevsTagsAuto;
		self::load();
		if ( !self::autoReviewEnabled() ) {
			return 0; // shouldn't happen
		}
		if ( isset( $wgFlaggedRevsTagsAuto[$tag] ) ) {
			return (int)$wgFlaggedRevsTagsAuto[$tag];
		} else {
			return 1; // B/C (before $wgFlaggedRevsTagsAuto)
		}
	}

	/**
	 * Is a "stable version" used as the default display
	 * version for all pages in reviewable namespaces?
	 * @return bool
	 */
	public static function isStableShownByDefault() {
		global $wgFlaggedRevsOverride;
		if ( self::useSimpleConfig() ) {
			return false; // must be configured per-page
		}
		return (bool)$wgFlaggedRevsOverride;
	}

	/**
	 * Are pages reviewable only if they have been manually
	 * configured by an admin to use a "stable version" as the default?
	 * @return bool
	 */
	public static function useOnlyIfProtected() {
		global $wgFlaggedRevsProtection;
		return (bool)$wgFlaggedRevsProtection;
	}

	/**
	 * Whether simple configuration settings should be used
	 * @return bool
	 */
	public static function useSimpleConfig() {
		return self::useOnlyIfProtected();
	}

	/**
	 * Return the include handling configuration
	 * @return int
	 */
	public static function inclusionSetting() {
		global $wgFlaggedRevsHandleIncludes;
		return $wgFlaggedRevsHandleIncludes;
	}

	/**
	 * Should tags only be shown for unreviewed content for this user?
	 * @return bool
	 */
	public static function lowProfileUI() {
		global $wgFlaggedRevsLowProfile;
		return $wgFlaggedRevsLowProfile;
	}

	/**
	 * Are there site defined protection levels for review
	 * @return bool
	 */
	public static function useProtectionLevels() {
		global $wgFlaggedRevsProtection;
		return $wgFlaggedRevsProtection && self::getRestrictionLevels();
	}

	/**
	 * Get the autoreview restriction levels available
	 * @return array
	 */
	public static function getRestrictionLevels() {
		self::load();
		return self::$restrictionLevels;
	}

	/**
	 * Get the array of tag dimensions and level messages
	 * @return array
	 */
	public static function getDimensions() {
		self::load();
		return self::$dimensions;
	}

	/**
	 * Get the associative array of tag dimensions
	 * (tags => [levels => msgkey])
	 * @return array
	 */
	public static function getTags() {
		self::load();
		return array_keys( self::$dimensions );
	}

	/**
	 * Get the associative array of tag restrictions
	 * (tags => [rights => levels])
	 * @return array
	 */
	public static function getTagRestrictions() {
		self::load();
		return self::$tagRestrictions;
	}

	/**
	 * Get the UI name for a tag
	 * @param string $tag
	 * @return string
	 */
	public static function getTagMsg( $tag ) {
		return wfMessage( "revreview-$tag" )->escaped();
	}

	/**
	 * Get the levels for a tag. Gives map of level to message name.
	 * @param string $tag
	 * @return array (integer -> string)
	 */
	public static function getTagLevels( $tag ) {
		self::load();
		return self::$dimensions[$tag] ?? [];
	}

	/**
	 * Get the UI name for a value of a tag
	 * @param string $tag
	 * @param int $value
	 * @return string
	 */
	public static function getTagValueMsg( $tag, $value ) {
		self::load();
		if ( !isset( self::$dimensions[$tag] ) ) {
			return '';
		} elseif ( !isset( self::$dimensions[$tag][$value] ) ) {
			return '';
		}
		# Return empty string if not there
		return wfMessage( 'revreview-' . self::$dimensions[$tag][$value] )->escaped();
	}

	/**
	 * Are there no actual dimensions?
	 * @return bool
	 */
	public static function dimensionsEmpty() {
		self::load();
		return empty( self::$dimensions );
	}

	/**
	 * Get corresponding text for the api output of flagging levels
	 *
	 * @param int $level
	 * @return string
	 */
	public static function getQualityLevelText( $level ) {
		static $levelText = [
			0 => 'stable',
			1 => 'quality',
			2 => 'pristine'
		];
		if ( isset( $levelText[$level] ) ) {
			return $levelText[$level];
		} else {
			return '';
		}
	}

	/**
	 * Get the 'diffonly=' value for diff URLs. Either ('1','0','')
	 * @return array
	 */
	public static function diffOnlyCGI() {
		$val = trim( wfMessage( 'flaggedrevs-diffonly' )->inContentLanguage()->text() );
		if ( strpos( $val, '&diffonly=1' ) !== false ) {
			return [ 'diffonly' => 1 ];
		} elseif ( strpos( $val, '&diffonly=0' ) !== false ) {
			return [ 'diffonly' => 0 ];
		}
		return [];
	}

	# ################ Permission functions #################

	/**
	 * Sanity check a (tag,value) pair
	 * @param string $tag
	 * @param int $value
	 * @return bool
	 */
	public static function tagIsValid( $tag, $value ) {
		$levels = self::getTagLevels( $tag );
		$highest = count( $levels ) - 1;
		if ( !$levels || $value < 0 || $value > $highest ) {
			return false; // flag range is invalid
		}
		return true;
	}

	/**
	 * Check if all of the required site flags have a valid value
	 * @param array $flags
	 * @return bool
	 */
	public static function flagsAreValid( array $flags ) {
		foreach ( self::getDimensions() as $qal => $levels ) {
			if ( !isset( $flags[$qal] ) || !self::tagIsValid( $qal, $flags[$qal] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns true if a user can set $tag to $value
	 * @param User $user
	 * @param string $tag
	 * @param int $value
	 * @return bool
	 */
	public static function userCanSetTag( $user, $tag, $value ) {
		# Sanity check tag and value
		if ( !self::tagIsValid( $tag, $value ) ) {
			return false; // flag range is invalid
		}
		$restrictions = self::getTagRestrictions();
		# No restrictions -> full access
		if ( !isset( $restrictions[$tag] ) ) {
			return true;
		}
		# Validators always have full access
		if ( $user->isAllowed( 'validate' ) ) {
			return true;
		}
		# Check if this user has any right that lets him/her set
		# up to this particular value
		foreach ( $restrictions[$tag] as $right => $level ) {
			if ( $value <= $level && $level > 0 && $user->isAllowed( $right ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns true if a user can set $flags for a revision via review.
	 * Requires the same for $oldflags if given.
	 * @param User $user
	 * @param array $flags suggested flags
	 * @param array $oldflags pre-existing flags
	 * @return bool
	 */
	public static function userCanSetFlags( $user, array $flags, $oldflags = [] ) {
		if ( !$user->isAllowed( 'review' ) ) {
			return false; // User is not able to review pages
		}
		# Check if all of the required site flags have
		# a valid value that the user is allowed to set...
		foreach ( self::getDimensions() as $qal => $levels ) {
			if ( !isset( $flags[$qal] ) ) {
				return false; // unspecified
			} elseif ( !self::userCanSetTag( $user, $qal, $flags[$qal] ) ) {
				return false; // user cannot set proposed flag
			} elseif ( isset( $oldflags[$qal] )
				&& !self::userCanSetTag( $user, $qal, $oldflags[$qal] )
			) {
				return false; // user cannot change old flag
			}
		}
		return true;
	}

	/**
	 * Check if a user can set the autoreview restiction level to $right
	 * @param User $user
	 * @param string $right the level
	 * @return bool
	 */
	public static function userCanSetAutoreviewLevel( $user, $right ) {
		if ( $right == '' ) {
			return true; // no restrictions (none)
		}
		if ( !in_array( $right, self::getRestrictionLevels() ) ) {
			return false; // invalid restriction level
		}
		# Don't let them choose levels above their own rights
		if ( $right == 'sysop' ) {
			// special case, rewrite sysop to editprotected
			if ( !$user->isAllowed( 'editprotected' ) ) {
				return false;
			}
		} elseif ( $right == 'autoconfirmed' ) {
			// special case, rewrite autoconfirmed to editsemiprotected
			if ( !$user->isAllowed( 'editsemiprotected' ) ) {
				return false;
			}
		} elseif ( !$user->isAllowed( $right ) ) {
			return false;
		}
		return true;
	}

	# ################ Parsing functions #################

	/**
	 * Get the HTML output of a revision, using PoolCounter in the process
	 *
	 * Returns a Status if pool is full, null if the revision is missing
	 *
	 * @param FlaggedRevision $frev
	 * @param ParserOptions $pOpts
	 * @return ParserOutput|Status|null
	 */
	public static function parseStableRevisionPooled(
		FlaggedRevision $frev, ParserOptions $pOpts
	) {
		$page = WikiPage::factory( $frev->getTitle() );
		$keyPrefix = FRParserCacheStable::singleton()->getKey( $page, $pOpts );
		$keyPrefix = $keyPrefix ?: wfMemcKey( 'articleview', 'missingcachekey' );

		$work = new PoolCounterWorkViaCallback(
			'ArticleView', // use standard parse PoolCounter config
			$keyPrefix . ':revid:' . $frev->getRevId(),
			[
				'doWork' => function () use ( $frev, $pOpts ) {
					return FlaggedRevs::parseStableRevision( $frev, $pOpts );
				},
				'doCachedWork' => function () use ( $page, $pOpts ) {
					// Use new cache value from other thread
					return FRParserCacheStable::singleton()->get( $page, $pOpts );
				},
				'fallback' => function () use ( $page, $pOpts ) {
					// Use stale cache if possible
					return FRParserCacheStable::singleton()->getDirty( $page, $pOpts );
				},
				'error' => function ( Status $status ) {
					return $status;
				},
			]
		);

		return $work->execute();
	}

	/**
	 * Get the HTML output of a revision.
	 * @param FlaggedRevision $frev
	 * @param ParserOptions $pOpts
	 * @return ParserOutput|null
	 */
	public static function parseStableRevision( FlaggedRevision $frev, ParserOptions $pOpts ) {
		# Notify Parser if includes should be stabilized
		$resetManager = false;
		$incManager = FRInclusionManager::singleton();
		if ( $frev->getRevId() && self::inclusionSetting() != FR_INCLUDES_CURRENT ) {
			# Use FRInclusionManager to do the template/file version query
			# up front unless the versions are already specified there...
			if ( !$incManager->parserOutputIsStabilized() ) {
				$incManager->stabilizeParserOutput( $frev );
				$resetManager = true; // need to reset when done
			}
		}
		# Parse the new body
		$content = $frev->getRevision()->getContent();
		if ( $content === null ) {
			return null; // missing revision
		}

		// Make this parse use reviewed/stable versions of templates
		$oldCurrentRevisionCallback = $pOpts->setCurrentRevisionCallback(
			function ( $title, $parser = false ) use ( &$oldCurrentRevisionCallback, $incManager ) {
				if ( !( $parser instanceof Parser ) ) {
					// nothing to do
					return call_user_func( $oldCurrentRevisionCallback, $title, $parser );
				}
				if ( $title->getNamespace() < 0 || $title->getNamespace() == NS_MEDIAWIKI ) {
					// nothing to do (bug 29579 for NS_MEDIAWIKI)
					return call_user_func( $oldCurrentRevisionCallback, $title, $parser );
				}
				if ( !$incManager->parserOutputIsStabilized() ) {
					// nothing to do
					return call_user_func( $oldCurrentRevisionCallback, $title, $parser );
				}
				$id = false; // current version
				# Check for the version of this template used when reviewed...
				$maybeId = $incManager->getReviewedTemplateVersion( $title );
				if ( $maybeId !== null ) {
					$id = (int)$maybeId; // use if specified (even 0)
				}
				# Check for stable version of template if this feature is enabled...
				if ( FlaggedRevs::inclusionSetting() == FR_INCLUDES_STABLE ) {
					$maybeId = $incManager->getStableTemplateVersion( $title );
					# Take the newest of these two...
					if ( $maybeId && $maybeId > $id ) {
						$id = (int)$maybeId;
					}
				}
				# Found a reviewed/stable revision
				if ( $id !== false ) {
					# If $id is zero, don't bother loading it (page does not exist)
					return $id === 0 ? null : Revision::newFromId( $id );
				}
				# Otherwise, fall back to default behavior (load latest revision)
				return call_user_func( $oldCurrentRevisionCallback, $title, $parser );
			}
		);

		$parserOut = $content->getParserOutput( $frev->getTitle(), $frev->getRevId(), $pOpts );
		# Stable parse done!
		if ( $resetManager ) {
			$incManager->clear(); // reset the FRInclusionManager as needed
		}
		$pOpts->setCurrentRevisionCallback( $oldCurrentRevisionCallback );
		return $parserOut;
	}

	# ################ Tracking/cache update update functions #################

	/**
	 * Update the page tables with a new stable version.
	 * @param WikiPage|Title $page
	 * @param FlaggedRevision|null $sv the new stable version (optional)
	 * @param FlaggedRevision|null $oldSv the old stable version (optional)
	 * @param PreparedEdit|null $editInfo Article edit info about the current revision (optional)
	 * @return bool stable version text/file changed and FR_INCLUDES_STABLE
	 * @throws Exception
	 */
	public static function stableVersionUpdates(
		$page, $sv = null, $oldSv = null, $editInfo = null
	) {
		if ( $page instanceof FlaggableWikiPage ) {
			$article = $page;
		} elseif ( $page instanceof WikiPage ) {
			$article = FlaggableWikiPage::getTitleInstance( $page->getTitle() );
		} elseif ( $page instanceof Title ) {
			$article = FlaggableWikiPage::getTitleInstance( $page );
		} else {
			throw new Exception( "First argument must be a Title or WikiPage." );
		}
		$title = $article->getTitle();

		$changed = false;
		if ( $oldSv === null ) { // optional
			$oldSv = FlaggedRevision::newFromStable( $title, FR_MASTER );
		}
		if ( $sv === null ) { // optional
			$sv = FlaggedRevision::determineStable( $title, FR_MASTER );
		}

		if ( !$sv ) {
			# Empty flaggedrevs data for this page if there is no stable version
			$article->clearStableVersion();
			# Check if pages using this need to be refreshed...
			if ( self::inclusionSetting() == FR_INCLUDES_STABLE ) {
				$changed = (bool)$oldSv;
			}
		} else {
			# Update flagged page related fields
			$article->updateStableVersion( $sv, $editInfo ? $editInfo->revid : null );
			# Check if pages using this need to be invalidated/purged...
			if ( self::inclusionSetting() == FR_INCLUDES_STABLE ) {
				$changed = (
					!$oldSv ||
					$sv->getRevId() != $oldSv->getRevId() ||
					$sv->getFileTimestamp() != $oldSv->getFileTimestamp() ||
					$sv->getFileSha1() != $oldSv->getFileSha1()
				);
			}
			# Update template/file version cache...
			if (
				$editInfo &&
				$sv->getRevId() != $editInfo->revid &&
				self::inclusionSetting() !== FR_INCLUDES_CURRENT
			) {
				FRInclusionCache::setRevIncludes( $title, $editInfo->revid, $editInfo->output );
			}
		}
		# Lazily rebuild dependencies on next parse (we invalidate below)
		self::clearStableOnlyDeps( $title->getArticleID() );
		# Clear page cache unless this is hooked via ArticleEditUpdates, in
		# which case these updates will happen already with tuned timestamps
		if ( !$editInfo ) {
			$title->invalidateCache();
			self::purgeSquid( $title );
		}

		return $changed;
	}

	/**
	 * Clear FlaggedRevs tracking tables for this page
	 * @param int|array $pageId (int or array)
	 */
	public static function clearTrackingRows( $pageId ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'flaggedpages', [ 'fp_page_id' => $pageId ], __METHOD__ );
		$dbw->delete( 'flaggedrevs_tracking', [ 'ftr_from' => $pageId ], __METHOD__ );
		$dbw->delete( 'flaggedpage_pending', [ 'fpp_page_id' => $pageId ], __METHOD__ );
	}

	/**
	 * @param Page $article
	 * @param ParserOutput $stableOut
	 * @param int $mode FRDependencyUpdate::DEFERRED/FRDependencyUpdate::IMMEDIATE
	 * Updates the stable-only cache dependency table
	 */
	public static function updateStableOnlyDeps( Page $article, ParserOutput $stableOut, $mode ) {
		$frDepUpdate = new FRDependencyUpdate( $article->getTitle(), $stableOut );
		$frDepUpdate->doUpdate( $mode );
	}

	/**
	 * Clear tracking table of stable-only links for this page
	 * @param int|array $pageId (int or array)
	 */
	public static function clearStableOnlyDeps( $pageId ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'flaggedrevs_tracking', [ 'ftr_from' => $pageId ], __METHOD__ );
	}

	/**
	 * @param Title $title
	 * Updates squid cache for a title. Defers till after main commit().
	 */
	public static function purgeSquid( Title $title ) {
		DeferredUpdates::addCallableUpdate( function () use ( $title ) {
			$title->purgeSquid();
			HTMLFileCache::clearFileCache( $title );
		} );
	}

	/**
	 * Do cache updates for when the stable version of a page changed.
	 * Invalidates/purges pages that include the given page.
	 * @param Title $title
	 */
	public static function HTMLCacheUpdates( Title $title ) {
		# Invalidate caches of articles which include this page...
		DeferredUpdates::addUpdate( new HTMLCacheUpdate( $title, 'templatelinks' ) );
		if ( $title->getNamespace() == NS_FILE ) {
			DeferredUpdates::addUpdate( new HTMLCacheUpdate( $title, 'imagelinks' ) );
		}
		DeferredUpdates::addUpdate( new FRExtraCacheUpdate( $title ) );
	}

	/**
	 * Invalidates/purges pages where only stable version includes this page.
	 * @param Title $title
	 */
	public static function extraHTMLCacheUpdate( Title $title ) {
		DeferredUpdates::addUpdate( new FRExtraCacheUpdate( $title ) );
	}

	# ################ Revision functions #################

	/**
	 * Mark a revision as patrolled if needed
	 * @param Revision $rev
	 * @return bool DB write query used
	 */
	public static function markRevisionPatrolled( Revision $rev ) {
		$rcid = $rev->isUnpatrolled();
		# Make sure it is now marked patrolled...
		if ( $rcid ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update( 'recentchanges',
				[ 'rc_patrolled' => 1 ],
				[ 'rc_id' => $rcid ],
				__METHOD__
			);
			return true;
		}
		return false;
	}

	# ################ Other utility functions #################

	/**
	 * Get a memcache storage object
	 * @param mixed $val
	 * @return Object (val,time) tuple
	 */
	public static function makeMemcObj( $val ) {
		$data = (object)[];
		$data->value = $val;
		$data->time = wfTimestampNow();
		return $data;
	}

	/**
	 * Return memc value if not expired
	 * @param object|bool $data makeMemcObj() tuple
	 * @param Page $article
	 * @param string $allowStale Use 'allowStale' to skip page_touched check
	 * @return mixed
	 */
	public static function getMemcValue( $data, Page $article, $allowStale = '' ) {
		if ( is_object( $data ) ) {
			if ( $allowStale === 'allowStale' || $data->time >= $article->getTouched() ) {
				return $data->value;
			}
		}
		return false;
	}

	/**
	 * @param array $flags
	 * @return bool is this revision at basic review condition?
	 */
	public static function isChecked( array $flags ) {
		self::load();
		return self::tagsAtLevel( $flags, self::$minSL );
	}

	/**
	 * @param array $flags
	 * @return bool is this revision at quality review condition?
	 */
	public static function isQuality( array $flags ) {
		self::load();
		return self::tagsAtLevel( $flags, self::$minQL );
	}

	/**
	 * @param array $flags
	 * @return bool is this revision at pristine review condition?
	 */
	public static function isPristine( array $flags ) {
		self::load();
		return self::tagsAtLevel( $flags, self::$minPL );
	}

	/**
	 * Checks if $flags meets $reqFlagLevels
	 * @param array $flags
	 * @param array $reqFlagLevels
	 * @return bool
	 */
	protected static function tagsAtLevel( array $flags, $reqFlagLevels ) {
		self::load();
		if ( empty( $flags ) ) {
			return false;
		}
		foreach ( self::$dimensions as $f => $x ) {
			if ( !isset( $flags[$f] ) || $reqFlagLevels[$f] > $flags[$f] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the quality tier of review flags
	 * @param array $flags
	 * @param int $default Return value if one of the tags has value < 0
	 * @return int flagging tier (FR_PRISTINE,FR_QUALITY,FR_CHECKED,-1)
	 */
	public static function getQualityTier( array $flags, $default = -1 ) {
		if ( self::isPristine( $flags ) ) {
			return FR_PRISTINE; // 2
		} elseif ( self::isQuality( $flags ) ) {
			return FR_QUALITY; // 1
		} elseif ( self::isChecked( $flags ) ) {
			return FR_CHECKED; // 0
		}
		return (int)$default;
	}

	/**
	 * Get minimum level tags for a tier
	 * @param int $tier FR_PRISTINE/FR_QUALITY/FR_CHECKED
	 * @return array
	 */
	public static function quickTags( $tier ) {
		self::load();
		if ( $tier == FR_PRISTINE ) {
			return self::$minPL;
		} elseif ( $tier == FR_QUALITY ) {
			return self::$minQL;
		}
		return self::$minSL;
	}

	/**
	 * Get minimum tags that are closest to $oldFlags
	 * given the site, page, and user rights limitations.
	 * @param User $user
	 * @param array $oldFlags previous stable rev flags
	 * @return mixed array or null
	 */
	public static function getAutoReviewTags( $user, array $oldFlags ) {
		if ( !self::autoReviewEdits() ) {
			return null; // shouldn't happen
		}
		$flags = [];
		foreach ( self::getTags() as $tag ) {
			# Try to keep this tag val the same as the stable rev's
			$val = $oldFlags[$tag] ?? 1;
			$val = min( $val, self::maxAutoReviewLevel( $tag ) );
			# Dial down the level to one the user has permission to set
			while ( !self::userCanSetTag( $user, $tag, $val ) ) {
				$val--;
				if ( $val <= 0 ) {
					return null; // all tags vals must be > 0
				}
			}
			$flags[$tag] = $val;
		}
		return $flags;
	}

	/**
	 * Get the list of reviewable namespaces
	 * @return array
	 */
	public static function getReviewNamespaces() {
		self::load(); // validates namespaces
		return self::$reviewNamespaces;
	}

	/**
	 * Is this page in reviewable namespace?
	 * Note: this checks $wgFlaggedRevsWhitelist
	 * @param Title $title
	 * @return bool
	 */
	public static function inReviewNamespace( Title $title ) {
		global $wgFlaggedRevsWhitelist;
		if ( in_array( $title->getPrefixedDBKey(), $wgFlaggedRevsWhitelist ) ) {
			return false; // page is one exemption whitelist
		}
		$ns = ( $title->getNamespace() == NS_MEDIA ) ?
			NS_FILE : $title->getNamespace(); // treat NS_MEDIA as NS_FILE
		return in_array( $ns, self::getReviewNamespaces() );
	}

	# ################ Auto-review function #################

	/**
	 * Automatically review an revision and add a log entry in the review log.
	 *
	 * This is called during edit operations after the new revision is added
	 * and the page tables updated, but before LinksUpdate is called.
	 *
	 * $auto is here for revisions checked off to be reviewed. Auto-review
	 * triggers on edit, but we don't want those to count as just automatic.
	 * This also makes it so the user's name shows up in the page history.
	 *
	 * If $flags is given, then they will be the review tags. If not, the one
	 * from the stable version will be used or minimal tags if that's not possible.
	 * If no appropriate tags can be found, then the review will abort.
	 * @param WikiPage $article
	 * @param User $user
	 * @param Revision $rev
	 * @param array|null $flags
	 * @param bool $auto
	 * @return true
	 */
	public static function autoReviewEdit(
		WikiPage $article, $user, Revision $rev, array $flags = null, $auto = true
	) {
		$title = $article->getTitle(); // convenience
		# Get current stable version ID (for logging)
		$oldSv = FlaggedRevision::newFromStable( $title, FR_MASTER );
		$oldSvId = $oldSv ? $oldSv->getRevId() : 0;

		if ( self::useSimpleConfig() ) {
			$flags = [];
			$quality = FR_CHECKED;
			$tags = '';
		} else {
			# Set the auto-review tags from the prior stable version.
			# Normally, this should already be done and given here...
			if ( !is_array( $flags ) ) {
				if ( $oldSv ) {
					# Use the last stable version if $flags not given
					if ( $user->isAllowed( 'bot' ) ) {
						$flags = $oldSv->getTags(); // no change for bot edits
					} else {
						# Account for perms/tags...
						$flags = self::getAutoReviewTags( $user, $oldSv->getTags() );
					}
				} else { // new page?
					$flags = self::quickTags( FR_CHECKED ); // use minimal level
				}
				if ( !is_array( $flags ) ) {
					return false; // can't auto-review this revision
				}
			}

			$quality = self::getQualityTier( $flags, FR_CHECKED /* sanity */ );
			$tags = FlaggedRevision::flattenRevisionTags( $flags );
		}

		# Note: this needs to match the prepareContentForEdit() call WikiPage::doEditContent.
		# This is for consistency and also to avoid triggering a second parse otherwise.
		$editInfo = $article->prepareContentForEdit(
			$rev->getContent(), null, $user, $rev->getContentFormat() );
		$poutput  = $editInfo->output; // revision HTML output

		# Get the "review time" versions of templates and files.
		# This tries to make sure each template/file version either came from the stable
		# version of that template/file or was a "review time" version used in the stable
		# version of this page. If a pending version of a template/file is currently vandalism,
		# we try to avoid storing its ID as the "review time" version so it won't show up when
		# someone views the page. If not possible, this stores the current template/file.
		if ( self::inclusionSetting() === FR_INCLUDES_CURRENT ) {
			$tVersions = $poutput->getTemplateIds();
			$fVersions = $poutput->getFileSearchOptions();
		} else {
			$tVersions = $oldSv ? $oldSv->getTemplateVersions() : [];
			$fVersions = $oldSv ? $oldSv->getFileVersions() : [];
			foreach ( $poutput->getTemplateIds() as $ns => $pages ) {
				foreach ( $pages as $dbKey => $revId ) {
					if ( !isset( $tVersions[$ns][$dbKey] ) ) {
						$srev = FlaggedRevision::newFromStable( Title::makeTitle( $ns, $dbKey ) );
						if ( $srev ) { // use stable
							$tVersions[$ns][$dbKey] = $srev->getRevId();
						} else { // use current
							$tVersions[$ns][$dbKey] = $revId;
						}
					}
				}
			}
		}
		foreach ( $poutput->getFileSearchOptions() as $dbKey => $info ) {
			if ( !isset( $fVersions[$dbKey] ) ) {
				$srev = FlaggedRevision::newFromStable( Title::makeTitle( NS_FILE, $dbKey ) );
				if ( $srev && $srev->getFileTimestamp() ) { // use stable
					$fVersions[$dbKey]['time'] = $srev->getFileTimestamp();
					$fVersions[$dbKey]['sha1'] = $srev->getFileSha1();
				} else { // use current
					$fVersions[$dbKey]['time'] = $info['time'];
					$fVersions[$dbKey]['sha1'] = $info['sha1'];
				}
			}
		}

		# If this is an image page, get the corresponding file version info...
		$fileData = [ 'name' => null, 'timestamp' => null, 'sha1' => null ];
		if ( $title->getNamespace() == NS_FILE ) {
			# We must use WikiFilePage process cache on upload or get bitten by slave lag
			$file = ( $article instanceof WikiFilePage || $article instanceof ImagePage )
				? $article->getFile() // uses up-to-date process cache on new uploads
				: wfFindFile( $title, [ 'bypassCache' => true ] ); // skip cache; bug 31056
			if ( is_object( $file ) && $file->exists() ) {
				$fileData['name'] = $title->getDBkey();
				$fileData['timestamp'] = $file->getTimestamp();
				$fileData['sha1'] = $file->getSha1();
			}
		}

		# Our review entry
		$flaggedRevision = new FlaggedRevision( [
			'rev'	      		=> $rev,
			'user_id'	       	=> $user->getId(),
			'timestamp'     	=> $rev->getTimestamp(), // same as edit time
			'quality'      	 	=> $quality,
			'tags'	       		=> $tags,
			'img_name'      	=> $fileData['name'],
			'img_timestamp' 	=> $fileData['timestamp'],
			'img_sha1'      	=> $fileData['sha1'],
			'templateVersions' 	=> $tVersions,
			'fileVersions'     	=> $fVersions,
			'flags'             => $auto ? 'auto' : '',
		], $title );
		$flaggedRevision->insert();
		# Update the article review log
		FlaggedRevsLog::updateReviewLog( $title,
			$flags, [], '', $rev->getId(), $oldSvId, true, $auto, $user );

		# Update page and tracking tables and clear cache
		self::stableVersionUpdates( $article );

		return true;
	}

	/**
	 * Get JS script params.
	 *
	 * These will be exported client-side as wgFlaggedRevsParams,
	 * for use by ext.flaggedRevs.review.js.
	 *
	 * @return array|null
	 */
	public static function getJSTagParams() {
		self::load();
		// Param to pass to JS function to know if tags are at quality level
		$tagsJS = [];
		foreach ( self::$dimensions as $tag => $x ) {
			$tagsJS[$tag] = [];
			$tagsJS[$tag]['levels'] = count( $x ) - 1;
			$tagsJS[$tag]['quality'] = self::$minQL[$tag];
			$tagsJS[$tag]['pristine'] = self::$minPL[$tag];
		}
		if ( $tagsJS ) {
			return [ 'tags' => $tagsJS ];
		} else {
			return null;
		}
	}
}

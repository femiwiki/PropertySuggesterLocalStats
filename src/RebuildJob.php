<?php

namespace MediaWiki\Extension\PropertySuggesterLocalStats;

use MediaWiki\JobQueue\GenericParameterJob;
use MediaWiki\JobQueue\Job;
use MediaWiki\MediaWikiServices;

/**
 * Rebuilds wbs_propertypairs. Jobs that are queued and not yet run are
 * merged, as one rebuild covers every edit before it.
 *
 * @license GPL-2.0-or-later
 */
class RebuildJob extends Job implements GenericParameterJob {

	public const TYPE = 'propertySuggesterLocalStatsRebuild';

	public function __construct( array $params = [] ) {
		parent::__construct( self::TYPE, $params );
		$this->removeDuplicates = true;
	}

	public function run(): bool {
		/** @var TableBuilder $tableBuilder */
		$tableBuilder = MediaWikiServices::getInstance()
			->getService( 'PropertySuggesterLocalStats.TableBuilder' );
		$tableBuilder->rebuild();
		return true;
	}

}

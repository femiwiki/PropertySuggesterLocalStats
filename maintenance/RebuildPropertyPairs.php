<?php

namespace MediaWiki\Extension\PropertySuggesterLocalStats\Maintenance;

use MediaWiki\Extension\PropertySuggesterLocalStats\TableBuilder;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Refills wbs_propertypairs from this wiki's own items.
 *
 * @license GPL-2.0-or-later
 */
class RebuildPropertyPairs extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Refill PropertySuggester's property pair table from this wiki's items" );
		$this->requireExtension( 'PropertySuggesterLocalStats' );
	}

	public function execute() {
		$services = $this->getServiceContainer();
		/** @var TableBuilder $tableBuilder */
		$tableBuilder = $services->getService( 'PropertySuggesterLocalStats.TableBuilder' );
		$rows = $tableBuilder->rebuild();
		$this->output( "Wrote $rows rows.\n" );
	}

}

// @codeCoverageIgnoreStart
$maintClass = RebuildPropertyPairs::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd

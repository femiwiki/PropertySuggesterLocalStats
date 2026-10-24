<?php

namespace MediaWiki\Extension\PropertySuggesterLocalStats\Tests;

use MediaWiki\Extension\PropertySuggesterLocalStats\Maintenance\RebuildPropertyPairs;
use MediaWiki\Extension\PropertySuggesterLocalStats\RebuildJob;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\PropertySuggesterLocalStats\Maintenance\RebuildPropertyPairs
 * @group Database
 * @license GPL-2.0-or-later
 */
class RebuildPropertyPairsTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return RebuildPropertyPairs::class;
	}

	public function testExecute() {
		$this->maintenance->execute();
		$this->expectOutputString( "Wrote 0 rows.\n" );
	}

	public function testQueue() {
		$this->maintenance->loadWithArgv( [ '--queue' ] );
		$this->maintenance->execute();
		$this->expectOutputString( "Queued a rebuild.\n" );
		$this->assertSame( 1, $this->getServiceContainer()->getJobQueueGroup()
			->get( RebuildJob::TYPE )->getSize() );
	}

}

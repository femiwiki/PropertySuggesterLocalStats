<?php

namespace MediaWiki\Extension\PropertySuggesterLocalStats\Tests;

use MediaWiki\Extension\PropertySuggesterLocalStats\RebuildJob;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\PropertySuggesterLocalStats\RebuildJob
 * @group Database
 * @license GPL-2.0-or-later
 */
class RebuildJobTest extends MediaWikiIntegrationTestCase {

	public function testRun() {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'wbs_propertypairs' )
			->row( [ 'pid1' => 9, 'qid1' => 0, 'pid2' => 8, 'count' => 1, 'probability' => 1.0, 'context' => 'item' ] )
			->caller( __METHOD__ )
			->execute();

		$this->assertTrue( ( new RebuildJob() )->run() );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'wbs_propertypairs' )
			->assertFieldValue( '0' );
	}

	public function testRemovesDuplicates() {
		$this->assertTrue( ( new RebuildJob() )->ignoreDuplicates() );
	}

}

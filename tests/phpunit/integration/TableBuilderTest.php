<?php

namespace MediaWiki\Extension\PropertySuggesterLocalStats\Tests;

use MediaWiki\Extension\PropertySuggesterLocalStats\TableBuilder;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers \MediaWiki\Extension\PropertySuggesterLocalStats\TableBuilder
 * @group Database
 * @license GPL-2.0-or-later
 */
class TableBuilderTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'PropertySuggesterClassifyingPropertyIds' => [ 1 ],
			'PropertySuggesterLocalStatsMinProbability' => 0.01,
		] );
	}

	private function saveItem( Statement ...$statements ): void {
		WikibaseRepo::getEntityStore()->saveEntity(
			new Item( null, null, null, new StatementList( ...$statements ) ),
			'',
			$this->getTestUser()->getUser(),
			EDIT_NEW
		);
	}

	private function getTableBuilder(): TableBuilder {
		return $this->getServiceContainer()->getService( 'PropertySuggesterLocalStats.TableBuilder' );
	}

	public function testRebuild() {
		$instanceOf = new Statement( new PropertyValueSnak(
			new NumericPropertyId( 'P1' ),
			new EntityIdValue( new ItemId( 'Q5' ) )
		) );
		$this->saveItem( $instanceOf, new Statement( new PropertySomeValueSnak( new NumericPropertyId( 'P3' ) ) ) );
		$this->saveItem( $instanceOf );
		// Left from an earlier rebuild, which this one replaces
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'wbs_propertypairs' )
			->row( [ 'pid1' => 9, 'qid1' => 0, 'pid2' => 8, 'count' => 1, 'probability' => 1.0, 'context' => 'item' ] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame( 2, $this->getTableBuilder()->rebuild() );

		$this->newSelectQueryBuilder()
			->select( [ 'pid1', 'qid1', 'pid2', 'count', 'context' ] )
			->from( 'wbs_propertypairs' )
			->orderBy( [ 'pid1', 'pid2' ] )
			->assertResultSet( [
				[ '1', '5', '3', '1', 'item' ],
				[ '3', '0', '1', '1', 'item' ],
			] );
		$this->newSelectQueryBuilder()
			->select( 'probability' )
			->from( 'wbs_propertypairs' )
			->where( [ 'pid1' => 1 ] )
			->assertFieldValue( '0.5' );
	}

	public function testRebuildWithoutItems() {
		$this->assertSame( 0, $this->getTableBuilder()->rebuild() );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'wbs_propertypairs' )
			->assertFieldValue( '0' );
	}

}

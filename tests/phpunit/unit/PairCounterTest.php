<?php

namespace MediaWiki\Extension\PropertySuggesterLocalStats\Tests;

use MediaWiki\Extension\PropertySuggesterLocalStats\PairCounter;
use MediaWikiUnitTestCase;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;

/**
 * @covers \MediaWiki\Extension\PropertySuggesterLocalStats\PairCounter
 * @license GPL-2.0-or-later
 */
class PairCounterTest extends MediaWikiUnitTestCase {

	private function instanceOf( int $qid ): Statement {
		return new Statement( new PropertyValueSnak(
			new NumericPropertyId( 'P1' ),
			new EntityIdValue( new ItemId( "Q$qid" ) )
		) );
	}

	private function some( int $pid, ?SnakList $qualifiers = null, ?ReferenceList $references = null ): Statement {
		return new Statement(
			new PropertySomeValueSnak( new NumericPropertyId( "P$pid" ) ),
			$qualifiers,
			$references
		);
	}

	private function rowsByKey( PairCounter $counter, float $min = 0.0 ): array {
		$rows = [];
		foreach ( $counter->getRows( $min ) as $row ) {
			$key = "{$row['context']} {$row['pid1']}/{$row['qid1']} {$row['pid2']}";
			$rows[$key] = [ $row['count'], $row['probability'] ];
		}
		ksort( $rows );
		return $rows;
	}

	public function testItemPairs() {
		$counter = new PairCounter( [ 1 ] );
		$counter->addItemStatements( new StatementList(
			$this->instanceOf( 5 ), $this->some( 3 ), $this->some( 4 )
		) );
		$counter->addItemStatements( new StatementList(
			$this->instanceOf( 5 ), $this->some( 3 )
		) );

		$this->assertSame( [
			'item 1/5 3' => [ 2, 1.0 ],
			'item 1/5 4' => [ 1, 0.5 ],
			'item 3/0 1' => [ 2, 1.0 ],
			'item 3/0 4' => [ 1, 0.5 ],
			'item 4/0 1' => [ 1, 1.0 ],
			'item 4/0 3' => [ 1, 1.0 ],
		], $this->rowsByKey( $counter ) );
	}

	public function testRepeatedPropertyCountsOnce() {
		$counter = new PairCounter( [] );
		$counter->addItemStatements( new StatementList(
			$this->some( 3 ), $this->some( 3 ), $this->some( 4 )
		) );

		$this->assertSame( [
			'item 3/0 4' => [ 1, 1.0 ],
			'item 4/0 3' => [ 1, 1.0 ],
		], $this->rowsByKey( $counter ) );
	}

	public function testQualifiersAndReferences() {
		$counter = new PairCounter( [] );
		$qualifiers = new SnakList( [
			new PropertyNoValueSnak( new NumericPropertyId( 'P7' ) ),
			new PropertyNoValueSnak( new NumericPropertyId( 'P7' ) ),
		] );
		$references = new ReferenceList( [
			new Reference( [ new PropertyNoValueSnak( new NumericPropertyId( 'P8' ) ) ] ),
		] );
		$counter->addItemStatements( new StatementList(
			$this->some( 3, $qualifiers, $references ),
			$this->some( 3 )
		) );

		$this->assertSame( [
			'qualifier 3/0 7' => [ 1, 1.0 ],
			'reference 3/0 8' => [ 1, 1.0 ],
		], $this->rowsByKey( $counter ) );
	}

	public function testMinProbability() {
		$counter = new PairCounter( [] );
		$counter->addItemStatements( new StatementList( $this->some( 3 ), $this->some( 4 ) ) );
		$counter->addItemStatements( new StatementList( $this->some( 3 ) ) );

		$this->assertSame( [
			'item 4/0 3' => [ 1, 1.0 ],
		], $this->rowsByKey( $counter, 0.5 ) );
	}

}

<?php

namespace MediaWiki\Extension\PropertySuggesterLocalStats;

use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\StatementList;

/**
 * Counts which properties appear together, the way property-suggester-scripts
 * does for a Wikidata dump, and turns the counts into wbs_propertypairs rows.
 *
 * @license GPL-2.0-or-later
 */
class PairCounter {

	/** @var array<int,true> */
	private array $classifyingPropertyIds;

	/** @var array<string,int> "pid1|qid1" => number of items */
	private array $itemOccurrences = [];

	/** @var array<string,array<int,int>> "pid1|qid1" => pid2 => number of items */
	private array $itemPairs = [];

	/** @var array<string,array<int,int>> context => main pid => number of statements */
	private array $mainOccurrences = [ 'qualifier' => [], 'reference' => [] ];

	/** @var array<string,array<int,array<int,int>>> context => main pid => pid2 => number of statements */
	private array $snakPairs = [ 'qualifier' => [], 'reference' => [] ];

	/**
	 * @param int[] $classifyingPropertyIds Properties counted per value, such as
	 *  "instance of", as in $wgPropertySuggesterClassifyingPropertyIds
	 */
	public function __construct( array $classifyingPropertyIds ) {
		$this->classifyingPropertyIds = array_fill_keys( $classifyingPropertyIds, true );
	}

	public function addItemStatements( StatementList $statements ): void {
		$pids = [];
		$classes = [];
		foreach ( $statements->toArray() as $statement ) {
			$snak = $statement->getMainSnak();
			$pid = $this->numericId( $snak );
			if ( $pid === null ) {
				continue;
			}
			$pids[$pid] = true;
			$qid = $this->classValue( $snak );
			if ( $qid !== null ) {
				$classes[$pid][$qid] = true;
			}

			$this->addSnaks( 'qualifier', $pid, $statement->getQualifiers() );
			$references = [];
			foreach ( $statement->getReferences() as $reference ) {
				foreach ( $reference->getSnaks() as $referenceSnak ) {
					$references[] = $referenceSnak;
				}
			}
			$this->addSnaks( 'reference', $pid, new SnakList( $references ) );
		}

		$pids = array_keys( $pids );
		foreach ( $pids as $pid1 ) {
			if ( !isset( $this->classifyingPropertyIds[$pid1] ) ) {
				$this->addItemPairs( "$pid1|", $pid1, $pids );
			}
		}
		foreach ( $classes as $pid1 => $qids ) {
			foreach ( array_keys( $qids ) as $qid ) {
				$this->addItemPairs( "$pid1|$qid", $pid1, $pids );
			}
		}
	}

	/**
	 * @param string $key "pid1|qid1", with qid1 empty for a property counted as a whole
	 * @param int $pid1
	 * @param int[] $pids
	 */
	private function addItemPairs( string $key, int $pid1, array $pids ): void {
		$this->itemOccurrences[$key] = ( $this->itemOccurrences[$key] ?? 0 ) + 1;
		foreach ( $pids as $pid2 ) {
			if ( $pid2 !== $pid1 ) {
				$this->itemPairs[$key][$pid2] = ( $this->itemPairs[$key][$pid2] ?? 0 ) + 1;
			}
		}
	}

	private function addSnaks( string $context, int $mainPid, SnakList $snaks ): void {
		$pids = [];
		foreach ( $snaks as $snak ) {
			$pid = $this->numericId( $snak );
			if ( $pid !== null ) {
				$pids[$pid] = true;
			}
		}
		if ( !$pids ) {
			return;
		}
		$this->mainOccurrences[$context][$mainPid] = ( $this->mainOccurrences[$context][$mainPid] ?? 0 ) + 1;
		foreach ( array_keys( $pids ) as $pid2 ) {
			$this->snakPairs[$context][$mainPid][$pid2] =
				( $this->snakPairs[$context][$mainPid][$pid2] ?? 0 ) + 1;
		}
	}

	private function numericId( Snak $snak ): ?int {
		$id = $snak->getPropertyId();
		return $id instanceof NumericPropertyId ? $id->getNumericId() : null;
	}

	private function classValue( Snak $snak ): ?int {
		$pid = $this->numericId( $snak );
		if ( $pid === null || !$snak instanceof PropertyValueSnak ||
			!isset( $this->classifyingPropertyIds[$pid] )
		) {
			return null;
		}
		$value = $snak->getDataValue();
		if ( !$value instanceof EntityIdValue ) {
			return null;
		}
		$entityId = $value->getEntityId();
		return $entityId instanceof ItemId ? $entityId->getNumericId() : null;
	}

	/**
	 * @param float $minProbability Rows at or below this are left out
	 * @return array[] Rows for wbs_propertypairs
	 */
	public function getRows( float $minProbability ): array {
		$rows = [];
		foreach ( $this->itemPairs as $key => $pairs ) {
			[ $pid1, $qid1 ] = explode( '|', $key );
			$total = $this->itemOccurrences[$key];
			foreach ( $pairs as $pid2 => $count ) {
				$rows[] = $this->row( (int)$pid1, (int)$qid1,
					$pid2, $count, $count / $total, 'item' );
			}
		}
		foreach ( $this->snakPairs as $context => $byMain ) {
			foreach ( $byMain as $pid1 => $pairs ) {
				$total = $this->mainOccurrences[$context][$pid1];
				foreach ( $pairs as $pid2 => $count ) {
					$rows[] = $this->row( $pid1, 0, $pid2, $count, $count / $total, $context );
				}
			}
		}
		return array_values( array_filter(
			$rows,
			static fn ( array $row ) => $row['probability'] > $minProbability
		) );
	}

	/**
	 * @param int $pid1
	 * @param int $qid1 0 unless pid1 is counted per value, as PropertySuggester
	 *  looks such rows up by qid1 = 0
	 * @param int $pid2
	 * @param int $count
	 * @param float $probability
	 * @param string $context
	 * @return array
	 */
	private function row( int $pid1, int $qid1, int $pid2, int $count, float $probability, string $context ): array {
		return [
			'pid1' => $pid1,
			'qid1' => $qid1,
			'pid2' => $pid2,
			'count' => $count,
			'probability' => $probability,
			'context' => $context,
		];
	}

}

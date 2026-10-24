<?php

namespace MediaWiki\Extension\PropertySuggesterLocalStats;

use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\Lib\Store\EntityNamespaceLookup;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

/**
 * Fills wbs_propertypairs from this wiki's own items.
 *
 * @license GPL-2.0-or-later
 */
class TableBuilder {

	private const TABLE = 'wbs_propertypairs';

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly EntityLookup $entityLookup,
		private readonly EntityNamespaceLookup $entityNamespaceLookup,
		private readonly array $classifyingPropertyIds,
		private readonly float $minProbability,
		private readonly int $batchSize = 500,
	) {
	}

	/**
	 * @return int Number of rows written
	 */
	public function rebuild(): int {
		$counter = new PairCounter( $this->classifyingPropertyIds );
		foreach ( $this->getItemIds() as $itemId ) {
			$item = $this->entityLookup->getEntity( $itemId );
			if ( $item instanceof Item ) {
				$counter->addItemStatements( $item->getStatements() );
			}
		}
		$rows = $counter->getRows( $this->minProbability );

		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__, IDatabase::ATOMIC_CANCELABLE );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE )
			->where( IDatabase::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
		foreach ( array_chunk( $rows, $this->batchSize ) as $batch ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( self::TABLE )
				->rows( $batch )
				->caller( __METHOD__ )
				->execute();
		}
		$dbw->endAtomic( __METHOD__ );

		return count( $rows );
	}

	/**
	 * @return iterable<ItemId>
	 */
	private function getItemIds(): iterable {
		$namespace = $this->entityNamespaceLookup->getEntityNamespace( Item::ENTITY_TYPE );
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$after = 0;
		do {
			$query = $dbr->newSelectQueryBuilder()
				->select( [ 'page_id', 'page_title' ] )
				->from( 'page' )
				->where( [
					'page_namespace' => $namespace,
					'page_is_redirect' => 0,
					$dbr->expr( 'page_id', '>', $after ),
				] )
				->orderBy( 'page_id' )
				->limit( $this->batchSize )
				->caller( __METHOD__ );
			$titles = [];
			foreach ( $query->fetchResultSet() as $row ) {
				$titles[] = $row->page_title;
				$after = (int)$row->page_id;
			}
			foreach ( $titles as $title ) {
				if ( preg_match( '/^Q[1-9]\d*$/', $title ) ) {
					yield new ItemId( $title );
				}
			}
		} while ( count( $titles ) === $this->batchSize );
	}

}

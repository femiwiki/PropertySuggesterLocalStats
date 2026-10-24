<?php

use MediaWiki\Extension\PropertySuggesterLocalStats\TableBuilder;
use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;

/** @phpcs-require-sorted-array */
return [
	'PropertySuggesterLocalStats.TableBuilder' => static function ( MediaWikiServices $services ): TableBuilder {
		$config = $services->getMainConfig();
		return new TableBuilder(
			$services->getConnectionProvider(),
			WikibaseRepo::getEntityLookup( $services ),
			WikibaseRepo::getEntityNamespaceLookup( $services ),
			$config->get( 'PropertySuggesterClassifyingPropertyIds' ),
			(float)$config->get( 'PropertySuggesterLocalStatsMinProbability' )
		);
	},
];

# PropertySuggesterLocalStats

A MediaWiki extension that fills the statistics of
[PropertySuggester](https://www.mediawiki.org/wiki/Extension:PropertySuggester)
from the wiki's own Wikibase items.

PropertySuggester suggests properties from the `wbs_propertypairs` table, and
it suggests nothing while the table is empty. The extension itself only loads
that table from a CSV file, which
[property-suggester-scripts](https://gerrit.wikimedia.org/r/plugins/gitiles/wikibase/property-suggester-scripts/)
makes from a Wikidata JSON dump with Python. This extension counts the same
pairs in PHP straight from the wiki's items, so a Wikibase other than Wikidata
needs neither the dump nor Python.

## License

GPL-2.0-or-later

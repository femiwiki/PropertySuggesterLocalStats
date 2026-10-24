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

## What it counts

The counts follow property-suggester-scripts:

- `item`: for each property on an item, how often each other property is on
  the same item. A property listed in `$wgPropertySuggesterClassifyingPropertyIds`
  (such as "instance of") is counted for each of its item values instead.
- `qualifier`: for each statement property, how often each qualifier property
  appears, among the statements that have qualifiers.
- `reference`: the same as `qualifier`, for reference properties.

## License

GPL-2.0-or-later

## ADDED Requirements

### Requirement: Explicit full and bounded partial selection

The category exporter SHALL treat an originally empty ID list as intentional full export. A nonempty input MUST remain bounded to the existing categories resolved from that input and their existing parent expansion; an unresolved nonempty input MUST NOT become an unrestricted category query and selects no categories.

#### Scenario: Existing category with parents
- **WHEN** a partial export selects an existing category
- **THEN** export includes that category and the parents returned by existing path resolution
- **AND** every page query retains the resolved nonempty ID filter

#### Scenario: Intentional full export
- **WHEN** the original input IDs are `[]`
- **THEN** export retains the existing unrestricted, paginated category selection
- **AND** parent resolution is not required

#### Scenario: Mixed existing and missing IDs
- **WHEN** a partial export contains an existing category ID and a missing ID
- **THEN** export retains the existing behavior of selecting found categories and their parents
- **AND** missing IDs do not cause unrestricted selection or rejection of the found subset

### Requirement: Unresolved row selection removes stale documents

When a nonempty input resolves to no eligible category IDs, the exporter SHALL yield no documents without a category page query and without raising an error. Row indexing (scheduled MView updates and list/row reindexing) MUST then complete normally: it runs transaction-key cleanup restricted to the requested IDs and dispatches the successful indexing event, so deleted categories leave the index and the Magento view version advances.

#### Scenario: Deleted-only changelog batch
- **WHEN** row indexing receives only IDs of deleted categories, such as `[50]`
- **THEN** no category page query or document write occurs
- **AND** cleanup removes stale documents restricted to the requested IDs
- **AND** `VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER` is dispatched with the requested IDs and the update returns normally

#### Scenario: Deleted unassigned root with the tree root
- **WHEN** row indexing receives `[1, 48, 49]` after root category 48 and its child 49 are deleted
- **THEN** the batch resolves to no eligible IDs and does not become an unrestricted category query
- **AND** cleanup and the successful indexing event occur as for a deleted-only batch

### Requirement: Single-entity command rejects a missing category

`vsbridge:index` for the category indexer SHALL check the requested category before reindexing. When the ID resolves to no eligible category, the command MUST exit nonzero with a readable lookup error identifying the ID, before the category generator, document writes, transaction cleanup, and the successful indexing event.

#### Scenario: Single missing category
- **WHEN** `vsbridge:index vsbridge_category_indexer 3 2147483647` selects a nonexistent category
- **THEN** command execution reports a lookup error containing the requested ID and exits nonzero
- **AND** no category page query, document write, transaction cleanup, or `VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER` event occurs
- **AND** no cache request is triggered by a successful indexing event for this operation

#### Scenario: Existing category through the command
- **WHEN** `vsbridge:index vsbridge_category_indexer 3 <existing ID>` selects an existing category
- **THEN** the command indexes the category and its parents and exits successfully

#### Scenario: Other indexers through the command
- **WHEN** `vsbridge:index` runs for an indexer without a registered entity check
- **THEN** the command keeps its existing behavior for that indexer

### Requirement: Symfony Console 7 command compatibility

All package console commands SHALL load and register under Symfony Console 7 with compatible overridden method signatures. Existing command names, arguments, options, return codes, and exception propagation MUST remain unchanged.

#### Scenario: Register package commands
- **WHEN** `vsbridge:index`, `vsbridge:reset`, and `vsbridge:reindex` are loaded and registered with Symfony Console 7
- **THEN** registration succeeds without incompatible method declarations
- **AND** command execution returns integer status codes

#### Scenario: Missing category on Symfony Console 7
- **WHEN** the single-entity command selects a missing category under Symfony Console 7
- **THEN** the existing missing-category regression runs without a compatibility skip
- **AND** the lookup error propagates as a nonzero result without successful indexing side effects

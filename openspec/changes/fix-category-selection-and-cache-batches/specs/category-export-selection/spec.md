## ADDED Requirements

### Requirement: Explicit full and bounded partial selection

The category exporter SHALL treat an originally empty ID list as intentional full export. A nonempty input MUST remain bounded to the existing categories resolved from that input and their existing parent expansion; an unresolved nonempty input MUST NOT become an unrestricted category query.

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

### Requirement: Missing selection fails without success side effects

The exporter SHALL raise a readable lookup error identifying the requested IDs when a nonempty input resolves to no eligible category IDs. The single-entity console command MUST fail with a nonzero exit code. The failure MUST precede category document writes, transaction cleanup, and the successful indexing event, preventing cache invalidation driven by that event.

#### Scenario: Single missing category
- **WHEN** `vsbridge:index vsbridge_category_indexer 3 2147483647` selects a nonexistent category
- **THEN** command execution reports a lookup error containing the requested ID and exits nonzero
- **AND** no category page query, document write, transaction cleanup, or `VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER` event occurs
- **AND** no cache request is triggered by a successful indexing event for this operation

#### Scenario: Entire partial selection is missing
- **WHEN** a nonempty list of multiple missing category IDs resolves to no eligible IDs
- **THEN** export fails before a category page query or document write
- **AND** no successful indexing event occurs

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

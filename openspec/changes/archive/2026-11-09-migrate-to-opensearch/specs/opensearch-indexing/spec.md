## ADDED Requirements

### Requirement: Typeless OpenSearch requests

Mapping requests SHALL send the mapping body directly without a mapping-type wrapper, `type` parameter, or `include_type_name`. Bulk indexing/deletion metadata SHALL omit `_type`. Delete-by-query SHALL omit the mapping-type parameter. Valid field-type declarations MUST be preserved.

#### Scenario: Creating an entity mapping
- **WHEN** a product or another entity index is created
- **THEN** the mapping body contains its `properties` directly and the request targets the index mapping endpoint without a mapping type
- **AND** field definitions retain their configured data types

#### Scenario: Bulk operations
- **WHEN** documents are indexed or deleted in bulk
- **THEN** action metadata identifies the index and document ID without `_type`
- **AND** bulk serialization retains valid action/document boundaries

#### Scenario: Transaction-key cleanup
- **WHEN** stale documents are cleaned using a transaction key, optionally restricted to document IDs
- **THEN** the query targets only the entity-specific index without a type parameter
- **AND** documents outside the requested cleanup selection remain intact

### Requirement: One entity index per store

The indexer SHALL use the existing entity-specific index and alias naming behavior for every configured entity/store. Persisted legacy engine settings MUST NOT select the shared Elasticsearch 5 layout. Existing store ID/code and default-store naming options SHALL remain effective.

#### Scenario: Legacy engine setting remains stored
- **WHEN** an installation retains an `elasticsearch5` engine value after upgrade
- **THEN** indexing uses the entity-specific OpenSearch layout
- **AND** migration documentation explains that the old engine selection is obsolete

#### Scenario: Equal IDs in different entities
- **WHEN** a product and category share the same document ID in one store
- **THEN** each is written to its own entity index and neither overwrites the other

#### Scenario: Existing entity-specific aliases
- **WHEN** an installation already uses the entity-specific alias naming convention
- **THEN** the migration preserves that naming convention, including configured store identifiers

### Requirement: Full and incremental indexing

Both the rebuild command and individual full index actions SHALL create/populate entity-specific indexes. Incremental updates, deletes, and stale-document cleanup SHALL operate on the corresponding active entity index. A disabled indexer SHALL retain its existing no-indexing behavior.

#### Scenario: Repeated full rebuild
- **WHEN** full indexing runs twice for enabled entities and stores
- **THEN** each run builds the intended entity indexes and the second run exercises alias replacement

#### Scenario: Incremental update and deletion
- **WHEN** an existing entity is changed or removed after a full rebuild
- **THEN** the corresponding active index reflects the update or deletion
- **AND** indexes for other entities and stores remain unaffected

### Requirement: Bulk diagnostics without mapping types

Bulk response processing SHALL identify failed items and aggregate errors without requiring `_type`. Diagnostics MUST retain the index, operation, reason, count, and document ID samples. Item failures MUST make a full rebuild unsuccessful and prevent publication of the incomplete replacement index.

#### Scenario: Mixed successful and failed bulk items
- **WHEN** a bulk response contains successful and failed items without `_type`
- **THEN** error processing reports the failed items without undefined-key errors
- **AND** the failed batch prevents replacement of the current alias

### Requirement: Safe alias rollover and settings restoration

A completed replacement index SHALL be refreshed before an atomic alias update. Superseded indexes SHALL be deleted only after successful alias update. A failed mapping, bulk operation, refresh, or alias update MUST leave the previous active index accessible through its alias. Temporary indexing settings MUST be restored after successful and failed batches without hiding the original error.

#### Scenario: Successful rollover
- **WHEN** a replacement index has completed indexing and refresh
- **THEN** the alias is switched atomically to that index
- **AND** superseded indexes are eligible for deletion only after the switch succeeds

#### Scenario: Rebuild failure
- **WHEN** mapping creation, indexing, refresh, or alias update fails
- **THEN** the previous active alias remains usable and its index is not deleted
- **AND** temporary refresh/replica settings on surviving indexes are restored

### Requirement: Preserve entity extension contracts

Logical entity identifiers, mapping extension events, document contents, and cache invalidation contracts SHALL remain usable after protocol mapping types are removed.

#### Scenario: Product indexing extension
- **WHEN** an observer extends product mapping or indexing emits cache invalidation events
- **THEN** the existing entity identifier and event contract are preserved
- **AND** the resulting mapping is sent using the typeless protocol

### Requirement: Documented fresh-index migration

Migration documentation SHALL describe rebuilding enabled entities/stores into fresh OpenSearch indexes, validating data and external VSF API queries, coordinating reader/writer cutover and writes during migration, and rollback to a retained previous cluster. It MUST distinguish a supported client/server range from combinations actually tested.

#### Scenario: Migrating a shared-index installation
- **WHEN** an operator follows the migration procedure from the old shared-index layout
- **THEN** the procedure identifies the new entity-specific aliases and required consumer changes
- **AND** production cutover requires successful data/query validation and a defined rollback path

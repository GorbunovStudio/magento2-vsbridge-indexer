## Context

The root currently requires Elasticsearch PHP `~8.0` and the user's working tree adds OpenSearch PHP `2.4.5`. The wrapper uses `Elastic\Elasticsearch` response objects, but mapping requests, bulk metadata, and delete-by-query still contain mapping types. Seven module manifests independently require Elasticsearch `~5.0`.

Configuration selects either a shared Elasticsearch 5 index or entity-specific Elasticsearch 6+ indexes. This affects parsing, alias naming, the generic handler, full reindex actions, and the rebuild command. Only the latter layout is suitable for the chosen OpenSearch 2.x target.

Investigation also found that alias lookup returns mapping objects instead of index-name strings on its Elasticsearch 8 branch, basic authentication writes `pass` but reads `password`, and timeout defaults are unused. Bulk diagnostics require response `_type`. Existing tests use older PHPUnit conventions, and the current installation has no PHPUnit executable or live Magento/OpenSearch test environment established.

## Goals / Non-Goals

**Goals:**

- Support OpenSearch 2.x directly using OpenSearch PHP, with consistent packaging and documented runtime compatibility.
- Preserve document contents, entity identities, extension events, store configuration, and entity-specific aliases while removing protocol mapping types.
- Make initial indexing, repeat rebuilds, incremental updates, deletion, cleanup, and operational checks verifiable against OpenSearch.

**Non-Goals:**

- Maintain Elasticsearch adapters, certify OpenSearch 3.x, or add an intermediate Elasticsearch 8 release.
- Rename every Elasticsearch-prefixed class/configuration/event as cosmetic cleanup, redesign relevance, or implement external VSF API changes.
- Add AWS authentication or migrate old cluster snapshots in place.

## Decisions

### 1. Replace the client directly and pin the initial baseline

Use OpenSearch PHP `2.4.5` as the first implementation baseline, matching the package already added by the user. Remove direct Elasticsearch requirements from the root and all seven module manifests. Keep the resolved package if Magento itself still needs it transitively. Declare PHP `^8.1` or a stricter floor required by the Magento combinations actually supported; do not preserve the inaccurate PHP 7 claim.

An Elasticsearch 8 intermediate release would require further work on a response/transport contract that the final integration does not use. A client upgrade beyond 2.4.5 can be evaluated separately after this baseline passes.

### 2. Use an explicit synchronous PSR-18 transport

Use `OpenSearch\GuzzleClientFactory`, verified present in the installed 2.4.5 package, with explicit compatible Guzzle and PSR-7 dependencies. Map existing host, port, scheme, username, and password configuration to transport options. Apply connect/request timeouts, retain certificate verification, and select an explicit retry policy. Do not rely on discovery selecting a different installed HTTP client or on deprecated legacy transport behavior.

The module wrapper receives `OpenSearch\Client` and returns arrays or booleans according to its own interface. Remove Elastic-specific response checks, conversions, and promise handling. Update generated-factory annotations and DI references where necessary. Cosmetic class renaming is unnecessary to achieve this contract.

For this transport, missing-alias handling catches `OpenSearch\Exception\NotFoundHttpException` only. Authentication, authorization, connectivity, and server errors propagate. `indices()->exists()` retains its native boolean behavior. Alias lookup uses the aliases API, returning `array_keys()` of the decoded response rather than full mapping data.

### 3. Separate entity identity from mapping types

Mapping requests use only the index and the mapping body, for example `['index' => $name, 'body' => ['properties' => ...]]`. Remove the mapping-type argument from the module mapping API and its callers. Bulk action metadata contains `_index` and `_id`; delete-by-query contains index and query body without a type parameter. Preserve NDJSON serialization and document ID behavior.

Keep logical names such as `product`, `category`, and `taxrule` for data-provider resolution, events, index selection, and cache invalidation. Field mapping declarations such as `type: keyword` remain valid. Bulk diagnostics use index, operation, ID, and error reason; they do not require a server-provided document type.

### 4. Use the existing entity-specific layout unconditionally

Reuse the behavior of `ConfigES6plusParser` as the single parser. Remove the engine selector/resolver and Elasticsearch 5 branches from full reindex and rebuild flows. Persisted `es_version` values become obsolete and cannot change the layout; retain existing connection/settings paths and document that the old selector is ignored. Remove obsolete default/UI/DI entries and legacy parser tests, and adapt relevant tests to the single layout.

Keep aliases generated by `IndexSettings::getIndexAlias()` for entity-specific indexes, including store ID/code and default-store options. Never combine entities by simply dropping `_type`: IDs can collide and mappings can differ.

### 5. Verify index lifecycle before publishing a replacement

Return a list of concrete index names from alias lookup. A missing alias returns an empty list. Build and populate a fresh index, restore configured indexing settings, refresh it, and atomically update the alias. Delete superseded indexes only after the alias update succeeds. A failed mapping, failed bulk item, or failed refresh must stop publication and leave the existing alias usable; bulk errors must therefore affect the rebuild result, not only logging.

Use `try/finally` or equivalent cleanup so temporary refresh/replica settings are restored after a failed batch, without masking the original failure. Preserve disabled-indexer behavior and entity-specific incremental indexing.

### 6. Treat operational checks as explicit contracts

Keep health checks optional and request JSON explicitly if retaining CAT endpoints. Preserve the wrapper's expected health shape or adapt every consumer together. Test configured green/yellow/red behavior.

The existing queue guard compares cluster pending tasks and batch document count with a search queue's `max_queue_size`. This is not a reliable bulk-capacity metric. During implementation, inspect actual OpenSearch 2.x node/thread-pool responses before selecting a replacement. Do not silently coerce missing metrics to zero and report success. If equivalent behavior cannot be established, an enabled guard must fail with an actionable unsupported-configuration message; document this limitation rather than redesigning backpressure within this migration.

## Risks / Trade-offs

- [Old Magento dependency constraints conflict with PHP, PSR HTTP message, or PSR log requirements] → Validate intended Magento/PHP combinations with Composer and publish only the verified matrix; one successful local dependency resolution does not certify every advertised Magento version.
- [External VSF API still sends typed requests] → Require a consumer compatibility check before production cutover; no consumer source is present here.
- [Mappings extended by observers differ from built-in mappings] → Keep extension events and test representative deployed mappings, dates, configurable children, category trees, field counts, and analyzer settings on a real server.
- [An incomplete new index replaces a good index] → Verify failed-batch behavior and refresh ordering before alias publication.
- [Successful rollover immediately deletes old indexes] → Use a separate destination cluster for the production migration and retain the previous Elasticsearch cluster for rollback; the existing cleanup policy is not an index-retention feature.

## Migration Plan

1. Implement and validate dependency/runtime compatibility and the client wrapper without overwriting unrelated working-tree changes.
2. Implement typeless requests, the single index layout, bulk diagnostics, and lifecycle fixes as one coherent migration.
3. Run focused regression checks and integration indexing against an explicitly recorded OpenSearch 2.x server version. If a broader 2.x support range is claimed, test its declared minimum and deployment target.
4. Deploy to a staging Magento environment connected to a fresh OpenSearch cluster; reindex all enabled entities and stores, then repeat the rebuild to exercise rollover.
5. Verify document counts, representative fields and queries, incremental changes/deletes, and cache invalidation. Verify external VSF API queries against the new aliases.
6. For production, pause scheduled/incremental writes during the final rebuild and validation, or use a documented catch-up procedure. Switch the reader and indexer configuration together after validation. Resume indexing and check subsequent updates.
7. For rollback, restore the prior module version and both reader/writer connection settings to the retained previous cluster; reindex or catch up changes made after cutover. Fresh OpenSearch indexes remain separate from the old cluster.

## Open Questions

- Which exact OpenSearch 2.x deployment version and Magento/PHP combinations will be used for integration certification? This does not block implementation; it is required before compatibility is claimed.
- Does the deployed VSF API already support typeless entity-specific indexes? Verification is outside this repository and blocks production cutover.
- Resolved during implementation: the old queue heuristic is unsupported. OpenSearch 2.15.0 exposes `search.queue_size` and `write.queue_size`, not the expected `search.max_queue_size`; queued cluster tasks and batch document counts also do not represent write-queue occupancy. Enabled configurations receive the documented actionable error.

## Investigation References

- [OpenSearch mapping-type removal](https://docs.opensearch.org/latest/breaking-changes/#remove-mapping-types-parameter)
- [Mapping API](https://docs.opensearch.org/2.19/api-reference/index-apis/put-mapping/)
- [Bulk API](https://docs.opensearch.org/2.19/api-reference/document-apis/bulk/)
- [PHP client compatibility](https://github.com/opensearch-project/opensearch-php/blob/main/COMPATIBILITY.md)
- [PHP client transport upgrade guide](https://github.com/opensearch-project/opensearch-php/blob/main/UPGRADING.md)

Package-specific runtime requirements, factory availability, response types, and exception behavior were also checked directly in the installed 2.4.5 source; current online examples can describe newer client versions.

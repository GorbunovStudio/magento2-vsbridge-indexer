## 1. Dependencies and verification setup

- [x] 1.1 Record the current working-tree changes and identify package/lockfile ownership so the migration preserves unrelated user edits.
- [x] 1.2 Replace direct Elasticsearch requirements in the root and all seven module manifests with the consistent OpenSearch PHP 2.4.5 baseline; declare the chosen Guzzle/PSR-7 transport dependencies and accurate PHP constraints.
- [x] 1.3 Resolve applicable dependencies without unrelated upgrades, reconcile maintained lockfiles, and validate Composer metadata/platform compatibility for the intended Magento/PHP baseline.
- [x] 1.4 Establish a runnable focused test harness compatible with that baseline, adapting the relevant older PHPUnit tests and generated-factory setup as needed.

## 2. OpenSearch client boundary

- [x] 2.1 Replace client construction with the installed OpenSearch Guzzle factory; map HTTP/HTTPS, basic auth, certificate verification, request/connect timeouts, and an explicit retry policy.
- [x] 2.2 Replace the wrapper's injected client type and Elastic response/promise handling with OpenSearch array/boolean contracts; update builder annotations and DI/factory references.
- [x] 2.3 Implement alias lookup through the aliases API, returning index-name strings and handling only missing-alias responses as an empty list.
- [x] 2.4 Verify successful responses, true/false existence, malformed responses, missing aliases, authentication/authorization failures, and connection/server errors with focused regression checks.

## 3. Typeless requests and diagnostics

- [x] 3.1 Remove the mapping-type argument from the module mapping interface, implementation, and callers; send direct mapping bodies without type wrappers or include_type_name.
- [x] 3.2 Remove _type from both bulk index and delete metadata while preserving IDs and valid NDJSON serialization.
- [x] 3.3 Remove the type parameter from transaction-key delete-by-query and verify both unrestricted and document-ID-restricted cleanup selection.
- [x] 3.4 Update bulk error aggregation/logging to work without response _type and retain useful index, operation, reason, count, and document-ID diagnostics.
- [x] 3.5 Verify request payloads and mixed success/failure responses, including preservation of valid field-type definitions and logical entity identifiers.

## 4. Single entity-specific index layout

- [x] 4.1 Make entity-per-store parsing unconditional, reusing ConfigES6plusParser behavior and removing the shared-index parser path.
- [x] 4.2 Remove Elasticsearch engine selection from defaults, admin UI, resolver, and DI; ensure persisted legacy settings cannot reactivate the shared-index layout.
- [x] 4.3 Simplify identifier selection, full reindex action, and rebuild command to use entity-specific indexes consistently; update affected tests and remove obsolete legacy-parser tests.
- [x] 4.4 Verify existing alias naming for store ID/code and default-store options, equal IDs across entity types, and preserved data-provider/event/cache contracts.

## 5. Rollover, cleanup, and operational checks

- [x] 5.1 Make bulk item failures stop full rebuild publication; restore temporary indexing settings on success and exceptions without masking the original error.
- [x] 5.2 Refresh completed replacement indexes before atomic alias update and delete superseded indexes only after successful publication.
- [x] 5.3 Verify initial alias creation, repeat rebuilds, alias-update failure, failed mapping/bulk/refresh, old-index preservation, settings restoration, and disabled-indexer behavior.
- [x] 5.4 Normalize optional health checks with explicit JSON or structured APIs and verify enabled/disabled and green/yellow/red cases.
- [x] 5.5 Inspect OpenSearch 2.x thread-pool responses and resolve the queue guard according to the design: a verified supported metric contract or an actionable unsupported-configuration failure when enabled; cover unavailable metrics explicitly.

## 6. OpenSearch integration validation

- [x] 6.1 Provide a reproducible isolated OpenSearch 2.x integration fixture and record exact server, client, Magento, and PHP versions; include basic-auth/HTTPS coverage and both the declared minimum server and deployment target if claiming a range.
- [x] 6.2 Validate all enabled built-in entity mappings, analyzer settings, and representative dates, configurable children, category trees, and extended mapping payloads on the server.
- [x] 6.3 Exercise initial full indexing and repeat rebuild/rollover for multiple stores, checking aliases, document counts, representative documents, and superseded-index cleanup.
- [x] 6.4 Exercise incremental updates/deletes and transaction-key cleanup, proving that unrelated entities/stores remain intact and cache invalidation contracts are preserved.
- [x] 6.5 Exercise failed bulk items, missing aliases, access failures, optional operational checks, and indexing-settings restoration against the integration environment.
- [x] 6.6 Run appropriate lint, focused tests, and Composer checks; record results and unresolved environment limitations without claiming unexecuted integration checks passed.

## 7. Compatibility and operator documentation

- [x] 7.1 Replace obsolete Elasticsearch/runtime compatibility claims and configuration instructions with the verified OpenSearch client/server and Magento/PHP matrix.
- [x] 7.2 Document breaking internal API changes, obsolete engine settings, preserved configuration paths/events, fresh entity-specific aliases, and any queue-guard limitation.
- [x] 7.3 Document full rebuild, data/query validation, external VSF API typeless compatibility checks, write pause or catch-up, coordinated reader/writer cutover, and rollback to the retained previous cluster.
- [x] 7.4 Record external VSF API validation as a production deployment prerequisite; do not claim consumer certification from repository-only tests.

## Validation record

- 2026-10-19: PHP 8.5.6, PHPUnit 10.5.66, OpenSearch PHP 2.4.5, OpenSearch server 2.15.0. Composer resolved Magento Framework 103.0.9. The dependency update removed only six unused Elasticsearch-related packages; it upgraded none.
- Core unit suite: 32 tests, 61 assertions, passing. Existing core tests were adapted for PHPUnit 10; new checks cover typeless requests, response/errors, alias publication failures, bulk/settings failures, health policies, disabled checks, entity events, and alias naming.
- Live OpenSearch fixture: 1 lifecycle test, 173 assertions, passing; all eight built-in entity mappings with product additions, two stores, two rebuilds, incremental updates/deletion/cleanup, restoration, and a failed replacement preserving the old alias.
- Live HTTPS demo fixture: 1 test, 4 assertions, passing; production builder rejects the untrusted certificate, fixture-only trust bypass permits basic-auth success, wrong credentials propagate 401.
- Thread-pool inspection confirmed `search.queue_size` and `write.queue_size`, with no expected `search.max_queue_size`; the unsupported legacy heuristic now raises an actionable error when enabled.
- PHP syntax validation, module XML parsing, Composer manifest/platform checks, OpenSpec strict validation, and diff whitespace checks completed. Composer reports existing broad Magento constraints and the deliberate client pin as warnings.
- Integration uses real OpenSearch with fixture/mocked Magento inputs, not a deployed Magento database. Full Magento export, generated DI compilation, store-specific EAV data, production certificates, external VSF API queries, and production cutover are documented staging prerequisites, not claimed completed deployment checks.

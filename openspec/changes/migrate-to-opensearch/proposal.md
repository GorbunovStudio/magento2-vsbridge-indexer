## Why

The indexer still sends mapping types that OpenSearch 2.x no longer supports, and its Elasticsearch 8 response handling cannot be reused unchanged with the OpenSearch PHP client. Replace the integration directly with OpenSearch so full and incremental indexing have one supported protocol and index layout.

## What Changes

- Replace this project's direct `elasticsearch/elasticsearch` requirements with `opensearch-project/opensearch-php`, initially using the existing 2.4.5 baseline, across the root and all seven module manifests.
- Build an OpenSearch client with explicit HTTP transport, authentication, TLS, timeout, and retry configuration; normalize decoded responses and boolean existence checks at the module client boundary.
- Remove mapping-type parameters, mapping wrappers, `include_type_name`, and bulk `_type` metadata. Make bulk error reporting independent of response `_type`.
- **BREAKING**: Support OpenSearch server 2.x only; remove Elasticsearch 5/6/7/8 support and the legacy shared-index layout. Use one index per entity and store, preserving existing entity-specific alias naming.
- **BREAKING**: Raise the declared PHP floor to match the selected client, at least PHP 8.1, and document the Magento/PHP combinations actually validated.
- Correct alias lookup and rollover, including missing-alias handling, and verify indexing settings and optional operational checks.
- Preserve logical entity identifiers, mapping extension events, and cache invalidation behavior. Document fresh-index rebuilding and the consuming VSF API cutover requirements.

## Capabilities

### New Capabilities

- `opensearch-client`: OpenSearch connection configuration, response contracts, exception handling, and dependency/runtime requirements.
- `opensearch-indexing`: Typeless entity indexes, full and incremental indexing, cleanup, bulk diagnostics, alias rollover, and migration compatibility.

### Modified Capabilities

None. There are no existing capability specs in this repository.

## Impact

Affected code includes the core client and builder, client interfaces and DI wiring, configuration parsers and engine settings, bulk requests/responses/logging, generic indexer handler, full-index action, rebuild command, and their tests. Packaging changes affect the root and seven module Composer manifests and applicable lockfiles.

Deployments using shared Elasticsearch 5 indexes must rebuild into entity-specific OpenSearch indexes and update their consuming application. VSF/VSF-API code is outside this repository and requires a separate compatibility check. This change removes the indexer's direct Elasticsearch dependency; Magento or another installed extension may still require that package transitively.

## Non-goals

- An intermediate Elasticsearch 8 release or simultaneous support for Elasticsearch and OpenSearch.
- OpenSearch 3.x certification, AWS SigV4 authentication, or a redesign of document contents and relevance.
- Implementing changes in external storefront repositories or migrating cluster snapshots in place.

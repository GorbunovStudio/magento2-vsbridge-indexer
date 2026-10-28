# Migrating to OpenSearch

This release targets OpenSearch 2.x directly. Elasticsearch backends, mapping types, and the shared Elasticsearch 5 index layout have been removed. Rebuild fresh indexes from Magento; this procedure does not upgrade cluster snapshots in place.

## Runtime and validation

The client is pinned to `opensearch-project/opensearch-php:2.4.5`. The packages require PHP `^8.1`, Guzzle `^7.5`, and Guzzle PSR-7 `^2.7`; Magento can impose a stricter PHP requirement. Composer successfully resolved the current environment with Magento Framework 103.0.9 and PHP 8.5.6, without upgrading unrelated packages. The stale Elasticsearch 5 lockfile inside the core library was removed; the development checkout's root lockfile is the dependency snapshot.

Validation on 2026-10-19: PHPUnit 10.5.66 core suite passed 32 tests / 61 assertions; the lifecycle fixture passed 1 test / 173 assertions; the demo HTTPS/auth fixture passed 1 test / 4 assertions.

Type API cleanup validated on 2026-10-21: 33 core unit tests / 64 assertions and the updated OpenSearch 2.15.0 lifecycle test / 173 assertions passed.

The focused core PHPUnit suite and the isolated OpenSearch 2.15.0 integration fixture exercise the client, actual built-in mappings (including product additions), repeated rebuild/rollover across two stores, incremental changes, bulk deletion, transaction-key cleanup, bulk mapping failure, and temporary settings restoration. Magento database/data-provider inputs are fixtures and mocks. A deployed Magento database, generated DI compilation, store-specific EAV mappings, external VSF API, and production certificates still require staging validation. Other OpenSearch 2.x versions are a target, not independently certified versions.

## Configuration and compatibility

Existing connection/settings paths under `vsbridge_indexer_settings`, entity identifiers, mapping events, and cache invalidation contracts are retained. The `es_version` selector and resolver are removed; old stored values are ignored. Each entity/store now always has its own index and alias:

| Configuration | Product alias |
| --- | --- |
| Default prefix and store ID 1 | `vue_storefront_catalog_1_product` |
| Store code `en` | `vue_storefront_catalog_en_product` |
| Default store identifier disabled | `vue_storefront_catalog_product` |

Category, attribute, CMS, review, tax, and agreement aliases use their configured entity names in the same position. Existing entity-specific alias naming is unchanged. Physical index names now include a random suffix after the timestamp to avoid collisions during rapid rebuilds.

Basic authentication uses the configured username/password. HTTPS verifies certificates; there is no production option to disable verification. Request and connect timeouts default to 30 seconds and can be supplied as builder options. Automatic retries are disabled to avoid replaying a bulk operation after an ambiguous failure.

The optional green-cluster health check remains supported and requests structured JSON. The legacy `max_bulk_queue_requirement` heuristic is unsupported: disable `vsbridge_indexer_settings/optimization_settings/max_bulk_queue_requirement` (admin: Check max bulk queue). An enabled value raises an explanatory error instead of silently passing an unavailable metric.

The client classes now use the `Divante\VsbridgeIndexerCore\OpenSearch` namespace and directory. The single-layout parser is now `Index\Indices\ConfigParser`. Update extension imports, DI preferences, and plugins referring to the previous class names.

Extensions implementing the module client must update `putMapping($indexName, array $mapping)` and use OpenSearch responses/exceptions. The obsolete `getMasterMaxQueueSize()` client method is removed. The index-operation interface now explicitly declares its existing `getIndexAlias()` operation. Constructor dependencies for the client, configuration resolver, handler, full action, and rebuild service have changed. Rebuild generated Magento factories/DI after deployment. Bulk error summaries no longer include `document_type`; index, operation, error reason, counts, and document IDs remain available. Bulk methods now accept only the index and documents/IDs: `addDocuments($index, array $data)` and `deleteDocuments($index, array $docIds)`. 

The index exposes a single `getMapping()` in place of `getTypes()`/`getType()`; `TypeInterface`, `Type`, and the dummy mapping-type constant are removed. Index configuration/factory arguments now use `mapping` instead of `types`. 

Indexers, action factories, and DI arguments use `entityName` instead of `typeName`. The handler no longer exposes the unused `getTypeName()` or the private identifier pass-through. Entity names still select data providers and index aliases; event payloads retain the `data_type` key.

## Deployment

1. Confirm the chosen Magento/PHP installation can resolve these dependencies and the consuming VSF API supports typeless OpenSearch requests and entity-specific aliases. Reader compatibility is a production prerequisite; indexer tests cannot certify external applications.
2. Provision a separate OpenSearch 2.x destination and configure the indexer's existing host, port, HTTPS, and authentication settings. Verify certificates and permissions for indexing, mappings, aliases, settings, deletion, and any enabled health monitoring.
3. Deploy the module and run `bin/magento setup:upgrade` and the normal generated DI/cache deployment steps for the installation.
4. In staging, run `bin/magento vsbridge:reindex --all` (or `--store=<id-or-code>`) for enabled stores. Rebuild again to check alias rollover. A failed entity rebuild now propagates failure instead of printing an overall success message.
5. Verify counts, representative dates/numbers, configurable children, category trees, dynamic EAV fields, observer-added mapping fields, and storefront queries. Exercise scheduled/incremental changes, removals, cache invalidation, and optional health checks.
6. For the final production rebuild, pause incremental jobs and catalog writes, or establish a catch-up procedure. Validate the destination, switch the reader and indexer connections together, then resume writes/jobs and verify subsequent updates.
7. Retain the previous cluster and module release for rollback. Rollback restores both reader/writer connection settings and the old module version, followed by catch-up or reindexing of changes made since cutover. Successful rollovers delete superseded indexes in the destination; do not use them as a retention mechanism.

## Running checks

Use PHPUnit 10.5 (a local executable or PHAR) without adding it to production dependencies:

```sh
php /path/to/phpunit-10.phar -c phpunit.xml.dist

docker compose -f tests/opensearch.compose.yaml up -d
# Wait for http://127.0.0.1:19200/ to report readiness.
OPENSEARCH_TEST_URL=http://127.0.0.1:19200 php /path/to/phpunit-10.phar --bootstrap tests/bootstrap.php tests/Integration/OpenSearchTest.php
docker compose -f tests/opensearch.compose.yaml down
```

The fixture binds only to localhost with security disabled. Run it on an isolated development machine. The integration test deletes only indexes under a randomly generated `vsbridge_test_` prefix. It does not run Magento database exports. The test bootstrap supplies mockable placeholders only for Magento factories normally generated during installation; production continues to use Magento-generated factories.

For basic authentication and rejection of untrusted HTTPS certificates, use the optional disposable demo-security fixture:

```sh
docker compose -f tests/opensearch.compose.yaml --profile tls up -d secure
# Wait for the demo HTTPS endpoint to start.
OPENSEARCH_DEMO_TLS_URL=https://127.0.0.1:19201 php /path/to/phpunit-10.phar --bootstrap tests/bootstrap.php tests/Integration/DemoTlsTest.php
docker compose -f tests/opensearch.compose.yaml --profile tls down
```

This fixture uses a public development-only password and self-signed demo certificates. The test first checks that the module builder rejects the untrusted certificate; it then bypasses verification only in the fixture client to check successful authentication and a wrong-password 401. It does not certify production certificate configuration.

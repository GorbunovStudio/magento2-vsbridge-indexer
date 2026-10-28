## ADDED Requirements

### Requirement: Consistent OpenSearch dependencies

The root package and all seven module packages SHALL replace their direct Elasticsearch PHP requirements with a consistent OpenSearch PHP constraint, initially version 2.4.5. Their declared runtime requirements MUST accommodate the selected client, including a PHP floor of at least 8.1. The integration SHALL declare the HTTP implementation dependencies it uses explicitly.

#### Scenario: Installing the indexer
- **WHEN** the root or a published module package is installed in a documented supported Magento/PHP environment
- **THEN** Composer resolves the selected OpenSearch client and HTTP implementation without relying on a direct Elasticsearch PHP requirement from this project
- **AND** documentation identifies the runtime combinations actually validated

### Requirement: Configured synchronous connection

The module SHALL construct an OpenSearch client using a synchronous transport and the configured store connection settings. It MUST apply host, port, HTTP/HTTPS scheme, optional basic authentication, request/connect timeouts, and an explicit retry policy. TLS certificate verification MUST remain enabled for HTTPS connections.

#### Scenario: Authenticated HTTPS connection
- **WHEN** a store uses HTTPS and enabled basic authentication
- **THEN** the configured username and password are supplied to the HTTP client
- **AND** certificate verification and configured timeouts are applied

#### Scenario: Connection without authentication
- **WHEN** basic authentication is disabled
- **THEN** the client can connect without supplying basic-auth credentials

### Requirement: Stable module response contracts

The module client SHALL return decoded arrays for JSON data operations and booleans for index existence. It MUST NOT depend on Elastic response objects or their conversion methods. Unexpected response formats MUST produce a diagnostic failure rather than an implicit null success.

#### Scenario: Index existence
- **WHEN** OpenSearch reports an index exists or is missing
- **THEN** the module returns true or false respectively

#### Scenario: JSON operation result
- **WHEN** a bulk, index-settings, index-creation, or index-deletion operation succeeds with a JSON response
- **THEN** the wrapper exposes decoded array data where its contract returns data

### Requirement: Precise missing-alias and error handling

Alias lookup SHALL return concrete index-name strings. A missing alias SHALL return an empty list. Authentication, authorization, connection, and server failures MUST remain observable and MUST NOT be converted into a missing-alias result.

#### Scenario: Existing alias
- **WHEN** an alias resolves to one or more indexes
- **THEN** lookup returns only their names, without mapping or alias metadata objects

#### Scenario: Missing alias
- **WHEN** OpenSearch returns a not-found response for the requested alias
- **THEN** lookup returns an empty list

#### Scenario: Access denied
- **WHEN** OpenSearch returns an authentication or authorization failure during alias lookup
- **THEN** lookup propagates the failure instead of returning an empty list

### Requirement: Explicit operational checks

Optional health checks SHALL consume a known structured response and retain their configured health policy. An enabled queue check MUST NOT silently pass because an expected metric is missing. If supported equivalent queue behavior cannot be established, the check SHALL report an actionable unsupported-configuration failure.

#### Scenario: Health check enabled
- **WHEN** a configured health check receives green, yellow, or red cluster status
- **THEN** the indexer applies its documented policy using the decoded status

#### Scenario: Queue metric unavailable
- **WHEN** the queue check is enabled and the required metric is unavailable or unsupported
- **THEN** the indexer reports which check cannot be evaluated and how to disable or correct it
- **AND** it does not report a successful capacity check by substituting zero

#### Scenario: Checks disabled
- **WHEN** optional health and queue checks are disabled
- **THEN** indexing does not require the corresponding monitoring requests

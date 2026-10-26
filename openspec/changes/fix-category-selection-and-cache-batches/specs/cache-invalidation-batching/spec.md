## ADDED Requirements

### Requirement: Ordered batch-scoped invalidation

For positive configured batch sizes, each cache invalidation request SHALL contain tags derived only from that batch's IDs, in input order, using the existing tag rules. Endpoint, connection configuration, HTTP protocol, tag extension events, and HTTP error handling MUST retain their existing behavior.

#### Scenario: Batch size one
- **WHEN** IDs `[101, 202]` are invalidated with batch size `1`
- **THEN** two requests are sent in order, containing only the tag for `101` and only the tag for `202`, respectively

#### Scenario: Incomplete last batch
- **WHEN** IDs `[101, 202, 303, 404, 505]` are invalidated with batch size `2`
- **THEN** requests contain tags for `[101, 202]`, `[303, 404]`, and `[505]`, respectively

#### Scenario: Batch size exceeds ID count
- **WHEN** IDs `[101, 202]` are invalidated with batch size `5`
- **THEN** one request contains both IDs' tags in order

### Requirement: Preserve disabled batching

A configured batch size of zero, including values cast to zero by the existing configuration contract, SHALL disable splitting and produce one request with all selected IDs in order.

#### Scenario: Splitting disabled
- **WHEN** IDs `[101, 202]` are invalidated with configured batch size `0`
- **THEN** one request contains both IDs' tags using the existing URL and tag rules

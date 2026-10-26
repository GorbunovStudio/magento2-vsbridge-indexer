## Why

A partial category export whose IDs do not exist currently becomes a full export and emits a success event that can trigger an invalid cache request. Cache invalidation batching also repeats the entire ID list in every request. Both defects were reproduced in an installed package; their relationship to the OpenSearch migration is unproven.

## What Changes

- Reject a nonempty category selection when parent/category resolution returns no eligible IDs, with an actionable error propagated through the single-entity command before document writes and the success event.
- Preserve intentional full exports, existing-category parent expansion, and the current mixed-selection behavior of exporting found categories and their parents.
- Build each cache invalidation URL from its current batch, preserving ordering, tag rules, disabled batching, endpoint, and HTTP error handling.
- Add isolated regression tests covering category selection, the command/event path, and intercepted cache HTTP requests.

## Capabilities

### New Capabilities

- `category-export-selection`: Explicit full versus partial category selection and failure propagation that prevents success-driven invalidation for missing categories.
- `cache-invalidation-batching`: Ordered, batch-scoped invalidation requests and unchanged unbatched behavior.

### Modified Capabilities

None. `openspec/specs/` is empty. The completed, unarchived `migrate-to-opensearch` change defines protocol and lifecycle capabilities; this independent fix does not amend that migration or attribute these defects to it.

## Impact

Primary production files are catalog `Model/Indexer/Action/Category.php` and core `Cache/Processor.php`. Tests will exercise the existing core row action, handler consumption, and console failure path; change those callers only if necessary for propagation. The test bootstrap/configuration may need small additions for catalog discovery and generated Magento factories. No dependency additions or changes in external repositories are planned.

## Non-goals

Magento integration revalidation, real catalog/database/cache operations, changes to Budsies modules, unrelated defects, OpenSearch client upgrades, and changes to cache configuration or integration protocol.

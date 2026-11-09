## Context

`Category::rebuild()` replaces a nonempty input with `getParentIds()`. That resource method returns existing category path IDs, including the selected category and ancestors except the global tree root. A missing selection returns `[]`; `getCategories()` treats that as an unrestricted selection. An originally empty input intentionally means full export.

The execution path is `SingleEntityIndexCommand → Magento indexer → Base::executeRow → Rows::execute → GenericIndexerHandler::saveIndex → Category` iteration. Scheduled updates take the same path from `Magento\Framework\Mview\View::update() → Base::execute → Rows::execute`. `Rows` performs transaction-key cleanup restricted to the requested IDs and emits `VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER` after saving. The handler does not catch category lookup exceptions. The command returns success only after `reindexRow()` returns; an uncaught lookup exception must reach the console application's failure handling.

Deleted categories reach `Rows` through the changelog. `getParentIds()` queries only surviving rows and drops the tree root (ID 1), so a deleted-only chunk or a deleted unassigned root batch such as `[1, 48, 49]` resolves to `[]`. Ordinary admin deletions usually include surviving ancestors and resolve to a nonempty list. The MView version advances only when `execute()` returns.

`Processor::cleanCacheInBatches()` correctly chunks IDs but passes the original list to URL construction. Its configuration casts the batch size to int; zero disables chunking. Existing tag preparation and HTTP error handling can remain intact.

## Goals / Non-Goals

**Goals:** Never turn an unresolved partial selection into a full export; let row indexing remove deleted categories and advance; reject a missing category requested through `vsbridge:index` before any indexing side effect; preserve successful selection semantics; and issue ordered batch-specific cache requests with isolated regression coverage.

**Non-Goals:** Change deletion workflows, all store-visibility semantics, event payloads for successful mixed selections, HTTP errors, cache configuration, OpenSearch protocol, dependencies, or external Budsies observers. No Magento integration run or real data/cache access.

## Decisions

### End the category generator for an unresolved partial selection

Remember partial intent through the existing nonempty-input branch and return from the generator without documents if resolution is empty. Do so before `getCategories()` and the first yield. This prevents the unrestricted query while `Rows` still cleans up the requested IDs and dispatches its event. Changing resource-wide empty-array semantics is rejected because full export relies on them.

An earlier revision threw `NoSuchEntityException` here. Review of #37156 rejected it: the exception aborted `Rows` before cleanup, left deleted category documents in the index, and kept the MView version from advancing, so the same deleted-only batch failed on every cron run and blocked later views.

Keep `getParentIds()` and pagination unchanged when resolution is nonempty. Mixed selections continue to export found categories and their ancestors while ignoring missing IDs, and successful event payloads keep the original IDs.

### Reject a missing category at the single-entity command boundary

`vsbridge:index` is a manual tool for one entity, and a nonexistent ID there is an operator error. `SingleEntityIndexCommand` accepts an `entityValidators` argument: `SingleEntityValidatorInterface` class names keyed by indexer code, resolved lazily from the command's object manager. The catalog module registers `CategoryEntityValidator` for `vsbridge_category_indexer`; it uses the same `getParentIds()` resolution and throws `NoSuchEntityException` with the existing message and ID. The command validates before `reindexRow()`, so the error reaches the console application's failure handling before the generator, cleanup, or event. Indexers without a registered validator keep their behavior. The core module does not depend on catalog classes.

### Verify lazy execution and failure propagation

Use the real category generator, `Base`, and `Rows` with the real indexer handler and mocked resource/storage boundaries. Verify that unresolved batches write nothing, clean up the requested IDs, and dispatch the event, and that mixed and existing selections stay bounded. Exercise the console application's command execution, including Magento's real `reindexRow()`, to establish a nonzero result and readable error for a missing category and a successful run for an existing one.

### Construct invalidation URLs from the current batch

Pass `$batch` to `getCacheInvalidateUrl()` inside the existing loop. Keep chunk order, tag expansion events, tag prefixes, URL, HTTP GET/1.0, connection options, and exception/logging behavior. Zero batch size continues to send one request for the full ordered list.

### Extend the existing isolated test harness

Reuse the repository's PHPUnit harness without adding dependencies. Mock Magento resources and index operations; intercept `CurlFactory`/adapter writes and return synthetic HTTP responses. Cover real tag construction rather than stubbing URL generation. Add catalog test discovery and minimal generated-factory placeholders only if needed. Demonstrate that focused regressions fail on the old implementation and pass after the fix, without overwriting pre-existing edits.

## Risks / Trade-offs

- [Generators defer execution] → Test actual iteration through the handler and row action, not just generator creation.
- [Missing-category updates previously appeared successful] → Fail only `vsbridge:index` requests for a missing category with the ID in the message; row indexing of unresolved IDs succeeds without documents; preserve existing mixed/full semantics.
- [Scheduled row indexing reports success for IDs that no longer exist] → Intended: cleanup of those IDs is the update, and the success event matches what baseline dispatched for them.
- [External Budsies observer is outside this repository] → Assert the triggering success event is absent. Do not claim direct execution of that observer or Magento integration certification.
- [Pre-existing uncommitted migration/test files] → Preserve them and identify the exact new changes at each user-requested commit checkpoint.

## Migration Plan

No schema, configuration, or dependency migration. User reviews and commits OpenSpec artifacts first, then implementation and regression tests at the next major checkpoint. Package update and Magento integration verification belong to a later user-directed task. Rollback uses the previous package revision.

## Open Questions

The user expanded scope to support Symfony Console 7 in the single-entity command and audit the other commands. `SingleEntityIndexCommand` and `ResetEsIndexCommand` now declare `execute(): int` and `configure(): void`. `RebuildEsIndexCommand` already had those signatures. Existing method bodies, exit codes, and exception propagation are unchanged.

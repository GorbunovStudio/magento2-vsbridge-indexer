## Context

`Category::rebuild()` replaces a nonempty input with `getParentIds()`. That resource method returns existing category path IDs, including the selected category and ancestors except the global tree root. A missing selection returns `[]`; `getCategories()` treats that as an unrestricted selection. An originally empty input intentionally means full export.

The execution path is `SingleEntityIndexCommand → Magento indexer → Base::executeRow → Rows::execute → GenericIndexerHandler::saveIndex → Category` iteration. `Rows` performs transaction-key cleanup and emits `VSBRIDGE_INDEXER_ACTION_EXECUTE_AFTER` after saving. The handler does not catch category lookup exceptions. The command returns success only after `reindexRow()` returns; an uncaught lookup exception must reach the console application's failure handling. Merely ending the generator would still permit cleanup and the success event.

`Processor::cleanCacheInBatches()` correctly chunks IDs but passes the original list to URL construction. Its configuration casts the batch size to int; zero disables chunking. Existing tag preparation and HTTP error handling can remain intact.

## Goals / Non-Goals

**Goals:** Reject an unresolved partial selection before any category document write, prevent its success event and consequent invalidation, preserve successful selection semantics, and issue ordered batch-specific cache requests with isolated regression coverage.

**Non-Goals:** Change deletion workflows, all store-visibility semantics, event payloads for successful mixed selections, HTTP errors, cache configuration, OpenSearch protocol, dependencies, or external Budsies observers. No Magento integration run or real data/cache access.

## Decisions

### Guard category resolution at the category action boundary

Remember partial intent through the existing nonempty-input branch and throw `Magento\Framework\Exception\NoSuchEntityException` with the requested IDs if resolution is empty. Do so before `getCategories()` and the first yield. This prevents both unrestricted queries and later success handling. An empty generator is rejected because it allows `Rows` to report success; changing resource-wide empty-array semantics is rejected because full export relies on them.

Keep `getParentIds()` and pagination unchanged when resolution is nonempty. Mixed selections continue to export found categories and their ancestors while ignoring missing IDs. Do not reject a mixed selection merely because one ID is absent, and do not alter the original IDs in successful event payloads. An entirely unresolved nonempty selection fails, including multiple missing IDs.

### Preserve failure propagation and verify lazy execution

Use the real category generator and row execution path with mocked resource/storage boundaries. Verify consumption raises before document writes, transaction cleanup, and success dispatch. Exercise the console application's command execution with isolated Magento collaborators to establish a nonzero result and a readable error. Change callers only if this verification finds exception suppression; avoid adding a redundant command-specific catch merely to duplicate console behavior.

### Construct invalidation URLs from the current batch

Pass `$batch` to `getCacheInvalidateUrl()` inside the existing loop. Keep chunk order, tag expansion events, tag prefixes, URL, HTTP GET/1.0, connection options, and exception/logging behavior. Zero batch size continues to send one request for the full ordered list.

### Extend the existing isolated test harness

Reuse the repository's PHPUnit harness without adding dependencies. Mock Magento resources and index operations; intercept `CurlFactory`/adapter writes and return synthetic HTTP responses. Cover real tag construction rather than stubbing URL generation. Add catalog test discovery and minimal generated-factory placeholders only if needed. Demonstrate that focused regressions fail on the old implementation and pass after the fix, without overwriting pre-existing edits.

## Risks / Trade-offs

- [Generators defer execution] → Test actual iteration through the handler and row action, not just generator creation.
- [Missing-category updates previously appeared successful] → Intentionally fail unresolved partial requests with IDs in the message; preserve existing mixed/full semantics.
- [External Budsies observer is outside this repository] → Assert the triggering success event is absent. Do not claim direct execution of that observer or Magento integration certification.
- [Pre-existing uncommitted migration/test files] → Preserve them and identify the exact new changes at each user-requested commit checkpoint.

## Migration Plan

No schema, configuration, or dependency migration. User reviews and commits OpenSpec artifacts first, then implementation and regression tests at the next major checkpoint. Package update and Magento integration verification belong to a later user-directed task. Rollback uses the previous package revision.

## Open Questions

The user expanded scope to support Symfony Console 7 in the single-entity command and audit the other commands. `SingleEntityIndexCommand` and `ResetEsIndexCommand` now declare `execute(): int` and `configure(): void`. `RebuildEsIndexCommand` already had those signatures. Existing method bodies, exit codes, and exception propagation are unchanged.

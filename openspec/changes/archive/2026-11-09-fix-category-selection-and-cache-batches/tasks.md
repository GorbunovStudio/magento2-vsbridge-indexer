## 1. Isolated regression coverage

- [x] 1.1 Confirm the available isolated PHP/PHPUnit runtime, preserve pre-existing changes, and extend catalog test discovery or generated-factory placeholders only as needed without adding dependencies.
- [x] 1.2 Add category tests for existing IDs with parents, missing single and all-missing IDs, original empty input, mixed input, and filtered pagination.
- [x] 1.3 Add a regression through category generator consumption, row execution, and console failure handling proving a readable error/nonzero result and absence of document writes, transaction cleanup, success event, and event-driven cache requests for a missing category.
- [x] 1.4 Add intercepted HTTP tests for batch sizes 1, 2 with an incomplete final batch, larger than the input, and zero; assert ordered URLs and existing tag rules.
- [x] 1.5 Run the focused regressions against the original production implementation and record the expected failures reproducing both defects.

## 2. Minimal production fixes

- [x] 2.1 Reject unresolved nonempty category selections before page queries/yields using a readable lookup exception containing the original IDs, while preserving full, mixed, and parent-expanded exports.
- [x] 2.2 Confirm exceptions propagate past the handler and row action to console failure without success dispatch; change callers only if required by verified suppression.
- [x] 2.3 Use the current batch when constructing cache invalidation URLs, preserving configuration, tag preparation, endpoint/protocol, and HTTP error handling.

## 3. Verification and review checkpoint

- [x] 3.1 Run focused regressions and the available isolated unit suite; lint changed PHP files and validate this OpenSpec change.
- [x] 3.2 Review the diff for scope and preservation of pre-existing edits; record changed files, exact verification commands/results, and unperformed checks with reasons.
- [x] 3.3 Leave changes ready for user review and request a commit at the implementation checkpoint. Do not run Magento integration checks, commit on the user's behalf, or archive the change automatically.

## 4. User-requested Symfony Console 7 follow-up

- [x] 4.1 Audit all package console commands and update incompatible overrides to `execute(): int`, declaring `configure(): void` consistently without changing command behavior.
- [x] 4.2 Remove the Console compatibility skip and verify all three commands load/register on the installed Console 7; run the missing-category command regression directly.
- [x] 4.3 Run isolated checks, update verification results, and leave the expanded scope ready for user review and commit.

## 5. Deleted-category correction (#37156 review)

Supersedes the generator exception from 2.1 and the row-path failure expectations from 1.2 and 1.3.

- [x] 5.1 Return no documents for an unresolved nonempty selection without a page query, keeping full, mixed, and parent-expanded exports unchanged.
- [x] 5.2 Add a row-indexing regression through `Base`, `Rows`, the category action, and the indexer handler: deleted-only, several deleted, and deleted unassigned root batches write nothing, clean up the requested IDs, and dispatch the success event; mixed and existing batches stay bounded.
- [x] 5.3 Move the missing-category failure to `vsbridge:index` through a per-indexer validator registered by the catalog module; verify a nonzero result and readable error before the generator, cleanup, and event, and a successful run for an existing category.
- [x] 5.4 Run the regressions against the PR head and baseline category actions and record which fail there; run the full isolated unit suite and validate this change.

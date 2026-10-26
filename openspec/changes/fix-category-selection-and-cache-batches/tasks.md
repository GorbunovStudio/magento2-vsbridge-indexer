## 1. Isolated regression coverage

- [ ] 1.1 Confirm the available isolated PHP/PHPUnit runtime, preserve pre-existing changes, and extend catalog test discovery or generated-factory placeholders only as needed without adding dependencies.
- [ ] 1.2 Add category tests for existing IDs with parents, missing single and all-missing IDs, original empty input, mixed input, and filtered pagination.
- [ ] 1.3 Add a regression through category generator consumption, row execution, and console failure handling proving a readable error/nonzero result and absence of document writes, transaction cleanup, success event, and event-driven cache requests for a missing category.
- [ ] 1.4 Add intercepted HTTP tests for batch sizes 1, 2 with an incomplete final batch, larger than the input, and zero; assert ordered URLs and existing tag rules.
- [ ] 1.5 Run the focused regressions against the original production implementation and record the expected failures reproducing both defects.

## 2. Minimal production fixes

- [ ] 2.1 Reject unresolved nonempty category selections before page queries/yields using a readable lookup exception containing the original IDs, while preserving full, mixed, and parent-expanded exports.
- [ ] 2.2 Confirm exceptions propagate past the handler and row action to console failure without success dispatch; change callers only if required by verified suppression.
- [ ] 2.3 Use the current batch when constructing cache invalidation URLs, preserving configuration, tag preparation, endpoint/protocol, and HTTP error handling.

## 3. Verification and review checkpoint

- [ ] 3.1 Run focused regressions and the available isolated unit suite; lint changed PHP files and validate this OpenSpec change.
- [ ] 3.2 Review the diff for scope and preservation of pre-existing edits; record changed files, exact verification commands/results, and unperformed checks with reasons.
- [ ] 3.3 Leave changes ready for user review and request a commit at the implementation checkpoint. Do not run Magento integration checks, commit on the user's behalf, or archive the change automatically.

# Low Widont PHP compatibility

Status: blocked best candidate; do not publish. Complete configured PHP 7.4–8.5 runs pass 541 cases / 2,759 assertions each, but actual native probes confirm that the serialization handler changes callback-installed handler state and the previous handler's partial severity mask. Full native API preservation requires compatible upstream/runtime support or an explicitly revised scope. No approximate handler reconstruction is included; independent review and final approval remain outstanding. The original archive and all historical test reports remain available from the pinned baseline. This campaign changes the distributed source and rebuilds the actual PHAR; historical tests-only coverage percentages do not measure this candidate.

The isolated branch starts at fetched `origin/unit-tests-2026` commit `6a2fb11be4d7145a381bd478465b692aebb59470`. The pinned `develop` comparison is `3a38b1fb9146daa87ed9f1a83699521ea03091da`. The primary checkout and unrelated `toms-phar` PR are preserved.

## Strict initial measurement

All seven configured runs completed on the unchanged distributed production bytes before source repairs. The launcher and actual native children report `error_reporting=-1`, `display_errors=stderr` and `display_startup_errors=1`; diagnostics under `@` are retained before throwing. Every run retains full output, fresh JUnit, actual runtime/input identities and process cleanup receipts.

| PHP | Cases | Assertions | Errors | Failures |
| --- | ---: | ---: | ---: | ---: |
| 7.4.33 | 450 | 2208 | 44 | 50 |
| 8.0.30 | 450 | 2200 | 46 | 51 |
| 8.1.33 | 450 | 2200 | 46 | 51 |
| 8.2.30 | 450 | 2194 | 47 | 51 |
| 8.3.28 | 450 | 2194 | 47 | 51 |
| 8.4.15 | 450 | 2194 | 47 | 51 |
| 8.5.0 | 450 | 2194 | 47 | 51 |

Warnings, skips, risky and incomplete cases were zero. The checked launcher refused every initial run. These failures include source defects, incorrect collaborators and pre-existing deliberate harness fault controls; they are not all separate production bugs. Assertions differ from historical coverage checkpoints because strict diagnostics abort affected paths earlier.

## Source and contract scope

Repairs cover plugin text handling, loader/session extraction, hook registration, fieldtype lookup, MCP pagination/license input and legacy dynamic controller data, request/persistent cache keys and values, stored native serialization, navigation, module-specific settings caches, legacy query-builder cloning and licensing table caches. The builder preserves the shipped Foundation 4.10.0 behavior, licensing JavaScript, member modes, alias, metadata and SHA-256 signature algorithm rather than adopting the current shared 4.11.0 release.

The unresolved handler boundary affects both field settings and raw persistent-cache decoding. Original/candidate PHP 7.4 and 8.5 probes retain callback installations, stacked handlers, callback throws and later restores; a balanced callback also proves that the previous notice-only handler incorrectly receives an excluded warning. This best candidate remains blocked while that full native contract is required.

Stored values continue through the native PHP parser, including objects, references, ArrayAccess carriers, native scalar roots, clamped integers and legacy escaped strings. Actual original/candidate comparisons retain 3,000 binary/reference value checks. New tests exercise malformed-tail callback propagation, decimal bounds, extraction root/container links, POSIX search permission, native cache directory aliases and settings read/write boundaries. Native installed EE query-driver compilation proves cloned queries reset their inherited predicates without changing the caller; it opens no database connection. Six original PHP 7.4 MCP consumer forms match the candidate on all seven runtimes.

The plugin's documented text input preserves native scalar/Stringable behavior and falsey fallback. Unsupported selected arrays, resources and non-Stringable objects fail explicitly before text processing. An actual original-plugin probe establishes the supported text results and the existing PHP 8.5 TypeError boundary; no source diagnostic is accepted as a passing test.

## Checked validation

Use one explicit runtime:

```sh
PHP_BINARY="/Users/tomjaeger/Library/Application Support/Herd/bin/php74" ./tests/.phpunit/run-phpunit.sh -c phpunit.xml
```

The pinned PHPUnit 9.6.38 checksum remains unchanged. The Python launcher rejects unexpected diagnostics even when PHP code catches the resulting ErrorException. It also rejects nonzero or noisy ordinary native children, missing startup receipts, stale/empty reports and incomplete owned process cleanup. Source tests execute the distributed artifact, with the active source inventory refreshed after rebuilding.

The inherited suite deliberately injects harness faults. Finite control validation binds those cases to their actual qualified JUnit identity, owned child invocation, nonce path, generated dependency bytes, diagnostic statement/severity/message and expected terminal streams. Every other retained event fails validation. These controls grant no exception for a production warning. The separate nine-case output-destination suite uses the same checked process path and preserves files and symlink targets on rejection.

Current focused endpoint proofs pass 105 cases / 312 assertions for source boundaries, 20 / 112 for cache aliases, and 9 / 97 for separate authored output controls on every configured runtime. Ninety-one fresh caught-diagnostic controls, including an unrelated caught diagnostic inside native mkdir handling, on all seven runtimes fail the checked invocation while the underlying one-case PHPUnit result is green. The best-candidate complete matrix has 541 cases / 2,759 assertions on each runtime, with zero configured errors, failures, warnings, skips, risky, incomplete and unexpected diagnostics. All 450 original case identities remain. These configured counts do not override the separate native handler-state counterexamples, and are not an acceptance result for the complete compatibility goal.

## Limits

This validation uses actual distributed source with narrow EE collaborators and selected installed native EE contracts. It does not establish a live EE request, SQL transaction, browser, licensing transport, Windows filesystem or universal coverage result. Historical coverage and continuation observations remain separate evidence and do not count as a compatibility pass. Independent review and coordinator approval are required before publication; no merge or deployment is included.

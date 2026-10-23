# Low Widont PHPUnit campaign

Status: **blocked from full completion; independently reviewed checkpoint published**. This is useful test coverage and red-regression work, not a full-coverage milestone. Production changes are not authorized.

## Baseline and scope

- Baseline: `3a38b1fb9146daa87ed9f1a83699521ea03091da` (`origin/develop`).
- Clean `develop` fetched from origin, then `git pull --ff-only origin develop` succeeded; exact parity was `0 0` before creating `unit-tests-2026`.
- Origin: `https://github.com/EEHarbor/low_widont.git`. No worktrees, dirty work, or existing testing branch were displaced.
- All four loose PHP files, all 22 archive PHP entries, and the archive extraction stub are included: 27 source scopes and 260 PHP function/method/closure declarations, including abstract declarations. `SOURCE_INVENTORY.json` records token-based names, lines, sizes and SHA-256 hashes.
- Tests/support/config/docs only. `git diff --exit-code 3a38b1fb9146daa87ed9f1a83699521ea03091da -- low_widont` succeeds.

## Tooling and diagnostics

Read-only preflight found no harness. Scaffold plus local PHAR bootstrap succeeded. Resolved command: `./tests/.phpunit/run-phpunit.sh -c phpunit.xml`. PHPUnit 9.6.38 is pinned and hash-verified for every runtime; the installer also pins the skill's PHPUnit 10/11 downloads without altering production Composer dependencies. PHAR binaries and generated reports are ignored locally.

Preflight subsequently printed `PRECHECK_STATUS=ok` with a help-pipeline note. Capturing `--help` to a file independently confirms `--coverage-clover`; real Clover and PHP coverage artifacts were generated successfully. The helper's pipeline note is not a missing-driver failure.

`E_ALL` is enabled. Every test installs a handler that fails every diagnostic even under `@`; dedicated suppressed `E_USER_DEPRECATED` and `E_USER_WARNING` fixtures verify this. No suppression mask is honored. Native compilation checks every shipped scope in fresh selected-runtime processes. Loader probes exercise direct-access protection, missing archive/class errors, real PHAR stream loading, OPcache permission validation and self-extraction under private unique temporary roots. No global runtime, INI or service switches occur.

The EE facade and database/result/helper doubles were checked against `/Users/tomjaeger/Sites/ee_repo_2/system/ee/ExpressionEngine/Boot/boot.php`, legacy file/form helpers, and core test patterns. Missing template parameters use the actual `fetch_param()` default `false`; no fabricated null-return deprecation is counted as a source defect.

## Complete configured matrix

Command: `./tests/run-matrix.sh`. Reports: `tests/reports/matrix.n3co7M/`, with fresh per-runtime JUnit XML, logs, exact versions and selected binaries. All commands run every configured test, including regressions.

| PHP | Tests | Assertions | Errors | Failures | Warnings | Skipped | Risky / incomplete |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 7.4.33 | 163 | 836 | 15 | 9 | 0 | 0 | 0 / 0 |
| 8.0.30 | 163 | 836 | 15 | 9 | 0 | 0 | 0 / 0 |
| 8.1.33 | 163 | 836 | 15 | 9 | 0 | 0 | 0 / 0 |
| 8.2.30 | 163 | 830 | 16 | 9 | 0 | 0 | 0 / 0 |
| 8.3.28 | 163 | 830 | 16 | 9 | 0 | 0 | 0 / 0 |
| 8.4.15 | 163 | 830 | 16 | 9 | 0 | 0 | 0 / 0 |
| 8.5.0 | 163 | 830 | 16 | 9 | 0 | 0 | 0 / 0 |

Every full PHP invocation exits 2, and the matrix runner reports failure. Lower assertion totals on PHP 8.2+ reflect the additional source deprecation interrupting a test. All failures/errors below are attributed to unchanged source. A diagnostic-only randomized run excluding the labeled regressions passed: 138 tests, 796 assertions, seed `20261023`. That run is not the full matrix and is not a completion gate. A zero-match selection (`--filter NoSuchTestCase`) returned exit 1; empty suites are rejected.

## Confirmed source defects retained as red tests

1. `FluxCapacitor/Base/Pi.php:30`: static `info()` uses `$this`, raising `Error`. `PI_INFO_BLOCKER.json` records three independent invocation attempts (static, instance, reflection), all failing at line 30. Statements 31–35 cannot be reached normally after that unconditional error; the method also contains no return. Production cannot be repaired in this campaign.
2. `FluxCapacitor/Conduit/PersistentCache.php:21` calls `StaticCache::get($key, true)` before normalizing the documented string-or-array key. Seven composite-key payload vectors fail with an illegal array-offset warning/TypeError, including scalar, object, nested, empty, false, zero and null cached values.
3. Persistent cache `set()` stores request data under the SHA-1 key, while `get()` initially looks up the raw key. Removing the disk file after a successful set exposes the missed request-cache hit.
4. `FluxCapacitor/Conduit/StaticCache.php:55` returns `isset()` after unsetting the entry, so successful deletion reports false instead of the documented success boolean.
5. `FluxCapacitor/Base/Ext.php:189`: legacy `register_extension()` resets the custom hook, priority and enabled arguments instead of forwarding them.
6. `FluxCapacitor/Base/Mcp.php:165`: `paginateModel()` creates undeclared `$data`, producing an `E_DEPRECATED` on PHP 8.2+. The regression passes on PHP 7.4–8.1, then fails on all four newer runtimes through the diagnostic handler.


7. `StaticCache.php:18`: documented empty array values, zero, numeric-string zero and empty strings become cache misses although `has()` reports the stored entry. Four new red vectors preserve this boundary defect.
8. `StaticCache.php:106`: filtering the composite key drops a meaningful zero component, so `['entry', 0, 'site']` collides with `['entry', 'site']` and silently overwrites data. The prior algorithm-shaped assertion was replaced by a distinct-key regression.
9. `GlobalFieldSettings.php:21`: a missing setting in a nonempty record emits an undefined-index/key warning hidden by `@`.
10. `GlobalFieldSettings.php:33`: malformed persisted serialization emits an `unserialize()` warning hidden by `@`.
11. `PersistentCache.php:196,215`: a file occupying either cache directory causes suppressed `mkdir()` warnings before the documented guard/logging return. Five red path-guard vectors now expose these ordinary diagnostics.
12. PHAR stub line 177: native no-stream fallback extraction repeatedly calls `@mkdir()` on an existing directory and emits `File exists`. Its former green loader case is now an explicitly labeled red diagnostic regression. Native child stderr is merged into stdout to avoid sequential pipe-drain deadlocks.

The independent review required strict handling of ordinary suppressed warnings and meaningful falsey/zero-key cache tests. Both findings were corrected without editing production. The matrix now contains 25 regression cases; PHP 7.4–8.1 also pass the dynamic-property case, while PHP 8.2+ fail it as expected from unchanged source.

## Coverage and exact remaining work

There were no pre-existing tests or coverage artifacts. Initial no-harness preflight is the baseline; no empty run is called coverage. The first valid plugin-only coverage checkpoint was `tests/reports/coverage.VpNcAk/` (25 cases). Final checkpoint command: `./tests/coverage.sh`, with process-local Xdebug 3.5.3 under explicit Herd PHP 8.2.30 and EE 7.5.0. Report: `tests/reports/coverage.PB4S6r/`.

`COVERAGE_CHECKPOINT.json` preserves exact covered/uncovered executable lines for every filtered scope, the skill's 223 method-line rows (including magic methods), and 175 compiled method/procedural graphs with all raw exits and positive observed-path proof. Token inventory is authoritative for shipped declarations; the skill's regex method scanner does not enumerate every closure, abstract method or procedural scope.

- Measured lines: 1148 / 2052. This aggregate is progress only.
- Skill method rows at 100% line coverage: 123 / 223. Its missing branch metrics remain unknown despite helper `at_target` labels.
- The actual plugin constructor has 5 / 5 executable lines, 4 / 4 basic blocks and 4 / 4 outgoing edges positively proven across both observed paths. The source guard is also tested in a native subprocess; its coverage is not merged into the parent graph.
- Broad archive coverage is still incomplete: EE2, fieldtype, updater, navigation, licensing, MCP license-save flows, remaining modern bridge navigation and exceptional paths require further tests. They are not waived or claimed blocked merely because they remain unwritten.
- Genuine full-completion blockers include `Pi::info()`'s unconditional static-context error, PHP-version-specific historical branches such as `Mcp.php:146–147` (`PHP_VERSION_ID < 50306`) on the mandated PHP 7.4–8.5 matrix, and Windows-only archive extraction branches on this macOS host. Synthetic/exceptional instrumentation exits remain unknown unless independently proven.
- PHPUnit discards coverage from some failed tests; a native error reproduction does not turn unreported statements into covered lines. Failing regression coverage is not credited artificially.

`processUncoveredFiles=false` avoids PHPUnit executing caller-dependent procedural files while generating a report. All source remains in the filter, inventory and missing-coverage accounting. Measurement archive copies are byte-identical and hashed; no production archives are rebuilt. Native child data, alternate runtimes and incompatible compiled graphs are not merged.

## Instrumentation evidence

`tests/fixtures/BranchOutcomes.php` plus direct native Xdebug and PHPUnit two-case probes establish that all basic blocks can run while a genuine loop-exit outcome remains absent. The native and merged exports have matching source/runtime/compiled-graph provenance; native positive counters can become zero in PHPUnit's merge. `INSTRUMENTATION_EVIDENCE.json` preserves this discrepancy. The coverage report therefore retains raw `out/out_hit`, graph hashes, and consecutive hit-path proof; zeros from the known lossy merge are not proof of unreachable code. Full path coverage is not required.

Exact reproduction: `./tests/coverage.sh`. The report directory contains `native-instrumentation.json`, `instrumentation.php`, `instrumentation-validation.json`, `branches.json`, `inventory.json`, `coverage.php` and `clover.xml`. Regenerate portable checkpoints with `python3 tests/tools/write-checkpoint.py /absolute/report/directory`.

## Review and publication

Independent reviewer `/root/tests_low_variables` approved exact replacement SHA-256 `2bd73f71e915fc99b42d592ba8b5aa4256fe4ba839ac0db8aaa0fc53099b91a8` after both actionable harness/vector findings were fixed. Its separate baseline-plus-patch rebuild reproduced all seven matrix results and the coverage/instrumentation runner. No actionable test/harness finding remains. The reviewer approved this red-regression checkpoint, not full completion. No publication occurs before actionable test/harness findings are resolved. Scoped implementation commits separate reusable tooling, main plugin/packaging behavior, base classes, modern bridges, field settings, filesystem cache and source regressions. Each is explicitly a test/regression checkpoint, without a 100% milestone claim. Intended red regression commits are authorized by the campaign. The implementation/evidence checkpoint is committed and published; verification is recorded below.

Reviewed implementation commits:

```text
e1ec539 tests(low-widont): add pinned strict PHPUnit and coverage tooling
62897cd tests(low-widont): exercise plugin text and fallback inputs
f76d87d tests(low-widont): verify metadata license views and native compilation
61daa3a tests(low-widont): exercise archive loaders and retain extraction regression
00214df tests(low-widont): cover legacy field settings and diagnostic regressions
29d25a7 tests(low-widont): exercise modern EE bridge behavior
59c92d1 tests(low-widont): cover base classes and preserve metadata hook regressions
77a94b9 tests(low-widont): retain control panel pagination deprecation regression
5ea540e tests(low-widont): preserve static cache value key and deletion regressions
a4a8a3e tests(low-widont): exercise filesystem cache and retain guard regressions
```

Publication verified: `origin/unit-tests-2026` at implementation/evidence checkpoint `1eaf01993a8f50258fa8a2431cae46936833e062`. `git ls-remote origin refs/heads/unit-tests-2026` matched local HEAD and `origin/unit-tests-2026`; `git rev-list --left-right --count HEAD...origin/unit-tests-2026` returned `0 0`. Checkout was clean, and production source still matched the pinned baseline. This metadata-only follow-up records that verified push; final branch parity is checked again after publishing it.

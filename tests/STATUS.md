# Low Widont PHPUnit campaign

Status: **incomplete; fourth source-red checkpoint awaiting independent exact-diff review**. Production is immutable, and this checkpoint is not 100% method line/outcome coverage.

## Baseline and scope

Baseline `3a38b1fb9146daa87ed9f1a83699521ea03091da` is the clean, freshly fetched `origin/develop` after `git pull --ff-only origin develop`, with verified `0 0` parity before creating `unit-tests-2026`. Origin is `https://github.com/EEHarbor/low_widont.git`. No reset, clean, stash, force, or unrelated edits were used. Production comparison remains empty:

```sh
git diff --exit-code 3a38b1fb9146daa87ed9f1a83699521ea03091da -- low_widont
```

`SOURCE_INVENTORY.json` includes four loose PHP files, 22 archive PHP entries and the physical extraction stub: 27 scopes and 260 function/method/closure declarations. Twenty-six declarations are abstract and have no executable body. The inventory now records exact source ranges. Ordinary tests use shipped source/archive bytes; measurement copies and private request mirrors are verified byte-for-byte. Archives are never rebuilt.

The previous published checkpoint is `56c52715c7c783dc3afb1c7658a415f6d9097706`, independently approved under exact patch SHA256 `2bd73f71e915fc99b42d592ba8b5aa4256fe4ba839ac0db8aaa0fc53099b91a8`. Its final local/origin parity was `0 0`, with clean status. That approval applies only to the first checkpoint.

## Tooling and EE contracts

The original no-harness preflight was followed by successful scaffold/local tool bootstrap. Resolved command is `./tests/.phpunit/run-phpunit.sh -c phpunit.xml`; PHPUnit 9.6.38 is pinned to SHA256 `6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7`. PHPUnit binaries and fresh run directories remain ignored. No production Composer dependency or global Herd/INI/service setting changes.

Every test fails all E_ALL diagnostics, including ordinary warnings and deprecations under `@`. Dedicated suppressed warning/deprecation tests verify the handler. Native compilation covers all 27 shipped scopes on every selected runtime. Reflection accessibility is changed only below PHP 8.1. Native pipes combine stderr/stdout to avoid sequential drain deadlocks. Temporary roots and reports are owned and isolated; source-induced CWD/include-path/autoloader/global/static changes are restored. Native evidence JSON is fully written before atomic publication.

Doubles were checked against installed EE in `/Users/tomjaeger/Sites/ee_repo_2`: legacy Input missing POST returns false, modern Request uses caller defaults, Session::set_cache returns self, Facade::set rejects already loaded values including null, and fresh database query builders share underlying connection state. Database mutations assert actual rows and unrelated site preservation, not an earlier SELECT's predicates. A stale table-list fixture exercises the source's documented refresh behavior on independently created builders. The small fieldtype parent mirrors only the settings/content ID initialization used here. Helpers match EE file locking/read semantics; optional legacy callbacks are tested as delegation boundaries.

## Complete configured matrix

Command: `./tests/run-matrix.sh`. Fresh reports: `tests/reports/matrix.QySwsQ/`. Portable complete logs/JUnit, versions, commands and SHA256 hashes are retained in `tests/evidence/`.

| PHP | Tests | Assertions | Errors | Failures | Warnings | Skipped | Risky / incomplete |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 7.4.33 | 404 | 1929 | 44 | 35 | 0 | 0 | 0 / 0 |
| 8.0.30 | 404 | 1921 | 46 | 36 | 0 | 0 | 0 / 0 |
| 8.1.33 | 404 | 1921 | 46 | 36 | 0 | 0 | 0 / 0 |
| 8.2.30 | 404 | 1915 | 47 | 36 | 0 | 0 | 0 / 0 |
| 8.3.28 | 404 | 1915 | 47 | 36 | 0 | 0 | 0 / 0 |
| 8.4.15 | 404 | 1915 | 47 | 36 | 0 | 0 | 0 / 0 |
| 8.5.0 | 404 | 1915 | 47 | 36 | 0 | 0 | 0 / 0 |

All complete suites exit 2 because unchanged source is red. PHP differences reflect the invalid legacy comparison operator and dynamic property diagnostic, not skipped cases. The full randomized PHP 8.3 run (seed 20261027) reproduces 404 / 1915 / 47 errors / 36 failures. The preceding second checkpoint also had a diagnostic-only non-regression run; it is not a final full-matrix gate. Zero-test selections are rejected.

All 19 configured test files were also run individually on PHP 7.4 and 8.5 (38 fresh runs). Their case outcome kinds and exception types match the complete suite with no discovery-order differences. Commands and results are in `evidence/individual-files.json.gz`.

The published second checkpoint added archive manifest extraction for every actual entry, compression/read-chunk boundaries, all licensing schema/read/write/pixel methods, MCP license forms/saves, updater install/uninstall, fieldtype/channel resolution, modern/legacy navigation and bridge boundaries. Twenty-two native contexts cover EE2–EE7 selection, CP/site requests, optional navigation and extension availability, legacy permissions/cache, theme constants and alternate fieldtype parent layouts. Context failures remain ordinary red assertions while independent cases continue running.

## Confirmed source defects retained red

`REGRESSION_CHECKPOINT.json` and complete matrix logs preserve exact case names, diagnostics and source traces. No source failure is converted into an expected-success assertion.

1. `Base/Pi.php:30`: static info() uses $this unconditionally and has no return. Three independent invocation forms in `PI_INFO_BLOCKER.json` fail before statements 31–35.
2. `PersistentCache.php:21`: documented array keys reach StaticCache as processed array offsets before normalization; ten composite payload vectors fail.
3. Persistent set/get use different static keys; removing the disk file after set exposes the missed request hit.
4. `StaticCache.php:55`: delete returns isset after unset and reports false on success.
5. `Base/Ext.php:189`: register_extension discards caller hook/priority/enabled. Its unregister_extension alias likewise discards the custom hook and deletes the wrong registration.
6. `Base/Mcp.php:165`: undeclared data property is deprecated on PHP 8.2+. A legitimate derived controller declaring data separately verifies functional pagination and clone/URL behavior.
7. StaticCache get loses stored [], 0, '0' and empty-string values; composite key normalization also drops meaningful zero components and overwrites distinct data.
8. GlobalFieldSettings missing keys and malformed persisted serialization emit suppressed diagnostics.
9. Persistent path creation emits suppressed mkdir warnings when files occupy directories. Malformed disk serialization also emits an uncaught diagnostic; has ignores its processed-key flag; zero composite keys overwrite their parent entry.
10. PHAR fallback extraction repeats mkdir on existing directories. Nested cleanup calls rmdir on a nonempty directory before its intended recursion. Size mismatch reporting dereferences undefined $stat.
11. Version's cached table verification does not restore a new instance's private corruption state; SHOW KEYS hard-codes exp_; insertion/deletion leave cached existence results stale.
12. MCP license conflict checks treat SQL string site_id and integer config site_id as different sites. Array-shaped POST license values reach strlen rather than a clean rejection.
13. Modern bridge active-map navigation assigns a header object as the page title. overwriteEEClass forwards to a Facade setter that refuses ordinary/null already-loaded services. App settings cache is shared between separately identified bridge instances. Short URL segments and malformed fieldtype serialization emit suppressed diagnostics.
14. Legacy EE2 getBaseURL uses invalid operator =<. PHP 8+ throws; PHP 7.4 misses the 2.6–2.7.3 admin-session workaround. Legacy persistent delete/clear leave native cached values intact; missing URL query keys emit suppressed diagnostics.
15. Missing ChannelEntry results are dereferenced; plugin-only updater includes a missing optional extension file under @; updater catches a namespaced Exception rather than the global exception thrown by an extension; loading shared McpNav unconditionally includes an absent optional addon file.
16. Negative page input reaches the CP pagination service without a positive-page guard.
17. EE2 getCurrentPage advertises default options but reads a missing per_page option when normal per_page query input is present (line 178). The public default invocation remains red.
18. EE2 settings builders inherit pending predicates when cloned. A pending valid setting-column filter causes a read miss and an attempted extra insert rather than updating the current site. Separate red vectors preserve the unrelated pending query and other-site records. Actual installed EE driver/query-compiler observations retain source hashes and the inherited protected SQL predicates in `evidence/ee-query-clone.json`; no live SQL was executed.
19. A real unreadable warmed fallback cache file reaches the source include warning at `autoload.php:84` instead of the explicit loader rejection boundary. The red subprocess vector retains the strict diagnostic.
20. EE2 URL information treats a normal `D=cp&module=low_widont` request as non-CP because it tests the first slash-delimited query segment against `cp`. The full-map intended `cp=true` oracle remains red; absent-D and non-CP query controls pass.

## Coverage and evidence boundaries

Parent measurement command: `./tests/coverage.sh`, explicit Herd PHP 8.2.30 / Xdebug 3.5.3 / EE 7.5.0. Fresh report: `tests/reports/coverage.917XQJ/`. `COVERAGE_CHECKPOINT.json` contains 27 line scopes, 223 skill method rows and 233 measured method/procedural graphs.

- Parent Clover: 1685 / 2039 measured lines; 180 / 223 skill rows have 100% lines. These are progress only. The denominator is compiled-graph dependent and differs from the first checkpoint; aggregate totals are not closure proof.
- Skill branch_percent=null remains unknown even when its helper labels a row at_target.
- Native command: `./tests/native-coverage.sh`; report `tests/reports/native.5KYjy2/`. It executes the complete 404-case suite without PHPUnit's coverage reset/discard behavior and retains 56 separate main/context/terminal-child reports. Extra assertions (2272) validate child coverage units against original source hashes. Every context must retain actual archive graphs; autoload-only or missing reports fail validation.
- `NATIVE_COVERAGE_CHECKPOINT.json` has all 260 declarations plus 27 procedural rows. It validates every raw unit/hash/flag and retains each runtime/compiled-graph observation separately. Raw native counters are never merged across incompatible graphs. All 234 executable declarations are behavior-invoked across the normal suite and isolated contexts; this does not establish every exit or line.
- Xdebug aliases the conditional Bridge/EE.php constructor graphs to the EE2 declaration at line 42 even when ReflectionMethod and actual parent selection prove later source declarations at lines 50/58/66/74/82. Twenty-five such observations are quarantined in ambiguous_graphs, leaving those five constructor metrics unknown. Raw reports preserve both facts.
- Source errors block Pi info continuation, mkdir guard/log return paths, recursive nonempty cleanup and cold fallback extraction continuation. A separately preloaded immutable archive and verified owned cache exercise the loader continuation after the PHAR wrapper is removed, without crediting cold extraction. An actual unreadable cache file remains red at the source include warning. Historical PHP_VERSION_ID < 50306, Windows-only tmpdir logic and archive-global compression/manifest choices cannot change under the mandated runtimes/platform and immutable shipped archive. Failed extraction vectors remain red; unreachable later code is not credited.
- Synthetic terminal/exception exits and native epilogue line markers remain explicit instrumentation limitations. Per-graph remaining lines/blocks/exits stay in the reports. A never-invoked opcode graph variant is not silently treated as complete because another variant ran.

The earlier macOS /var versus /private/var child filter mismatch was found and fixed: private mirrors now use canonical paths. Old native.oAT5DA/J4gxUR reports are superseded, not used as archive-context coverage evidence. Measurement-only original-stub loading and direct archive loader isolation were also corrected before this snapshot.

Independent review found the invalid-instrumentation guard referenced its report name before assignment. The guard now identifies the offending record whether it appears first or after valid input. `python3 -B tests/tools/test_native_report.py` retains both malformed-input probes; valid native checkpoint output is unchanged.

`INSTRUMENTATION_EVIDENCE.json` and the BranchOutcomes fixture preserve the independent native-versus-PHPUnit merge-loss proof: all blocks can run with one loop exit absent; normalized PHPUnit out_hit can lose native positive counters even for the same compiled graph. Basic blocks are named as such, outgoing counters/observed paths are retained, and no 100% path target is imposed.

## Exact remaining work and publication

Full completion remains blocked/incomplete: remaining actual outgoing outcomes, synthetic/conditional-class provenance limitations, platform/runtime/archive-fixed paths and confirmed source errors are unwaived. Real database execution, full EE request bootstrap/permissions, race/IO failures and all historical host versions remain integration gaps. Independent feasible work continues after this checkpoint; this is not a per-repository exhaustion claim.

The third checkpoint exercises all six previously listed ordinary vectors, empty Structure site rows, preloaded fieldtype parents through system/legacy/installer lookup combinations, and OPcache permission/API variants in separate processes. Its OpenSSL observation seam delegates to the real encryption function and asserts every plaintext payload field with and without Pro. Low Widont uses a local Pro query result and passes these expectations. `REMAINING_COVERAGE.md` maps remaining outcomes to source failures, terminal boundaries, fixed runtimes/platform/archive choices and unresolved driver/loop/switch evidence. `REMAINING_COVERAGE.json` retains exact per-graph line and node-edge triage; no missing counter is waived or treated as impossibility proof. Ordinary semantic vectors are distinguished from still-unknown graph outcomes; this remains a checkpoint rather than an exhaustion claim.

The first checkpoint's independently reviewed implementation commits are e1ec539, 62897cd, f76d87d, 61daa3a, 00214df, 29d25a7, 59c92d1, 77a94b9, 5ea540e and a4a8a3e, with evidence/publication metadata at 1eaf019 and 56c5271.

The second checkpoint was independently approved by the Low Variables owner under exact patch SHA256 `0bcbd55121b92071b22463475a26985c9bd847e17d0b635337d90802e7c1f547` (105 tests-only paths, base `56c52715c7c783dc3afb1c7658a415f6d9097706`). Its private reconstruction reproduced the full seven-runtime counts, all 38 individual-file runs, all 58 retained evidence hashes and the 33-record native report. The invalid-flags guard finding was corrected and both malformed-input probes passed; no actionable test/harness finding remained. Residual integration and coverage gaps were expressly retained. Reviewed implementation/evidence commit `02f4bdec6719f96ee36abfd2ca57afb796d19954` was pushed to `origin/unit-tests-2026`, with local/origin parity `0 0`, clean status and unchanged production source verified afterward. This later STATUS-only publication note is permitted factual review metadata. Red regression publication is expressly authorized by the campaign; no production repair is included.

The third checkpoint adds 28 cases (390 total), with the configured seven-runtime, full randomized and all 38 individual-file runs refreshed. The native report now contains 48 records (main, 30 contexts and 17 terminal children); 256 of 287 inventory rows have a known compiled graph, with 26 abstract declarations and 23 quarantined conditional-constructor observations. Clover remains 1685/2039 lines and 180/223 line-only skill rows; added behavior and child contexts are not inflated into parent line gains. Both empty and populated native ArrayIterator hook collections now complement the ordinary array vectors, exercising the feasible foreach entry outcome without changing EE result-array contracts. Conditional constructor records missing runtime dispatch metadata are quarantined; a retained-real-record probe verifies that no declaration is credited. Two Python tests retain all malformed-flags and missing-dispatch vectors. The Python guard now requires actual integer flags 7 and rejects missing/0/6/7.0 records both first and after valid input. Independent exact review and origin publication are recorded below; assigned coverage work continues.


The Low Variables reviewer independently approved exact third-checkpoint patch SHA256 `c9f489d6569567415148d5ccfc4771b0b4fc4d4f6cdca0c7b79801949f50fd7e` against `99cebb3e050fb61a76382526ea8959f18f845313`. Its private reconstruction verified all 121 path/deletion hashes, all 73 portable evidence hashes, pinned PHPUnit and every production file against the original baseline. It reproduced all seven 390-case results, randomized PHP 8.3 outcomes, all 38 individual-file PHP 7.4/8.5 outcome sets and the fresh 48-record native capture. The actual extension iterator graph now has all five raw exits hit; missing-dispatch constructors remain unknown. No actionable test/harness issue remained in the bounded review. Real EE/SQL/permissions/IO/platform/historical/source-terminal and remaining graph/outcome gaps remain incomplete. Factual review/publication metadata additions were explicitly permitted.

Reviewed implementation/evidence commit `5f7095dd998743e3b01d5bf7fb06685d02c108c2` was pushed only to `origin/unit-tests-2026`. `git ls-remote --heads origin unit-tests-2026` returned that exact SHA, local/origin parity was `0 0`, status was clean and production comparison remained empty. This subsequent STATUS-only audit records publication; no PR, merge or production repair is included.


The fourth checkpoint adds 14 cases (404 total): native add-on navigation collections and empty-string aliases; normal default-selector legacy URL information; absent table-list metadata; pending legacy query read/write isolation; warmed fallback cache success/miss with OPcache disabled/enabled; unavailable OPcache API under both compiled variants; and an unreadable POSIX cache file. Complete seven-runtime, randomized PHP 8.3 seed 20261027, all 38 individual-file, native and Clover/skill evidence are refreshed. Native capture retains 56 reports (main, 32 contexts and 23 terminal children), 256/287 known graphs and 25 quarantined conditional-constructor observations. Counts and positive edges stay attached to exact source/runtime/compiled graphs; no synthetic exit is waived. The remaining-work ledger now has 38 graph rows; default selectors, absent metadata, native navigation collections/aliases and warmed loader continuations remove concrete ordinary gaps without declaring unknown outcomes complete. Independent exact review and publication are recorded below for this fourth checkpoint.


The fourth-checkpoint original exact patch `6bb9e1277dcb997a361e3b3bbb07011aa6a45de547b383a631b1bf989740bde6` is withdrawn. Independent review identified a new realistic EE2 CP query whose oracle incorrectly accepted the source's false CP flag. The replacement preserves the complete map with the semantic `cp=true` expectation as red and adds separate non-CP query controls. Full seven-runtime, random-order, individual-file and native evidence were refreshed after the correction; production is unchanged.


The Low Variables reviewer independently approved exact fourth-checkpoint patch SHA256 `14aee93bfc8c0cf69d5042023149905b85f4d1e72a575216657b413e07503611` against `4a5cd66f3c1e4576764a1c414b3299a6b1b640f6`. Its private reconstruction verified all 140 path/deletion hashes, 82 portable evidence hashes, pinned PHPUnit and immutable production/source inventory hashes. It reproduced all seven 404-case results, randomized PHP 8.3 outcomes, all 38 individual-file PHP 7.4/8.5 outcome sets and a fresh 56-record native capture with the exact 38-row remaining ledger. Both flags/dispatch probes passed. The corrected CP=true red oracle and non-CP controls resolve the review finding; no actionable test/harness issue remained. Real SQL/full EE/permissions/platform/conditional/outgoing gaps remain incomplete. Factual review/publication metadata additions were explicitly permitted.

Reviewed implementation/evidence commit `63cbb451e82e82ee6c347de8538e5d828aa1d51a` was pushed only to `origin/unit-tests-2026`. The remote returned that exact SHA, local/origin parity was `0 0`, status was clean and production comparison remained empty. This subsequent STATUS-only audit records publication; coverage work continues without a full-completion claim.

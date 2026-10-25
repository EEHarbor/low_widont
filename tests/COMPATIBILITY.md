# Low Widont PHP compatibility

Status: blocked best candidate; do not publish. The configured suite contains 702 case identities, including positive opaque native Stringable compatibility failures on affected runtimes. The current immutable blocker packet records exact per-runtime assertions and outcomes; this is not an all-green compatibility result. Actual native probes also confirm that the serialization and native mkdir handlers change callback-installed handler state and the previous handler's partial severity mask. Full native API preservation requires compatible upstream/runtime support or an explicitly revised scope. No approximate handler reconstruction is included; independent review and final approval remain outstanding. The original archive and all historical test reports remain available from the pinned baseline. This campaign changes the distributed source and rebuilds the actual PHAR; historical tests-only coverage percentages do not measure this candidate.

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

The unresolved handler boundary affects field settings, raw persistent-cache decoding and custom-stream mkdir callbacks. Original/candidate PHP 7.4 and 8.5 probes retain callback installations, stacked handlers, callback throws and later restores; a balanced callback also proves that the previous notice-only handler incorrectly receives an excluded warning. This best candidate remains blocked while that full native contract is required.

Stored values continue through the native PHP parser, including objects, references, ArrayAccess carriers, native scalar roots, clamped integers and legacy escaped strings. Actual original/candidate comparisons retain 3,000 binary/reference value checks. New tests exercise malformed-tail callback propagation, decimal bounds, extraction root/container links, POSIX search permission, native cache directory aliases and settings read/write boundaries. Native installed EE query-driver compilation proves cloned queries reset their inherited predicates without changing the caller; it opens no database connection. Six original PHP 7.4 MCP consumer forms match the candidate on all seven runtimes.

Raw integer overflow retains its original warning policy. Actual original PHP 7.4/8.5 raw cache reads emit `Numerical result out of range` and return the native clamp to a recording handler; this input was already diagnostic-producing. The raw cache explicitly requests forwarding of that exact native range warning, while originally `@unserialize` settings reads keep their quiet clamp/read/write contract. Existing settings overflow tests remain unchanged. A new ordinary raw-cache regression uses exact native integer boundaries. A separate two-case raw-reader negative fixture catches both range diagnostics; all seven checked invocations must still reject with 70 with raw PHPUnit 2/A2 green. This control is outside the configured ordinary suite and does not accept a source diagnostic as a passing compatibility result.

The ordinary review repairs restore an explicitly supplied caller directory after direct cleanup of existing/dangling/trailing root links and retain the outside target and hidden data. Optional navigation overrides must be readable before inclusion; readable extensions and missing-file fallback remain covered. Float array-key reads and writes preserve native PHP 7.4 integer-width results without a warning-producing cast. Original PHP 7.4 and current PHP 7.4–8.5 public read/write/reread probes agree for 2,011 deterministic IEEE float keys. String-offset writes retain the first byte for text, UTF-8 and null-byte values. The conversion remains local to existing methods, preserving existing child method names; a positive legacy-subclass regression guards a discarded helper prototype's PHP 7.4 signature conflict. The newly authored fractional-key null expectation was corrected against the actual original API.

The ordinary checked runner validates requested JUnit destinations with the existing physical destination guard before allocating a run or starting PHPUnit. It rechecks the destination before exclusive creation. Both separated and inline CLI forms preserve source, existing files and alias targets on rejection. Two direct wrapper regression cases cover eight unsafe invocations and two fresh positive outputs per endpoint, using an owned export. The tests pass on PHP 7.4 and 8.5; the complete configured matrix also exercises fresh report publication on all seven runtimes.

The plugin's documented text input preserves native scalar/Stringable behavior and falsey fallback. Unsupported selected arrays, resources and non-Stringable objects fail explicitly before text processing. An actual original-plugin probe establishes the supported text results and the existing PHP 8.5 TypeError boundary; no source diagnostic is accepted as a passing test.

## Checked validation

Use one explicit runtime:

```sh
PHP_BINARY="/Users/tomjaeger/Library/Application Support/Herd/bin/php74" ./tests/.phpunit/run-phpunit.sh -c phpunit.xml
```

The pinned PHPUnit 9.6.38 checksum remains unchanged. The Python launcher rejects unexpected diagnostics even when PHP code catches the resulting ErrorException. It also rejects nonzero or noisy ordinary native children, missing startup receipts, stale/empty reports and incomplete owned process cleanup. Source tests execute the distributed artifact, with the active source inventory refreshed after rebuilding.

The inherited suite deliberately injects harness faults. Finite control validation binds those cases to their actual qualified JUnit identity, owned child invocation, nonce path, generated dependency bytes, diagnostic statement/severity/message and expected terminal streams. Every other retained event fails validation. These controls grant no exception for a production warning. The separate nine-case output-destination suite uses the same checked process path and preserves files and symlink targets on rejection.

The separate output-destination suite has nine cases / 97 assertions. Each runtime also has 18 owned one-case caught-diagnostic controls (126 across all seven runtimes) and one two-case raw-range control: the inner results are green while checked validation rejects the retained diagnostics. All 450 original case identities remain. The historical v5 checkpoint had 672 cases: PHP74–82 A3200 with zero configured issues; PHP83 F2/A3196; PHP84–85 F4/A3192. Only positive opaque native wire cases failed. Earlier 574-case/A2830 and 91-control figures describe older checkpoints and do not describe the current source. The current replacement packet binds its complete matrix and source hashes; native B1/B2 remain separate blockers.

## Limits

This validation uses actual distributed source with narrow EE collaborators and selected installed native EE contracts. It does not establish a live EE request, SQL transaction, browser, licensing transport, Windows filesystem or universal coverage result. Historical coverage and continuation observations remain separate evidence and do not count as a compatibility pass. Independent review and coordinator approval are required before publication; no merge or deployment is included.

The existing nonfinal PersistentCache class retains its original method names. Directory checks and native mkdir handling use local closures in checkPath; twelve actual archive child cases retain public, protected and private static and instance methods with the former helper names. Native handler B1 remains unchanged.


The renewed ordinary batch preserves both public setter parameters through serialization and database completion, first native callee binding through actual pinned PHPUnit static restoration, and temporary operand release on success and exceptional unwinding. Each original cache-directory creation site now retains independent first resolution, original arguments and its original reporting scope. A legal hook installed during generic-directory creation can govern the later module-directory site. NAN settings roots retain their original PHP 7.4 value without PHP 8.5 boolean coercion. Permanent positive contracts use actual distributed source and an unchanged EE file helper.

Nonstring parser arguments remain delegated to native conversion in its original frame and reporting scope. Legacy escaped-string and trailing-prefix bytes hidden inside those callbacks remain positive failing compatibility cases on affected newer runtimes; no diagnostic expectation or exclusion makes them green. Temporary string-parser and native-mkdir wrappers still cannot preserve arbitrary prior handler severity masks and callback-installed handler stacks (B1). The exact final matrix and original/current counterexamples are retained in the replacement blocker packet; this candidate is not publication-ready. The ordinary batch was authored05; the real EE helper is copied unchanged with recorded provenance. Historical lexer/packaging provenance remains as previously disclosed.


All four EE2–5 Bridge field-setting expressions receive the same independently bound original host hooks and temporary operand transfer. Their own positive contracts include untouched object carriers, one native-frame conversion, original reporting scopes and actual pinned PHPUnit restoration. The original release-specific sources and unrelated cache zero-key behavior remain retained.

Native command validation finds the PHP option region before enforcing required diagnostic settings. Selected inline execution stays guarded even when a later `-l` option is present; a real `--` keeps literal script arguments intact. The inline guard follows legal leading declarations and namespace headers and preserves an existing exact first authority import. Actual file, explicit-file, define and healthy lint forms retain their positive contracts. Separate compiler-diagnostic controls remain rejected even when caller options request `error_reporting=0`.

A file caller prelude executes after the strict authority in the original global scope, with no requirement to import test support. A separate guarded, owned native inline process reads the effective prelude from the same native INI selection; `-c`, scanned/default settings and explicit overrides follow the selected interpreter rather than assumptions about Herd. Every discovery process has the same strict startup, stream, PID and cleanup accounting. Finite fault controls bind the exact healthy configuration reader before their original child; their diagnostic and terminal ownership checks remain in force.

The current main matrix is 715/A3387 on PHP 7.4, 717/A3393 on PHP 8.0–8.2, 717/F2/A3389 on 8.3 and 717/F4/A3385 on 8.4–8.5, solely retained positive opaque-wire failures. All 702 previous and 450 original identities remain. The ordinary nine-case destination suite passes9/A97 on all7. The separate fixture-only instrumentation configuration passes2/A2 on all7 and establishes no add-on source behavior.

`python3 tests/check-native-cli.py --php /absolute/php /absolute/new/external/evidence` runs eight actual checked controls on one selected runtime: seven caught inline/prelude diagnostics keep the inner testcase green while checked validation rejects70, and an ordinary kernel-free caller prelude remains healthy. The tool verifies the exact retained user deprecation and successful quiet child completion, so an alternate child failure cannot satisfy the control. Run it for each pinned runtime. Existing126 one-case and7 two-case raw-range controls also remain required. No native handler or opaque-wire blocker is waived.

Virtual stream storage retains the original writable-directory decision without a host POSIX execute-bit requirement. Plain and `file://` filesystem paths retain their search-permission check. Positive stream creation and persisted bytes are tested separately from mode0200 host failures.

A legal by-reference mkdir hook may retain an opaque path object. The host-search predicate checks only actual strings, preserving the original number of native conversions and the successful write.

Leading declaration bodies retain their native placement before namespace headers. Empty and nested empty bodies stay empty; executable bodies receive authority before their first live statement. PHP 7.4 retains its native restriction on live declaration bodies before a namespace. Empty inline programs still start authority. The caller prelude uses native include-once file identity, so selecting the authority itself through an exact or normalized path does not load its class twice.

Only punctuation tokens delimit declaration braces; interpolated literal brace text remains stored unchanged through the actual shipped cache. The earlier v8 attempt is retained as superseded by this focused self-review repair.

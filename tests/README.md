# Low Widont tests

This tests-only checkpoint runs shipped source or the original PHAR. It does not load ExpressionEngine itself. Collaborator doubles match the legacy facade, query-builder, result-object and view-helper contracts; production code is unchanged.

Install the exact local tools from the checked-in checksum manifest:

```sh
cd /Users/tomjaeger/Sites/add-ons/low_widont
./tests/.phpunit/install-tools.sh
```

Run the complete configured suite with one explicit Herd binary, or all seven runtimes:

```sh
PHP_BINARY="/Users/tomjaeger/Library/Application Support/Herd/bin/php82" ./tests/.phpunit/run-phpunit.sh -c phpunit.xml
./tests/run-matrix.sh
```

Every normal suite includes the red source regression tests. The diagnostic handler fails every E_ALL diagnostic, including ordinary warnings and deprecations hidden by @. The `regression` group labels them for diagnosis; it is never excluded by the full matrix or coverage runner. Native subprocesses inherit the selected runtime and INI/environment. Tests include native compilation probes for all 27 shipped PHP scopes, direct-access and autoload exceptions, PHAR-stream loading, OPcache permission validation, and fallback extraction under a unique private temporary directory. No global Herd or PHP configuration changes are made.

Produce actual Xdebug block/exit evidence and Clover method-line reports:

```sh
./tests/coverage.sh
python3 tests/tools/write-checkpoint.py /absolute/path/printed/as/REPORT_DIR
./tests/native-coverage.sh
python3 tests/tools/native-report.py /absolute/path/printed/as/NATIVE_REPORT_DIR > tests/NATIVE_COVERAGE_CHECKPOINT.json
python3 -B tests/tools/test_native_report.py
```

Coverage runs use new report directories. The archive measurement copies are extracted byte-for-byte, hashed against the original entries, and never rebuilt into a PHAR. Ordinary execution uses the actual archive. The measurement config retains every source file, including unexecuted files and the archive stub. `processUncoveredFiles=false` prevents PHPUnit from executing unrelated procedural views without their caller variables while building a report; unexecuted source stays in the filter and inventory. Missing branch graphs remain unknown.

The skill's Clover report gives method **line** coverage. Its `branch_percent=null` values and `at_target` labels do not establish branch coverage. `branches.json` separately records basic blocks, every outgoing edge, raw exit counters, positive observed-path evidence, runtime/source hashes, and compiled graph hashes. PHPUnit 9's merged outgoing counters can lose positive hits; a zero is not evidence that a path is unreachable. Hit paths prove only consecutive edges in the same compiled graph. Synthetic and exceptional exits are retained without waivers. Full path coverage is not required.

The instrumentation fixture independently proves the block/exit distinction with a do/while that breaks on its second iteration: all blocks can execute while the loop's false condition exit is absent. Direct native Xdebug data and PHPUnit's merged export are compared under one PHP/Xdebug/source/compiled graph. `INSTRUMENTATION_EVIDENCE.json` preserves the observed discrepancy.

The native runner preserves failed-test coverage and 32 separate EE2–EE7 request contexts, plus terminal loader/extractor/bootstrap children. Every context validates original archive-entry hashes and requires actual archive graphs. Native JSON records retain source bytes, runtime, EE version, instrumentation flags and independent compiled graphs. Conditional duplicate EE constructor locations can be aliased by Xdebug; reflection dispatch evidence quarantines those observations instead of assigning them to the first declaration. Synthetic exits remain unknown. `NATIVE_COVERAGE_CHECKPOINT.json` includes every declaration and procedural scope without claiming completion.

`evidence/SHA256.json` pins portable full-matrix logs/JUnit, randomized order, individual-file outcome comparisons, Clover/instrumentation and raw native JSON. Native reports are gzip-compressed under `evidence/native/`; decompress to a private directory preserving relative names before running `native-report.py`. These reports contain test fixture data and source provenance, not a live EE application or database execution.

`SOURCE_INVENTORY.json` includes procedural scopes, constructors, magic methods, closures, abstract declarations and extraction-stub functions. Generate it with:

```sh
"/Users/tomjaeger/Library/Application Support/Herd/bin/php82" tests/tools/source-inventory.php > tests/SOURCE_INVENTORY.json
```

`STATUS.md` records matrix results, confirmed source defects, incomplete coverage and publication evidence. This checkpoint is not a full-coverage milestone.

`REMAINING_COVERAGE.md` records ordinary vectors exercised and the precise source, terminal, runtime/platform and instrumentation boundaries still open. `REMAINING_COVERAGE.json` indexes unresolved line/node-edge observations without granting completion. The isolated license-payload request observes plaintext at a namespaced seam and delegates to real OpenSSL; its payload and ciphertext assertions run only in the selected-runtime child. Preloaded fieldtype and OPcache capability/permission variations use owned mirrors and process-local settings.

The optional read-only EE clone probe accepts an installed legacy database source directory: `php tests/tools/ee-query-clone-probe.php /path/to/system/ee/legacy/database`. It instantiates the real driver without opening a connection, compiles protected predicates before/after clone, and emits source hashes. `evidence/ee-query-clone.json` retains the explicit Herd 7.4/8.5 observations used to validate the pending-query regressions.

Separate source-warning observations use actual native PHP continuation after recoverable warnings:

```sh
./tests/warning-observations.sh
python3 -B tests/tools/warning-report.py /absolute/path/printed/by/the/runner > /private/tmp/warning-summary.json
python3 -B tests/tools/test_warning_report.py
```

An inline strict E_ALL handler is installed before the first support require. Archive/member expectations come from SOURCE_INVENTORY and are verified before original source execution. Only diagnostics from the exact hash-verified original cache source or physical archive may continue. Each is recorded and emitted to stderr immediately, including under `@`; the handler returns false so PHP retains its native warning behavior. Every support, generated-file or harness diagnostic still throws. The ordinary configured parent regressions fail on every observed diagnostic. Existing strict source tests are unchanged.

The dedicated 26-case run contains ten red observation contexts and sixteen passing controls. Twelve bootstrap fault controls exercise both entry scripts at all three support requires with suppressed warnings/deprecations; two corrupt-owned-archive controls verify pre-execution pins, and two observer-after-load controls remain. Base/module filesystem collisions exercise get/set/delete/clear. Real cold extraction verifies every one of the 22 archive members, the marker and loaded class path, then reuses the actual cache; the cleanup context calls the original recursive cleanup and retains its residual directories. Extracted compiled files are verified against original member bytes before source cleanup removes them, and raw records label that verification time.

Raw source graphs, diagnostics, stderr/stdout, exit statuses, bootstrap/pin control metadata/output and JUnit are separately retained under `evidence/warning-observations/` with gzip compression. Decompress files preserving names before invoking `warning-report.py`. Records explicitly set `mode=observational-warning-continuation`, `diagnostic_free=false` and `strict_suite_graphs_mergeable=false`. Their positive counters describe unhealthy source execution only. They are never merged into strict main/context reports or used to claim healthy EE flow, method closure or a coverage percentage gain. Synthetic epilogues remain raw instrumentation markers. Eight real-data guard tests reject missing records/graphs, wrong flags, lost diagnostics/red statuses, healthy/mergeable relabeling and mismatched source/tool hashes.

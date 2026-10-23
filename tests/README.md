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
```

Coverage runs use new report directories. The archive measurement copies are extracted byte-for-byte, hashed against the original entries, and never rebuilt into a PHAR. Ordinary execution uses the actual archive. The measurement config retains every source file, including unexecuted files and the archive stub. `processUncoveredFiles=false` prevents PHPUnit from executing unrelated procedural views without their caller variables while building a report; unexecuted source stays in the filter and inventory. Missing branch graphs remain unknown.

The skill's Clover report gives method **line** coverage. Its `branch_percent=null` values and `at_target` labels do not establish branch coverage. `branches.json` separately records basic blocks, every outgoing edge, raw exit counters, positive observed-path evidence, runtime/source hashes, and compiled graph hashes. PHPUnit 9's merged outgoing counters can lose positive hits; a zero is not evidence that a path is unreachable. Hit paths prove only consecutive edges in the same compiled graph. Synthetic and exceptional exits are retained without waivers. Full path coverage is not required.

The instrumentation fixture independently proves the block/exit distinction with a do/while that breaks on its second iteration: all blocks can execute while the loop's false condition exit is absent. Direct native Xdebug data and PHPUnit's merged export are compared under one PHP/Xdebug/source/compiled graph. `INSTRUMENTATION_EVIDENCE.json` preserves the observed discrepancy.

`SOURCE_INVENTORY.json` includes procedural scopes, constructors, magic methods, closures, abstract declarations and extraction-stub functions. Generate it with:

```sh
"/Users/tomjaeger/Library/Application Support/Herd/bin/php82" tests/tools/source-inventory.php > tests/SOURCE_INVENTORY.json
```

`STATUS.md` records matrix results, confirmed source defects, incomplete coverage and publication evidence. This checkpoint is not a full-coverage milestone.

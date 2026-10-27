# Current practical-suite status — 2026-10-27

The entire retained configured suite passes on PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5: 25 tests / 66 assertions per runtime, with zero errors, failures, warnings or skips and empty stderr. Runs use explicit Herd binaries and pinned PHPUnit 9.6.38 with E_ALL, display_errors and display_startup_errors enabled; no coverage is collected.

Before removal, the ordinary plugin test selection passed on PHP 7.4 and 8.5. The original complete suite was deliberately not executed because static inventory includes obsolete native loader, archive and harness controls. No retained normal-flow defect was observed. The removed Foundation regressions remain shared-code concerns; their removal does not certify or repair those behaviors.

Production source, shipped PHAR and autoload code are unchanged. Validation uses EE collaborator doubles and does not establish installed-EE, live database or production-site compatibility. Historical checkpoint files and evidence are preserved for reference; they describe the previous larger suite.

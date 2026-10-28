# Low Widont tests

Current review corrections and PHP 7.4–8.5 results: [PRACTICAL_REVIEW_2026.md](PRACTICAL_REVIEW_2026.md). Earlier campaign counts are historical.

The suite keeps ordinary string formatting, whitespace, markup, Unicode, template fallback and empty template behavior; shipped add-on setup metadata; and the loose license view with legacy/modern forms and escaping.

Direct tests of bundled Foundation/PHAR classes, archive loading/extraction/compilation, coverage collectors and synthetic diagnostic/process controls were removed. Foundation owns its shared-code tests. Ordinary add-on code still loads its real shipped parent through the normal autoloader. Unsupported structured/scalar inputs and private-helper states that ordinary callers cannot produce were also removed.

Use the pinned PHPUnit 9.6.38 runner with an explicit PHP binary:

```sh
./tests/.phpunit/install-tools.sh
"/Users/tomjaeger/Library/Application Support/Herd/bin/php82" \
  -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 \
  tests/.phpunit/phpunit-9.phar -c phpunit.xml
```

Run the same complete configuration on PHP 7.4 and 8.0–8.5. The strict diagnostic handler remains active; real source warnings and deprecations fail tests. EE collaborators are finite doubles: these tests do not execute customer SQL, render through the installed EE parser, or prove live-site compatibility.

Older evidence and coverage documents are preserved as historical records of the larger suite, not current scope or results. See `STATUS.md` for the retained-suite validation.

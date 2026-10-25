# Low Widont Foundation build

Build the distributed archive from this repository's templates with one explicit PHP runtime:

```sh
"/Users/tomjaeger/Library/Application Support/Herd/bin/php74" -d phar.readonly=0 build/foundation.php
```

After rebuilding, refresh the complete current source inventory before running the checked tests:

```sh
"/Users/tomjaeger/Library/Application Support/Herd/bin/php74" -n \
  -d error_reporting=-1 -d display_errors=stderr -d display_startup_errors=1 \
  tests/tools/source-inventory.php > tests/SOURCE_INVENTORY.json
```

The generator reads the rebuilt distributed archive. Existing source-pin checks deliberately reject a stale inventory; keep those checks enabled. `foundation.php` packages bytes and does not refresh that separate test inventory.

The builder follows the shared Foundation task's token substitutions, file modes, PHAR alias and generated stub length. It verifies every generated member before replacing `low_widont/low_widont.phar`, and removes only its own private temporary directory. Run the configured tests against the resulting distributed archive.

`foundation/provenance.json` pins the original archive, member bytes, modes, licensing JavaScript and shared source commit. The shipped Foundation version is **4.10.0**, with the original `true` licensing constant, `low_widont.phar` alias and **SHA-256** signature algorithm. Its 22 original templates reproduce the pinned original members from `packettide/tools` commit `ff0c07b2faa042e72cfa9452b208bbf671da94b1`; the current shared 4.11.0 templates are not substituted for that release. `SerializedValue.php` is an added compatibility helper. Original licensing JavaScript, archive metadata and member compression remain unchanged. Rebuilding naturally refreshes archive timestamps.

Compatibility changes were adapted from this campaign owner's Low Options source to the original 4.10.0 templates, then verified against Low Widont's own distributed artifact and native contracts. The packaging builder was originally adapted from the Playa campaign's local builder. The serializer's lexical approach originated in Fieldpack, with subsequent owner fixes for native callbacks, references, malformed tails, signed counts and decimal bounds. These origins are implementation provenance, not independent review approval.

Source changes belong in `foundation/contents/` and the extraction stub in `foundation/stub.php`. `foundation/validation.js` contains the exact original embedded JavaScript. Do not run the shared tools repository's broad build task or replace unrelated Foundation behavior while repairing this archive.

The current best candidate remains blocked on native callback error-handler stack and severity-mask preservation. A reproducible build and configured green suite do not resolve that API blocker; see [../tests/COMPATIBILITY.md](../tests/COMPATIBILITY.md).


The renewed ordinary batch preserves both public setter parameters through serialization and database completion, first native callee binding through actual pinned PHPUnit static restoration, and temporary operand release on success and exceptional unwinding. Each original cache-directory creation site now retains independent first resolution, original arguments and its original reporting scope. A legal hook installed during generic-directory creation can govern the later module-directory site. NAN settings roots retain their original PHP 7.4 value without PHP 8.5 boolean coercion. Permanent positive contracts use actual distributed source and an unchanged EE file helper.

Nonstring parser arguments remain delegated to native conversion in its original frame and reporting scope. Legacy escaped-string and trailing-prefix bytes hidden inside those callbacks remain positive failing compatibility cases on affected newer runtimes; no diagnostic expectation or exclusion makes them green. Temporary string-parser and native-mkdir wrappers still cannot preserve arbitrary prior handler severity masks and callback-installed handler stacks (B1). The exact final matrix and original/current counterexamples are retained in the replacement blocker packet; this candidate is not publication-ready. The ordinary batch was authored05; the real EE helper is copied unchanged with recorded provenance. Historical lexer/packaging provenance remains as previously disclosed.


All four EE2–5 Bridge field-setting expressions receive the same independently bound original host hooks and temporary operand transfer. Their own positive contracts include untouched object carriers, one native-frame conversion, original reporting scopes and actual pinned PHPUnit restoration. The original release-specific sources and unrelated cache zero-key behavior remain retained.

The POSIX search-permission check applies to plain filesystem paths and `file://` paths. Other native stream wrappers retain the original writability decision and can create/write storage without POSIX execute bits. Actual virtual-stream writes and plain/file-URI mode0200 failures are covered separately.

Opaque cache-directory operands changed by a legal by-reference mkdir hook retain their original native conversions. The added host-search predicate inspects only actual strings and does not coerce an object path.

PHP native stream prefixes accept at least two ASCII letters, digits or `+.-` characters. A one-letter `a://` spelling remains a local filesystem path and retains the search check; a native `1x://` virtual stream retains its original writable-storage semantics.

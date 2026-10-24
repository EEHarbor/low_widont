# Low Widont Foundation build

Build the distributed archive from this repository's templates with one explicit PHP runtime:

```sh
"/Users/tomjaeger/Library/Application Support/Herd/bin/php74" -d phar.readonly=0 build/foundation.php
```

The builder follows the shared Foundation task's token substitutions, file modes, PHAR alias and generated stub length. It verifies every generated member before replacing `low_widont/low_widont.phar`, and removes only its own private temporary directory. Run the configured tests against the resulting distributed archive.

`foundation/provenance.json` pins the original archive, member bytes, modes, licensing JavaScript and shared source commit. The shipped Foundation version is **4.10.0**, with the original `true` licensing constant, `low_widont.phar` alias and **SHA-256** signature algorithm. Its 22 original templates reproduce the pinned original members from `packettide/tools` commit `ff0c07b2faa042e72cfa9452b208bbf671da94b1`; the current shared 4.11.0 templates are not substituted for that release. `SerializedValue.php` is an added compatibility helper. Original licensing JavaScript, archive metadata and member compression remain unchanged. Rebuilding naturally refreshes archive timestamps.

Compatibility changes were adapted from this campaign owner's Low Options source to the original 4.10.0 templates, then verified against Low Widont's own distributed artifact and native contracts. The packaging builder was originally adapted from the Playa campaign's local builder. The serializer's lexical approach originated in Fieldpack, with subsequent owner fixes for native callbacks, references, malformed tails, signed counts and decimal bounds. These origins are implementation provenance, not independent review approval.

Source changes belong in `foundation/contents/` and the extraction stub in `foundation/stub.php`. `foundation/validation.js` contains the exact original embedded JavaScript. Do not run the shared tools repository's broad build task or replace unrelated Foundation behavior while repairing this archive.

The current best candidate remains blocked on native callback error-handler stack and severity-mask preservation. A reproducible build and configured green suite do not resolve that API blocker; see [../tests/COMPATIBILITY.md](../tests/COMPATIBILITY.md).

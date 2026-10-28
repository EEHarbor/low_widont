# Practical test review — 2026-10-28

This update keeps tests focused on ordinary supported add-on behavior. Dedicated PHAR/Foundation internal contracts belong in the Foundation repository. Production code and bundled dependencies are unchanged.

- Use a portable PHP default in the test entry points.

The complete retained suites ran under all seven PHP versions with strict diagnostics. Errors and failures remain visible for the current source; these tests do not certify compatibility or a green suite. Earlier campaign measurements are historical and were not recomputed for this review.

| PHP | Suite | Tests | Assertions | Errors | Failures |
| --- | --- | ---: | ---: | ---: | ---: |
| 7.4 | main | 25 | 66 | 0 | 0 |
| 8.0 | main | 25 | 66 | 0 | 0 |
| 8.1 | main | 25 | 66 | 0 | 0 |
| 8.2 | main | 25 | 66 | 0 | 0 |
| 8.3 | main | 25 | 66 | 0 | 0 |
| 8.4 | main | 25 | 66 | 0 | 0 |
| 8.5 | main | 25 | 66 | 0 | 0 |

Test code revision: `1371fc03a88d57838525cb67d15876c451776bf1`. Validation uses isolated EE collaborators and does not replace an installed ExpressionEngine/database/browser integration run.

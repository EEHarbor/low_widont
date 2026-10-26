"""Recognize finite native PHP output frames without changing literal stream bytes."""
import re


def native_stdout(data):
    """Recognize finite native frames, preserving unframed Warning prose."""
    header = False
    for line in data.splitlines():
        header = header or bool(re.search(
            rb'(?:PHP )?(?:<b>)?(?:Warning|Notice|Deprecated|Strict Standards|Fatal error|Parse error|Recoverable fatal error)(?:</b>)?:', line))
        if not header:
            continue
        location, separator, number = line.rpartition(b' on line ')
        if (separator and re.fullmatch(rb'(?:<b>)?[0-9]+(?:</b>)?(?:<br\s*/?>)?', number.strip())
                and re.search(rb' in (?:<b>)?(?:/|[A-Za-z][A-Za-z0-9+.-]*://|(?:Command line code|Standard input code|Unknown)(?:</b>)?$)', location)):
            return True
    return False


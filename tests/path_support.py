"""Keep report publication and temporary storage outside immutable repository inputs."""
import os
from pathlib import Path
import stat
import tempfile

ROOT = Path(__file__).absolute().parent.parent


def physical_path(value):
    """Normalize Darwin's system aliases and reject caller-created symlink components."""
    path = Path(os.path.abspath(value))
    for alias, target in ((Path('/tmp'), Path('/private/tmp')), (Path('/var'), Path('/private/var'))):
        if alias.is_symlink() and alias.resolve() == target and (path == alias or alias in path.parents):
            path = target / path.relative_to(alias)
    for component in (path, *path.parents):
        if component.is_symlink():
            raise ValueError('Owned paths cannot contain symlink components: ' + str(component))
    return path


def contains_identity(path, root):
    """Recognize case aliases by existing ancestor device/inode rather than spelling."""
    expected = root.stat()
    for component in (path, *path.parents):
        try:
            current = component.stat()
        except FileNotFoundError:
            continue
        if (current.st_dev, current.st_ino) == (expected.st_dev, expected.st_ino):
            return True
    return False


def private_parent(path):
    """Require an existing caller-owned directory that other users cannot modify."""
    info = path.stat()
    if not stat.S_ISDIR(info.st_mode) or info.st_uid != os.getuid() or info.st_mode & 0o022:
        raise ValueError('Owned storage requires a physical caller-owned private parent.')


def destination(value, root=ROOT):
    """Allow only fresh files in the known report tree or an owned temporary directory."""
    path = physical_path(value)
    root = physical_path(root)
    reports = root / 'tests/.reports'
    if contains_identity(path, root):
        if reports not in path.parents or path == reports:
            raise ValueError('Reports cannot target repository source, support or tooling.')
    else:
        temporary_roots = (Path('/private/tmp'), Path('/private/var/folders'), Path('/tmp'))
        if not any(parent in path.parents for parent in temporary_roots):
            raise ValueError('External reports require owned temporary storage.')
    if os.path.lexists(path):
        raise ValueError('Refusing existing report destination: ' + str(path))
    if not path.parent.is_dir():
        raise ValueError('Report parent must already exist: ' + str(path.parent))
    private_parent(path.parent)
    return path


def temporary_parent(environment, root=ROOT):
    """Reject repository/alias roots before allocating any disposable child tree."""
    parent = physical_path(environment.get('TMPDIR') or tempfile.gettempdir())
    root = physical_path(root)
    if contains_identity(parent, root):
        raise ValueError('Temporary roots cannot target repository inputs or reports.')
    if not any(parent == base or base in parent.parents for base in (Path('/private/tmp'), Path('/private/var/folders'), Path('/tmp'))):
        raise ValueError('Temporary roots must remain in platform temporary storage.')
    if parent not in (Path('/private/tmp'), Path('/tmp')):
        private_parent(parent)
    return parent


def report_directory(prefix, root=ROOT):
    """Allocate only a fresh child of the physical known repository report tree."""
    reports = physical_path(root) / 'tests/.reports'
    physical_path(reports)
    if not reports.exists():
        reports.mkdir(mode=0o700)
    private_parent(reports)
    return Path(tempfile.mkdtemp(prefix=prefix, dir=reports))


def regular_file(path):
    """Require an owned single-link regular artifact before publication or consumption."""
    info = path.lstat()
    if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1 or info.st_uid != os.getuid():
        raise ValueError('Expected an owned single-link regular artifact: ' + str(path))
    return info

"""Hold one owned session leader until group cleanup and output capture finish."""
import ctypes
import math
import os
from pathlib import Path
import platform
import shutil
import signal
import subprocess
import sys
import tempfile
import time

from path_support import temporary_parent, regular_file


class SuiteSignal(BaseException):
    """Unwind owned resources before delivering the caller's original signal."""

    def __init__(self, signum):
        self.signum = signum


class SigValue(ctypes.Union):
    _fields_ = [('integer', ctypes.c_int), ('pointer', ctypes.c_void_p)]


class SigInfo(ctypes.Structure):
    """Darwin arm64 siginfo_t; admit its checked layout before launching."""

    _fields_ = [('signo', ctypes.c_int), ('error', ctypes.c_int), ('code', ctypes.c_int),
                ('pid', ctypes.c_int), ('uid', ctypes.c_uint), ('status', ctypes.c_int),
                ('address', ctypes.c_void_p), ('value', SigValue), ('band', ctypes.c_long),
                ('padding', ctypes.c_ulong * 7)]


def ownership_api():
    """Refuse unsupported ownership capabilities before a child exists."""
    if (sys.platform != 'darwin' or platform.machine() != 'arm64' or
        ctypes.sizeof(SigInfo) != 104 or SigInfo.pid.offset != 12 or
        any(getattr(os, name, None) != value for name, value in
            (('P_PID', 1), ('WEXITED', 4), ('WNOHANG', 1), ('WNOWAIT', 32))) or
        signal.getsignal(signal.SIGCHLD) != signal.SIG_DFL):
        raise RuntimeError('The ordinary owner requires Darwin arm64 and default SIGCHLD.')
    library = ctypes.CDLL('/usr/lib/libSystem.B.dylib', use_errno=True)
    library.waitid.argtypes = [ctypes.c_uint, ctypes.c_uint, ctypes.POINTER(SigInfo), ctypes.c_int]
    library.waitid.restype = ctypes.c_int
    library.proc_listpids.argtypes = [ctypes.c_uint, ctypes.c_uint, ctypes.c_void_p, ctypes.c_int]
    library.proc_listpids.restype = ctypes.c_int
    members = (ctypes.c_int * 4096)()
    ctypes.set_errno(0)
    size = library.proc_listpids(2, os.getpgrp(), members, ctypes.sizeof(members))
    error = ctypes.get_errno()
    if error or size <= 0 or size >= ctypes.sizeof(members) or size % ctypes.sizeof(ctypes.c_int):
        raise RuntimeError('Current group capability is denied, incomplete or unknown; no child launched.')
    identifiers = set(members[:size // ctypes.sizeof(ctypes.c_int)])
    if os.getpid() not in identifiers or any(identifier <= 0 for identifier in identifiers):
        raise RuntimeError('Current group capability omits its caller; no child launched.')
    return library


def child_exited(process, library):
    """Observe this child without releasing its PID or process group."""
    if process.returncode is not None:
        raise RuntimeError('Child ownership was released before group cleanup.')
    information = SigInfo()
    ctypes.set_errno(0)
    result = library.waitid(os.P_PID, process.pid, ctypes.byref(information), os.WEXITED | os.WNOHANG | os.WNOWAIT)
    error = ctypes.get_errno()
    if result != 0 or error:
        raise OSError(error, 'The owned unreaped child could not be observed.')
    if information.pid == 0:
        return False
    if information.pid != process.pid or information.signo != signal.SIGCHLD or information.code not in (1, 2, 3):
        raise RuntimeError('The waitable child identity or terminal state differs.')
    return True


def group_members(process, library):
    """Inspect only a group whose unreaped session leader is still held."""
    if process.returncode is not None:
        raise RuntimeError('A released group cannot be inspected.')
    members = (ctypes.c_int * 4096)()
    ctypes.set_errno(0)
    size = library.proc_listpids(2, process.pid, members, ctypes.sizeof(members))
    error = ctypes.get_errno()
    if error or size <= 0 or size >= ctypes.sizeof(members) or size % ctypes.sizeof(ctypes.c_int):
        raise RuntimeError('Owned group membership is denied, incomplete or unknown.')
    identifiers = set(members[:size // ctypes.sizeof(ctypes.c_int)])
    if process.pid not in identifiers or any(identifier <= 0 for identifier in identifiers):
        raise RuntimeError('The held leader is absent from its owned group.')
    return identifiers


def cleanup(process, library, grace, output_streams=()):
    """Reap last, after the exited leader is the group's sole remaining member."""
    observed = set()
    signals = []
    output_limit = False
    for sent, budget in ((signal.SIGTERM, grace), (signal.SIGKILL, 1)):
        deadline = time.monotonic() + budget
        while time.monotonic() < deadline:
            exited = child_exited(process, library)
            members = group_members(process, library)
            observed.update(members)
            output_limit = output_limit or sum(os.fstat(stream.fileno()).st_size for stream in output_streams) > 8 * 1024 * 1024
            if exited and members == {process.pid}:
                process.wait(timeout=1)
                return {'held_leader': process.pid, 'members_before_final_reap': sorted(members),
                        'exited_before_final_reap': True, 'leader_reaped': True,
                        'actual_status': process.returncode,
                        'observed_members': sorted(observed), 'signals_sent': signals,
                        'output_limit_exceeded': output_limit,
                        'post_reap_group_operations': 0}
            if output_limit and sent == signal.SIGTERM:
                break
            try:
                os.killpg(process.pid, sent)
                signals.append(int(sent))
            except ProcessLookupError:
                continue
            time.sleep(0.01)
    raise RuntimeError('Owned group disappearance is unknown; captures retained.')


def run_suite(command, *, cwd, environment, timeout=120, output_paths=None):
    """Run a bounded ordinary child and retain ownership until final cleanup."""
    library = ownership_api()
    grace = float(os.environ.get('SUBS_CLEANUP_GRACE', '10'))
    if not math.isfinite(grace) or not 1 <= grace <= 10:
        raise ValueError('Owned cleanup nesting budget exhausted or invalid; no child launched.')
    previous = {}
    interrupted = None
    cleaning = False
    spawning = False
    completed = None
    temporary = None
    verified = False
    retain = False
    process = None

    def interrupt(signum, frame):
        nonlocal interrupted
        if interrupted is None:
            interrupted = signum
        if not cleaning and not spawning:
            raise SuiteSignal(interrupted)

    try:
        for signum in (signal.SIGTERM, signal.SIGINT):
            previous[signum] = signal.getsignal(signum)
            if previous[signum] != signal.SIG_IGN:
                signal.signal(signum, interrupt)
        temporary = Path(tempfile.mkdtemp(prefix='subs-suite-', dir=temporary_parent(environment, Path(cwd))))
        env = dict(environment)
        env.update(TMPDIR=str(temporary), SUBS_SUITE_TMPDIR=str(temporary),
                   SUBS_SUITE_OWNER_PID=str(os.getpid()), SUBS_PYTHON=sys.executable,
                   SUBS_CLEANUP_GRACE=str(grace - 3))
        paths = tuple(map(Path, output_paths)) if output_paths else (temporary / 'output.bin',)
        if len(paths) not in (1, 2) or len(set(paths)) != len(paths):
            raise ValueError('Owned output paths must be one merged or two distinct streams.')
        streams = []
        identities = []
        captured = []
        timeout_error = None
        try:
            for path in paths:
                streams.append(path.open('x+b'))
                actual = os.fstat(streams[-1].fileno())
                identities.append((actual.st_dev, actual.st_ino))
            if len(paths) == 2:
                env['SUBS_STDOUT_IDENTITY'] = str(identities[0][0]) + ':' + str(identities[0][1])
            spawning = True
            try:
                process = subprocess.Popen(command, cwd=cwd, env=env, stdout=streams[0],
                                           stderr=streams[1] if len(streams) == 2 else subprocess.STDOUT,
                                           start_new_session=True)
            finally:
                spawning = False
            if interrupted is not None:
                raise SuiteSignal(interrupted)
            deadline = time.monotonic() + timeout
            while not child_exited(process, library):
                if sum(os.fstat(stream.fileno()).st_size for stream in streams) > 8 * 1024 * 1024 or shutil.disk_usage(temporary).free < 512 * 1024 * 1024:
                    raise RuntimeError('Owned output or free-space bound exceeded.')
                if time.monotonic() >= deadline:
                    timeout_error = subprocess.TimeoutExpired(command, timeout)
                    raise timeout_error
                time.sleep(0.01)
        finally:
            cleaning = True
            try:
                if process is not None:
                    ownership = cleanup(process, library, grace, streams)
                    verified = True
                else:
                    verified = True
            finally:
                try:
                    retain = sum(os.fstat(stream.fileno()).st_size for stream in streams) > 8 * 1024 * 1024
                    if retain:
                        raise RuntimeError('Owned output exceeded its bound; bytes were not read and captures are retained.')
                    for path, stream, identity in zip(paths, streams, identities):
                        try:
                            named = regular_file(path)
                        except (ValueError, OSError) as error:
                            retain = True
                            raise RuntimeError('Owned output path identity is unavailable; capture refused.') from error
                        opened = os.fstat(stream.fileno())
                        if (named.st_dev, named.st_ino) != identity or (opened.st_dev, opened.st_ino) != identity or opened.st_nlink != 1:
                            retain = True
                            raise RuntimeError('Owned output stream identity changed; capture refused.')
                        stream.seek(0)
                        value = stream.read(8 * 1024 * 1024 + 1)
                        if len(value) > 8 * 1024 * 1024:
                            retain = True
                            raise RuntimeError('Owned output grew beyond its bounded read; capture refused.')
                        captured.append(value)
                    if timeout_error is not None:
                        timeout_error.output = captured[0].decode('utf-8', errors='replace')
                finally:
                    for stream in streams:
                        stream.close()
        if shutil.disk_usage(temporary).free < 512 * 1024 * 1024:
            raise RuntimeError('Final owned output or free-space bound exceeded.')
        completed = subprocess.CompletedProcess(command, process.returncode,
            captured[0].decode('utf-8', errors='replace'),
            captured[1].decode('utf-8', errors='replace') if len(paths) == 2 else None)
        completed.stdout_bytes = captured[0]
        completed.stderr_bytes = captured[1] if len(paths) == 2 else b''
        completed.ownership = ownership
    except SuiteSignal as error:
        interrupted = error.signum
    except BaseException as error:
        if interrupted is None:
            raise
        print('Subs interrupted cleanup failed: ' + str(error), file=sys.stderr)
    finally:
        for signum, handler in previous.items():
            signal.signal(signum, handler)
        if temporary is not None and (verified or process is None) and not retain:
            shutil.rmtree(temporary)
        elif temporary is not None:
            print(('Owned output refused' if verified else 'Owned group cleanup unverified') + '; temporary directory retained: ' + str(temporary), file=sys.stderr)
    if interrupted is not None:
        os.kill(os.getpid(), interrupted)
        raise InterruptedError('Suite interrupted by signal ' + str(interrupted))
    return completed

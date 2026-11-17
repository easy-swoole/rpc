"""Run the documented HTTP gateway and verify early TCP header rejection."""

import hashlib
import hmac
import json
import os
import re
import signal
import socket
import struct
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
from contextlib import ExitStack
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def allocate_ports():
    with ExitStack() as stack:
        listeners = [
            stack.enter_context(socket.socket(type=kind))
            for kind in [socket.SOCK_STREAM] * 4 + [socket.SOCK_DGRAM]
        ]
        for listener in listeners:
            listener.bind(("127.0.0.1", 0))
        return dict(zip(
            ("9501", "9601", "9502", "8080", "9600"),
            (str(listener.getsockname()[1]) for listener in listeners),
        ))


def wait_until(check, timeout=5):
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        try:
            if check():
                return
        except OSError:
            pass
        time.sleep(0.05)
    raise AssertionError("Timed out waiting for " + check.__name__)


def main():
    quickstart = (ROOT / "README.md").read_text().split("## 快速开始", 1)[1]
    snippets = re.findall(r"```php\n(.*?)```", quickstart, re.S)[:3]
    ports = allocate_ports()
    with tempfile.TemporaryDirectory() as directory:
        workspace = Path(directory)
        (workspace / "vendor").symlink_to(ROOT / "vendor", target_is_directory=True)
        for name, code in zip(("service", "server", "gateway"), snippets, strict=True):
            code = re.sub(r"\b(?:9501|9601|9502|8080|9600)\b", lambda match: ports[match[0]], code)
            if name == "server":
                code = code.replace("$server->start();", "$server->set(['worker_num' => 1, 'dispatch_mode' => 1]);\n$server->start();")
                code = code.replace(
                    "$server->close($fd);",
                    r"""$workers = [];
    foreach (\EasySwoole\Component\Process\Manager::getInstance()->getProcessTable() as $row) {
        if (str_starts_with($row['name'], 'rpc-tcp-')) {
            $workers[] = $row['pid'];
        }
    }
    $server->send($fd, json_encode($workers) . "\n");
    $server->close($fd);""",
                    1,
                )
            if name == "gateway":
                # A client-only gateway may use its configured RPC port as the HTTP port.
                code = code.replace(f"listenPort: {ports['9502']}", f"listenPort: {ports['8080']}")
            (workspace / f"{name}.php").write_text(code)

        processes = []
        try:
            def start(name):
                process = subprocess.Popen(["php", f"{name}.php"], cwd=workspace, start_new_session=True)
                processes.append(process)
                return process

            def request():
                try:
                    with urllib.request.urlopen(f"http://127.0.0.1:{ports['8080']}/", timeout=1) as response:
                        return response.status, json.load(response)
                except urllib.error.HTTPError as response:
                    with response:
                        return response.code, json.load(response)

            def no_node():
                status, payload = request()
                return status == 502 and payload["status"] == 1001

            def discovered_node():
                status, payload = request()
                return status == 200 and payload["result"]["id"] == 1001

            start("gateway")
            wait_until(no_node)
            provider = start("server")
            wait_until(discovered_node)

            body = b"x" * 512
            header = b"RPC\x01" + struct.pack("!IQ", len(body), int(time.time()))
            header += hashlib.sha256(body).digest() + bytes(32)
            with socket.create_connection(("127.0.0.1", int(ports["9601"])), timeout=1) as connection:
                connection.sendall(header[:17])
                time.sleep(0.03)
                connection.sendall(header[17:])
                assert connection.recv(1) == b"", "Invalid header must close without a body or response"

            for declared_body, sent_body, message in (
                (b"x", b"y", "body hash mismatch"),
                (b"{", b"{", "json decode error"),
            ):
                header = b"RPC\x01" + struct.pack("!IQ", len(declared_body), int(time.time()))
                header += hashlib.sha256(declared_body).digest()
                signature = hmac.digest(b"local-demo-key", header, "sha256")
                with socket.create_connection(("127.0.0.1", int(ports["9601"])), timeout=1) as connection:
                    connection.sendall(header + signature + sent_body)
                    with connection.makefile("rb") as stream:
                        length = struct.unpack("!I", stream.read(4))[0]
                        payload = json.loads(stream.read(length))["payload"]
                        assert payload["status"] == 2002 and payload["msg"] == message, payload

            def workers():
                with socket.create_connection(("127.0.0.1", int(ports["9501"])), timeout=1) as connection:
                    connection.sendall(b"workers")
                    with connection.makefile("rb") as stream:
                        known = json.loads(stream.readline())
                return {pid for pid in known if process_exists(pid)}

            def process_exists(pid):
                try:
                    os.kill(pid, 0)
                    return True
                except ProcessLookupError:
                    return False

            initial = set()

            def three_workers():
                initial.update(workers())
                return len(initial) == 3

            wait_until(three_workers)
            exited = min(initial)
            os.kill(exited, signal.SIGKILL)

            def worker_replaced():
                current = workers()
                return len(current) == 3 and exited not in current and len(current & initial) == 2

            wait_until(worker_replaced)
            assert discovered_node(), "RPC should remain available after loss of one TCP process"

            active = workers()
            supervisors = {
                int(subprocess.check_output(["ps", "-o", "ppid=", "-p", str(pid)]))
                for pid in active
            }
            # Pause automatic respawn while the assist process observes all three workers exiting.
            for pid in supervisors:
                os.kill(pid, signal.SIGSTOP)
            try:
                for pid in active:
                    os.kill(pid, signal.SIGKILL)
                wait_until(no_node)
            finally:
                for pid in supervisors:
                    os.kill(pid, signal.SIGCONT)
            wait_until(discovered_node)
            print("PASS: TCP pool tolerates one exit, withdraws when empty and republishes after respawn")

            provider.terminate()
            provider.wait(timeout=4)
            # The default TTL is 15 seconds, so this requires the UDP offline message.
            wait_until(no_node)
            print("PASS: HTTP gateway discovers and removes the RPC node via UDP")
            print("PASS: invalid split TCP header closes the connection before the body")
        finally:
            for process in reversed(processes):
                if process.poll() is None:
                    process.terminate()
                    try:
                        process.wait(timeout=4)
                    except subprocess.TimeoutExpired:
                        os.killpg(process.pid, signal.SIGKILL)
                        process.wait()


if __name__ == "__main__":
    main()

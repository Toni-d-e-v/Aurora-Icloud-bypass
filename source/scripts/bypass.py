import socketserver
import paramiko
import usbmux
import select

from threading import Thread
from socket import socket


class SocketRelay(object):

    def __init__(self, a: socket, b: socket, max_buffer: int = 65535):
        self.a = a
        self.b = b
        self.atob = bytes()
        self.btoa = bytes()
        self.max_buffer = max_buffer

    def handle(self) -> None:
        while True:
            rlist = list()
            wlist = list()
            xlist = [self.a, self.b]
            if self.atob:
                wlist.append(self.b)
            if self.btoa:
                wlist.append(self.a)
            if len(self.atob) < self.max_buffer:
                rlist.append(self.a)
            if len(self.btoa) < self.max_buffer:
                rlist.append(self.b)
            rlo, wlo, xlo = select.select(rlist, wlist, xlist)
            if xlo:
                return
            if self.a in wlo:
                n = self.a.send(self.btoa)
                self.btoa = self.btoa[n:]
            if self.b in wlo:
                n = self.b.send(self.atob)
                self.atob = self.atob[n:]
            if self.a in rlo:
                s = self.a.recv(self.max_buffer - len(self.atob))
                if not s:
                    return
                self.atob += s
            if self.b in rlo:
                s = self.b.recv(self.max_buffer - len(self.btoa))
                if not s:
                    return
                self.btoa += s


class TCPRelay(socketserver.BaseRequestHandler):
    def handle(self) -> None:
        print("Incoming connection to %d" % self.server.server_address[1])
        mux = usbmux.USBMux(None)
        print("Waiting for devices...")
        if not mux.devices:
            mux.process(1.0)
        if not mux.devices:
            print("No device found")
            self.request.close()
            return
        dev = mux.devices[0]
        print("Connecting to device %s" % str(dev))
        d_sock = mux.connect(dev, self.server.r_port)
        l_sock = self.request
        print("Connection established, relaying data")
        try:
            fwd = SocketRelay(d_sock, l_sock, self.server.buffer_size * 1024)
            fwd.handle()
        finally:
            d_sock.close()
            l_sock.close()
        print("Connection closed")


class TCPServer(socketserver.TCPServer):
    allow_reuse_address = True


class ThreadedTCPServer(socketserver.ThreadingMixIn, TCPServer):
    allow_reuse_address = True


class PhoneConnect:

    def __init__(self,
                 host: str = "localhost", mobile_port: int = 44, computer_port: int = 2222, buffer_size: int = 128):
        self.host = host
        self.mobile_port = mobile_port
        self.computer_port = computer_port
        self.buffer_size = buffer_size

    def start(self) -> None:

        servers = list()
        ports = [(self.mobile_port, self.computer_port)]

        for r_port, l_port in ports:
            print(f"Forwarding local port {l_port} to remote port {r_port}")
            server = ThreadedTCPServer((self.host, l_port), TCPRelay)
            server.r_port = r_port
            server.buffer_size = self.buffer_size
            servers.append(server)

        alive = True

        while alive:
            try:
                rl, wl, xl = select.select(servers, [], [])
                for server in rl:
                    server.handle_request()
            except KeyboardInterrupt:
                print("Server stopped")
            except Exception:
                alive = False


if __name__ == '__main__':
    print("Aurora Bypass by Toni.Dev")
    server = PhoneConnect()

    thread = Thread(target=server.start)
    thread.start()

    host = "localhost"
    user = "root"
    secret = "alpine"
    port = 2222
    command = "cd /;mount -o rw,union,update /;cd /Applications;mv Setup.app Setup.app.bak;uicache -a;" \
              "killall -9 SpringBoard"

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(hostname=host, username=user, password=secret, port=port)
        client.exec_command(command)
    except paramiko.ssh_exception.AuthenticationException:
        print("Authentication failed")
    except paramiko.ssh_exception.NoValidConnectionsError:
        print("Failed to establish connection with the server")

    client.close()
    print("Done!")

    thread.join()
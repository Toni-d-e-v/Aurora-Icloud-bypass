#!/usr/bin/python3
# -*- coding: utf-8 -*-

import sys
import socket
import struct
import select
import plistlib


have_plist = True


class MuxError(Exception):
    pass


class MuxVersionError(MuxError):
    pass


class SafeStreamSocket:

    def __init__(self, address: str, family: 'AddressFamily'):
        self.sock = socket.socket(family, socket.SOCK_STREAM)
        self.sock.connect(address)

    def send(self, msg: bytes) -> None:
        total_sent = 0
        while total_sent < len(msg):
            sent = self.sock.send(msg[total_sent:])
            if sent == 0:
                raise MuxError("socket connection broken")
            total_sent = total_sent + sent

    def recv(self, size: int) -> bytes:
        msg = bytes()
        while len(msg) < size:
            chunk = self.sock.recv(size - len(msg))
            if chunk == b'':
                raise MuxError("socket connection broken")
            msg = msg + chunk
        return msg


class MuxDevice(object):

    def __init__(self, devid: int, usbprod: int, serial: str, location: int):
        self.devid = devid
        self.usbprod = usbprod
        self.serial = serial
        self.location = location

    def __str__(self):
        return f"<MuxDevice: ID %d ProdID 0x%04x Serial '%s' Location 0x%x>" % (
            self.devid, self.usbprod, self.serial, self.location)


class BinaryProtocol(object):

    TYPE_RESULT = 1
    TYPE_CONNECT = 2
    TYPE_LISTEN = 3
    TYPE_DEVICE_ADD = 4
    TYPE_DEVICE_REMOVE = 5
    VERSION = 0

    def __init__(self, socket: socket):
        self.socket = socket
        self.connected = False

    def _pack(self, req, payload):
        if req == self.TYPE_CONNECT:
            return struct.pack("IH", payload['DeviceID'], payload['PortNumber']) + b"\x00\x00"
        elif req == self.TYPE_LISTEN:
            return bytes()
        else:
            raise ValueError(f"Invalid outgoing request type {req}")

    def _unpack(self, resp, payload):
        if resp == self.TYPE_RESULT:
            return {'Number': struct.unpack("I", payload)[0]}
        elif resp == self.TYPE_DEVICE_ADD:
            dev_id, usb_pid, serial, pad, location = struct.unpack("IH256sHI", payload)
            serial = serial.split(b"\0")[0]
            return {'DeviceID': dev_id,
                    'Properties': {'LocationID': location, 'SerialNumber': serial, 'ProductID': usb_pid}}
        elif resp == self.TYPE_DEVICE_REMOVE:
            dev_id = struct.unpack("I", payload)[0]
            return {'DeviceID': dev_id}
        else:
            raise MuxError("Invalid incoming request type")

    def send_packet(self, req, tag, payload=None):
        if payload is None:
            payload = {}
        payload = self._pack(req, payload)
        if self.connected:
            raise MuxError("Mux is connected, cannot issue control packets")
        length = 16 + len(payload)
        data = struct.pack("IIII", length, self.VERSION, req, tag) + payload
        self.socket.send(data)

    def get_packet(self):
        if self.connected:
            raise MuxError("Mux is connected, cannot issue control packets")
        d_len = self.socket.recv(4)
        d_len = struct.unpack("I", d_len)[0]
        body = self.socket.recv(d_len - 4)
        version, resp, tag = struct.unpack("III", body[:0xc])
        if version != self.VERSION:
            raise MuxVersionError(f"Version mismatch: expected {self.VERSION}, got {version}")
        payload = self._unpack(resp, body[0xc:])
        return resp, tag, payload


class PlistProtocol(BinaryProtocol):

    TYPE_RESULT = "Result"
    TYPE_CONNECT = "Connect"
    TYPE_LISTEN = "Listen"
    TYPE_DEVICE_ADD = "Attached"
    TYPE_DEVICE_REMOVE = "Detached"
    TYPE_PLIST = 8
    VERSION = 1

    def __init__(self, socket: socket):
        if not have_plist:
            raise Exception("You need the plistlib module")
        BinaryProtocol.__init__(self, socket)

    def _pack(self, req, payload: bytes) -> bytes:
        return payload

    def _unpack(self, resp, payload: bytes) -> bytes:
        return payload

    def send_packet(self, req, tag, payload=None) -> None:
        if payload is None:
            payload = {}
        payload['ClientVersionString'] = 'usbmux.py'
        if isinstance(req, int):
            req = [self.TYPE_CONNECT, self.TYPE_LISTEN][req - 2]
        payload['MessageType'] = req
        payload['ProgName'] = 'tcprelay'
        BinaryProtocol.send_packet(self, self.TYPE_PLIST, tag, plistlib.dumps(payload))

    def get_packet(self) -> tuple:
        resp, tag, payload = BinaryProtocol.get_packet(self)
        if resp != self.TYPE_PLIST:
            raise MuxError(f"Received non-plist type {resp}")
        payload = plistlib.loads(payload)
        return payload['MessageType'], tag, payload


class MuxConnection(object):

    def __init__(self, socketpath: str, protoclass: type):
        self.socketpath = socketpath
        if sys.platform in ['win32', 'cygwin']:
            family = socket.AF_INET
            address = ('127.0.0.1', 27015)
        else:
            family = socket.AF_UNIX
            address = self.socketpath
        self.socket = SafeStreamSocket(address, family)
        self.proto = protoclass(self.socket)
        self.pkttag = 1
        self.devices = list()

    def _getreply(self) -> tuple or None:
        while True:
            resp, tag, data = self.proto.get_packet()
            if resp == self.proto.TYPE_RESULT:
                return tag, data
            else:
                raise MuxError(f"Invalid packet type received: {resp}")

    def _processpacket(self) -> None:
        resp, tag, data = self.proto.get_packet()
        if resp == self.proto.TYPE_DEVICE_ADD:
            self.devices.append(
                MuxDevice(data['DeviceID'], data['Properties']['ProductID'], data['Properties']['SerialNumber'],
                          data['Properties']['LocationID']))
        elif resp == self.proto.TYPE_DEVICE_REMOVE:
            for item in self.devices:
                if item.devid == data['DeviceID']:
                    self.devices.remove(item)
        elif resp == self.proto.TYPE_RESULT:
            raise MuxError(f"Unexpected result: {resp}")
        else:
            raise MuxError(f"Invalid packet type received: {resp}")

    def _exchange(self, req: str, payload: dict = None) -> str:
        if payload is None:
            payload = {}
        mytag = self.pkttag
        self.pkttag += 1
        self.proto.send_packet(req, mytag, payload)
        recvtag, data = self._getreply()
        if recvtag != mytag:
            raise MuxError(f"Reply tag mismatch: expected {mytag}, got {recvtag}")
        return data['Number']

    def listen(self) -> None:
        ret = self._exchange(self.proto.TYPE_LISTEN)
        if ret != 0:
            raise MuxError(f"Listen failed: error {ret}")

    def process(self, timeout: int or float = None) -> None:
        if self.proto.connected:
            raise MuxError("Socket is connected, cannot process listener events")
        rlo, wlo, xlo = select.select([self.socket.sock], [], [self.socket.sock], timeout)
        if xlo:
            self.socket.sock.close()
            raise MuxError("Exception in listener socket")
        if rlo:
            self._processpacket()

    def connect(self, device: MuxDevice, port: int) -> socket:
        ret = self._exchange(self.proto.TYPE_CONNECT,
                             {'DeviceID': device.devid, 'PortNumber': ((port << 8) & 0xFF00) | (port >> 8)})
        if ret != 0:
            raise MuxError(f"Connect failed: error {ret}")
        self.proto.connected = True
        return self.socket.sock

    def close(self) -> None:
        self.socket.sock.close()


class USBMux(object):

    def __init__(self, socket_path: str = None):
        if socket_path is None:
            if sys.platform == 'darwin':
                socket_path = "/var/run/usbmuxd"
            else:
                socket_path = "/var/run/usbmuxd"
        self.socketpath = socket_path
        self.listener = MuxConnection(socket_path, BinaryProtocol)
        try:
            self.listener.listen()
            self.version = 0
            self.protoclass = BinaryProtocol
        except MuxVersionError:
            self.listener = MuxConnection(socket_path, PlistProtocol)
            self.listener.listen()
            self.protoclass = PlistProtocol
            self.version = 1
        self.devices = self.listener.devices

    def process(self, timeout=None) -> None:
        self.listener.process(timeout)

    def connect(self, device, port) -> socket:
        connector = MuxConnection(self.socketpath, self.protoclass)
        return connector.connect(device, port)


if __name__ == "__main__":
    mux = USBMux()
    print("Waiting for devices...")
    if not mux.devices:
        mux.process(0.1)
    while True:
        print("Devices:")
        for dev in mux.devices:
            print(dev)
        mux.process()

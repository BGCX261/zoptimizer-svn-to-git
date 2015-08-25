import errno
import functools
from tornado import ioloop
from tornado import iostream
import socket

class HttpHandler(object):
  def __init__(self, sock):
    self.stream = iostream.IOStream(sock)
    self.resp = bytearray("HTTP/1.1 200 OK\r\nKeep-Alive: timeout=5, max=100\r\nConnection: Keep-Alive\r\nContent-Length: 121\r\n\r\nHello world!\r\n")
    self.resp_size = len(self.resp)
  def start(self):
    self.stream.read_bytes(108, self.handle_req)
  def handle_req(self, data):
    self.stream.write(self.resp)
    self.stream.write(data, None)
    self.start()

class Server(object):
  def __init__(self, port):
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM, 0)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.setblocking(0)
    sock.bind(("localhost", port))
    sock.listen(1024)
    self.listen_sock = sock
    self.io_loop = ioloop.IOLoop.instance()

  def connection_ready(self, sock, fd, events):
    while True:
      try:
        connection, address = sock.accept()
      except socket.error, e:
        if e[0] not in (errno.EWOULDBLOCK, errno.EAGAIN):
          raise
        return
      connection.setblocking(0)
      data_stream = HttpHandler(connection)
      data_stream.start()

  def start(self):
    callback = functools.partial(self.connection_ready, self.listen_sock)
    self.io_loop.add_handler(self.listen_sock.fileno(), callback, self.io_loop.READ)
    self.io_loop.start()

s = Server(20000)
s.start()


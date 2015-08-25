import errno
import functools
from tornado import ioloop
import socket
import iostream

class Echo(object):
  def __init__(self, sock):
    self.stream = iostream.IOStream(sock)
  def start(self):
    self.stream.read_bytes(10, self.handle_req)
  def handle_req(self, buf, offset, num_bytes):
    self.stream.write(buf, offset, num_bytes)
    self.stream.flush()
    self.start()

class Server(object):
  def __init__(self, port):
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM, 0)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.setblocking(0)
    sock.bind(("localhost", port))
    sock.listen(128)
    self.listen_sock = sock
    self.io_loop = ioloop.IOLoop.instance()
    self.data_streams = []

  def connection_ready(self, sock, fd, events):
    while True:
      try:
        connection, address = sock.accept()
      except socket.error, e:
        if e[0] not in (errno.EWOULDBLOCK, errno.EAGAIN):
          raise
        return
      connection.setblocking(0)
      data_stream = Echo(connection)
      self.data_streams.append(data_stream)
      data_stream.start()

  def start(self):
    callback = functools.partial(self.connection_ready, self.listen_sock)
    self.io_loop.add_handler(self.listen_sock.fileno(), callback, self.io_loop.READ)
    self.io_loop.start()

s = Server(20000)
s.start()


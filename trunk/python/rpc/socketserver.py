import errno
import socket
import struct
import functools
from tornado import ioloop, iostream
from multiprocessing import cpu_count, Process
from channel import NetworkChannel, IpcChannel
from collections import deque

"""RpcServer in this module."""

def get_address_signature(address):
  """Generates 6 bytes signature for a given socket."""
  packed_ip = socket.inet_aton(address[0])
  packed_sock_id = struct.pack("4sH", packed_ip, address[1])
  return packed_sock_id

class SocketServer(object):
  """This class implements a typical non-blocking, async socket server"""

  def __init__(self, port, payload_handler,
               io_loop = ioloop.IOLoop.instance(),
               max_connection_num = 1024,
               ip_addr = "localhost",
               worker_num = 2 * cpu_count()):
    # prepares socket
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM, 0)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.setblocking(False)
    sock.bind((ip_addr, port))
    self._listen_sock = sock
    self._max_connection_num = max_connection_num
    self._net_channels = {}
    # prepares IO loop
    self._io_loop = io_loop
    # prepares process pool
    self._ipc_channels = {}
    self._worker_processes = {}
    self.__next_worker_queue = deque()
    for worker_id in xrange(worker_num):
      server_connection, worker_connection = socket.socketpair()
      ipc_channel = IpcChannel(server_connection, worker_id,
                               self._outbound_callback, None,
                               functools.partial(self.destory_worker,
                                                 worker_id), self._io_loop)
      self._ipc_channels[worker_id] = ipc_channel
      process = SocketWorker(worker_connection, worker_id, payload_handler)
      self._worker_processes[worker_id] = process
      self.__next_worker_queue.append(worker_id)

  def _inbound_callback(self, addr_id, payload):
    # round-robin selection
    worker_id = self.__next_worker_queue.popleft()
    self.__next_worker_queue.append(worker_id)
    ipc_channel = self._ipc_channels[worker_id]
    # send message
    ipc_channel.write(addr_id, payload)

  def _outbound_callback(self, addr_id, payload):
    if addr_id not in self._net_channels:
      return  # discards the response if the sock already closed.
    net_channel = self._net_channels[addr_id]
    # send message
    net_channel.write(payload)

  def _connection_ready(self, fd, events):
    """Accepts cominng connection requests."""
    while True:
      try:
        net_connection, addr = self._listen_sock.accept()
      except socket.error as e:
        if e[0] not in (errno.EWOULDBLOCK, errno.EAGAIN):
          raise
        return
      net_connection.setblocking(0)
      addr_id = get_address_signature(addr)
      self._net_channels[addr_id] = NetworkChannel(net_connection,
                                                   functools.partial(self._inbound_callback,
                                                                     addr_id),
                                                   None,
                                                   functools.partial(self.close_net_channel,
                                                                     addr_id),
                                                   self._io_loop)
      self._net_channels[addr_id].start_read()

  def close_net_channel(self, addr_id):
    if addr_id in self._net_channels:
      del self._net_channels[addr_id]

  def destory_worker(self, worker_id):
    if worker_id in self._worker_processes:
      self._worker_processes[worker_id].stop() # TODO: grace time
      del self._worker_processes[worker_id]
    if worker_id in self._ipc_channels:
      self._ipc_channels[worker_id].close()
      del self._ipc_channels[worker_id]
    try:
      self.__next_worker_queue.remove(worker_id)
    except ValueError:
      # just ignore it
      pass

  def start(self):
    #TODO: quit gracefully
    # listen the port
    self._listen_sock.listen(self._max_connection_num)
    # starts worker processes pool
    for worker_process in self._worker_processes.itervalues():
      worker_process.start()
    # starts ipc channel
    for ipc_channel in self._ipc_channels.itervalues():
      ipc_channel.start_read()
    # starts io_loop
    self._io_loop.add_handler(self._listen_sock.fileno(),
                              self._connection_ready, ioloop.IOLoop.READ)
    self._io_loop.start()

class SocketWorker(Process):
  """This class implements the worker process for socket server."""

  def __init__(self, connection, worker_id, payload_handler):
    Process.__init__(self)
    self._io_loop = ioloop.IOLoop()
    self._payload_handler = payload_handler
    self._ipc_channel = IpcChannel(connection, worker_id,
                                   self._inbound_callback, None,
                                   self.stop, self._io_loop)
    self._worker_id = worker_id

  def run(self):
    self._ipc_channel.start_read()
    self._io_loop.start()

  def stop(self):
    self._io_loop.stop()
    self._ipc_channel.close()

  def payload_callback(self, addr_id, result):
    self._ipc_channel.write(addr_id, result)

  def _inbound_callback(self, addr_id, payload):
    callback = functools.partial(self.payload_callback, addr_id)
    self._payload_handler(payload, callback)

def main():
  # only for test
  def echo_handler(payload, callback):
    callback(payload)
  def www_handler(payload, callback):
    callback("HTTP/1.1 200 OK\r\nKeep-Alive: timeout=5, max=100\r\nConnection: Keep-Alive\r\nContent-Length: 12\r\n\r\nHello world!\r\n")
  io_loop = ioloop.IOLoop()
  server = SocketServer(20000, www_handler, io_loop, worker_num = 8)
  server.start()

if __name__ == '__main__':
  main()

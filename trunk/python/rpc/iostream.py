#!/usr/bin/env python
#
# Copyright 2010 Zoptimizer
#
# Licensed under the Apache License, Version 2.0 (the "License"); you may
# not use this file except in compliance with the License. You may obtain
# a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.
#
# Author: Jacky Wang (chaowang@zoptimizer.com)

"""A utility class to write to and read from a non-blocking socket."""

import errno
import logging
import socket
from collections import deque
from tornado import ioloop

class IOStream(object):
  def __init__(self, socket, io_loop=None, name=None, min_buf_size=131072, max_buf_size=16777216, io_chunk_size=32768):
    """Initiate the iostream object.

    Args:
      socket: The raw socket that iostream works on.
      io_loop: The IO loop, on which the read/write operations depends; default using global IOLoop instance.
      name: The name of this object, could be used in debug info output.
      min_buf_size: Minimum size of the read/write buffer, default set to be 128K bytes.
      max_buf_size: Maximum size of the read/write buffer, default set to be 16M bytes.
      io_chunk_size: Chunk size for each socket read, default set to be 32K bytes.
    """
    self.socket = socket
    self.socket.setblocking(False)
    self.io_loop = io_loop or ioloop.IOLoop.instance()
    self.name = name or "IOStream"
    self.min_buf_size = min_buf_size
    self.max_buf_size = max_buf_size
    self.io_chunk_size = io_chunk_size

    self._read_buf = bytearray(min_buf_size)
    self._read_buf_size = min_buf_size  # the current read buffer size
    self._read_start = 0
    self._read_end = 0

    self._write_buf = bytearray(min_buf_size)
    self._write_buf_size = min_buf_size  # the current write buffer size
    self._write_start = 0
    self._write_end = 0

    self._read_callbacks = deque()
    self._write_callbacks = deque()
    self._close_callback = None
    self._state = self.io_loop.ERROR
    self.io_loop.add_handler(self.socket.fileno(), self._handle_events, self._state)

  def _run_callback(self, callback, *args, **kwargs):
    """Run registered callbacks.

    Args:
      callback: The function will be called.  If it is None, the function will return directly.
    """
    try:
      callback(*args, **kwargs)
    except:
      # Close the socket on an uncaught exception from a user callback
      # (It would eventually get closed when the socket object is
      # gc'd, but we don't want to rely on gc happening before we
      # run out of file descriptors)
      self.close()
      # Re-raise the exception so that IOLoop.handle_callback_exception
      # can see it and log the error
      raise

  def __realloc(self, buf, length, buf_size):
    """Re-alloc the buffer according to the length of bytes it contains.

    If the length is smaller than 1/4 of the buf_size, the buffer shrinks.
    If the length is larger than 3/4 of the buf_size, the buffer extends.
    Otherwise, it just reuses the existing buffer.

    Args:
      buf: The original data buffer.
      length: The length of bytes that the buffer currently contains.
      buf_size: The original size of this data buffer.

    Returns:
      The list of the buffer size and the buffer which will be used in the following functions.
    """
    if length > self.max_buf_size:
      return (0, None)  # length is too long to fit into the buffer
    if length < buf_size / 4:
      if buf_size == self.min_buf_size:
        return (buf_size, buf)
      else:
        return (buf_size / 2, bytearray(buf_size / 2))  # shrinks the buffer size by half
    if length < buf_size * 3 / 4:
      return (buf_size, buf)  # returns the existing buffer
    new_size = buf_size * 2  # extends the buffer size by double
    while new_size <= self.max_buf_size:
      if length < new_size * 3 / 4:
        return (new_size, bytearray(new_size))  # returns the new buffer
      new_size = buf_size * 2  # extends the buffer size by double
    if buf_size == self.max_buf_size:
      return (buf_size, buf)
    else:
      return (self.max_buf_size, bytearray(self.max_buf_size))

  def read(self, num_bytes, callback):
    """Call callback when we read the given number of bytes.

    Args:
      num_bytes: The number of bytes the caller want to retrieve.
      callback: The function will be called after these bytes have been retrieved.
          Function fingerprint: callback(buf, offset, num_bytes)
    """
    if not self._read_callbacks and (self._read_end - self._read_start) >= num_bytes:
      self._read_consume(num_bytes, callback)
      return
    if not self.socket:
      raise IOError("Attempt to read/write to closed stream")
    self._read_callbacks.append((num_bytes, callback))
    self._add_io_state(self.io_loop.READ)

  def _read_consume(self, num_bytes, callback):
    """Consume bytes from read buffer and trigger callback.

    Args:
      num_bytes: The number of bytes will be consumed.
      callback: The function will be applied on these bytes.
    """
    start = self._read_start
    self._read_start += num_bytes
    if not callback:
      return
    self._run_callback(callback, self._read_buf, start, num_bytes)

  def write(self, buf, offset, num_bytes, callback=0):
    """Write the given data to this stream.

    By default, this operation won't acctually send the data out through socket.
    Instead, it just writes these data into the write buffer thus may be more
    efficient than calling socket.send each time.  Except:
      1. callback array is not empty, indicating that there're some methods waiting.
      2. more than io_chunk_size data is waiting to write out.
    The user may like to apply the callback to None to manually send the waiting
    write buffer to socket.

    Args:
      buf: The data stored in this buffer.
      offset: Offset of the data in the buffer.
      num_bytes: Data length.
      callback: Call this function if all data has been successfully written
          to the stream.  Default set to 0.
          
          Function fingerprint: callback()
    """
    if not self.socket:
      raise IOError("Attempt to read/write to closed stream")
    if (self._write_end + num_bytes) >= self._write_buf_size:
      # reach the end of the write buffer, needs re-allocation.
      length = self._write_end - self._write_start
      new_size, new_buf = self.__realloc(self._write_buf, length + num_bytes, self._write_buf_size)
      if new_size is 0:
        # buffer overflow, reports error
        logging.error("%s: Reached maximum write buffer size", self.name)
        self.close()
      new_buf[:length] = self._write_buf[self._write_start:self._write_end]  # copy existing data into new buffer
      self._write_buf = new_buf
      self._write_buf_size = new_size
      self._write_start = 0
      self._write_end = length
      # adjust the registered callbacks
      self._write_callbacks = deque([(pos - length, write_callback) for pos, write_callback in self._write_callbacks])
    self._write_buf[self._write_end:self._write_end + num_bytes] = buf[offset:offset + num_bytes]
    self._write_end += num_bytes
    if callback is not 0:
      self._write_callbacks.append((self._write_end, callback))
    if not not self._write_callbacks or (self._write_end - self._write_start > self.io_chunk_size):
      self._add_io_state(self.io_loop.WRITE)

  def set_close_callback(self, callback):
    """Call the given callback when the stream is closed.

    Args:
      callback: Call this function when this iostream object is closed.
    """
    self._close_callback = callback

  def close(self):
    """Close this stream."""
    if self.socket:
      self.io_loop.remove_handler(self.socket.fileno())
      self.socket.close()
      self.socket = None
      if self._close_callback:
        self._run_callback(self._close_callback)
      self._read_buf = None
      self._write_buf = None
      self._read_callbacks.clear()
      self._read_callbacks = None
      self._write_callbacks.clear()
      self._write_callbacks = None

  def _add_io_state(self, state):
    """Add io state monitoring onto the current io loop."""
    if not self._state & state:
      self._state = self._state | state
      self.io_loop.update_handler(self.socket.fileno(), self._state)

  def _handle_events(self, fd, events):
    """Event dispatcher for io loop."""
    if not self.socket:
      logging.warning("%s: Got events for closed stream %d", self.name, fd)
      return
    if events & self.io_loop.READ:
      self._handle_read()
      if not self.socket: return  # double check socket status after read
    if events & self.io_loop.WRITE:
      self._handle_write()
      if not self.socket: return  # double check socket status after write
    if events & self.io_loop.ERROR:
      self.close()
      return
    # Update the io_loop monitoring states
    state = self.io_loop.ERROR
    if not not self._read_callbacks:
      state |= self.io_loop.READ
    if not not self._write_callbacks or (self._write_end - self._write_start > self.io_chunk_size):
      state |= self.io_loop.WRITE
    if state != self._state:
      self._state = state
      self.io_loop.update_handler(self.socket.fileno(), self._state)

  def _handle_write(self):
    """Handler to send data when it's ready."""
    while self._write_end > self._write_start:
      try:
        length = self._write_end - self._write_start
        num_bytes = self.socket.write(self._write_buf, self._write_start, length)
        self._write_start += num_bytes
      except socket.error, e:
        if e[0] in (errno.EWOULDBLOCK, errno.EAGAIN):
          break
        else:
          logging.warning("%s: Write error on %d: %s", self.name, self.socket.fileno(), e)
          self.close()
          return
      if num_bytes == length:
        break
      if not num_bytes:
        logging.warning("%s: Write 0 bytes from %d", self.name, self.socket.fileno())
        self.close()
        return
    while not not self._write_callbacks:
      pos, callback = self._write_callbacks.popleft()
      if pos > self._write_start:
        self._write_callbacks.appendleft((pos, callback))
        return
      if not callback:
        continue
      self._run_callback(callback)

  def _handle_read(self):
    """Handler to retrieve data when it's ready."""
    while True:
      if (self._read_end + self.io_chunk_size) >= self._read_buf_size:
        # reach the end of the read buffer, needs re-allocation.
        length = self._read_end - self._read_start
        new_size, new_buf = self.__realloc(self._read_buf, length + self.io_chunk_size, self._read_buf_size)
        if new_size is 0:
          # buffer overflow, reports error
          logging.error("%s: Reached maximum read buffer size", self.name)
          self.close()
        new_buf[:length] = self._read_buf[self._read_start:self._read_end]  # copy existing data into new buffer
        self._read_buf = new_buf
        self._read_buf_size = new_size
        self._read_start = 0
        self._read_end = length
      try:
        num_bytes = self.socket.read(self._read_buf, self._read_end, self.io_chunk_size)
        self._read_end += num_bytes
      except socket.error, e:
        if e[0] in (errno.EWOULDBLOCK, errno.EAGAIN):
          break
        else:
          logging.warning("%s: Read error on %d: %s", self.name, self.socket.fileno(), e)
          self.close()
          return
      if num_bytes < self.io_chunk_size:
        break
      if not num_bytes:
        logging.warning("%s: Read 0 bytes from %d", self.name, self.socket.fileno())
        self.close()
        return
    while not not self._read_callbacks:
      num_bytes, callback = self._read_callbacks.popleft()
      if num_bytes > (self._read_end - self._read_start):
        self._read_callbacks.appendleft((num_bytes, callback))
        return
      if not callback:
        continue
      self._read_consume(num_bytes, callback)


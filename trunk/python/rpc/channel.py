import struct
import iostream

"""Channels used in socketserver"""

def _callback_to_read_handler(channel_obj, callback):
  """Method decoration, convert a callback into channel's read handler.

  Args:
    channel_obj: The channel object.
    callback: The function will be called in the handler.
  """
  def _handler(channel_obj, *args, **kwargs):
    if callback:
      try:
        callback(*args, **kwargs)
      except:
        # Close the socket on an uncaught exception from a user callback
        # (It would eventually get closed when the socket object is
        # gc'd, but we don't want to rely on gc happening before we
        # run out of file descriptors)
        channel_obj.close()
        # Re-raise the exception so that IOLoop.handle_callback_exception
        # can see it and log the error
        raise
    channel_obj.read()
  return _handler

def _read_handler_to_ipc(handler):
  def _ipc_handler(buf, offset, num_bytes):
    if handler:
      handler(buf[offset:offset+6], buf, offset+6, num_bytes-6)
  return _ipc_handler

class NetworkChannel(object):
  """This class handles network packages."""

  def __init__(self, sock, data_callback, control_callback=None, close_callback=None, io_loop=None, name=None):
    """Initiate the network channel for socket server to receive/send messages.

    Args:
      sock: The socket for receiving / sending messages.
      data_callback: The handler for data messages.
          Function fingerprint: callback(buf, offset, num_bytes)
      control_callback: The handler for control messages.
          Function fingerprint: callback(buf, offset, num_bytes)
      close_callback: The callback method triggered when this channel closed.
          Function fingerprint: callback()
      io_loop: The IO loop, on which the read/write operations depends; default using global IOLoop instance.
      name: The name of this object, could be used in debug info output.
    """
    self._stream = iostream.IOStream(sock, io_loop, name)
    self._stream.set_close_callback(close_callback)
    self._data_handler = _callback_to_read_handler(self, data_callback)
    self._control_handler = _callback_to_read_handler(self, control_callback)
    self._name = name
    self._header_parser = struct.struct("<i")
    self._header_buf = bytearray(4)

  def close(self):
    """Close the channel."""
    self._stream.close()

  def read(self):
    """Start the channel reading.

    The data receiving / sending on this channel follows the format:
    | 4 bytes header | data_payload / control_message |
    If the header is > 0, then len(data_payload) = header.
    If the header is < 0, then len(control_message) = -header.
    """
    self._stream.read_bytes(4, self._handle_header)

  def _handle_header(self, buf, offset, num_bytes):
    """Handle the data header, and start reading the true payload data or control message.

    Args:
      buf: The buffer stores the header.
      offset: The offset that header starts on the buffer.
      num_bytes: Header length, should be 4 in this case.
    """
    assert num_bytes is 4, "%s: Header length is wrong: %d!" % (self._name, num_bytes)
    payload_length = self._header_parser.unpack_from(buf, offset)[0]
    assert payload_length is not 0, "%s: The payload length should not be 0!" % self._name
    handler = self._data_handler if payload_length > 0 else self._control_handler
    self._stream.read_bytes(abs(payload_length), handler)

  def write(self, buf, offset, num_bytes, is_data=True, callback=None):
    """Write payload data or control message to channel."""
    header = num_bytes if is_data else -num_bytes
    self._header_parser.pack_into(self._header_buf, 0, header)
    self._stream.write(self._header_buf, 0, 4)
    self._stream.write(buf, offset, num, callback)

class TestNetworkChannel(object):
  """This class is the network channel for HTTP benchmark test."""

  def __init__(self, sock, data_callback, control_callback=None, close_callback=None, io_loop=None, name=None):
    self._stream = iostream.IOStream(sock, io_loop, name)
    self._stream.set_close_callback(close_callback)
    self._data_handler = _callback_to_read_handler(self, data_callback)
    self._name = name

  def close(self):
    self._stream.close()

  def read(self):
    self._stream.read_bytes(113, self._data_handler)

  def write(self, buf, offset, num_bytes, is_data=True, callback=None):
    self._stream.write(buf, offset, num, callback)



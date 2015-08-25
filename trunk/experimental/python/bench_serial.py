import struct
import ctypes

def test_struct(buf, offset):
  return struct.unpack_from("I", buf, offset)[0]

def test_ctypes(buf, offset):
  return ctypes.c_uint32.from_buffer(buf, offset).value

def test_multi(buf, offset):
  return buf[offset] + (buf[offset+1] << 8) + (buf[offset+2] << 16) + (buf[offset+3] << 24)

buf_w = bytearray(5)
buf_w[1] = 1
buf_w[2] = 0
buf_w[3] = 0
buf_w[4] = 0
buf_r = buffer(buf_w)

if __name__ == '__main__':
  import timeit

  t1 = timeit.Timer("test_struct(buf_r, 1)",
                    "from __main__ import test_struct, buf_r")
  t2 = timeit.Timer("test_ctypes(buf_w, 1)",
                    "from __main__ import test_ctypes, buf_w")
  t3 = timeit.Timer("test_multi(buf_w, 1)",
                    "from __main__ import test_multi, buf_w")
  print t1.timeit(number=1000000)
  print t2.timeit(number=1000000)
  print t3.timeit(number=1000000)

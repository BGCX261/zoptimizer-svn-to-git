import socket
import struct
import time

def send_rpc_msg(sock, msg):
  msg_length = len(msg)
  output = struct.pack("I%ds" % msg_length, msg_length, msg)
  sock.send(output)
  resp = sock.recv(msg_length + 4)

def send_msg(sock, msg):
  sock.send(msg)
  resp = sock.recv(len(msg))
  print resp

def main():
  sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM, 0)
  sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
  sock.connect(("localhost", 20000))
  for i in xrange(50):
    send_msg(sock, "helloworld")
  sock.close()

if __name__ == "__main__":
  main()

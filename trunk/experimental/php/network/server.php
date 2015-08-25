<?php
namespace Zopt\Network;

require_once 'base/base.php';
require_once 'base/logger.php';
require_once 'network/iostream.php';

class TcpServer {
  private static $_logger;

  private $_addr;
  private $_port;
  private $_listenQueue;
  private $_started;

  private $_sock;
  private $_listenEvent;
  private $_eventBase;
  private $_streams;

  // Signals
  private $_signalEvents;

  public function __construct($addr = '127.0.0.1', $port = 8305, $listenQueue = 512) {
    if (is_null(self::$_logger)) {
      self::$_logger = \Zopt\Base\Logger::getLogger(__CLASS__);
    }

    if (!extension_loaded('libevent')) {  
      self::$_logger->crit('FATAL: Please firstly install libevent extension.'); 
      die(); 
    }

    $this->_addr = $addr;
    $this->_port = $port;
    $this->_listenQueue = $listenQueue;
    $this->_started = FALSE;
    
    $this->_sock = NULL;

    // Init the event machine using libevent c extension
    $this->_listenEvent = NULL;
    $this->_eventBase = event_base_new();
    $this->_streams = array();

    // Register signal handlers
    $this->_signalEvents = array();
    foreach (array(SIGTERM, SIGHUP, SIGINT, SIGQUIT) as $signo) {
      $event = event_new();
      event_set($event, $signo, EV_SIGNAL | EV_PERSIST, array($this, 'handleSignalEvent'), $signo);
      event_base_set($event, $this->_eventBase);
      event_add($event);
      $this->_signalEvents[$signo] = $event;
    }
  }

  public function __destruct() {
    foreach ($this->_streams as $key => $stream) {
      unset($this->_streams[$key]);
    }

    $this->stop();

    event_base_free($this->_eventBase);
  }

  public function start() {
    self::$_logger->info('Server starts.');

    // Init a non-blocking TCP socket for listening
    $this->_sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!$this->_sock) {
      $errno = socket_last_error();
      $errstr = socket_strerror($errno);
      self::$_logger->err("Socket create error: $errstr ($errno)");
      die();
    }
    socket_set_nonblock($this->_sock);
    if (!socket_bind($this->_sock, $this->_addr, $this->_port)) {
      $errno = socket_last_error();
      $errstr = socket_strerror($errno);
      self::$_logger->err("Socket bind error: $errstr ($errno)");
      die();
    }
    if (!socket_listen($this->_sock, $this->_listenQueue)) {
      $errno = socket_last_error();
      $errstr = socket_strerror($errno);
      self::$_logger->err("Socket listen error: $errstr ($errno)");
      die();
    }

    // For the listening socket, we use raw event to handle it.
    $this->_listenEvent = event_new();
    event_set($this->_listenEvent, $this->_sock, EV_READ | EV_PERSIST, array($this, 'handleAcceptEvent'));
    event_base_set($this->_listenEvent, $this->_eventBase);
    event_add($this->_listenEvent);
    
    // Endless loop
    $this->_started = TRUE;
    event_base_loop($this->_eventBase);

    // The loop ends here.
    $this->stop();
  }

  public function stop() {
    if (!$this->_started) {
      return;
    }
    self::$_logger->info('Server stops.  The event loop ends.');

    event_base_loopbreak($this->_eventBase);

    if ($this->_listenEvent) {
      event_del($this->_listenEvent);
      event_free($this->_listenEvent);
      $this->_listenEvent = NULL;
    }

    if ($this->_sock) {
      socket_close($this->_sock);
      $this->_sock = NULL;
    }
    
    foreach ($this->_streams as $stream) {
      $stream->forceClose();
    }
    
    // TODO: need to clear the streams too
    
    foreach ($this->_signalEvents as $signo => $event) {
      event_del($event);
      event_free($event);
      unset($this->_signalEvents[$signo]);
    }

    $this->_started = FALSE;
  }

  public function handleSignalEvent($fd, $flag, $signo) {
    switch ($signo) {
      case SIGTERM:  // handle shutdown tasks
      case SIGHUP:   // handle restart tasks
      case SIGINT:   // handle ctrl+c interrupt
      case SIGQUIT:  // handle ctrl+\ interrupt
        $this->stop();
        break;
      default:
        break;
    }
  }

  public function handleStreamClose($name) {
    $this->_streams[$name] = NULL;
    unset($this->_streams[$name]);
    self::$_logger->info("Connection $name is closed.");
  }

  public function handleAcceptEvent($sock, $flag) {
    // Accept the incoming connection and set that as non-blocking.
    $connection = socket_accept($sock);
    $streamName = NULL;
    $stream = new IOStream(  // Wrap the data socket into an IO stream
        $connection,
        $this->_eventBase,
        $streamName,
        array($this, 'handleStreamClose'));
    $this->_streams[$streamName] = $stream;
    self::$_logger->info("Accepted a new connection $streamName.");

    // Handle the stream 
    $stream->recv(4, array($this, 'printByte4'));
  }
  
  public function printByte4($streamName, $byte4) {
    if (is_null($byte4)) {
      // print "$streamName: NULL\n";
      return;
    }
    // print "$streamName: $byte4\n";
    if (strpos($byte4, '1') !== FALSE) {
      error_log(">>> quit received, active close");
      $this->_streams[$streamName]->close();
    } else {
      $this->_streams[$streamName]->send($byte4, array($this, 'dummy'));
      $this->_streams[$streamName]->recv(4, array($this, 'printByte4'));
    }
  }
  
  public function dummy() {
  }
}

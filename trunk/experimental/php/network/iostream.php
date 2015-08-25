<?php
/**
 * %copyright_license
 *
 * Stream is an common abstract for socket and other input/output approaches.
 *
 * @package Zopt::Network
 * @auther chaowang <chaowang@zoptimizer.com>
 */
namespace Zopt\Network;

require_once 'base/base.php';
require_once 'base/logger.php';

/**
 * BufferCallback is a struct which stores the callback information which can be triggered later.
 *
 * Defining class/object vs. using flexible associate-array since it provides (kinda) strong typing.
 *
 * Usage:
 * <code>
 * require_once 'network/iostream.php';
 *
 * $functionCallback = new \Zopt\Network\BufferCallback($len, 'function_name');
 * call_user_func($functionCallback->func, $arg);
 * $methodCallback = new \Zopt\Network\BufferCallback($len, array(obj, 'method_name'));
 * call_user_func($methodCallback->func, $arg);
 * $nullCallback = new \Zopt\Network\BufferCallback($len, NULL);
 * call_user_func($nullCallback->func, $arg);  // ERROR!  Call a NULL function
 * </code>
 */
class BufferCallback {
  public $len;
  public $func;

  /**
   * Create a new buffer callback object.
   *
   * @access public
   * @param int $len the length of buffer that will be affected.
   * @param string|array $func string - function, array - method of a class. See call_user_func.
   * @return N/A
   */
  public function __construct($len, $func) {
    $this->len = $len;
    $this->func = $func;
  }
}

/**
 * SockUtil defines many common constants and utilities for socket manipulation.
 *
 * Usage:
 * <code>
 * require_once 'network/iostream.php';
 *
 * $name = \Zopt\Network\SockUtil::getUniqueName($sock);
 * </code>
 */
class SockUtil {
  /**
   * This class can not be instance/new.
   *
   * @access private
   * @return N/A
   */
  private function __construct() {
  }

  /**
   * Get the unique name of an connected socket.
   *
   * A random number (time()) is attached to distinguish the following sockets A and B:
   * - peer socket A is established on addr:port.
   * - socket A is closed.
   * - peer socket B is established on the same addr:port like A.
   *
   * If the input socket is empty, ':0|time' will be generated.
   *
   * @access public
   * @static
   * @param resource $sock the peer socket
   * @return string the socket name
   */
  public static function getUniqueName($sock) {
    $addr = '';
    $port = 0;
    $sock && socket_getpeername($sock, $addr, $port);
    return $addr . ':' . $port . '|' . time();
  }
}

/**
 * IOStream wraps up the functionality of raw socket.
 * - implements the recv/send buffer management
 * - driven by events and callbacks
 * - handles graceful closing
 */
class IOStream {
  private static $_logger;
  private static $_maxBufSize = 20971520;  // 20M bytes buffer
  private static $_chunkSize = 4096;  // 4K bytes recv/send frame
  private static $_timeout = 2000000;  // 2s graceful quit timeout

  private $_sock;
  private $_eventBase;
  private $_name;

  private $_recvEvent = NULL;
  private $_recvBuf = '';
  private $_recvBufCallbacks = array();  // recvBufCallback->func($name, $msg)

  private $_sendEvent = NULL;
  private $_sendBuf = '';
  private $_sendBufCallbacks = array();  // sendBufCallback->func($name, $isSucceed)

  private $_isActiveClosing = FALSE;
  private $_isPassiveClosing = FALSE;
  private $_timeoutEvent = NULL;

  private $_closeCallback = NULL;  // closeCallback($name)

  public function __construct($sock, $eventBase, &$name = NULL, $closeCallback = NULL) {
    if (is_null(self::$_logger)) {
      self::$_logger = \Zopt\Base\Logger::getLogger(__CLASS__);
    }

    $this->_sock = $sock;
    $this->_eventBase = $eventBase;
    $name = is_null($name) ? SockUtil::getUniqueName($sock) : $name;
    $this->_name = $name;
    $this->_closeCallback = $closeCallback;

    if (is_null($sock) || is_null($eventBase)) {
      self::$_logger->warning("IOStream initialized with null socket $sock or event base $eventBase.");
      $this->_closeCallback && call_user_func($this->_closeCallback, $this->_name);
      return;
    }

    socket_set_nonblock($sock);

    $this->_recvEvent = event_new();
    event_set($this->_recvEvent, $this->_sock, EV_READ, array($this, '_handleRecvEvent'));
    event_base_set($this->_recvEvent, $this->_eventBase);

    $this->_sendEvent = event_new();
    event_set($this->_sendEvent, $this->_sock, EV_WRITE, array($this, '_handleSendEvent'));
    event_base_set($this->_sendEvent, $this->_eventBase);
  }
  
  public function __destruct() {
    $this->forceClose();
  }
  
  public function forceClose($closeCallback = NULL) {
    if (!is_null($closeCallback)) {
      $this->_closeCallback = $closeCallback;
    }
    if ($this->_recvEvent) {
      $this->_clearRecv();
    }
    if ($this->_sendEvent) {
      $this->_clearSend();
    }
    if ($this->_timeoutEvent) {
      event_del($this->_timeoutEvent);
      event_free($this->_timeoutEvent);
      $this->_timeoutEvent = NULL;
    }
    if (!is_null($this->_sock)) {
      socket_close($this->_sock);
      $this->_sock = NULL;
      $this->_closeCallback && call_user_func($this->_closeCallback, $this->_name);
    }
  }
  
  public function close($closeCallback = NULL) {
    if (!is_null($closeCallback)) {
      $this->_closeCallback = $closeCallback;
    }
    $this->_isActiveClosing = TRUE;
    $this->_handleSendEvent($this->_sock, NULL);  // trigger send operation  
  }
  
  private function _clearRecv() {
    if (!is_null($this->_recvEvent)) {
      event_del($this->_recvEvent);
      event_free($this->_recvEvent);
      $this->_recvEvent = NULL;
    }
    $this->_recvBuf = '';
    while (!empty($this->_recvBufCallbacks)) {
      $callback = array_shift($this->_recvBufCallbacks);
      $callback->func && call_user_func($callback->func, $this->_name, NULL); // NULL indicates recv fails
    }
  }
  
  public function isRecving() {
    return !empty($this->_recvBufCallbacks);
  }

  private function _consume() {
    while (!empty($this->_recvBufCallbacks)) {
      $callback = array_shift($this->_recvBufCallbacks);
      if ($callback->len > strlen($this->_recvBuf)) {
        array_unshift($this->_recvBufCallbacks, $callback);
        break;
      }
      $consumed = substr($this->_recvBuf, 0, $callback->len);
      $this->_recvBuf = substr($this->_recvBuf, $callback->len);
      $callback->func && call_user_func($callback->func, $this->_name, $consumed);
    }
    if ($this->isRecving()) {
      event_add($this->_recvEvent);
    }
  }

  public function _handleRecvEvent($sock, $flag, $isTimeout = FALSE) {
    // recv from socket
    $sum = 0;
    $segment = '';
    $len = 0;
    while (!$isTimeout && $len = @socket_recv($sock, $segment, self::$_chunkSize, 0)) {
      $sum += $len;
      $this->_recvBuf .= $segment;
    }
    
    // check the return value: can be the following 3 cases: "string", "", FALSE
    if ($sum > 0) {
      // handle the normal recving
      self::$_logger->info("<---, normal: $sum");
      // check if the receive buffer is too big.
      if (strlen($this->_recvBuf) > self::$_maxBufSize) {
        self::$_logger->warning("IOStream recv buffer exceed.");
        $this->forceClose();
      }
      $this->_consume();
    } elseif ($sum === 0 && $len === 0) {
      // the peer is closing
      self::$_logger->info("<---, peer closing: 0, 0");
      $this->_isPassiveClosing = TRUE;
      $this->_clearRecv();
      @socket_shutdown($sock, 0);  // shutdown the recv channel
      $this->_handleSendEvent($sock, NULL);  // trigger send operation
    } else {
      // handle error
      self::$_logger->info("<---, error");
      $this->forceClose();
    }
  }

  public function recv($len, $func = NULL) {
    if ($this->_isPassiveClosing || !$this->_sock) {
      // if remote is closing or the whole socket is closed, skip this action.
      self::$_logger->warning("Try to recv from a passive closing / closed socket " . $this->_name);
      $func && call_user_func($func, $this->_name, NULL);
      return;
    }

    $callback = new BufferCallback($len, $func);
    array_push($this->_recvBufCallbacks, $callback);
    $this->_consume();
  }

  private function _clearSend() {
    if (!is_null($this->_sendEvent)) {
      event_del($this->_sendEvent);
      event_free($this->_sendEvent);
      $this->_sendEvent = NULL;
    }
    $this->_sendBuf = '';
    while (!empty($this->_sendBufCallbacks)) {
      $callback = array_shift($this->_sendBufCallbacks);
      $callback->func && call_user_func($callback->func, $this->_name, FALSE); // FALSE indicates send fails
    }
  }
  
  public function isSending() {
    if (strlen($this->_sendBuf) === 0) {
      return FALSE;
    }
    if ($this->_isActiveClosing || $this->_isPassiveClosing) {
      // always flushing the writing queue if it's closing.
      return TRUE;
    }
    foreach ($this->_sendBufCallbacks as $callback) {
      if (!is_null($callback->func)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function _handleSendEvent($sock, $flag) {
    // handle the normal writing
    $sum = 0;
    while ($len = @socket_send($sock, $this->_sendBuf, self::$_chunkSize, 0)) {
      $sum += $len;
      $this->_sendBuf = substr($this->_sendBuf, $len);
    }

    // check the return value: can be the following 2 cases: >= 0 / FALSE
    if ($sum > 0 || $len === 0) {
      // handle the send callbacks
      self::$_logger->info("===>, normal: $sum, $len");
      while (!empty($this->_sendBufCallbacks)) {
        $callback = array_shift($this->_sendBufCallbacks);
        if ($callback->len > $sum) {
          $callback->len -= $sum;
          array_unshift($this->_sendBufCallbacks, $callback);
          break;
        }
        $sum -= $callback->len;
        $callback->func && call_user_func($callback->func, $this->_name);
      }

      if ($this->isSending()) {
        self::$_logger->info("===>, normal: need to send more");
        event_add($this->_sendEvent);
      } elseif ($this->_isActiveClosing || $this->_isPassiveClosing) {
        // close the socket
        self::$_logger->info("===>, normal: all sent, close local");
        $this->_clearSend();
        if ($this->_isPassiveClosing) {
          self::$_logger->info("===>, normal: passive closing");
          @socket_shutdown($sock, 1);  // shutdown the send channel
          $this->forceClose();
        } else {
          // timeout
          self::$_logger->info("===>, normal: set time out");
          $this->_timeoutEvent = event_new();
          event_set($this->_timeoutEvent, $sock, EV_TIMEOUT, array($this, '_handleRecvEvent'), TRUE);
          event_base_set($this->_timeoutEvent, $this->_eventBase);
          event_add($this->_timeoutEvent, self::$_timeout);
          event_add($this->_recvEvent);
          @socket_shutdown($sock, 1);  // shutdown the send channel
        }
      } else {
        self::$_logger->info("===>, normal: all sent");
      }
    } else {
      // handle error
      self::$_logger->info("===>, error");
      $this->forceClose();
    }
  }
  
  public function send($msg, $func = NULL) {
    if ($this->_isActiveClosing || !$this->_sock) {
      // if local is closing or the whole socket is closed, skip this action.
      self::$_logger->warning("Try to send to an active closing / closed socket " . $this->_name);
      $func && call_user_func($func, $this->_name, FALSE);
      return;
    }
    
    $this->_sendBuf .= $msg;
    $len = strlen($this->_sendBuf);
    if ($len > self::$_maxBufSize) {  // check if the send buffer is too long
      self::$_logger->warning("IOStream send buffer exceed.");
      $this->forceClose();
    }

    $callback = new BufferCallback($len, $func);
    array_push($this->_sendBufCallbacks, $callback);
    if ($this->isSending()) {
      event_add($this->_sendEvent);
    }
  }
}


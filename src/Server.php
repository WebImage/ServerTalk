<?php

namespace WebImage\ServerTalk;

use Exception;
use WebImage\Socket\SocketConnectionException;

class Server
{
	/** @var bool */
	private $running; // Whether the server has been started with run()
	private /*string*/ $host;
	private /*int*/ $port;
	private $serverSocket;
	private $shouldStopHandler = null;
	/** @var ConnectionInterface[] */
	private $connections       = [];
	/** @var callable */
	private $onConnectionHandler = null;
	/** @var callable */
	private $onMessageHandler = null;
	/** @var callable */
	private $onLogHandler = null;

	public function __construct(string $host, int $port)
	{
		$this->host    = $host;
		$this->port    = $port;
	}

	public function shouldStop(callable $handler)
	{
		$this->shouldStopHandler = $handler;
	}

	public function run()
	{
		if ($this->running) throw new \RuntimeException('Cannot call run() twice');
		$this->running = true;

		$this->listen();
		try {
			$this->loop();
		} catch (Exception $e) {
			$this->log('Caught an error');
		}
		$this->close();
	}

	private function listen()
	{
		$this->serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($this->serverSocket === false) throw new SocketConnectionException('Unable to create socket');
		if (!socket_bind($this->serverSocket, $this->host, $this->port)) throw new SocketConnectionException('Unable to bind to ' . $this->host . ':' . $this->port);
		if (!socket_listen($this->serverSocket)) throw new SocketConnectionException('Unable to listen on ' . $this->host . ':' . $this->port);
		$this->log('Listening on ' . $this->port);
	}

	private function loop()
	{
		while (true) {
			// If stopHandler() set, check if we should cancel
			if ($this->shouldStopHandler !== null && is_callable($this->shouldStopHandler)) {
				if (true === call_user_func($this->shouldStopHandler)) break;
			}

			$read = array_merge([$this->serverSocket], array_map(function(ConnectionInterface $connection) { return $connection->getSocket(); }, $this->connections));

			$numChanges = socket_select($read, $write, $execptions, 0);

			if ($numChanges == 0) {
				usleep(100000);
				continue;
			}
			// Handle connection requests
			if (in_array($this->serverSocket, $read)) {
				$serverIx = array_search($this->serverSocket, $read);
				$this->acceptSocket();
				unset($read[$serverIx]);
			}

			// Accept any new connections
			$this->processConnections($read);
		}
	}

	private function close()
	{
		socket_close($this->serverSocket);
	}

	private function acceptSocket()
	{
		$socket = @socket_accept($this->serverSocket);

		if ($socket === false) return;

		if (!is_resource($socket)) return;

		$connection          = $this->negotiateConnectionType($socket);
		$this->log('New connection: ' . $connection->getAddr() . ':' . $connection->getPort());
		$this->connections[] = $connection;

		// Call onConnection handler
		if ($this->onConnectionHandler !== null) call_user_func($this->onConnectionHandler, $connection, $this);
	}

	/**
	 * Determine whether this is a WebSocket or Socket client
	 * @param resource $socket
	 * @return ConnectionInterface $socket
	 */
	private function negotiateConnectionType($socket)
	{
		/**
		 * Attempt to read any initial
		 */
		$data = '';
		socket_recv($socket, $data, 5, MSG_PEEK | MSG_DONTWAIT);
		socket_getpeername($socket, $addr, $port);
		if (substr($data, 0, 5) == 'GET /') return $this->createWebSocketConnection($socket, $addr, $port);


		return $this->createSocketConnection($socket, $addr, $port);
	}

	private function createSocketConnection($clientSocket, string $addr, int $port)
	{
		return new SocketConnection($this, $clientSocket, $addr, $port);
	}

	private function createWebSocketConnection($clientSocket, string $addr, int $port)
	{
		/**
		 * Negotiate WebSocket handshake
		 */
		return new WebSocketConnection($this, $clientSocket, $addr, $port);
	}

	private function processConnections(array $read)
	{
		foreach($this->connections as $ix => $connection) {
			if (!in_array($connection->getSocket(), $read)) continue;

			$message = $connection->receive();

			if ($message !== null && $this->onMessageHandler !== null) {
				call_user_func($this->onMessageHandler, $message, $connection, $this);
			}
		}

		$this->cleanupClosedConnections();
	}

	/**
	 * Removed connections that have been closed
	 */
	private function cleanupClosedConnections()
	{
		$closedAny = false;
		foreach($this->connections as $ix => $connection) {
			if ($connection->isClosed()) {
				unset($this->connections[$ix]);
				$closedAny = true;
			}
		}

		if ($closedAny) $this->connections = array_values($this->connections); // Reset connection indexes
	}

	/**
	 * @param callable $handler function(ConnectionInterface $connection)
	 */
	public function onConnection(callable $handler)
	{
		$this->onConnectionHandler = $handler;
	}

	public function log(string $txt)
	{
		if ($this->onLogHandler === null) return;
		call_user_func($this->onLogHandler, $txt);
	}

	/**
	 * @param callable $handler function(MessageInterface, ClientInterface)
	 */
	public function onMessage(callable $handler)
	{
		$this->onMessageHandler = $handler;
	}

	public function onLog(callable $handler)
	{
		$this->onLogHandler = $handler;
	}

	/**
	 * @return ConnectionInterface[]
	 */
	public function getConnections()
	{
		return $this->connections;
	}
}

<?php

namespace WebImage\ServerTalk;

abstract class AbstractConnection implements ConnectionInterface
{
	const PROCESS_X_MESSAGES = 10;

	/** @var Server */
	private $server;
	/** @var resource */
	private $addr;
	private $port;
	private $socket;
	/** @var bool */
	private $isClosed = false;

	/**
	 * Client constructor.
	 *
	 * @param Server $server
	 * @param resource $socket
	 *
	 * @throws \RuntimeException
	 */
	public function __construct(Server $server, $socket, string $addr='', int $port=0)
	{
		if (!$socket) throw new \RuntimeException('Socket is expected to be a resource');

		$this->server = $server;
		$this->socket = $socket;
		$this->addr = $addr;
		$this->port = $port;
		$this->context = new Context();
	}

	public function getServer(): Server
	{
		return $this->server;
	}

	public function getSocket() /* : resource */
	{
		return $this->socket;
	}

	public function read(int $length = 1024): ?string
	{
		$data = @socket_read($this->getSocket(), $length, PHP_NORMAL_READ);
		if ($data === false) $this->close();

		return $data === false ? null : $data;
	}

	public function receive(): ?MessageInterface
	{
		$data = '';

		// Read one character at a time, looking for \n to end message
		while (($read = $this->read(1)) !== null) {
			if ($read == "\r") continue; // Ignore characters
			else if ($read == "\n") { // Indicates end of message
//				$this->getServer()->log('Received message: ' . $data);
//				$messages[] = new Message($data);
//				$data = '';

//				if (count($messages) >= self::PROCESS_X_MESSAGES) break;

				break;
			}
			$data .= $read;
		}

		return strlen($data) == 0 ? null : new Message($data);
	}

	/**
	 * Send raw data to server
	 *
	 * @param $data
	 *
	 * @return false|int|mixed
	 */
	public function write($data)
	{
		$result = @socket_write($this->getSocket(), $data, strlen($data));

		if ($result === false) {
			$this->close();
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	public function getAddr(): string
	{
		return $this->addr;
	}

	public function getPort(): int
	{
		return $this->port;
	}

	/**
	 * @inheritDoc
	 */
	public function close()
	{
		$this->isClosed = true;
		if ($this->getSocket()) @socket_close($this->getSocket());
	}

	/**
	 * @inheritDoc
	 */
	public function isClosed(): bool
	{
		return $this->isClosed;
	}
}

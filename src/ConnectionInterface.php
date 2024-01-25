<?php

namespace WebImage\ServerTalk;

interface ConnectionInterface
{
	public function getServer(): Server;

	public function getSocket();

	/**
	 * Read raw data from socket
	 * @param int $length
	 *
	 * @return string|null
	 */
	public function read(int $length = 1024): ?string;

	/**
	 * Process any new messages from the socket
	 * @return MessageInterface
	 */
	public function receive(): ?MessageInterface;

	/**
	 * Write raw data to socket
	 * @param $data
	 *
	 * @return mixed
	 */
	public function write($data);


	/**
	 * Connection address
	 * @return string
	 */
	public function getAddr(): string;

	/**
	 * Connection port
	 * @return int
	 */
	public function getPort(): int;

	/**
	 * Get contextual information for the connection
	 */
	public function getContext(): Context;

	/**
	 * Close the connection
	 */
	public function close();

	/**
	 * Whether the connection has been closed
	 * @return bool
	 */
	public function isClosed(): bool;
}

<?php

namespace WebImage\ServerTalk;

class Message implements MessageInterface
{
	/** @var string */
	private $data;

	/**
	 * Message constructor.
	 *
	 * @param string $data
	 */
	public function __construct(string $data)
	{
		$this->data = $data;
	}

	public function getData(): string
	{
		return $this->data;
	}
}

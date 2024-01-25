<?php

namespace WebImage\ServerTalk;

interface MessageInterface
{
	/**
	 * The body of the message
	 * @return string
	 */
	public function getData(): string;
}

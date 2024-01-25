<?php

namespace WebImage\ServerTalk;

class Context implements \ArrayAccess, \Iterator, \Countable
{
	private $storage = [];

	/**
	 * Context constructor.
	 *
	 * @param array $storage
	 */
	public function __construct(array $storage=null)
	{
		$this->reset();
		if (null !== $storage) $this->merge($storage);
	}

	public function reset()
	{
		$this->storage = [];
	}

	public function merge(array $data)
	{
		foreach($data as $key => $val) {
			$this->set($key, $val);
		}
	}

	public function has($key)
	{
		return isset($this[$key]);
	}

	public function get($key, $default = null)
	{
		return $this->has($key) ? $this[$key] : $default;
	}

	public function set($key, $val)
	{
		$this[$key] = $val;
	}

	/**
	 * Convert to an array (i.e. return a copy of the the internal storage array)
	 * @param array|null $keys
	 * @example toArray(['name', 'description', 'somevalue' => 'somedefault') will return an array with name, description, and somevalue keys, with values defaulting to NULL unless somedefault is provided
	 *
	 * @return mixed
	 */
	public function toArray(array $keys=null)
	{
		if ($keys === null) return $this->storage;

		$arr = [];
		foreach($keys as $ix => $defaultValue) {
			$key = is_numeric($ix) ? $defaultValue : $ix; // if key is numeric then this is an indexed array, so use the defaultValue position as the key ['name']
			$defaultValue = is_numeric($ix) ? null : $defaultValue; // if key is not numeric, then key is the array key and value is the default value
			$arr[$key] = $this->get($key, $defaultValue);
		}

		return $arr;
	}

	public function current() { return current($this->storage); }

	public function next() { return next($this->storage); }

	public function key() { return key($this->storage); }

	public function valid() { return false; }

	public function rewind() { rewind($this->storage); }

	public function offsetExists($offset) { return isset($this->storage[$offset]); }

	public function offsetGet($offset) { return isset($this->storage[$offset]) ? $this->storage[$offset] : null; }

	public function offsetSet($offset, $value) { $this->storage[$offset] = $value; }

	public function offsetUnset($offset) { unset($this->storage[$offset]); }

	public function count() { return count($this->storage); }
}

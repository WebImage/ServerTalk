<?php

namespace WebImage\ServerTalk;

class WebSocketConnection extends AbstractConnection
{
	public function __construct(Server $server, $socket, string $addr, int $port)
	{
		parent::__construct($server, $socket, $addr, $port);
		$this->negotiateHandshake();
	}

	private function negotiateHandshake()
	{
		$header = '';
		while ($data = socket_read($this->getSocket(), 1024)) {
			$header .= $data;
		}

		$lines = explode("\r\n", trim($header));
		$request = array_shift($lines);
		$sec_websocket_key = '';

		foreach($lines as $line) {
			list($key, $val) = explode(': ', $line, 2);
			if ($key == 'Sec-WebSocket-Key') {
				$sec_websocket_key = $val;
			}
		}

		/**
		 *
		 */
		$sec_websocket_accept = trim($sec_websocket_key);
		$sec_websocket_accept .= '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
		$sec_websocket_accept = sha1($sec_websocket_accept, true);
		$sec_websocket_accept = base64_encode($sec_websocket_accept);

		$response = "HTTP/1.1 101 Switching Protocols\r\n";
		$response .= "Upgrade: websocket\r\n";
		$response .= "Connection: Upgrade\r\n";
		$response .= "Sec-WebSocket-Accept: $sec_websocket_accept\r\n";
//		$response .= "Sec-WebSocket-Protocol: agent\r\n";
		$response .= "\r\n";

		parent::write($response);
	}

	/**
	 * @inheritDoc
	 */
	public function receive(): ?MessageInterface
	{
		/**
		 * HEADER rfc6455 - https://tools.ietf.org/html/rfc6455#section-5.2
		 * +-+-+-+-+-------+-+-------------+-------------------------------+
		 * |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
		 * |I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
		 * |N|V|V|V|       |S|             |   (if payload len==126/127)   |
		 * | |1|2|3|       |K|             |                               |
		 * +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
		 * |     Extended payload length continued, if payload len == 127  |
		 * + - - - - - - - - - - - - - - - +-------------------------------+
		 * |                               |Masking-key, if MASK set to 1  |
		 * +-------------------------------+-------------------------------+
		 * | Masking-key (continued)       |          Payload Data         |
		 * +-------------------------------- - - - - - - - - - - - - - - - +
		 * :                     Payload Data continued ...                :
		 * + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
		 * |                     Payload Data continued ...                |
		 * +---------------------------------------------------------------+
		 */
		$data = $this->read(2);
		if ($data === null || strlen($data) == 0) return null;

		$header = unpack('n', $data)[1];
		$is_fin = $header >> 15 == 1; // bit 16
		$opcode = ($header >> 8) & 0b1111; // bits 9-12
		$has_mask = ($header >> 7) & 0b1 == 1;
		$len = $header & 0b1111111;

		if ($len == 126) { // 126 is special code to read the next 16 bits (2 bytes) for the actual payload length
			$len = unpack('n', $this->read(2));
		} else if ($len == 127) { // 127 is a special to code to read the next 64 bits (8 bytes) for the actual payload length
			$len = unpack('n', $this->read(8));
		}

		$mask = $has_mask ? $this->read(4) : null; // 32 bits (4 bytes) IF mask key == 1

		$payload = $this->read($len);

		if ($has_mask) {
			// Unmask data
			for ($i = 0; $i < $len; $i++) {
				$payload[$i] = $payload[$i] ^ $mask[$i % 4];
			}
		}

		return new Message($payload);
	}

//	public function messageFromData($data): MessageInterface
//	{
//		$offset = 0;
//
//		$header = unpack('n', $data);
//		$header = $header[1];
//		// FIN RSV RSV RSV OP OP OP OP MSK LEN LEN LEN LEN LEN LEN LEN
//		// 16  15  14  13  12 11 10 09 08  07  06  05  04  03  02  01
//		//                                 64  32  16   8   4   2   1
//		$fin_mask = 32768; // 1 >> 15; //32768;
//		$op_mask = 3840;
//		$mask_mask = 128;
//		$len_mask = 127;
//
//		$is_final = ((($header && $fin_mask) >> 15) == 1);
//		$opcode = (($header && $op_mask) >> 8);
//		$has_mask = (($header & $mask_mask) >> 7);
//		$len = $header & $len_mask;
//
//		if ($has_mask == 1) {
//			$mask_offset = 2;
//			$payload_offset = 6; // Needs to be adjusted if $len >= 126
//
//			if ($len == 126) {
//				$mask_offset += 2;
//				$payload_offset += 2;
//			} else if ($len == 127) {
//				$mask_offset += 8;
//				$payload_offset += 8;
//			}
//
//			$mask = substr($data, $mask_offset, 4);
//			$payload = substr($data, $payload_offset, $len);
//
////			echo 'FINAL: ' . ($is_final?'Yes':'No') . PHP_EOL;
////			echo 'LEN: ' . $len . PHP_EOL;
////			echo 'OPCODE: ' . $opcode . PHP_EOL;
////			echo 'MASK: ' . $mask . PHP_EOL;
////			echo 'PAYLOAD 1: ' . $payload . PHP_EOL;
////			echo 'HEAD LEN: ' . strlen($header) . PHP_EOL;
////			echo 'DATA LEN: ' . strlen($data) . PHP_EOL;
////			for ($i = 0, $j = strlen($payload); $i < $j; $i++) {
//			for ($i = 0; $i < $len; $i++) {
//				$payload[$i] = $payload[$i] ^ $mask[$i % 4];
//			}
//			echo 'LEN: ' . $len . PHP_EOL;
//			echo 'PAYLOAD: ' . $payload . PHP_EOL;
//			echo 'PAYLEN: ' . (strlen($data) - $payload_offset) . PHP_EOL;
////			echo 'PAYLOAD 2: ' . $payload . PHP_EOL;
//
//			return parent::messageFromData($payload);
//		} else {
//			throw new \RuntimeException('Invalid MASK value.  Should have received 1 from client');
//		}
//	}

	public function write($data)
	{
		/**
		 * https://tools.ietf.org/html/rfc6455#section-5.2
		 * 1 bit FIN             0=NOT FINAL 1=FINAL
		 * 3 bits RESERVED		 0s
		 * 4 bits OPCODE         1=TEXT 2=BINARY
		 * 1 bit MASK            0=SERVER 1=CLIENT
		 * 7 bit PAYLOAD LENGTH
		 * ----
		 * HEADER LENGTH = 16 bits
		 */
		$fin_bit = 1 << 15;
		$opcode = 1 << 8;
		$len = strlen($data);

		$header = $fin_bit + $opcode + $len;

		$this->write(pack('n*', $header). $data); // Pack header as 16-bit integer
	}
}

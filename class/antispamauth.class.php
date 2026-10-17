<?php
/* Copyright (C) 2026 THERSANE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        crmclientconnector/class/antispamauth.class.php
 * \ingroup     crmclientconnector
 * \brief       Reading of the headers of an email: sender, subject, and authentication of the sender
 */

/**
 * Reading of the raw headers of an email for the anti-spam.
 *
 * The authentication of the sender is read only in the headers added by OUR receiving server, never in the ones
 * written by the sender:
 * - the first "Authentication-Results" from the top whose server id (authserv-id) is one of the configured ids. A
 *   sender can write his own "Authentication-Results: <our id>; dkim=pass", but it is below the one added by our
 *   server when the email is received;
 * - else the first "Received-SPF" (SPF only).
 *
 * Rule "sender authenticated", in this order:
 * 1. header.from of the result must be the domain of the From address, else not authenticated;
 * 2. dmarc=pass: authenticated (DMARC already checks the alignment of the From domain with DKIM or SPF);
 * 3. dmarc=fail: not authenticated;
 * 4. no DMARC policy at the sender: dkim=pass with header.d equal to the From domain (or one of its parents), or
 *    spf=pass with the domain of smtp.mailfrom equal to the From domain (or one of its parents): authenticated;
 * 5. no result added by our server: not verifiable (neither rescued nor flagged).
 */
class CrmClientConnectorAntispamAuth
{
	const RESULT_PASS = 'pass';
	const RESULT_FAIL = 'fail';
	const RESULT_UNKNOWN = 'unknown';

	/**
	 * Split raw headers into a list of [name, value], in their order (unfolded, not decoded)
	 *
	 * @param	string	$rawHeaders		Raw headers of the email
	 * @return	array<int,array{0:string,1:string}>
	 */
	public static function parseHeaders($rawHeaders)
	{
		$headers = array();
		$lines = preg_split("/\r\n|\n|\r/", (string) $rawHeaders);
		foreach ($lines as $line) {
			if ($line === '') {
				// End of the headers
				if (!empty($headers)) {
					break;
				}
				continue;
			}
			if (($line[0] === ' ' || $line[0] === "\t") && !empty($headers)) {
				// Folded line: continuation of the previous header
				$headers[count($headers) - 1][1] .= ' '.trim($line);
				continue;
			}
			$pos = strpos($line, ':');
			if ($pos === false) {
				continue;
			}
			$headers[] = array(strtolower(trim(substr($line, 0, $pos))), trim(substr($line, $pos + 1)));
		}
		return $headers;
	}

	/**
	 * First value of a header
	 *
	 * @param	array<int,array{0:string,1:string}>	$headers	Headers from parseHeaders()
	 * @param	string								$name		Header name (any case)
	 * @return	string									Raw value, '' if not found
	 */
	public static function getHeader($headers, $name)
	{
		$name = strtolower($name);
		foreach ($headers as $header) {
			if ($header[0] === $name) {
				return $header[1];
			}
		}
		return '';
	}

	/**
	 * Decode a MIME encoded header (=?utf-8?B?...?=) into UTF-8
	 *
	 * @param	string	$value	Raw value
	 * @return	string
	 */
	public static function decodeMime($value)
	{
		if (strpos($value, '=?') === false) {
			return $value;
		}
		$decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
		return $decoded === false ? $value : $decoded;
	}

	/**
	 * Single email address of a From header value (ex: "Name <a@b.fr>" or "a@b.fr")
	 *
	 * @param	string	$value	Raw value of the From header
	 * @return	string			Address in lower case, '' if none or several addresses
	 */
	public static function extractAddress($value)
	{
		$mailboxes = self::parseAddressList($value);
		return count($mailboxes) === 1 ? $mailboxes[0]['address'] : '';
	}

	/**
	 * Sender of an email, read only from the real addresses of the From header(s), never from the display name or a
	 * comment (they are free text for the sender).
	 *
	 * @param	array<int,array{0:string,1:string}>	$headers	Headers from parseHeaders()
	 * @return	array{address:string,ambiguous:bool,addresses:string[],displayAddresses:string[]}
	 *          address: the sender ('' when ambiguous); ambiguous: no From, several From headers or several addresses;
	 *          addresses: all the real addresses; displayAddresses: addresses written in the display names / comments
	 */
	public static function analyzeFrom($headers)
	{
		$values = array();
		foreach ($headers as $header) {
			if ($header[0] === 'from') {
				$values[] = $header[1];
			}
		}
		$addresses = array();
		$displayAddresses = array();
		foreach ($values as $value) {
			foreach (self::parseAddressList($value) as $mailbox) {
				$addresses[] = $mailbox['address'];
				// An address written in the display name, maybe MIME encoded (=?utf-8?B?...?=)
				if (preg_match_all('/[a-z0-9._%+\'-]+@[a-z0-9.-]+\.[a-z]{2,}/i', self::decodeMime($mailbox['display']), $regs)) {
					foreach ($regs[0] as $displayAddress) {
						$displayAddresses[] = strtolower($displayAddress);
					}
				}
			}
		}
		$addresses = array_values(array_unique(array_filter($addresses, 'strlen')));
		$ambiguous = count($values) !== 1 || count($addresses) !== 1;

		return array(
			'address' => $ambiguous ? '' : $addresses[0],
			'ambiguous' => $ambiguous,
			'addresses' => $addresses,
			'displayAddresses' => array_values(array_unique($displayAddresses)),
		);
	}

	/**
	 * Mailboxes of an address list header value (RFC 5322): the quoted strings and the comments are display text, the
	 * address is the one between <> or the bare addr-spec. MIME encoded words are not decoded (they can't hold an address).
	 *
	 * @param	string	$value	Raw header value
	 * @return	array<int,array{address:string,display:string}>	Addresses in lower case; display: display name and comments
	 */
	public static function parseAddressList($value)
	{
		$mailboxes = array();
		$plain = '';		// Text outside quotes, comments and <>
		$display = '';		// Quoted strings and comments
		$angle = null;		// Content of <>, null when none
		$inQuote = false;
		$inAngle = false;
		$depth = 0;
		$value = (string) $value;
		$length = strlen($value);

		for ($i = 0; $i <= $length; $i++) {
			$char = $i < $length ? $value[$i] : ',';
			if ($char === '\\' && ($inQuote || $depth > 0) && $i + 1 < $length) {
				$display .= $value[++$i];
				continue;
			}
			if ($inQuote) {
				if ($char === '"') {
					$inQuote = false;
				} else {
					$display .= $char;
				}
				continue;
			}
			if ($depth > 0) {
				if ($char === '(') {
					$depth++;
				} elseif ($char === ')') {
					$depth--;
				}
				if ($depth > 0) {
					$display .= $char;
				}
				continue;
			}
			if ($inAngle) {
				if ($char === '>') {
					$inAngle = false;
				} else {
					$angle .= $char;
				}
				continue;
			}
			if ($char === '"') {
				$inQuote = true;
			} elseif ($char === '(') {
				$depth = 1;
				$display .= ' ';
			} elseif ($char === '<') {
				$inAngle = true;
				$angle = '';
			} elseif ($char === ',' || $char === ';' || $char === ':') {
				// End of a mailbox (':' and ';' delimit a group "name: a@b, c@d;", the group name is display text)
				if ($char === ':') {
					$display .= ' '.$plain;
					$plain = '';
					continue;
				}
				$address = $angle !== null ? trim($angle) : trim($plain);
				if ($angle !== null) {
					$display .= ' '.$plain;
				}
				// Route of an obsolete angle address "<@route:a@b>"
				$address = strtolower((string) preg_replace('/^@[^:]*:/', '', $address));
				if ($address !== '' && preg_match('/^[^\s@<>()",;:]+@[^\s@<>()",;:]+$/', $address)) {
					$mailboxes[] = array('address' => $address, 'display' => trim($display));
				}
				$plain = '';
				$display = '';
				$angle = null;
			} else {
				$plain .= $char;
			}
		}

		return $mailboxes;
	}

	/**
	 * Domain of an address or of a domain (lower case)
	 *
	 * @param	string	$value	Address (a@b.fr) or domain (b.fr)
	 * @return	string
	 */
	public static function getDomain($value)
	{
		$value = strtolower(trim((string) $value, " \t<>\"'"));
		$pos = strrpos($value, '@');
		return $pos === false ? $value : substr($value, $pos + 1);
	}

	/**
	 * Is a domain the From domain or one of its parents (alignment of DKIM / SPF)
	 *
	 * @param	string	$domain		Domain of the signature or of the envelope (ex: example.com)
	 * @param	string	$fromDomain	Domain of the From address (ex: mail.example.com)
	 * @return	bool
	 */
	public static function isAligned($domain, $fromDomain)
	{
		$domain = strtolower(trim($domain));
		$fromDomain = strtolower(trim($fromDomain));
		// A top level domain alone (ex: "fr", "com") is never an aligned domain
		if ($domain === '' || $fromDomain === '' || strpos($domain, '.') === false) {
			return false;
		}
		return $domain === $fromDomain || substr($fromDomain, -strlen('.'.$domain)) === '.'.$domain;
	}

	/**
	 * Check the authentication of the sender of an email
	 *
	 * @param	array<int,array{0:string,1:string}>	$headers		Headers from parseHeaders()
	 * @param	string								$fromAddress	From address
	 * @param	string[]							$authservIds	Ids of our receiving servers (ex: srv11.example.com)
	 * @return	array{result:string,detail:string}		result: pass, fail or unknown; detail: plain text summary
	 */
	public static function check($headers, $fromAddress, $authservIds)
	{
		$fromDomain = self::getDomain($fromAddress);
		$authservIds = array_values(array_filter(array_map('strtolower', array_map('trim', (array) $authservIds)), 'strlen'));
		if ($fromDomain === '' || empty($authservIds)) {
			return array('result' => self::RESULT_UNKNOWN, 'detail' => '');
		}

		// 1. Authentication-Results of our server: the first one from the top
		foreach ($headers as $header) {
			if ($header[0] !== 'authentication-results') {
				continue;
			}
			$parts = self::splitStructured($header[1]);
			$authservId = $parts === null ? '' : strtolower((string) preg_replace('/\s.*$/', '', trim($parts[0])));
			if ($parts === null) {
				// Unbalanced quotes or comments: maybe ours, made unreadable on purpose by a forged value
				$authservId = strtolower((string) preg_replace('/[\s;].*$/', '', trim($header[1])));
				if (in_array($authservId, $authservIds, true)) {
					return array('result' => self::RESULT_FAIL, 'detail' => 'malformed Authentication-Results ['.$authservId.']');
				}
				continue;
			}
			if (!in_array($authservId, $authservIds, true)) {
				continue;
			}
			return self::checkAuthenticationResults(array_slice($parts, 1), $fromDomain, $authservId);
		}

		// 2. Else Received-SPF of our server (first one), unless disabled in the setup
		$spf = self::getHeader($headers, 'received-spf');
		if ($spf !== '' && !getDolGlobalInt('CRMCLIENTCONNECTOR_ANTISPAM_DISABLE_RECEIVED_SPF')) {
			$result = strtolower((string) preg_replace('/[\s(;].*$/', '', trim($spf)));
			$parts = self::splitStructured((string) preg_replace('/^\S+/', '', trim($spf)));
			$keys = array();
			$malformed = $parts === null;
			foreach ((array) $parts as $part) {
				// "key=value" pairs separated by ';' (the comment of the result is removed by splitStructured)
				if (!preg_match('/^\s*([a-z0-9_-]+)\s*=\s*(.*)$/i', $part, $reg)) {
					continue;
				}
				$key = strtolower($reg[1]);
				if (isset($keys[$key])) {
					$malformed = true;
				}
				$keys[$key] = trim(trim($reg[2]), '"<> ');
			}
			$envelopeFrom = isset($keys['envelope-from']) ? $keys['envelope-from'] : '';
			$detail = 'spf='.$result.($envelopeFrom !== '' ? ' ('.$envelopeFrom.')' : '').' [Received-SPF]';
			if (!$malformed && $result === 'pass' && (!isset($keys['identity']) || strtolower($keys['identity']) === 'mailfrom')
				&& self::isAligned(self::getDomain($envelopeFrom), $fromDomain)) {
				return array('result' => self::RESULT_PASS, 'detail' => $detail);
			}
			return array('result' => self::RESULT_FAIL, 'detail' => $detail);
		}

		return array('result' => self::RESULT_UNKNOWN, 'detail' => '');
	}

	/**
	 * Rule of the class description applied to the results of one Authentication-Results header
	 *
	 * @param	string[]	$results		Results ("dmarc=pass header.from=example.com", "dkim=pass header.d=..."...)
	 * @param	string		$fromDomain		Domain of the From address
	 * @param	string		$authservId		Id of the server that added the header
	 * @return	array{result:string,detail:string}
	 */
	protected static function checkAuthenticationResults($results, $fromDomain, $authservId)
	{
		$dmarc = '';
		$headerFrom = '';
		$dkimAligned = false;
		$spfAligned = false;
		$summary = array();

		$seen = array();
		$duplicate = false;
		foreach ($results as $result) {
			if (!preg_match('/^\s*([a-z0-9_-]+)\s*=\s*([a-z]+)(.*)$/is', $result, $reg)) {
				continue;
			}
			$method = strtolower($reg[1]);
			$value = strtolower($reg[2]);
			$properties = self::parseProperties($reg[3]);
			// One result of spf and of dmarc per header: a second one is a forged value (several dkim are normal)
			if (in_array($method, array('spf', 'dmarc'), true) && isset($seen[$method])) {
				$duplicate = true;
			}
			$seen[$method] = true;

			if ($method === 'dmarc') {
				$dmarc = $value;
				if (!empty($properties['header.from'])) {
					$headerFrom = self::getDomain($properties['header.from']);
				}
				$summary[] = 'dmarc='.$value;
			} elseif ($method === 'dkim') {
				$domain = !empty($properties['header.d']) ? $properties['header.d'] : (!empty($properties['header.i']) ? self::getDomain($properties['header.i']) : '');
				if ($value === 'pass' && self::isAligned($domain, $fromDomain)) {
					$dkimAligned = true;
				}
				$summary[] = 'dkim='.$value.($domain !== '' ? ' ('.$domain.')' : '');
			} elseif ($method === 'spf') {
				$domain = !empty($properties['smtp.mailfrom']) ? self::getDomain($properties['smtp.mailfrom']) : '';
				if ($value === 'pass' && self::isAligned($domain, $fromDomain)) {
					$spfAligned = true;
				}
				$summary[] = 'spf='.$value.($domain !== '' ? ' ('.$domain.')' : '');
			}
		}

		$detail = implode(', ', $summary).' ['.$authservId.']';

		if ($duplicate) {
			return array('result' => self::RESULT_FAIL, 'detail' => $detail.' (duplicate result)');
		}
		if ($headerFrom !== '' && $headerFrom !== $fromDomain) {
			return array('result' => self::RESULT_FAIL, 'detail' => $detail.' header.from='.$headerFrom);
		}
		if ($dmarc === 'pass') {
			// DMARC is only meaningful for the domain it was evaluated on
			if ($headerFrom === '') {
				return array('result' => self::RESULT_FAIL, 'detail' => $detail.' (dmarc without header.from)');
			}
			return array('result' => self::RESULT_PASS, 'detail' => $detail);
		}
		if ($dmarc === 'fail') {
			return array('result' => self::RESULT_FAIL, 'detail' => $detail);
		}
		if ($dkimAligned || $spfAligned) {
			return array('result' => self::RESULT_PASS, 'detail' => $detail);
		}
		return array('result' => self::RESULT_FAIL, 'detail' => $detail);
	}

	/**
	 * Properties of a result ("header.d=example.com smtp.mailfrom=a@b.fr"), values maybe quoted
	 *
	 * @param	string	$text	Text after "method=result" (comments already removed)
	 * @return	array<string,string>
	 */
	protected static function parseProperties($text)
	{
		$properties = array();
		if (preg_match_all('/([a-z0-9_.-]+)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^\s;]+)/i', $text, $regs, PREG_SET_ORDER)) {
			foreach ($regs as $reg) {
				$value = $reg[2];
				if (strlen($value) >= 2 && $value[0] === '"') {
					$value = stripcslashes(substr($value, 1, -1));
				}
				$properties[strtolower($reg[1])] = $value;
			}
		}
		return $properties;
	}

	/**
	 * Split a structured header value on ';' (RFC 8601 / RFC 5322): the comments are removed, the quoted strings are
	 * kept as is (a ';' or a '(' inside quotes is text, not a separator nor a comment), backslash escapes are respected.
	 *
	 * @param	string	$value	Header value
	 * @return	string[]|null		Parts, null when a quoted string or a comment is not closed (malformed value)
	 */
	public static function splitStructured($value)
	{
		$parts = array();
		$current = '';
		$inQuote = false;
		$depth = 0;
		$value = (string) $value;
		$length = strlen($value);
		for ($i = 0; $i < $length; $i++) {
			$char = $value[$i];
			if ($char === '\\' && ($inQuote || $depth > 0)) {
				if ($i + 1 >= $length) {
					return null;
				}
				if ($inQuote) {
					$current .= $char.$value[$i + 1];
				}
				$i++;
				continue;
			}
			if ($inQuote) {
				$current .= $char;
				if ($char === '"') {
					$inQuote = false;
				}
				continue;
			}
			if ($depth > 0) {
				if ($char === '(') {
					$depth++;
				} elseif ($char === ')') {
					$depth--;
				}
				continue;
			}
			if ($char === '"') {
				$inQuote = true;
				$current .= $char;
			} elseif ($char === '(') {
				$depth = 1;
				$current .= ' ';
			} elseif ($char === ')') {
				return null;
			} elseif ($char === ';') {
				$parts[] = $current;
				$current = '';
			} else {
				$current .= $char;
			}
		}
		if ($inQuote || $depth > 0) {
			return null;
		}
		$parts[] = $current;
		return $parts;
	}
}

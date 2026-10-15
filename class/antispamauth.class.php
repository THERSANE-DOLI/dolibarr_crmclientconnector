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
	 * Email address of a From header (ex: "Name <a@b.fr>" or "a@b.fr")
	 *
	 * @param	string	$value	Raw value of the From header
	 * @return	string			Address in lower case, '' if none
	 */
	public static function extractAddress($value)
	{
		$value = self::decodeMime($value);
		if (preg_match('/<\s*([^<>\s]+@[^<>\s]+)\s*>/', $value, $reg)) {
			return strtolower($reg[1]);
		}
		if (preg_match('/([^\s<>"\'(),;:]+@[^\s<>"\'(),;:]+)/', $value, $reg)) {
			return strtolower($reg[1]);
		}
		return '';
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
		if ($domain === '' || $fromDomain === '') {
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
			$value = self::removeComments($header[1]);
			$parts = array_map('trim', explode(';', $value));
			$authservId = strtolower((string) preg_replace('/\s.*$/', '', $parts[0]));
			if (!in_array($authservId, $authservIds, true)) {
				continue;
			}
			return self::checkAuthenticationResults(array_slice($parts, 1), $fromDomain, $authservId);
		}

		// 2. Else Received-SPF of our server (first one)
		$spf = self::getHeader($headers, 'received-spf');
		if ($spf !== '') {
			$result = strtolower((string) preg_replace('/[\s(;].*$/', '', $spf));
			$envelopeFrom = '';
			if (preg_match('/envelope-from=([^;\s]+)/i', $spf, $reg)) {
				$envelopeFrom = trim($reg[1], '"<> ');
			}
			$detail = 'spf='.$result.($envelopeFrom !== '' ? ' ('.$envelopeFrom.')' : '').' [Received-SPF]';
			if ($result === 'pass' && self::isAligned(self::getDomain($envelopeFrom), $fromDomain)) {
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

		foreach ($results as $result) {
			if (!preg_match('/^([a-z0-9_-]+)\s*=\s*([a-z]+)(.*)$/i', $result, $reg)) {
				continue;
			}
			$method = strtolower($reg[1]);
			$value = strtolower($reg[2]);
			$properties = self::parseProperties($reg[3]);

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

		if ($headerFrom !== '' && $headerFrom !== $fromDomain) {
			return array('result' => self::RESULT_FAIL, 'detail' => $detail.' header.from='.$headerFrom);
		}
		if ($dmarc === 'pass') {
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
	 * Properties of a result ("header.d=example.com smtp.mailfrom=a@b.fr")
	 *
	 * @param	string	$text	Text after "method=result"
	 * @return	array<string,string>
	 */
	protected static function parseProperties($text)
	{
		$properties = array();
		if (preg_match_all('/([a-z0-9_.-]+)\s*=\s*("[^"]*"|[^\s;]+)/i', $text, $regs, PREG_SET_ORDER)) {
			foreach ($regs as $reg) {
				$properties[strtolower($reg[1])] = trim($reg[2], '"');
			}
		}
		return $properties;
	}

	/**
	 * Remove the comments (in parentheses, maybe nested) of a header value
	 *
	 * @param	string	$value	Header value
	 * @return	string
	 */
	protected static function removeComments($value)
	{
		$result = '';
		$depth = 0;
		$length = strlen($value);
		for ($i = 0; $i < $length; $i++) {
			$char = $value[$i];
			if ($char === '(') {
				$depth++;
			} elseif ($char === ')' && $depth > 0) {
				$depth--;
			} elseif ($depth === 0) {
				$result .= $char;
			}
		}
		return $result;
	}
}

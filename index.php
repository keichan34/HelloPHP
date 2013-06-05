<?php
/*

HelloPHP Proxy.
Proxies requests to other servers.
Usage of this script should be taken with great care (and used only in dire circumstances)

The performance characteristics are shaky at best. I really mean it when I say, "use only in dire circumstances".

*/

error_reporting(0);

require_once(dirname(__FILE__) . '/configuration.php');

define('PROXYPASSREVERSE', PROXYPROTOCOL . '://' . PROXYHOST . PROXYSUBDIR);

ini_set('max_execution_time', 600);
ini_set('max_input_time', 600);

if (DEBUG) {
	$dbuf = '';
}

// Construct the path relative to this script
$pathinfo = (empty($_SERVER['PATH_INFO'])) ? '/' : $_SERVER['PATH_INFO'];

if ($qstr = $_SERVER['QUERY_STRING']) {
	$pathinfo .= '?' . $qstr;
}

// Stick it on to the destination header
$request_url = PROXYPASSREVERSE . $pathinfo;

$ch = curl_init($request_url);

// Set input headers
$input_headers = getallheaders();

$headers = array();
$headers = array_merge(
	$headers,
	array(
		'X-Proxied-By' => 'HelloPHPProxy',
		'X-Forwarded-Host' => $_SERVER['HTTP_HOST'],
		'X-Forwarded-For' => $_SERVER['REMOTE_ADDR'],
		'Host' => PROXYHOST
	)
);

$passthru_headers = array(
	'Authorization',
	'User-Agent',
	'Accept-Language',
	'If-Match',
	'If-Modified-Since',
	'If-None-Match',
	'Referer',
	'Cache-Control',
	'Cookie'
);
foreach ($passthru_headers as $pt) {
	// Filter out any headers that aren't in the whitelist
	if (isset($input_headers[$pt])) {
		if ($pt === 'Cookie') {
			// You can't set the Cookie: header with CURLOPT_HTTPHEADER, you need to use CURLOPT_COOKIE
			curl_setopt($ch, CURLOPT_COOKIE, $input_headers[$pt]);
		} else {
			$headers[$pt] = $input_headers[$pt];
		}
	}
}

// We want the results
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 100);
curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);

// We also want headers
curl_setopt($ch, CURLOPT_HEADER, 1);

// Keep track of temporary files we have received
$temps = array();

// Pass-through POST requests, too
if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' ) {
	// Set up the POST request

	// Re-upload POST variables
	$post = array();

	// We'll have to unroll multiple dimensions
	function _unroll_array($source, $state = array(), $keypath = '') {
		$add_to_state = array();
		foreach ($source as $key => $val) {
			if ($keypath == '') {
				$new_key = $key;
			} else {
				$new_key = $keypath . "[$key]";
			}
			if (is_array($val)) {
				$add_to_state = array_merge(
					$add_to_state,
					_unroll_array($val, array(), $new_key)
				);
			} else {
				// Move on.
				$add_to_state[$new_key] = $val;
			}
		}
		return array_merge($state, $add_to_state);
	}

	$post = _unroll_array($_POST);

	foreach ($_FILES as $filekey => $contents) {
		if (is_array($contents['name'])) {
			// We have a multi-level file upload
			foreach ($contents['name'] as $key => $name) {
				if ($contents['error'][$key] == 0) {
					$newtmp = '/tmp/' . $name;
					if (move_uploaded_file($contents['tmp_name'][$key], $newtmp)) {
						$temps[] = $newtmp;
						$post["{$filekey}[{$key}]"] = '@' . $newtmp /*. ";type={$contents['type'][$key]}"*/;
					}
				}
			}
		}
		if ($contents['error'] == 0) {
			$newtmp = '/tmp/' . $contents['name'];
			if (move_uploaded_file($contents['tmp_name'], $newtmp)) {
				$temps[] = $newtmp;
				$post[$filekey] = '@' . $newtmp /*. ";type={$contents['type']}"*/;
			}
		}
	}

	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
if (DEBUG) {
	$dbuf .= "==> Request URL: {$request_url}\n";
	$dbuf .= "=> Request Headers: \n\n";
	$dbuf .= print_r($headers, true);
}

// Do the fetch
$response = curl_exec($ch);

// Get the headers
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $header_size);
$body = substr($response, $header_size);

curl_close($ch);

if (DEBUG) {
	$dbuf .= "\n\n=> Response Headers: \n\n";
}

$no_body = false;

foreach(explode("\n", $header) as $line) {
	// If ETag is the same, then we can send a 304 response.
	if (preg_match('/ETag: (.*)$/', $line, $matches) == 1) {
		if (trim($matches[1]) == @$input_headers['If-None-Match']) {
			header('HTTP/1.1 304 Not Modified');
			$no_body = true;
		}
	}

	// We'll match a '/' or a '%2F'
	$subdir = str_replace('\/', '(?:\/|%2F)', preg_quote(PROXYSUBDIR, '/'));
	$line = preg_replace('/(http:\/\/)?'. preg_quote(PROXYHOST, '/') .'('. $subdir .')?/', '$1' . $_SERVER['HTTP_HOST'], $line);
	$line = str_replace(PROXYSUBDIR, '', $line);

	// We won't forward these headers:
	// Connection - Our connection is handled by PHP, not by the other server
	// Keep-Alive - ditto.
	// Content-Length - Our content-length may be different from their content-length
	if (strpos($line, "Connection") === 0 || strpos($line, "Keep-Alive") === 0 || strpos($line, "Content-Length") === 0 || strpos($line, "Transfer-Encoding") === 0) {
		continue;
	}
	if (DEBUG) {
		$dbuf .= $line . "\n";
	}
	if (strpos($line, "Set-Cookie") === 0) {
		// There may be multiple Set-Cookie headers; we want PHP to send every single one of them back to the user.
		header($line, false);
	} else {
		header($line);
	}
}

if (!$no_body) {
	// There may be remnants in the body
	$body = str_replace(PROXYSUBDIR, '', $body);

	echo $body;
}


if (DEBUG) {
	echo "\r\n<!--\r\n{$dbuf}\r\n-->";
}

// Get rid of any temporary files we made during this request.
foreach ($temps as $temp) {
	unlink($temp);
}

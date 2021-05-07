<?php

use Sabre\DAV\Exception\BadRequest;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\Xml\ParseException;

class WebDAVTempFilePlugin extends \Sabre\DAV\TemporaryFileFilterPlugin {
	/**
	 * This is the list of patterns we intercept.
	 * If new patterns are added, they must be valid patterns for preg_match.
	 *
	 * @var array
	 */
	public $temporaryFilePatterns = [
		// phpcs:disable
		'/^\._(.*)$/',     // OS/X resource forks
		'/^.DS_Store$/',   // OS/X custom folder settings
		'/^desktop.ini$/', // Windows custom folder settings
		'/^Thumbs.db$/',   // Windows thumbnail cache
		'/^.(.*).swp$/',   // ViM temporary files
		'/^\.dat(.*)$/',   // Smultron seems to create these
		'/^~lock.(.*)#$/', // Windows 7 lockfiles,
		'/Desktop\.ini$/', // Desktop.ini files
		'/^~\$.*$/', // MSOffice temp files
		'/^.*.tmp$/', // Office .tmp files
		'/^.*\.wbk$/' // safety copy files
		// phpcs:enable
	];

	/**
	 * This method is called before any HTTP method handler
	 *
	 * This method intercepts any GET, DELETE, PUT, PROPFIND and PROPPATCH calls to
	 * filenames that are known to match the 'temporary file' regex.
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return bool
	 */
	public function beforeMethod( RequestInterface $request, ResponseInterface $response ) {
		$tempLocation = $this->isTempFile( $request->getPath() );
		if ( !$tempLocation ) {
			return false;
		}
		switch ( $request->getMethod() ) {
			case 'GET':
				return $this->httpGet( $request, $response, $tempLocation );
			case 'PUT':
				return $this->httpPut( $request, $response, $tempLocation );
			case 'PROPFIND':
				return $this->httpPropfind( $request, $response, $tempLocation );
			case 'DELETE':
				return $this->httpDelete( $request, $response, $tempLocation );
			case 'PROPPATCH':
				return $this->httpProppatch( $request, $response, $tempLocation );
		}
	}

	/**
	 * TempFile PROPATCH
	 *
	 * Updates the properties of a temp file
	 * Answers with a brief response only
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @param string $tmp
	 * @return bool
	 * @throws BadRequest
	 */
	public function httpProppatch( RequestInterface $request, ResponseInterface $response, $tmp ) {
		try {
			$propPatch = $this->server->xml->expect( '{DAV:}propertyupdate', $request->getBody() );
		} catch ( ParseException $ex ) {
			throw new BadRequest( $ex->getMessage(), null, $ex );
		}
		$newProperties = $propPatch->properties;

		$result = $this->server->updateProperties( $tmp, $newProperties );
		$isOK = true;
		foreach ( $result as $prop => $code ) {
			if ( (int)$code > 299 ) {
				$isOK = false;
			}
		}

		if ( $isOK ) {
			$response->setStatus( 204 );
			return false;
		}
		return false;
	}
}

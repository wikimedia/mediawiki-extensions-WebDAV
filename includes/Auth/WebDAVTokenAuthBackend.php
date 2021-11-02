<?php

use MediaWiki\Extension\WebDAV\WebDAVCredentialAuthProvider;
use Sabre\DAV\Auth\Backend\BackendInterface;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class WebDAVTokenAuthBackend implements BackendInterface {

	/**
	 *
	 * @var string
	 */
	protected $sRealm = '';

	/**
	 *
	 * @var string
	 */
	protected $sPrincipalPrefix = 'principals/';

	/**
	 *
	 * @var RequestContext
	 */
	protected $oRequestContext;

	/**
	 *
	 * @var WebDAVTokenizer
	 */
	protected $oWebDAVTokenizer;
	/** @var WebDAVCredentialAuthProvider */
	protected $credentialAuthProvider;

	/**
	 *
	 * @param RequestContext $requestContext
	 * @param WebDAVTokenizer $webDAVTokenizer
	 * @param WebDAVCredentialAuthProvider $credentialAuthProvider
	 */
	public function __construct(
		$requestContext, $webDAVTokenizer, WebDAVCredentialAuthProvider $credentialAuthProvider
	) {
		$this->oRequestContext = $requestContext;
		$this->oWebDAVTokenizer = $webDAVTokenizer;
		$this->credentialAuthProvider = $credentialAuthProvider;
	}

	/**
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 */
	public function challenge( RequestInterface $request, ResponseInterface $response ) {
	}

	/**
	 * Does 3-way authorization
	 * - First checks URL for token
	 * - Looks at session cookie, to determine if and which user is logged in
	 * - Tries traditional Basic Auth authorization
	 * - - If Basic Auth header is not present, tries to log in user from static token
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return array
	 */
	public function check( RequestInterface $request, ResponseInterface $response ) {
		global $wgUser;

		$token = $this->getAndRemoveToken( $request );

		if ( empty( $token ) ) {
			$staticToken = $this->getAndRemoveToken( $request, 'stk' );
			if ( $this->oRequestContext->getUser()->isRegistered() ) {
				if ( $staticToken ) {
					$this->oWebDAVTokenizer->setUser( $this->oRequestContext->getUser() );
					$this->oWebDAVTokenizer->renewStaticToken( $staticToken );
				}
				return [ true, $this->sPrincipalPrefix . $this->oRequestContext->getUser()->getName() ];
			}

			return $this->tryBasicAuthLogin( $request, $response, $staticToken );
		}

		$user = $this->oWebDAVTokenizer->getUserFromTokenAndUrl( $token, $request->getUrl() );
		if ( $user === null ) {
			return [ false, "User not valid" ];
		}
		$user->setCookies();
		$this->oRequestContext->setUser( $user );
		$wgUser = $user;

		return [ true, $this->sPrincipalPrefix . $user->getName() ];
	}

	/**
	 * Detects if URL contains a token,
	 * removes it from the URL, and returns token
	 *
	 * @param \Sabre\HTTP\RequestInterface $request
	 * @param string $prefix
	 * @return string $sToken
	 */
	protected function getAndRemoveToken( RequestInterface $request, $prefix = 'tkn' ) {
		$url = urldecode( $request->getUrl() );
		$urlPieces = explode( '/', $url );

		$rawToken = '';
		foreach ( $urlPieces as $urlPiece ) {
			if ( strpos( $urlPiece, $prefix ) === 0 ) {
				$rawToken = $urlPiece;
			}
		}
		if ( empty( $rawToken ) ) {
			return '';
		}

		$token = substr( urldecode( $rawToken ), 3 );
		$finalUrl = str_replace( '/' . $rawToken, '', $url );
		$request->setUrl( $finalUrl );

		return $token;
	}

	/**
	 * Tries to login user over BasicAuth
	 * copy-paste from AbstractBasic
	 *
	 * @param \Sabre\HTTP\RequestInterface $request
	 * @param \Sabre\HTTP\ResponseInterface $response
	 * @param string $staticToken
	 * @return array
	 */
	protected function tryBasicAuthLogin( $request, $response, $staticToken = '' ) {
		$auth = new \Sabre\HTTP\Auth\Basic(
			$this->sRealm,
			$request,
			$response
		);

		$creds = $auth->getCredentials();
		// Do not try to login same user again
		if ( $this->oRequestContext->getUser()->getName() === $creds[0]
				&& $this->oRequestContext->getUser()->isRegistered() ) {
			return [ true, $this->sPrincipalPrefix . $creds[0] ];
		}

		if ( !$creds ) {
			if ( $staticToken && $this->tryLoginFromStaticToken( $staticToken ) ) {
				return [ true, $this->sPrincipalPrefix .
					$this->oRequestContext->getUser()->getName() ];
			}
			$auth->requireLogin();
			return [ false, "No 'Authorization: Basic' header found. Either the client didn't "
				. "send one, or the server is misconfigured" ];
		}

		if ( !$this->validateUserPass( $creds[0], $creds[1] ) ) {
			return [ false, "Username or password was incorrect" ];
		}

		if ( $staticToken ) {
			$res = $this->addStaticToken( $staticToken );
		}

		return [ true, $this->sPrincipalPrefix . $creds[0] ];
	}

	/**
	 * Validate username and password
	 *
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	protected function validateUserPass( $username, $password ) {
		$user = $this->credentialAuthProvider->getValidatedUser( $username, $password );
		if ( $user instanceof User ) {
			$this->doLogInUser( $user );
			return true;
		}

		return false;
	}

	/**
	 *
	 * @global \User $wgUser
	 * @param \User $user
	 */
	protected function doLogInUser( $user ) {
		global $wgUser;

		$user->setCookies();
		$this->oRequestContext->setUser( $user );
		$wgUser = $user;
	}

	/**
	 *
	 * @global \User $wgUser
	 * @param string $staticToken
	 * @return bool
	 */
	protected function tryLoginFromStaticToken( $staticToken ) {
		global $wgUser;

		$user = $this->oWebDAVTokenizer->getUserFromStaticToken( $staticToken );
		if ( $user === null ) {
			return false;
		}
		$user->setCookies();
		$this->oRequestContext->setUser( $user );
		$wgUser = $user;

		return true;
	}

	/**
	 *
	 * @param string $staticToken
	 * @return bool
	 */
	protected function addStaticToken( $staticToken ) {
		$this->oWebDAVTokenizer->setUser(
			$this->oRequestContext->getUser()
		);
		$res = $this->oWebDAVTokenizer->addStaticToken( $staticToken );
		return $res;
	}

	/**
	 *
	 * @param string $staticToken
	 * @param \User $user
	 * @return string
	 */
	protected function checkStaticToken( $staticToken, $user ) {
		$this->oWebDAVTokenizer->setUser( $user );
		return $this->oWebDAVTokenizer->checkStaticToken( $staticToken );
	}
}

<?php
// HINT in SabreDAV 2.x there will be Sabre\DAV\Auth\Backend\BasicCallback
// available as an alternative to this implemenation

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;

class WebDAVMediaWikiAuthBackend extends Sabre\DAV\Auth\Backend\AbstractBasic {

	/**
	 *
	 * @var \IContextSource
	 */
	protected $requestContext = null;

	/**
	 *
	 * @param \IContextSource $requestContext
	 */
	public function __construct( $requestContext ) {
		$this->requestContext = $requestContext;
	}

	/**
	 *
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	protected function validateUserPass( $username, $password ) {
		$username = utf8_encode( $username );
		$password = utf8_encode( $password );

		if ( static::doValidateUserAndPassword( $username, $password ) ) {
			$user = User::newFromName( $username );
			RequestContext::getMain()->setUser( $user );
			global $wgUser;
			$wgUser = $user;
		}
	}

	/**
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	public static function doValidateUserAndPassword( $username, $password ) {
		if ( method_exists( MediaWikiServices::class, 'getAuthManager' ) ) {
			// MediaWiki 1.35+
			$manager = MediaWikiServices::getInstance()->getAuthManager();
		} else {
			$manager = AuthManager::singleton();
		}
		$reqs = AuthenticationRequest::loadRequestsFromSubmission(
			$manager->getAuthenticationRequests( AuthManager::ACTION_LOGIN ),
			[
				'username' => $username,
				'password' => $password,
			]
		);
		$res = $manager->beginAuthentication( $reqs, 'null:' );
		if ( $res->status === AuthenticationResponse::PASS ) {
			return true;
		}
		return false;
	}
}

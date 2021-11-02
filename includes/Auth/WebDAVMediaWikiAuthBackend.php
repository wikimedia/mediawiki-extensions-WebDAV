<?php
// HINT in SabreDAV 2.x there will be Sabre\DAV\Auth\Backend\BasicCallback
// available as an alternative to this implemenation

use MediaWiki\Extension\WebDAV\WebDAVCredentialAuthProvider;

class WebDAVMediaWikiAuthBackend extends Sabre\DAV\Auth\Backend\AbstractBasic {

	/**
	 *
	 * @var \IContextSource
	 */
	protected $requestContext = null;
	/** @var WebDAVCredentialAuthProvider */
	protected $credentialAuthProvider;

	/**
	 *
	 * @param \IContextSource $requestContext
	 * @param WebDAVCredentialAuthProvider $credentialAuthProvider
	 */
	public function __construct(
		$requestContext, WebDAVCredentialAuthProvider $credentialAuthProvider
	) {
		$this->requestContext = $requestContext;
		$this->credentialAuthProvider = $credentialAuthProvider;
	}

	/**
	 *
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	protected function validateUserPass( $username, $password ) {
		$user = $this->credentialAuthProvider->getValidatedUser( $username, $password );

		if ( $user === null ) {
			return false;
		}

		$this->requestContext->setUser( $user );
		$GLOBALS['wgUser'] = $user;
		return true;
	}
}

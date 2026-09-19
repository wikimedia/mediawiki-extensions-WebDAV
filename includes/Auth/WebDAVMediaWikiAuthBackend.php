<?php
// HINT in SabreDAV 2.x there will be Sabre\DAV\Auth\Backend\BasicCallback
// available as an alternative to this implemenation

use MediaWiki\Context\MutableContext;
use MediaWiki\Extension\WebDAV\WebDAVCredentialAuthProvider;

class WebDAVMediaWikiAuthBackend extends Sabre\DAV\Auth\Backend\AbstractBasic {

	public function __construct(
		private readonly MutableContext $requestContext,
		private readonly WebDAVCredentialAuthProvider $credentialAuthProvider,
	) {
	}

	/**
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
		return true;
	}
}

<?php
// HINT in SabreDAV 2.x there will be Sabre\DAV\Auth\Backend\BasicCallback
// available as an alternative to this implemenation


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

		$user = User::newFromName( $username );
		if ( $user instanceof User ) {
			if ( $user->checkPassword( $password ) ) {
				$user->setCookies();
				// The new way
				$this->requestContext->setUser( $user );
				return true;
			}
		}
		return false;
	}
}

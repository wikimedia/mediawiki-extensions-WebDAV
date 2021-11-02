<?php

namespace MediaWiki\Extension\WebDAV\CredentialAuthProvider;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\WebDAV\WebDAVCredentialAuthProvider;
use User;

class MediaWikiAuth implements WebDAVCredentialAuthProvider {

	/**
	 * @inheritDoc
	 */
	public function getValidatedUser( $username, $password ) {
		$username = utf8_encode( $username );
		$password = utf8_encode( $password );

		$manager = AuthManager::singleton();
		$reqs = AuthenticationRequest::loadRequestsFromSubmission(
			$manager->getAuthenticationRequests( AuthManager::ACTION_LOGIN ),
			[
				'username' => $username,
				'password' => $password,
			]
		);
		$res = $manager->beginAuthentication( $reqs, 'null' );
		if ( $res->status === AuthenticationResponse::PASS ) {
			return User::newFromName( $username );
		}

		return null;
	}
}

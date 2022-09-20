<?php

namespace MediaWiki\Extension\WebDAV\CredentialAuthProvider;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\WebDAV\WebDAVCredentialAuthProvider;
use MediaWiki\MediaWikiServices;

class MediaWikiAuth implements WebDAVCredentialAuthProvider {

	/**
	 * @inheritDoc
	 */
	public function getValidatedUser( $username, $password ) {
		$username = utf8_encode( $username );
		$password = utf8_encode( $password );

		$services = MediaWikiServices::getInstance();
		$manager = $services->getAuthManager();
		$reqs = AuthenticationRequest::loadRequestsFromSubmission(
			$manager->getAuthenticationRequests( AuthManager::ACTION_LOGIN ),
			[
				'username' => $username,
				'password' => $password,
			]
		);
		$res = $manager->beginAuthentication( $reqs, 'null' );
		if ( $res->status === AuthenticationResponse::PASS ) {
			return $services->getUserFactory()->newFromName( $username );
		}

		return null;
	}
}

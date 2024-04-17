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
		$username = mb_convert_encoding( $username, 'UTF-8', 'ISO-8859-1' );
		$password = mb_convert_encoding( $password, 'UTF-8', 'ISO-8859-1' );

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

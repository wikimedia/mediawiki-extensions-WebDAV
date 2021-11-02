<?php

namespace MediaWiki\Extension\WebDAV;

use User;

interface WebDAVCredentialAuthProvider {
	/**
	 * @param string $username
	 * @param string $password
	 * @return User|null if user cannot be authenticated
	 */
	public function getValidatedUser( $username, $password );
}

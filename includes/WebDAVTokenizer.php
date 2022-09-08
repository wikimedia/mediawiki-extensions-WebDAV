<?php

use MediaWiki\MediaWikiServices;

class WebDAVTokenizer {
	protected $oUser;
	protected $oDB;
	protected $sFilename;
	protected $sToken = '';
	protected $iTokenExpiration = 0;
	protected $iStaticTokenExpiration = 0;
	protected $bUserNameAsStaticToken = false;
	protected $bInvalidateOnUnlock = true;

	/**
	 *
	 * @param Wikimedia\Rdbms\Database $db
	 * @param int $tokenExpiration
	 * @param int $staticTokenExpiration
	 * @param bool $userNameAsStaticToken
	 * @param bool $invalidateOnUnlock
	 */
	public function __construct( $db, $tokenExpiration, $staticTokenExpiration,
			$userNameAsStaticToken, $invalidateOnUnlock ) {
		$this->oDB = $db;
		$this->iTokenExpiration = $tokenExpiration;
		$this->iStaticTokenExpiration = $staticTokenExpiration;
		$this->bUserNameAsStaticToken = $userNameAsStaticToken;
		$this->bInvalidateOnUnlock = $invalidateOnUnlock;
	}

	/**
	 * Gets new token for user and file
	 * - Checks if this user has active tokens
	 * If not:
	 * - Generates new token for the file
	 *
	 * @param string $filename
	 * @return string
	 */
	public function getTokenForFile( $filename ) {
		$this->sFilename = $filename;

		if ( $this->bInvalidateOnUnlock ) {
			$this->checkForActiveTokens( $filename );
			if ( $this->sToken ) {
				return $this->sToken;
			}
		}

		$this->generateToken();
		return $this->sToken;
	}

	/**
	 *
	 * @param string $filename
	 * @return bool
	 */
	protected function checkForActiveTokens( $filename ) {
		if ( !$this->oUser instanceof User || $this->oUser->getId() === 0 ) {
			return false;
		}
		$conds = [
			'wdt_expire > ' . wfTimestamp( TS_UNIX ),
			'wdt_valid = 1'
		];
		$conds[ 'wdt_filename' ] = $filename;
		$res = $this->oDB->selectRow(
				'webdav_tokens',
				[ 'wdt_token' ],
				$conds
		);
		if ( $res === false ) {
			return null;
		}
		$this->sToken = $res->wdt_token;
	}

	/**
	 * Returns User object if valid token
	 * exists for token/file combination
	 *
	 * @param string $token
	 * @param string $url
	 * @return User
	 */
	public function getUserFromTokenAndUrl( $token, $url ) {
		$this->sFilename = WebDAVHelper::getFilenameFromUrl( $url );

		$conds = [
			'wdt_token' => $token,
			'wdt_valid' => 1,
		];
		if ( $this->sFilename ) {
			$conds[ 'wdt_filename' ] = $this->sFilename;
		}
		$conds[] = 'wdt_expire >= ' . wfTimestamp( TS_UNIX );
		$res = $this->oDB->selectRow(
				'webdav_tokens',
				[ 'wdt_user_id' ],
				$conds
		);
		if ( $res === false ) {
			return null;
		}
		$userId = $res->wdt_user_id;
		return MediaWikiServices::getInstance()->getUserFactory()->newFromId( $userId );
	}

	/**
	 *
	 * @return void
	 */
	protected function generateToken() {
		if ( !$this->oUser instanceof User || $this->oUser->isRegistered() === false ) {
			return;
		}
		// Invalidate existing tokens to make sure we have no multiple valid tokens
		$this->invalidateTokensForFile( $this->sFilename );

		$secret = \MWCryptRand::generateHex( 16 );
		$tokenObj = new \MediaWiki\Session\Token( $secret, $this->sFilename );
		$token = $tokenObj->toString();
		// Remove +/ suffix
		$token = substr( $token, 0, -2 );
		$expire = wfTimestamp( TS_UNIX, time() + $this->iTokenExpiration );
		$res = $this->oDB->insert(
				'webdav_tokens',
				[
					'wdt_user_id' => $this->oUser->getId(),
					'wdt_filename' => $this->sFilename,
					'wdt_token' => $token,
					'wdt_valid' => 1,
					'wdt_expire' => $expire
				]
		);
		if ( $res === false ) {
			return;
		}
		$this->sToken = $token;
	}

	/**
	 * Invalidates all valid tokens for
	 * file and current user
	 *
	 * @param string $filename
	 * @return bool
	 */
	public function invalidateTokensForFile( $filename ) {
		if ( !$this->oUser instanceof User || $this->oUser->getId() === 0 ) {
			return false;
		}
		return $this->invalidateUserTokens( $this->oUser->getId(), $filename );
	}

	/**
	 *
	 * @param int $userId
	 * @param string $filename
	 * @return bool
	 */
	protected function invalidateUserTokens( $userId, $filename = false ) {
		$conds = [
			'wdt_user_id' => $userId
		];
		if ( $filename ) {
			$conds[ 'wdt_filename' ] = $filename;
		}
		$res = $this->oDB->update(
			'webdav_tokens',
			[ 'wdt_valid' => 0 ],
			$conds
		);
		return $res !== false;
	}

	/**
	 * Sets current user
	 *
	 * @param User $user
	 */
	public function setUser( $user ) {
		$this->oUser = $user;
	}

	/**
	 * Creates static-token session
	 *
	 * @param string $staticToken
	 * @return bool
	 */
	public function addStaticToken( $staticToken ) {
		$expire = wfTimestamp( TS_UNIX, time() + $this->iStaticTokenExpiration );
		$this->oDB->delete(
			'webdav_static_tokens',
			[ 'wdst_user_id' => $this->oUser->getId() ]
		);

		$userToken = $this->getStaticToken( true );
		if ( $userToken == false || $this->checkStaticToken( $staticToken ) == false ) {
			return false;
		}

		$res = $this->oDB->insert(
				'webdav_static_tokens',
				[
					'wdst_user_id' => $this->oUser->getId(),
					'wdst_token' => $staticToken,
					'wdst_expire' => $expire
				]
		);
		if ( $res === false ) {
			return false;
		}
		return true;
	}

	/**
	 * Gets the user for static token
	 * if its session is active
	 *
	 * @param string $staticToken
	 * @return User|null
	 */
	public function getUserFromStaticToken( $staticToken ) {
		$res = $this->oDB->selectRow(
				'webdav_static_tokens',
				[ 'wdst_user_id' ],
				[
					'wdst_token' => $staticToken,
					'wdst_expire > ' . wfTimestamp( TS_UNIX )
				]
		);
		if ( $res === false ) {
			return null;
		}
		$userId = $res->wdst_user_id;
		return MediaWikiServices::getInstance()->getUserFactory()->newFromId( $userId );
	}

	/**
	 * Renews static token on every request
	 *
	 * @return bool
	 */
	public function renewStaticToken() {
		$expire = wfTimestamp( TS_UNIX, time() + $this->iStaticTokenExpiration );
		$res = $this->oDB->update(
				'webdav_static_tokens',
				[ 'wdst_expire' => $expire ],
				[
					'wdst_user_id' => $this->oUser->getId()
				]
		);
		if ( $res === false ) {
			return false;
		}
		return true;
	}

	/**
	 * Retrieves or generates personalized token for user
	 *
	 * @param bool $retrieveOnly
	 * @return bool
	 */
	public function getStaticToken( $retrieveOnly = false ) {
		$res = $this->oDB->selectRow(
				'webdav_user_static_tokens',
				[ 'wdust_token' ],
				[
					'wdust_user_id' => $this->oUser->getId()
				]
		);
		if ( $res === false ) {
			if ( $retrieveOnly ) {
				return false;
			}
			if ( $this->bUserNameAsStaticToken ) {
				$token = '_' . $this->oUser->getName();
			} else {
				$token = \MWCryptRand::generateHex( 10 );
			}
			$insertRes = $this->oDB->insert(
				'webdav_user_static_tokens',
				[
					'wdust_user_id' => $this->oUser->getId(),
					'wdust_token' => $token
				]
			);
			if ( $insertRes !== false ) {
				return $token;
			} else {
				return false;
			}
		}
		return $res->wdust_token;
	}

	/**
	 * Compares static token sent in request
	 * with the one saved for that user
	 *
	 * @param type $staticToken
	 * @return bool
	 */
	public function checkStaticToken( $staticToken ) {
		if ( $staticToken == '' ) {
			return true;
		}
		if ( $staticToken == $this->getStaticToken( true ) ) {
			return true;
		}
		return false;
	}
}

<?php

use MediaWiki\Extension\WebDAV\Extension as WebDAV;

class WebDAVHooks {
	/**
	 * Adds the lock table to the database
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable(
			'webdav_locks',
			dirname( __DIR__ ) . '/docs/webdav.locks.sql'
		);
		$updater->addExtensionTable(
			'webdav_tokens',
			dirname( __DIR__ ) . '/docs/webdav.tokens.sql'
		);
		$updater->addExtensionTable(
			'webdav_static_tokens',
			dirname( __DIR__ ) . '/docs/webdav.static.tokens.sql'
		);
		$updater->addExtensionTable(
			'webdav_user_static_tokens',
			dirname( __DIR__ ) . '/docs/webdav.user.static.tokens.sql'
		);
		return true;
	}

	/**
	 * Registeres appropriate auth plugin
	 * @param \Sabre\DAV\Server $server
	 * @param array &$plugins
	 * @return true
	 */
	public static function onWebDAVPlugins( $server, &$plugins ) {
		$services = \MediaWiki\MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'webdav' );
		$requestContext = RequestContext::getMain();
		$webDAVTokenizer = \MediaWiki\MediaWikiServices::getInstance()->getService( 'WebDAVTokenizer' );

		switch ( $config->get( 'WebDAVAuthType' ) ) {
			case WebDAV::WEBDAV_AUTH_MW:
				$plugins['auth'] = new \Sabre\DAV\Auth\Plugin(
						new WebDAVMediaWikiAuthBackend(
							$requestContext,
							$services->getService( 'WebDAVCredentialAuthProvider' )
						)
					);
				break;
			case WebDAV::WEBDAV_AUTH_TOKEN:
				$plugins['auth'] = new \Sabre\DAV\Auth\Plugin(
						new WebDAVTokenAuthBackend(
							$requestContext, $webDAVTokenizer,
							$services->getService( 'WebDAVCredentialAuthProvider' )
						)
					);
				break;
		}
		return true;
	}

	/**
	 * Invokes invalidation of user tokens for the file
	 * when that file is closed
	 *
	 * @param bool $success
	 * @param \Sabre\DAV\Locks\LockInfo $lockInfo
	 * @return true
	 */
	public static function onWebDAVLocksUnlock( $success, $lockInfo ) {
		$config = \MediaWiki\MediaWikiServices::getInstance()
				->getConfigFactory()->makeConfig( 'webdav' );
		if ( $config->get( 'WebDAVAuthType' ) !== WebDAV::WEBDAV_AUTH_TOKEN ) {
			return true;
		}

		if ( $config->get( 'WebDAVInvalidateTokenOnUnlock' ) == false ) {
			return true;
		}

		$webDAVTokenizer = \MediaWiki\MediaWikiServices::getInstance()->getService( 'WebDAVTokenizer' );
		$filename = WebDAVHelper::getFilenameFromUrl( $lockInfo->uri );
		$webDAVTokenizer->setUser( RequestContext::getMain()->getUser() );
		$webDAVTokenizer->invalidateTokensForFile( $filename );
		return true;
	}

	/**
	 * Adds URL for mounting WebDAV drive
	 * with staticToken to preferences
	 *
	 * @param User $user
	 * @param array &$preferences
	 * @return bool
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$config = \MediaWiki\MediaWikiServices::getInstance()
				->getConfigFactory()->makeConfig( 'webdav' );

		if ( $config->get( 'WebDAVAuthType' ) !== WebDAV::WEBDAV_AUTH_TOKEN ) {
			return true;
		}

		if ( $user->isAnon() ) {
			return true;
		}

		$webDAVTokenizer = \MediaWiki\MediaWikiServices::getInstance()->getService( 'WebDAVTokenizer' );
		$webDAVTokenizer->setUser( $user );
		$token = $webDAVTokenizer->getStaticToken();
		$webDAVUrl =
			$config->get( 'WebDAVServer' )
			. $config->get( 'WebDAVBaseUri' )
			. 'stk'
			. $token
			. '/';

		$preferences[ 'webdav-statictoken-info' ] = [
			'type' => 'info',
			'section' => 'personal/webdav',
			'label-message' => 'webdav-statictoken-prefs',
			'default' => $webDAVUrl,
			'help-message' => 'webdav-statictoken-prefs-help'
		];

		return true;
	}
}

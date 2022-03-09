<?php

use MediaWiki\Extension\WebDAV\WebDAVCredentialAuthProvider;

return [
	'WebDAVUrlProvider' => static function ( \MediaWiki\MediaWikiServices $services ) {
		$config = $services->getMainConfig();

		$user = RequestContext::getMain()->getUser();

		return new WebDAVUrlProvider(
				$config->get( 'WebDAVServer' ),
				$config->get( 'WebDAVUrlBaseUri' ),
				$config->get( 'WebDAVAuthType' ),
				\RequestContext::getMain(),
				$user
		);
	},
	'WebDAVTokenizer' => static function ( \MediaWiki\MediaWikiServices $services ) {
		$config = $services->getMainConfig();

		$db = wfGetDB( DB_PRIMARY );

		return new WebDAVTokenizer(
			$db,
			$config->get( 'WebDAVTokenExpiration' ),
			$config->get( 'WebDAVStaticTokenExpiration' ),
			$config->get( 'WebDAVUserNameAsStaticToken' ),
			$config->get( 'WebDAVInvalidateTokenOnUnlock' )
		);
	},
	'WebDAVCredentialAuthProvider' => static function ( \MediaWiki\MediaWikiServices $services ) {
		$config = $services->getMainConfig();
		$authProviderKey = $config->get( 'WebDAVCredentialAuthProvider' );
		$attribute = ExtensionRegistry::getInstance()->getAttribute(
			'WebDAVCredentialAuthProviders'
		);

		if ( !isset( $attribute[$authProviderKey] ) ) {
			throw new MWException(
				"CredentialAuthProvider with key $authProviderKey is not registered"
			);
		}
		$spec = $attribute[$authProviderKey];
		$instance = null;
		if ( $services->hasService( 'ObjectFactory' ) ) {
			$instance = $services->getService( 'ObjectFactory' )->createObject( $spec );
		} else {
			$instance = \Wikimedia\ObjectFactory\ObjectFactory::getObjectFromSpec( $spec );
		}

		if ( !$instance instanceof WebDAVCredentialAuthProvider ) {
			throw new MWException(
				'CredentialAuthProvider must be instance of ' . WebDAVCredentialAuthProvider::class
			);
		}

		return $instance;
	}
];

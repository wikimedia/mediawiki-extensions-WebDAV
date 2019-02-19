<?php

return [
	'WebDAVUrlProvider' => function ( \MediaWiki\MediaWikiServices $services ) {
		$config = $services->getConfigFactory()->makeConfig( 'wg' );

		$user = RequestContext::getMain()->getUser();

		return new WebDAVUrlProvider(
				$config->get( 'Server' ),
				$config->get( 'WebDAVUrlBaseUri' ),
				$config->get( 'WebDAVAuthType' ),
				\RequestContext::getMain(),
				$user
		);
	},
	'WebDAVTokenizer' => function ( \MediaWiki\MediaWikiServices $services ) {
		$config = $services->getConfigFactory()->makeConfig( 'wg' );

		$db = wfGetDB( DB_MASTER );

		return new WebDAVTokenizer(
			$db,
			$config->get( 'WebDAVTokenExpiration' ),
			$config->get( 'WebDAVStaticTokenExpiration' ),
			$config->get( 'WebDAVUserNameAsStaticToken' ),
			$config->get( 'WebDAVInvalidateTokenOnUnlock' )
		);
	}
];

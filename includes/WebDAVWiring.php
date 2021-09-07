<?php

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
	}
];

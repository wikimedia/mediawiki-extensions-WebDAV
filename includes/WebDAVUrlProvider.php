<?php

use MediaWiki\Extension\WebDAV\Extension as WebDAV;
use MediaWiki\MediaWikiServices;

class WebDAVUrlProvider {

	/**
	 *
	 * @var WebRequest
	 */
	protected $oRequest;

	/**
	 *
	 * @var string
	 */
	protected $sServer;

	/**
	 *
	 * @var string
	 */
	protected $sWebDAVUrlBaseUri;

	/**
	 *
	 * @var int
	 */
	protected $webDAVAuthType;

	/**
	 *
	 * @var User
	 */
	protected $oUser;

	/**
	 * @var MediaWikiServices
	 */
	protected $services = null;

	/**
	 *
	 * @param string $server
	 * @param string $webDAVUrlBaseUri
	 * @param string $webDAVAuthType
	 * @param WebRequest $request
	 * @param User $user
	 */
	public function __construct( $server, $webDAVUrlBaseUri, $webDAVAuthType, $request, $user ) {
		$this->sServer = $server;
		$this->sWebDAVUrlBaseUri = $webDAVUrlBaseUri;
		$this->webDAVAuthType = $webDAVAuthType;
		$this->oRequest = $request;
		$this->oUser = $user;

		$this->services = MediaWikiServices::getInstance();
	}

	/**
	 *
	 * @param Title $title
	 * @return string
	 */
	public function getURL( Title $title ) {
		$path = $this->services->getNamespaceInfo()->getCanonicalName( NS_MEDIA );
		$filename = $title->getDBKey();

		if ( $this->webDAVAuthType === WebDAV::WEBDAV_AUTH_TOKEN ) {
			$sToken = $this->getToken( $filename );
			$path = $sToken . $path;
		}
		$hookContainer = $this->services->getHookContainer();
		$hookContainer->run( 'WebDAVUrlProviderGetUrl', [ &$path, &$filename, $title ] );

		$sUrl = $this->sServer . $this->sWebDAVUrlBaseUri . $path . '/' . $filename;

		return $sUrl;
	}

	/**
	 *
	 * @param string $filename
	 * @return string
	 */
	protected function getToken( $filename ) {
		$webDAVTokenizer = $this->services->getService( 'WebDAVTokenizer' );
		$webDAVTokenizer->setUser( $this->oUser );
		return 'tkn' . $webDAVTokenizer->getTokenForFile( $filename ) . '/';
	}
}

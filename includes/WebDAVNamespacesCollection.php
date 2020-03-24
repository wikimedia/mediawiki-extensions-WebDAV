<?php

class WebDAVNamespacesCollection extends Sabre\DAV\Collection {

	/**
	 *
	 * @return array of Nodes
	 */
	public function getChildren() {
		$children = $this->makeChildren();
		return array_values( $children );
	}

	/**
	 * @global Language $wgContLang
	 * @return array of Nodes
	 */
	protected function makeChildren() {
		$children = [];
		$namespaceIds = self::getNamespaces();

		foreach ( $namespaceIds as $nsId => $nsText ) {
			$children[$nsId] = $this->makeChild( $nsId, $nsText );
		}

		return $children;
	}

	/**
	 *
	 * @global string $wgSitename
	 * @return string
	 */
	public function getName() {
		global $wgSitename;
		return $wgSitename;
	}

	/**
	 *
	 * @param int $nsId
	 * @param string $name
	 * @return Sabre\DAV\Node
	 */
	public function makeChild( $nsId, $name ) {
		$config = \MediaWiki\MediaWikiServices::getInstance()
			->getConfigFactory()->makeConfig( 'webdav' );

		$collectionClass = 'WebDAVPagesCollection';
		$collections = $config->get( 'WebDAVNamespaceCollections' );
		if ( isset( $collections[ $nsId ] ) ) {
			$collectionClass = $collections[ $nsId ];
		}
		return new $collectionClass( $this, $name, $nsId, '' );
	}

	/**
	 *
	 * @return int[]
	 */
	public static function getNamespaces() {
		$mwServices = \MediaWiki\MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'webdav' );

		$namespaceIds = [];
		$context = \RequestContext::getMain();
		$language = $context->getLanguage();
		$user = $context->getUser();
		foreach ( $language->getNamespaceIds() as $nsId ) {
			/* For some strange reasons there are duplicates in the list
			 * provided by Language object.
			 */
			if ( isset( $namespaceIds[$nsId] ) ) {
				continue;
			}

			if ( in_array( $nsId, $config->get( 'WebDAVSkipNamespaces' ) ) ) {
				continue;
			}

			if ( MWNamespace::isTalk( $nsId ) && $config->get( 'WebDAVSkipTalkNS' ) ) {
				continue;
			}

			$dummyTitle = Title::makeTitle( $nsId, 'X' );
			if ( class_exists( 'MediaWiki\Permissions\PermissionManager' ) ) {
				// MW 1.33+
				if ( !$mwServices->getPermissionManager()
					->userCan( 'read', $user, $dummyTitle )
				) {
					continue;
				}
			} else {
				if ( $dummyTitle->userCan( 'read' ) === false ) {
					continue;
				}
			}

			$name = MWNamespace::getCanonicalName( $nsId );
			if ( $nsId == NS_MAIN ) {
				$name = wfMessage( 'webdav-ns-main' )->plain();
			}

			$namespaceIds[$nsId] = str_replace( ' ', '_', $name );
		}

		return $namespaceIds;
	}
}

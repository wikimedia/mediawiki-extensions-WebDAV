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
		$config = $mwServices->getConfigFactory()->makeConfig( 'webdav' );

		$namespaceIds = [];
		$context = \RequestContext::getMain();
		$language = $context->getLanguage();
		$user = $context->getUser();
		$namespaceInfo = $mwServices->getNamespaceInfo();
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

			if ( $namespaceInfo->isTalk( $nsId ) && $config->get( 'WebDAVSkipTalkNS' ) ) {
				continue;
			}

			if ( !$namespaceInfo->isContent( $nsId )
					&& $config->get( 'WebDAVOnlyContentNS' )
					&& $nsId !== NS_MEDIA
				) {
				continue;
			}

			$dummyTitle = Title::makeTitle( $nsId, 'X' );
			if ( !$mwServices->getPermissionManager()
					->userCan( 'read', $user, $dummyTitle )
				) {
				continue;
			}

			$name = $namespaceInfo->getCanonicalName( $nsId );
			if ( $nsId == NS_MAIN ) {
				// When mounting the wiki as a WebDAV drive one will see e.g.
				// webdav/stk0a1b2c3d4e5f/<webdav-ns-main>/Main_Page.wiki
				// but in the Media folder for attachments one will see e.g.
				// webdav/stk0a1b2c3d4e5f/Media/<nsfilerepo-nsmain>/Example.docx
				// These two message keys are not necessarily the same.
				$name = $context->msg( 'webdav-ns-main' )->plain();
			}

			$namespaceIds[$nsId] = str_replace( ' ', '_', $name );
		}

		return $namespaceIds;
	}
}

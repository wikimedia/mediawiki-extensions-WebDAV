<?php

class WebDAVPagesCollection extends Sabre\DAV\Collection {

	protected $oParent = null;
	protected $sName = '';
	protected $iNSId = null;
	protected $sBasePath = '';

	/**
	 *
	 * @param Sabre\DAV\Node $parent
	 * @param string $name
	 * @param int $nsId
	 * @param string $basePath
	 */
	public function __construct( $parent, $name, $nsId, $basePath = '' ) {
		$this->oParent = $parent;
		$this->sName = $name;
		$this->iNSId = $nsId;
		$this->sBasePath = str_replace( ' ', '_', $basePath );
	}

	/**
	 *
	 * @return Sabre\DAV\Node[]
	 */
	public function getChildren() {
		$mwServices = \MediaWiki\MediaWikiServices::getInstance();
		$config = $mwServices->getConfigFactory()->makeConfig( 'webdav' );
		$user = RequestContext::getMain()->getUser();

		$dbr = wfGetDB( DB_REPLICA );

		$conds = [
			'page_namespace' => $this->iNSId,
			'page_title ' . $dbr->buildLike(
				$this->sBasePath,
				$dbr->anyString()
			)
		];

		$res = $dbr->select( 'page', '*', $conds );

		$children = [];
		foreach ( $res as $row ) {

			// '$row->page_title' be "A/B/C/D" and '$this->sBasePath' be "A/B/"
			// "C/D"
			$trimmedTitle = substr( $row->page_title, strlen( $this->sBasePath ) );
			// ["C", "D"]
			$trimmedTitleParts = explode( '/', $trimmedTitle, 2 );
			$baseTitle = $trimmedTitleParts[0];

			// e.g. if $row->page_title' is "A/B/C/D/" (trailing slash)
			if ( empty( $baseTitle ) ) {
				// It's not a file, it's not a folder... it's a 'fillder'
				// This is a valid MediaWiki title but it can not be formed into a file/folder
				// structure. Sorry for that :(
				wfDebugLog( 'WebDAV', __METHOD__ . ': Invalid characters in ' . $row->page_title );
				continue;
			}

			$regex = $config->get( 'WebDAVInvalidFileNameCharsRegEx' );
			if ( preg_match( $regex, $baseTitle ) !== 0 ) {
				wfDebugLog( 'WebDAV', __METHOD__ . ': Invalid characters in ' . $row->page_title );
				continue;
			}

			// This is not the leaf part
			if ( count( $trimmedTitleParts ) > 1 && MWNamespace::hasSubpages( $this->iNSId ) ) {
				// Prevent duplicates
				$key = 'COLLECTION_' . $baseTitle;
				if ( !isset( $children[$key] ) ) {
					$children[$key] = new WebDAVPagesCollection(
						$this, $baseTitle, $this->iNSId, $this->sBasePath . $baseTitle . '/'
					);
				}
			} else {
				$title = Title::newFromRow( $row );
				if ( class_exists( 'MediaWiki\Permissions\PermissionManager' ) ) {
					// MW 1.33+
					$canRead = $mwServices->getPermissionManager()
						->userCan( 'read', $user, $title );
				} else {
					$canRead = $title->userCan( 'read' );
				}
				if ( $canRead ) {
					$children[] = new WebDAVPageFile( $this, $title );
				}
			}
		}

		return array_values( $children );
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return $this->sName;
	}

	/**
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->sBasePath;
	}

	/**
	 *
	 * @param string $name
	 * @param resource|null $data
	 * @throws Sabre\DAV\Exception\Forbidden
	 */
	public function createFile( $name, $data = null ) {
		$nameParts = explode( '.', $name );
		$maybeFileExt = $nameParts[ count( $nameParts ) - 1 ];
		if ( strtolower( $maybeFileExt ) == 'wiki' ) {
			unset( $nameParts[ count( $nameParts ) - 1 ] );
			// remove '.wiki' extension
			$name = implode( '.', $nameParts );
		}
		$title = Title::makeTitle( $this->iNSId, $this->sBasePath . $name );
		if ( $title instanceof Title === false ) {
			$msg = 'Error creating page ' . $this->sBasePath . $name . ' in NS ' . $this->iNSId;
			wfDebugLog( 'WebDAV', __CLASS__ . ': ' . $msg );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}
		$wikiPage = WikiPage::factory( $title );

		$content = ContentHandler::makeContent(
			stream_get_contents( $data ),
			$title
		);

		$status = $wikiPage->doEditContent(
			$content,
			wfMessage( 'webdav-default-edit-comment' )->plain()
		);

		if ( !$status->isOK() ) {
			$msg = 'Error #2 creating page ' . $this->sBasePath . $name . ' in NS ' . $this->iNSId;
			wfDebugLog( 'WebDAV', __CLASS__ . ': ' . $msg );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}
	}

}

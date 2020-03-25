<?php
class WebDAVPageFile extends Sabre\DAV\File {

	/**
	 *
	 * @var WebDAVNamespacesCollection
	 */
	protected $oParent = null;

	/**
	 *
	 * @var Title
	 */
	protected $oTitle = null;

	/**
	 *
	 * @var WikiPage
	 */
	protected $oWikiPage = null;

	/**
	 *
	 * @param WebDAVNamespacesCollection $parent
	 * @param Title $title
	 */
	public function __construct( $parent, $title ) {
		$this->oParent = $parent;
		$this->oTitle = $title;
		$this->oWikiPage = WikiPage::factory( $title );
	}

	/**
	 *
	 * @return Title
	 */
	public function getTitle() {
		return $this->oTitle;
	}

	/**
	 *
	 * @return WikiPage
	 */
	public function getWikiPage() {
		return $this->oWikiPage;
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		// We always get the lowest possible part
		return str_replace( ' ', '_', $this->getTitle()->getSubpageText() ) . '.wiki';
		// $winfilename = iconv( 'cp1252','utf-8', $this->oTitle->getText() );
		// return urlencode( $winfilename ).'.wiki';
	}

	/**
	 *
	 * @return string
	 */
	public function get() {
		$content = $this->getWikiPage()->getContent();
		return ContentHandler::getContentText( $content );
	}

	/**
	 *
	 * @return int
	 */
	public function getSize() {
		return $this->getTitle()->getLength();
	}

	/**
	 *
	 * @return int
	 */
	public function getLastModified() {
		return wfTimestamp( TS_UNIX, $this->getTitle()->getTouched() );
	}

	/**
	 * This is similar to WebDAVFileFile implementation. Common base class?
	 * @param string $name The WikiPage SUBPAGE TEXT! With '.wiki' extension
	 * @throws Exception\Forbidden
	 */
	public function setName( $name ) {
		// cut off '.wiki' file extension
		$trimmedName = substr( $name, 0, -5 );
		$targetTitle = Title::makeTitle(
			$this->getTitle()->getNamespace(),
			$trimmedName
		);
		$result = $this->getTitle()->moveTo( $targetTitle );
		if ( !$result === true ) {
			$msg = 'Error moving page ' . $this->getTitle()->getPrefixedText() .
				' to ' . $targetTitle->getPrefixedText();
			wfDebugLog( 'WebDAV', __CLASS__ . ': ' . $msg );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}
	}

	/**
	 *
	 * @throws Sabre\DAV\Exception\Forbidden
	 */
	public function delete() {
		$reason = wfMessage( 'webdav-default-delete-comment' )->plain();
		if ( version_compare( MW_VERSION, '1.35', '<' ) ) {
			$result = $this->getWikiPage()->doDeleteArticleReal( $reason );
		} else {
			$result = $this->getWikiPage()->doDeleteArticleReal(
				$reason,
				RequestContext::getMain()->getUser()
			);
		}
		if ( !$result->isOk() ) {
			$msg = 'Error deleting page ' . $this->getTitle()->getPrefixedText();
			wfDebugLog( 'WebDAV', __CLASS__ . ': ' . $msg );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}
	}

	/**
	 *
	 * @global WebRequest $wgRequest
	 * @param resource $data
	 */
	public function put( $data ) {
		$content = ContentHandler::makeContent(
			stream_get_contents( $data ),
			$this->getTitle()
		);
		$status = $this->getWikiPage()->doEditContent(
			$content,
			wfMessage( 'webdav-default-edit-comment' )->plain()
		);
		if ( !$status->isOK() ) {
			$msg = 'Error editing page ' . $this->getTitle()->getPrefixedText();
			wfDebugLog( 'WebDAV', __CLASS__ . ': ' . $msg );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}
	}
}

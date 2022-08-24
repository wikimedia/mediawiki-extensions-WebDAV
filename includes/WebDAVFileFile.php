<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

class WebDAVFileFile extends Sabre\DAV\File {
	/**
	 *
	 * @var File
	 */
	protected $oFile = null;

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
	 * @var User
	 */
	protected $user = null;

	/**
	 * @var MediaWikiServices
	 */
	protected $services = null;

	/**
	 *
	 * @param File $file
	 */
	public function __construct( $file ) {
		$this->oFile = $file;
		$this->oTitle = $this->oFile->getTitle();
		$this->oWikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->getTitle() );

		$this->user = RequestContext::getMain()->getUser();
		$this->services = MediaWikiServices::getInstance();
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
		return $this->oFile->getName();
	}

	/**
	 *
	 * @return int
	 */
	public function getSize() {
		return $this->oFile->getSize();
	}

	/**
	 * Unix timestamp
	 * @return int
	 */
	public function getLastModified() {
		return wfTimestamp( TS_UNIX, $this->oFile->getTimestamp() );
	}

	/**
	 *
	 * @return string
	 */
	public function getContentType() {
		return $this->oFile->getMimeType();
	}

	/**
	 *
	 * @return File
	 */
	public function getFileObj() {
		return $this->oFile;
	}

	/**
	 *
	 * @return resource
	 */
	public function get() {
		$be = $this->oFile->getRepo()->getBackend();
		$localFile = $be->getLocalReference(
			[ 'src' => $this->oFile->getPath() ]
		);

		return fopen( $localFile->getPath(), 'r' );
	}

	/**
	 *
	 * @param resource $data
	 * @return void
	 */
	public function put( $data ) {
		wfDebugLog( 'WebDAV', __CLASS__ . ': Receiving data for ' . $this->oFile->getName() );
		$tmpPath = self::makeTmpFileName( $this->oFile->getName() );
		$data = stream_get_contents( $data );
		$fp = fopen( $tmpPath, 'wb' );
		fwrite( $fp, $data );
		fclose( $fp );

		$hookContainer = $this->services->getHookContainer();
		if ( !$hookContainer->run( 'WebDAVFileFilePutBeforePublish', [ $tmpPath, $this->oFile ] ) ) {
			return;
		}

		self::publishToWiki( $tmpPath, $this->oFile->getName() );
	}

	/**
	 * This is similar to MWPageFile implementation. Common base class?
	 * @param string $name
	 * @throws Sabre\DAV\Exception\Forbidden
	 */
	public function setName( $name ) {
		$targetTitle = Title::makeTitle( NS_FILE, $name );

		$movePage = $this->services->getMovePageFactory()
			->newMovePage( $this->getTitle(), $targetTitle );
		$moveStatus = $movePage->moveIfAllowed( $this->user, null, false );
		if ( !$moveStatus->isOK() ) {
			wfDebugLog(
				'WebDAV',
				__CLASS__ . ': Error when trying to change name of "' . $this->getTitle()->getPrefixedText()
					. '" to "' . $targetTitle->getPrefixedText() . '": ' . $moveStatus->getWikiText()
			);
			throw new Sabre\DAV\Exception\Forbidden( 'Permission denied to rename file' );
		}
		wfDebugLog(
			'WebDAV',
			__CLASS__ . ': Changed name of "' . $this->getTitle()->getPrefixedText()
				. '" to "' . $targetTitle->getPrefixedText() . '"'
		);
	}

	public function delete() {
		$reason = wfMessage( 'webdav-default-delete-comment' )->plain();
		$oFile = $this->oFile;
		$result = $oFile->deleteFile( $reason, RequestContext::getMain()->getUser() );

		if ( !$result === true ) {
			wfDebugLog(
				'WebDAV',
				__CLASS__ . ': Error when trying to delete "' . $this->getTitle()->getPrefixedText() . '"'
			);
			throw new Sabre\DAV\Exception\Forbidden( 'Permission denied to delete file' );
		}
		wfDebugLog(
			'WebDAV',
			__CLASS__ . ': Deleted "' . $this->getTitle()->getPrefixedText() . '"'
		);
	}

	/**
	 * Adapted from BsFileSystemHelper::uploadLocalFile
	 *
	 * @global FileRepo $wgLocalFileRepo
	 * @param string $sourceFilePath
	 * @param string $targetFileName
	 */
	public static function publishToWiki( $sourceFilePath, $targetFileName ) {
		global $wgLocalFileRepo;
		$services = MediaWikiServices::getInstance();

		$hookContainer = $services->getHookContainer();
		if ( !$hookContainer->run( 'WebDAVPublishToWiki', [ $sourceFilePath, $targetFileName ] ) ) {
			return;
		}
		# Validate a title
		// This title object is no longer necessary, other than to verify
		// that file target name is valid
		$title = Title::makeTitleSafe( NS_FILE, $targetFileName );
		if ( !is_object( $title ) ) {
			$msg = "{$targetFileName} could not be imported; a valid Title cannot be produced";
			wfDebugLog( 'WebDAV', __CLASS__ . ": $msg" );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}

		$user = RequestContext::getMain()->getUser();
		// reload from DB!
		$user->clearInstanceCache( 'name' );
		if ( $user->getBlock() !== null ) {
			$msg = $user->getName() . " was blocked! Aborting.";
			wfDebugLog( 'WebDAV', __CLASS__ . ": $msg" );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}

		$uploadStash = new UploadStash( new LocalRepo( $wgLocalFileRepo ), $user );
		$uploadFile = $uploadStash->stashFile( $sourceFilePath, "file" );

		if ( $uploadFile === false ) {
			$msg = "Could not stash file {$targetFileName}";
			wfDebugLog( 'WebDAV', __CLASS__ . ": $msg" );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}

		$uploadFromStash = new UploadFromStash( $user, $uploadStash, $wgLocalFileRepo );
		$uploadFromStash->initialize( $uploadFile->getFileKey(), $targetFileName );
		$verifyStatus = $uploadFromStash->verifyUpload();

		if ( $verifyStatus['status'] != UploadBase::OK ) {
			$msg = "File for upload could not be verified {$targetFileName}";
			wfDebugLog( 'WebDAV', __CLASS__ . ": $msg" );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}

		$commentText = wfMessage( 'webdav-default-edit-comment' )->plain();

		$uploadStatus = $uploadFromStash->performUpload( $commentText, '', false, $user );
		$uploadFromStash->cleanupTempFile();

		if ( !$uploadStatus->isGood() && !static::isNoChangeError( $uploadStatus ) ) {
			$msg = "Could not upload {$targetFileName}. (" . $uploadStatus->getWikiText() . ")";
			wfDebugLog( 'WebDAV', __CLASS__ . ": $msg" );
			throw new Sabre\DAV\Exception\Forbidden( $msg );
		}
		$repoFile = $services->getRepoGroup()->findFile( $title );
		if ( $repoFile !== false ) {
			$repoFileTitle = $repoFile->getTitle();
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $repoFileTitle );
			$comment = CommentStoreComment::newUnsavedComment( '' );
			$updater = $page->newPageUpdater( $user );
			// "Null-edit" to invoke hookhandlers like BlueSpiceExtendedSearch
			$updater->setContent( SlotRecord::MAIN, new WikitextContent( '' ) );
			$updater->saveRevision( $comment );

			$hookContainer->run( 'WebDAVPublishToWikiDone', [ $repoFile, $sourceFilePath ] );
		}
	}

	/**
	 * @param Status $uploadStatus
	 * @return bool
	 */
	protected static function isNoChangeError( $uploadStatus ) {
		$errors = $uploadStatus->getErrors();
		$hasNoChangeError = false;
		$hasNoOtherErrors = true;
		foreach ( $errors as $error ) {
			if ( $error['message'] === 'fileexists-no-change' ) {
				$hasNoChangeError = true;
			} else {
				$hasNoOtherErrors = false;
			}
		}

		return $hasNoChangeError && $hasNoOtherErrors;
	}

	/**
	 * In combination with other extensions like 'NSFileRepoConnector' there
	 * might be invalid chars in the name (e.g. ':')
	 * @param string $name the filename on the wiki
	 * @return string a tmp filename for file system storage
	 */
	public static function makeTmpFileName( $name ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'webdav' );

		$name = preg_replace(
			$config->get( 'WebDAVInvalidFileNameCharsRegEx' ),
			'',
			$name
		);

		$tempDir = wfTempDir() . '/WebDAV';
		wfMkdirParents( $tempDir );

		return $tempDir . '/' . $name;
	}
}

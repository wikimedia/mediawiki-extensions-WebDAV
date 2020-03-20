<?php

use MediaWiki\MediaWikiServices;

class WebDAVFilesCollection extends WebDAVPagesCollection {

	/**
	 *
	 * @return \WebDAVFileFile[]
	 */
	public function getChildren() {
		// HINT: http://sabre.io/dav/character-encoding/
		$config = \MediaWiki\MediaWikiServices::getInstance()
			->getConfigFactory()->makeConfig( 'webdav' );

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'image', '*' );

		$children = [];

		$regex = $config->get( 'WebDAVInvalidFileNameCharsRegEx' );
		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$localRepo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();
		} else {
			$localRepo = RepoGroup::singleton()->getLocalRepo();
		}
		foreach ( $res as $row ) {
			if ( preg_match( $regex, $row->img_name ) !== 0 ) {
				wfDebugLog( 'WebDAV', __METHOD__ . ': Invalid characters in ' . $row->img_name );
				continue;
			}

			$file = $localRepo->newFileFromRow( $row );
			$children[] = new WebDAVFileFile( $file );
		}

		return $children;
	}

	/**
	 * Experimental: This should be faster than using the base class
	 * implementation. If this is true has to be proven...
	 * @param string $name
	 * @return \WebDAVFileFile
	 * @throws Sabre\DAV\Exception\NotFound
	 */
	public function getChild( $name ) {
		$normalName = str_replace( ' ', '_', $name );
		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectRow(
			'image',
			'*',
			[ 'img_name' => $normalName ]
		);
		if ( $row === false ) {
			$msg = 'File not found: ' . $normalName;
			wfDebugLog( 'WebDAV', __CLASS__ . ': ' . $msg );
			throw new Sabre\DAV\Exception\NotFound( $msg );
		}

		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()
				->newFileFromRow( $row );
		} else {
			$file = RepoGroup::singleton()->getLocalRepo()->newFileFromRow( $row );
		}
		return new WebDAVFileFile( $file );
	}

	/**
	 *
	 * @param string $name
	 * @param resource|null $data
	 * @return void
	 */
	public function createFile( $name, $data = null ) {
		$tmpPath = WebDAVFileFile::makeTmpFileName( $name );
		$fp = fopen( $tmpPath, 'wb' );
		fwrite( $fp, stream_get_contents( $data ) );
		fclose( $fp );

		/**
		 * This is a hack to avoid a 0 byte version when a file gets created.
		 * Some WebDAV clients first place a 0 byte file and then override it
		 * with the actual content
		 */
		if ( filesize( $tmpPath ) === 0 ) {
			return;
		}

		WebDAVFileFile::publishToWiki( $tmpPath, $name );
	}
}

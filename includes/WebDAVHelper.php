<?php

use MediaWiki\MediaWikiServices;

/**
 * Class houses static utility functions
 */
class WebDAVHelper {
	/**
	 * Gets a Filename for URL/Uri
	 *
	 * @param string $url
	 * @return string
	 */
	public static function getFilenameFromUrl( $url ) {
		$url = rtrim( $url, '/' );
		if ( !static::isFileCall( $url ) ) {
			return '';
		}

		$filename = '';
		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		if ( !$hookContainer->run( 'WebDAVGetFilenameFromUrl', [ &$filename, $url ] ) ) {
			return $filename;
		}

		$urlBits = explode( '/', $url );
		$file = array_pop( $urlBits );
		$ns = array_pop( $urlBits );

		$title = Title::newFromText( "$ns:$file" );
		if ( $title instanceof Title && $title->getNamespace() === NS_MEDIA ) {
			$title = Title::makeTitle( NS_FILE, $title->getText() );
			if ( $title->exists() ) {
				$filename = $title->getText();
			}
		}

		return $filename;
	}

	/**
	 * Determine if a URL is request for a file
	 *
	 * Not perfect, but since namespace cannot contain periods,
	 * it will do what we need it to do
	 *
	 * @param string $url
	 * @return bool
	 */
	public static function isFileCall( $url ) {
		$bits = explode( '/', $url );
		$last = array_pop( $bits );
		return strpos( $last, '.' ) !== false;
	}
}

<?php

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
		$urlBits = explode( '/', $url );
		$file = array_pop( $urlBits );

		$filename = '';
		$title = Title::makeTitle( NS_FILE, $file );
		if ( $title instanceof Title && $title->getNamespace() === NS_FILE ) {
			$filename = $title->getText();
		}
		Hooks::run( 'WebDAVGetFilenameFromUrl', [ &$filename, $url, $title ] );

		return $filename;
	}
}

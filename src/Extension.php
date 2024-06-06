<?php
/**
 * WebDAV extension
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 *
 * @author     Patric Wirth <wirth@hallowelt.com>
 * @copyright  Copyright (C) 2017 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 * @filesource
 */

namespace MediaWiki\Extension\WebDAV;

class Extension {
	public const WEBDAV_AUTH_NONE = 'none';
	public const WEBDAV_AUTH_TOKEN = 'token';
	public const WEBDAV_AUTH_MW = 'mw';

	public static function onRegistration() {
		if ( empty( $GLOBALS['wgWebDAVBaseUri'] ) ) {
			// Base URI should be $wgWebDAVServer/webdav
			$GLOBALS['wgWebDAVBaseUri'] = '/webdav/';
		}
		if ( empty( $GLOBALS['wgWebDAVUrlBaseUri'] ) ) {
			// Used for constructing links, can differ from $wgWebDAVBaseUri on some setups
			$GLOBALS['wgWebDAVUrlBaseUri'] = $GLOBALS['wgWebDAVBaseUri'];
		}
		if ( empty( $GLOBALS['wgWebDAVNamespaceCollections'] ) ) {
			// Used for constructing links, can differ from $wgWebDAVBaseUri on some setups
			$GLOBALS['wgWebDAVNamespaceCollections'][NS_MEDIA] = 'WebDAVFilesCollection';
		}

		if ( $GLOBALS['wgWebDAVServer'] === '' ) {
			$GLOBALS['wgWebDAVServer'] = $GLOBALS['wgServer'];
		}
	}
}

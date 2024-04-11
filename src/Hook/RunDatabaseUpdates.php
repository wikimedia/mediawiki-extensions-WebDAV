<?php

namespace MediaWiki\Extension\WebDAV\Hook;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RunDatabaseUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$dir = dirname( __DIR__, 2 );

		$updater->addExtensionTable(
			'webdav_locks',
			"$dir/db/$dbType/webdav.locks.sql"
		);
		$updater->addExtensionTable(
			'webdav_tokens',
			"$dir/db/$dbType/webdav.tokens.sql"
		);
		$updater->addExtensionTable(
			'webdav_static_tokens',
			"$dir/db/$dbType/webdav.static.tokens.sql"
		);
		$updater->addExtensionTable(
			'webdav_user_static_tokens',
			"$dir/db/$dbType/webdav.user.static.tokens.sql"
		);
	}
}

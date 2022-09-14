<?php

use MediaWiki\MediaWikiServices;

class WebDAVMediaWikiDBLockBackend extends \Sabre\DAV\Locks\Backend\AbstractBackend {

	/** @var MediaWikiServices */
	protected $services = null;

	public function __construct() {
		$this->services = MediaWikiServices::getInstance();
	}

	/**
	 *
	 * @param string $uri
	 * @param bool $returnChildLocks
	 * @return array
	 */
	public function getLocks( $uri, $returnChildLocks ) {
		$dbr = wfGetDB( DB_REPLICA );
		$conds = [
			'wdl_uri' => $uri
		];

		$uriParts = explode( '/', $uri );
		array_pop( $uriParts );
		$currentPath = '';
		foreach ( $uriParts as $part ) {
			if ( $currentPath ) {
				$currentPath .= '/';
			}
			$currentPath .= $part;

			$conds[] = $dbr->makeList(
				[
					'wdl_depth != 0',
					'wdl_uri' => $currentPath
				],
				LIST_AND
			);
		}

		if ( $returnChildLocks ) {
			$conds[] = 'wdl_uri ' . $dbr->buildLike(
				$uri,
				'/',
				$dbr->anyString()
			);
		}

		$res = $dbr->select(
			'webdav_locks',
			'*',
			$dbr->makeList( $conds, LIST_OR )
		);

		$lockList = [];
		$now = wfTimestamp( TS_UNIX );
		$hookContainer = $this->services->getHookContainer();
		foreach ( $res as $row ) {
			$lockExpiraton = wfTimestamp( TS_UNIX, (int)$row->wdl_created + (int)$row->wdl_timeout );
			if ( $lockExpiraton < $now ) {
				$hookContainer->run( 'WebDAVGetLocksExpired', [ $row->wdl_uri, $row->wdl_owner ] );
				$bLockDeleted = $this->deleteLock( $row->wdl_id );
				if ( $bLockDeleted ) {
					continue;
				}
			}
			$lockInfo = new \Sabre\DAV\Locks\LockInfo();
			$lockInfo->owner = (string)$row->wdl_owner;
			$lockInfo->token = $row->wdl_token;
			$lockInfo->timeout = $row->wdl_timeout;
			$lockInfo->created = $row->wdl_created;
			$lockInfo->scope = $row->wdl_scope;
			$lockInfo->depth = $row->wdl_depth;
			$lockInfo->uri   = $row->wdl_uri;
			$lockList[] = $lockInfo;
		}
		return $lockList;
	}

	/**
	 *
	 * @param string $uri
	 * @param \Sabre\DAV\Locks\LockInfo $lockInfo
	 * @return bool
	 */
	public function lock( $uri, \Sabre\DAV\Locks\LockInfo $lockInfo ) {
		$config = $this->services->getConfigFactory()->makeConfig( 'webdav' );

		$lockInfo->owner = (string)RequestContext::getMain()->getUser()->getId();
		$lockInfo->timeout = $config->get( 'WebDAVLockTimeOut' );
		$lockInfo->created = time();
		$lockInfo->uri = $uri;

		$locks = $this->getLocks( $uri, false );

		$exists = false;
		foreach ( $locks as $lock ) {
			if ( $lock->token === $lockInfo->token ) {
				$exists = true;
				break;
			}
		}

		$dbw = wfGetDB( DB_PRIMARY );

		if ( $exists ) {
			$res = $dbw->update(
				'webdav_locks',
				[
					'wdl_owner' => (int)$lockInfo->owner,
					'wdl_timeout' => $lockInfo->timeout,
					'wdl_scope' => $lockInfo->scope,
					'wdl_depth' => $lockInfo->depth,
					'wdl_uri' => $lockInfo->uri,
					'wdl_created' => $lockInfo->created
				],
				[
					'wdl_token' => $lockInfo->token
				]
			);

		} else {
			$res = $dbw->insert(
				'webdav_locks',
				[
					'wdl_owner' => (int)$lockInfo->owner,
					'wdl_timeout' => $lockInfo->timeout,
					'wdl_scope' => $lockInfo->scope,
					'wdl_depth' => $lockInfo->depth,
					'wdl_uri' => $lockInfo->uri,
					'wdl_created' => $lockInfo->created,
					'wdl_token' => $lockInfo->token
				]
			);
		}

		return $res;
	}

	/**
	 *
	 * @param string $uri
	 * @param \Sabre\DAV\Locks\LockInfo $lockInfo
	 * @return bool
	 */
	public function unlock( $uri, \Sabre\DAV\Locks\LockInfo $lockInfo ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$res = $dbw->delete(
			'webdav_locks',
			[
				'wdl_uri' => $lockInfo->uri,
				'wdl_token' => $lockInfo->token
			]
		);
		$unlockSuccess = $res !== false;
		$hookContainer = $this->services->getHookContainer();
		$hookContainer->run( 'WebDAVLocksUnlock', [ &$unlockSuccess, $lockInfo ] );
		return $unlockSuccess;
	}

	/**
	 *
	 * @param int $lockId
	 * @return bool
	 */
	protected function deleteLock( $lockId ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$res = $dbw->delete(
			'webdav_locks',
			[
				'wdl_id' => $lockId
			]
		);
		return $res !== false;
	}

}

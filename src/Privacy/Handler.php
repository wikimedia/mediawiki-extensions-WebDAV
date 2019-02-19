<?php

namespace MediaWiki\Extension\WebDAV\Privacy;

use BlueSpice\Privacy\IPrivacyHandler;

class Handler implements IPrivacyHandler {
	protected $db;

	/**
	 *
	 * @param \Database $db
	 */
	public function __construct( \Database $db ) {
		$this->db = $db;
	}

	/**
	 *
	 * @param string $oldUsername
	 * @param string $newUsername
	 * @return \Status
	 */
	public function anonymize( $oldUsername, $newUsername ) {
		return \Status::newGood();
	}

	/**
	 *
	 * @param \User $userToDelete
	 * @param \User $deletedUser
	 * @return \Status
	 */
	public function delete( \User $userToDelete, \User $deletedUser ) {
		$this->db->delete(
			'webdav_locks',
			[ 'wdl_owner' => $userToDelete->getId() ]
		);

		$this->db->delete(
			'webdav_saves',
			[ 'wds_user_id' => $userToDelete->getId() ]
		);

		$this->db->delete(
			'webdav_static_tokens',
			[ 'wdst_user_id' => $userToDelete->getId() ]
		);

		$this->db->delete(
			'webdav_tokens',
			[ 'wdt_user_id' => $userToDelete->getId() ]
		);

		$this->db->delete(
			'webdav_user_static_tokens',
			[ 'wdust_user_id' => $userToDelete->getId() ]
		);

		return \Status::newGood();
	}

	/**
	 *
	 * @param array $types
	 * @param string $format
	 * @param \User $user
	 * @return \Status
	 */
	public function exportData( array $types, $format, \User $user ) {
		// Data collected by WebDAV are either confidential and/or very temporary
		// so no point in exporting it
		return \Status::newGood( [] );
	}
}

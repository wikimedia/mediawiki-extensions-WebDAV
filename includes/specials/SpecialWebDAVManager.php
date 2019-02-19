<?php

class SpecialWebDAVManager extends \SpecialPage {

	public function __construct() {
		parent::__construct( 'WebDAVManager' );
	}

	/**
	 *
	 * @param string $par
	 * @return void
	 */
	public function execute( $par ) {
		parent::execute( $par );

		$data = new stdClass();
		\Hooks::run( 'WebDAVManager', [ $this, $this->getOutput(), $data ] );
		\Hooks::run( 'BSWebDAVManager', [ $this, $this->getOutput(), $data ] );
	}
}

<?php

use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerAwareInterface;
use Sabre\DAV\Xml\Property\LockDiscovery;

// So extensions (and other code) can check whether they're running in WebDAV mode
define( 'WEBDAV', true );
define( 'MW_ENTRY_POINT', 'webdav' );

// Initialise common code.
if ( isset( $_SERVER['MW_COMPILED'] ) ) {
	require 'core/includes/WebStart.php';
} else {
	$baseDir = dirname( $_SERVER['SCRIPT_FILENAME'] );
	chdir( $baseDir );
	require $baseDir . '/includes/WebStart.php';
}
use MediaWiki\MediaWikiServices;
use Sabre\DAV;

$services = MediaWikiServices::getInstance();

try {
	$config = $services->getConfigFactory()->makeConfig( 'webdav' );
	$logger = LoggerFactory::getInstance( 'WebDAV' );
	$context = RequestContext::getMain();

	if ( $config->get( 'WebDAVHideLockRoot' ) ) {
		// This is if Microsoft Office has issues;
		// See http://sabre.io/dav/clients/msoffice/
		LockDiscovery::$hideLockRoot = true;
		$logger->debug( 'Hiding lock root' );
	}

	$rootNode = $config->get( 'WebDAVRootNode' );
	$server = new DAV\Server( new $rootNode() );
	$server->setBaseUri( $config->get( 'WebDAVBaseUri' ) );

	$plugins = [
		// Make the endpoint browseable using a webbrowser
		'browser' => new DAV\Browser\Plugin(),
		'locks' => new DAV\Locks\Plugin(
			// Make sure users don't overwrite each others changes
			new WebDAVMediaWikiDBLockBackend()
		),
		'temporaryFileFilter' => new WebDAVTempFilePlugin( wfTempDir() )
	];

	$hookContainer = $services->getHookContainer();
	$hookContainer->run( 'WebDAVPlugins', [ $server, &$plugins ] );
	$addingPlugins = [];
	foreach ( $plugins as $pluginkey => $plugin ) {
		$addingPlugins[] = [ $pluginkey => get_class( $plugin ) ];
		if ( $plugin instanceof LoggerAwareInterface ) {
			$plugin->setLogger( $logger );
		}
		$server->addPlugin( $plugin );
	}
	$logger->debug( 'Plugins added: ', $addingPlugins );

	$logger->debug( 'Base URI: ' . $server->getBaseUri() );
	$logger->debug( 'Root node type: ' . $rootNode );
	$logger->debug( 'User: ' . $context->getUser()->getName() );
	$logger->debug( 'URL: ' . $context->getRequest()->getRequestURL() );
	$logger->debug( 'User agent: ' . $_SERVER['HTTP_USER_AGENT'] );

	$server->start();
} catch ( Exception $e ) {
	$logger->error(
		'Exception: ' . $e->getMessage(),
		[ 'exception' => $e ]
	);
	if ( $e instanceof MWException ) {
		$logger->error( $e->getText() );
	}
	# throw $e;
}

$factory = $services->getDBLoadBalancerFactory();
$factory->commitPrimaryChanges();
$factory->shutdown();

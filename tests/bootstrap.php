<?php
/**
 * PHPUnit bootstrap file
 */

// Composer autoloader must be loaded before WP_PHPUNIT__DIR will be available
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function() {
	// test set up, plugin activation, etc.
	require dirname( __DIR__ ) . '/open-transposh.php';
} );

// Start up the WP testing environment.
require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';

// Activate the plugin so activation routines (table creation, options) run
// before the first test, instead of lazily mid-run.
activate_plugin( plugin_basename( dirname( __DIR__ ) . '/open-transposh.php' ) );

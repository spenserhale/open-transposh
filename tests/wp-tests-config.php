<?php

/* Path to the WordPress codebase you'd like to test. Add a forward slash in the end. */
define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );

/*
 * Path to the theme to test with.
 *
 * The 'default' theme is symlinked from test/phpunit/data/themedir1/default into
 * the themes directory of the WordPress installation defined above.
 */
define( 'WP_DEFAULT_THEME', 'default' );

/*
 * Test with multisite enabled.
 * Alternatively, use the tests/phpunit/multisite.xml configuration file.
 */
// define( 'WP_TESTS_MULTISITE', true );

/*
 * Force known bugs to be run.
 * Tests with an associated Trac ticket that is still open are normally skipped.
 */
// define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );

// ** MySQL settings ** //

/*
 * This configuration file will be used by the copy of WordPress being tested.
 * wordpress/wp-config.php will be ignored.
 *
 * WARNING WARNING WARNING!
 * These tests will DROP ALL TABLES in the database with the prefix named below.
 * DO NOT use a production database or one that is shared with something else.
 */

/*
 * Prefer the WORDPRESS_DB_* variables provided by wp-env containers, which
 * point at the dedicated tests database. Fall back to bare-metal defaults.
 */
define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ?: 'wp_tests' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ?: 'wp' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ?: 'wp' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ?: '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 */
define('AUTH_KEY',         '`^7^f_,}zXH4nEfS85ktJZs`v_Ei+4)WPHZ)F5$`?OA~/|Xh4kK|s-9`)g>|Vc4{');
define('SECURE_AUTH_KEY',  '-w!-y6*hVzRd_qny|JQ)ykh8X[C1=d++4NHJidKs:bXZ-f^o*0el>xAGsZ!!yr=g');
define('LOGGED_IN_KEY',    'L:I~cGn|b>/w,ZF]iKLMOBfU@w#xhV,w3-+jVf6xMhC|~uYmmEta@>;AYL4o|.~x');
define('NONCE_KEY',        '6iE[t2ID.J^(0fkqYDtC1YshU14I5Jia+!&:X}6v HHYUOB7cj;a!?BNy/o1U|rq');
define('AUTH_SALT',        'q3F0}}|]]Tv}8ZF{!/x2>hR)c_z/(+e|}:Q&Ml ct5_(Kdt4]uv52+Y?CUzuxZt@');
define('SECURE_AUTH_SALT', '33~0{h |:]8hSKy:L[`(0GR`Jk Ni@x-qrwFztJC+$`Mmelr%p-]/J]0&5+JQLCv');
define('LOGGED_IN_SALT',   '3v^V+T|D.mh>^!O4?1qX(C05WG|;9f4V82bkb0u*nM9I9:#qo..@phk|$r++Tn*m');
define('NONCE_SALT',       '@M@~mug&X_HZarS8d8&u2;Tmo/8X{#BSA.6hLuB4c2BiO>w$<}+M5-!b##-z3+Ft');

$table_prefix = 'wptests_';   // Only numbers, letters, and underscores please!

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );

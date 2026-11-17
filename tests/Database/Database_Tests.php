<?php

namespace Tests\Database;

use OpenTransposh\Database;
use OpenTransposh\Plugin;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_UnitTestCase;

/**
 * Test the translation database access layer.
 */
class Database_Tests extends WP_UnitTestCase {

	/**
	 * @var Plugin the plugin instance activated in tests/bootstrap.php
	 */
	protected Plugin $plugin;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 * Note: parent::setUp() is intentionally not called, see tests/Core/Utilities_Tests.php.
	 *
	 * @access protected
	 */
	protected function setUp(): void {
		$this->plugin = Plugin::get_instance();
		// never let a human translation trigger a live backup from within the tests
		$this->plugin->options->transposh_backup_schedule = 1;
	}

	/**
	 * Tears down the fixture.
	 * Note: parent::tearDown() is intentionally not called, see setUp() above.
	 *
	 * @access protected
	 */
	protected function tearDown(): void {
		global $wpdb;
		unset( $_POST['items'], $_POST['ln0'], $_POST['sr0'], $_POST['tk0'], $_POST['tr0'] );
		$wpdb->query( 'DELETE FROM ' . $this->translation_table() );
		$wpdb->query( 'DELETE FROM ' . $this->translation_log_table() );
		wp_set_current_user( 0 );
	}

	private function translation_table(): string {
		global $wpdb;

		return $wpdb->prefix . \TRANSLATIONS_TABLE;
	}

	private function translation_log_table(): string {
		global $wpdb;

		return $wpdb->prefix . \TRANSLATIONS_LOG;
	}

	/**
	 * Issue #27 - on a fresh install the SHOW INDEX introspection used to run before
	 * dbDelta() created the tables, filling the logs with "table doesn't exist" errors.
	 */
	public function testSetupDbCreatesMissingTablesWithoutQueryErrors() {
		global $wpdb;
		$translation_table     = $this->translation_table();
		$translation_log_table = $this->translation_log_table();
		$wpdb->query( "DROP TABLE IF EXISTS {$translation_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$translation_log_table}" );

		$this->plugin->database->setup_db( true );

		$this->assertSame( $translation_table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $translation_table ) ) );
		$this->assertSame( $translation_log_table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $translation_log_table ) ) );
		$this->assertSame( '', $wpdb->last_error );
	}

	/**
	 * Issue #13b - posted parameters are guarded before they are read.
	 */
	public function testUpdateTranslationRejectsMissingPostedParams() {
		unset( $_POST['items'], $_POST['ln0'], $_POST['sr0'] );
		$_POST['tk0'] = 'Orphan token';
		$_POST['tr0'] = 'Orphan translation';

		$this->assertNull( $this->plugin->database->update_translation( 'test-translator' ) );
		$this->assertSame( '', $GLOBALS['wpdb']->last_error );
	}

	/**
	 * Issue #13b - $items is a count of posted items, garbage must not drive the loop.
	 */
	public function testUpdateTranslationRejectsNonNumericItemCount() {
		global $wpdb;
		$_POST['items'] = 'garbage not a count';
		$_POST['ln0']   = 'en';
		$_POST['sr0']   = '0';
		$_POST['tk0']   = 'Never stored';
		$_POST['tr0']   = 'Never stored either';

		$this->assertNull( $this->plugin->database->update_translation( 'test-translator' ) );
		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->translation_table()} WHERE original = %s", 'Never stored' ) )
		);
	}

	/**
	 * The update sequence behind the Ajax path must still insert, archive and overwrite.
	 */
	public function testUpdateTranslationAppliesNewHumanTranslationAndArchivesOldValue() {
		global $wpdb;
		// a pre-existing automatic translation that the update will overwrite and archive
		$wpdb->insert(
			$this->translation_table(),
			array(
				'original'     => 'Hello world',
				'lang'         => 'en',
				'translated'   => 'Hola',
				'translated_by' => 'auto',
				'source'       => 1,
			)
		);
		$_POST['items'] = '1';
		$_POST['ln0']   = 'en';
		$_POST['sr0']   = '0';
		$_POST['tk0']   = 'Hello world';
		$_POST['tr0']   = 'Hello';

		$this->assertNull( $this->plugin->database->update_translation( 'test-translator' ) );

		// the main table now holds the human translation
		$this->assertSame(
			'Hello',
			$wpdb->get_var( $wpdb->prepare( "SELECT translated FROM {$this->translation_table()} WHERE original = %s AND lang = %s", 'Hello world', 'en' ) )
		);
		$this->assertSame(
			'0',
			(string) $wpdb->get_var( $wpdb->prepare( "SELECT source FROM {$this->translation_table()} WHERE original = %s AND lang = %s", 'Hello world', 'en' ) )
		);
		// the overwritten automatic translation was copied to the log first
		$this->assertSame(
			'Hola',
			$wpdb->get_var( $wpdb->prepare( "SELECT translated FROM {$this->translation_log_table()} WHERE original = %s AND lang = %s", 'Hello world', 'en' ) )
		);
		$this->assertSame( '', $wpdb->last_error );
	}

	/**
	 * Issue #29 - an empty or malformed timestamp must never reach a DELETE query.
	 *
	 * @param string $bad_timestamp timestamp as posted by a client
	 */
	#[DataProvider( 'invalidTimestampProvider' )]
	public function testDeleteHistoryRejectsBadTimestamps( string $bad_timestamp ) {
		global $wpdb;
		$this->seed_log_row( 'Delete guard original', 'Guarded', '2024-01-02 03:04:05' );
		$this->seed_log_row( 'Delete guard second', 'Also guarded', '2024-03-04 05:06:07' );

		$this->assertFalse( $this->plugin->database->del_translation_history( 'Delete guard original', 'en', $bad_timestamp ) );

		// no wildcard delete happened, both history rows are still there
		$this->assertSame(
			2,
			(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->translation_log_table()} WHERE original LIKE 'Delete guard%'" )
		);
		$this->assertSame( '', $wpdb->last_error );
	}

	public static function invalidTimestampProvider(): array {
		return array(
			'empty string'      => array( '' ),
			'whitespace only'   => array( '   ' ),
			'not a date'        => array( 'not-a-timestamp' ),
			'date without time' => array( '2024-01-02' ),
			'numeric string'    => array( '1234567890' ),
			'sql injection'     => array( "' OR '1'='1" ),
		);
	}

	/**
	 * Issue #29 - a well-formed timestamp still deletes exactly its own history row.
	 */
	public function testDeleteHistoryRemovesLogRowForValidTimestamp() {
		global $wpdb;
		$admin = self::factory()->user->create_and_get( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin->ID );
		$this->seed_log_row( 'Delete me please', 'Borrame', '2023-05-06 07:08:09' );

		$this->assertTrue( $this->plugin->database->del_translation_history( 'Delete me please', 'en', '2023-05-06 07:08:09' ) );

		$this->assertSame(
			0,
			(int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->translation_log_table()} WHERE original = %s", 'Delete me please' ) )
		);
		$this->assertSame( '', $wpdb->last_error );
	}

	/**
	 * Issue #28 - the history query joins the shared users table, so it works unchanged.
	 */
	public function testGetTranslationHistoryJoinsUsersTableWithoutError() {
		global $wpdb;
		$admin = self::factory()->user->create_and_get( array( 'role' => 'administrator', 'user_login' => 'historyadmin' ) );
		wp_set_current_user( $admin->ID );
		$wpdb->insert(
			$this->translation_log_table(),
			array(
				'original'     => 'Join me',
				'lang'         => 'en',
				'translated'   => 'Junte',
				'translated_by' => (string) $admin->ID,
				'source'       => 0,
				'timestamp'    => '2023-01-02 03:04:05',
			)
		);

		$rows = $this->plugin->database->get_translation_history( 'Join me', 'en' );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'historyadmin', $rows[0]->user_login );
		$this->assertTrue( isset( $rows[0]->can_delete ) );
		$this->assertSame( '', $wpdb->last_error );
	}

	/**
	 * Issue #32 - the memcached connection is deferred to first cache use: plain
	 * construction never attempts it, and without the extension the cache methods
	 * stay safe no-ops instead of erroring on a failed connection attempt.
	 */
	public function testCacheOperationsStaySafeWhenMemcacheUnavailable() {
		$database = new Database( $this->plugin );

		$attempted = new \ReflectionProperty( Database::class, 'memcache_connect_attempted' );
		$attempted->setAccessible( true );
		$this->assertFalse( $attempted->getValue( $database ), 'construction must not attempt the memcached connection' );

		$this->assertNull( $database->cache_fetch( 'never-stored-key', 'en' ) );
		$database->cache_store( 'never-stored-key', 'en', array( 'one', 'two' ), 60 );
		$database->cache_delete( 'never-stored-key', 'en' );
		$database->cache_clean();
		$this->addToAssertionCount( 1 );
	}

	private function seed_log_row( string $original, string $translated, string $timestamp ): void {
		global $wpdb;
		$wpdb->insert(
			$this->translation_log_table(),
			array(
				'original'     => $original,
				'lang'         => 'en',
				'translated'   => $translated,
				'translated_by' => '127.0.0.1',
				'source'       => 0,
				'timestamp'    => $timestamp,
			)
		);
	}

}

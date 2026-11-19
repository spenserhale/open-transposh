<?php

namespace Tests\Plugin;

use OpenTransposh\Plugin;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_UnitTestCase;

/**
 * Tests for Plugin helper methods: google token generation and url splitting.
 */
class Plugin_Tests extends WP_UnitTestCase {

	/**
	 * Like the other tests in this suite, no parent::setUp() - the wp-phpunit
	 * fixture machinery is PHPUnit 12 incompatible and these tests need no fixtures.
	 */
	protected function setUp(): void {
	}

	/**
	 * @return Plugin the shared plugin instance created on plugin load
	 */
	private function get_plugin(): Plugin {
		return Plugin::get_instance();
	}

	/**
	 * Runs a callback with all errors captured instead of reported
	 *
	 * @param callable $callback
	 *
	 * @return array [ mixed result, string[] error messages ]
	 */
	private function call_capturing_errors( callable $callback ): array {
		$errors = [];
		set_error_handler( static function ( int $errno, string $errstr ) use ( &$errors ): bool {
			$errors[] = $errstr;

			return true;
		}, E_ALL );
		try {
			$result = $callback();
		} finally {
			restore_error_handler();
		}

		return [ $result, $errors ];
	}

	public static function google_token_seeds_provider(): iterable {
		// Goldens generated from the pre-fix implicit-truncation expression, cross-checked on PHP 8.4.
		yield 'transposh seed' => [ 'somestring', '406448.272554134', '705125.848341' ];
		yield 'synthetic seed' => [ 'somestring', '123456.78901234', '542033.632593' ];
	}

	#[DataProvider( 'google_token_seeds_provider' )]
	public function testGoogleTokenMatchesIntegerMathWithoutDeprecation( string $input, string $seed, string $expected_token ) {
		$plugin = $this->get_plugin();

		[ $token, $errors ] = $this->call_capturing_errors( static fn(): string => $plugin->iq( $input, $seed ) );

		$this->assertEmpty( $errors, 'Expected no deprecations, got: ' . implode( ', ', $errors ) );
		$this->assertSame( $expected_token, $token );
	}

	public function testSplitUrlHandlesPathlessUrlWithoutWarning() {
		$plugin = $this->get_plugin();
		$home   = get_option( 'home' );

		[ $parts, $errors ] = $this->call_capturing_errors( static fn(): array => $plugin->split_url( $home ) );

		$this->assertEmpty( $errors, 'Expected no deprecations or warnings, got: ' . implode( ', ', $errors ) );
		$this->assertSame( [], $parts );
	}

	public function testSplitUrlStillSplitsUrlWithPath() {
		$plugin = $this->get_plugin();
		$home   = get_option( 'home' );

		[ $parts, $errors ] = $this->call_capturing_errors( static fn(): array => $plugin->split_url( $home . '/about/team-work' ) );

		$this->assertEmpty( $errors, 'Expected no deprecations or warnings, got: ' . implode( ', ', $errors ) );
		$this->assertContains( 'about', $parts );
		$this->assertContains( 'team-work', $parts );
		$this->assertContains( 'team work', $parts );
	}
}

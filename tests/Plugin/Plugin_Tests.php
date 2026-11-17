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
		yield 'transposh seed' => [ 'somestring', '406448.272554134' ];
		yield 'synthetic seed' => [ 'somestring', '123456.78901234' ];
	}

	#[DataProvider( 'google_token_seeds_provider' )]
	public function testGoogleTokenMatchesIntegerMathWithoutDeprecation( string $input, string $seed ) {
		$plugin = $this->get_plugin();

		[ $token, $errors ] = $this->call_capturing_errors( static fn(): string => $plugin->iq( $input, $seed ) );

		$this->assertEmpty( $errors, 'Expected no deprecations, got: ' . implode( ', ', $errors ) );
		$this->assertSame( $this->expected_google_token( $input, $seed ), $token );
	}

	/**
	 * Replicates the token algorithm with integer-only math, independent of Plugin::iq()
	 *
	 * @param string $input
	 * @param string $seed dotted seed string, e.g. "406448.272554134"
	 *
	 * @return string the expected token
	 */
	private function expected_google_token( string $input, string $seed ): string {
		[ $seed_part, $xor_part ] = explode( '.', $seed );
		$value = (int) $seed_part;
		for ( $i = 0, $length = strlen( $input ); $i < $length; $i ++ ) {
			$value += ord( $input[ $i ] );
			$value = $this->token_helper( $value, '+-a^+6' );
		}
		$value = $this->token_helper( $value, '+-3^+b+-f' );
		$value ^= (int) $xor_part;
		if ( 0 > $value ) {
			$value = $value & 2147483647 + 2147483648;
		}
		$x = $value % 1000000;

		return $x . '.' . ( $x ^ (int) $seed_part );
	}

	/**
	 * Integer-only copy of the token chunk mixer used by Plugin::hq()
	 */
	private function token_helper( int $a, string $chunk ): int {
		for ( $offset = 0; $offset < strlen( $chunk ) - 2; $offset += 3 ) {
			$b = $chunk[ $offset + 2 ];
			$b = ( $b >= 'a' ) ? ord( $b ) - 87 : (int) $b;
			$b = ( '+' === $chunk[ $offset + 1 ] ) ? $this->token_zfrs( $a, $b ) : $a << $b;
			$a = ( '+' === $chunk[ $offset ] ) ? $a + $b & 4294967295 : $a ^ $b;
		}

		return $a;
	}

	/**
	 * Integer-only copy of Plugin::_bitwise_zfrs()
	 */
	private function token_zfrs( int $a, int $b ): int {
		if ( 0 === $b ) {
			return $a;
		}

		return ( $a >> $b ) & ~ ( 1 << ( 8 * PHP_INT_SIZE - 1 ) >> ( $b - 1 ) );
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

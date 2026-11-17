<?php

namespace Tests\Widgets;

use OpenTransposh\Plugin;
use OpenTransposh\Widgets\Plugin_Widget;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_UnitTestCase;

/**
 * Tests for the sidebar widget asset collectors (empty widget_file fallback) and
 * the output escaping performed by the bundled widget templates.
 */
class Plugin_Widget_Tests extends WP_UnitTestCase {

	/**
	 * @return Plugin_Widget a fresh widget wired to the loaded plugin instance
	 */
	private function get_widget(): Plugin_Widget {
		return new Plugin_Widget();
	}

	/**
	 * Issue #21 - the widget name and description are translated after WordPress
	 * localization is up, not in the constructor (which runs before init).
	 */
	public function test_widget_strings_translate_after_localization() {
		$widget = $this->get_widget();
		$this->assertSame( 'Open Transposh', $widget->name, 'constructor must not request a translation' );
		$widget->translate_widget_strings();
		$this->assertSame( 'Open Transposh', $widget->name );
		$this->assertNotEmpty( $widget->widget_options['description'] );
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

	/**
	 * Registers a fake active transposh widget instance backed by saved settings
	 *
	 * @param array $instance saved widget settings as returned by get_settings()
	 * @param int  $number   widget instance number used in the fake widget id
	 */
	private function set_fake_active_widget( array $instance, int $number = 2 ): void {
		update_option( 'widget_transposh', array( $number => $instance ) );

		$this->saved_registered_widgets  = $GLOBALS['wp_registered_widgets'] ?? array();
		$this->saved_registered_sidebars = $GLOBALS['wp_registered_sidebars'] ?? array();
		$this->fake_widget_globals_saved = true;

		$GLOBALS['wp_registered_widgets'][ "transposh-$number" ] = array( 'id' => "transposh-$number" );
		wp_set_sidebars_widgets( array( 'sidebar-1' => array( "transposh-$number" ) ) );
	}

	/** @var array */
	private array $saved_registered_widgets = array();

	/** @var array */
	private array $saved_registered_sidebars = array();

	/**
	 * Like the other tests in this suite, no parent::setUp() - the wp-phpunit
	 * fixture machinery is PHPUnit 12 incompatible and these tests need no fixtures.
	 */
	protected function setUp(): void {
	}

	/** @var bool Whether set_fake_active_widget() saved the current globals */
	private bool $fake_widget_globals_saved = false;

	public function tearDown(): void {
		if ( $this->fake_widget_globals_saved ) {
			$GLOBALS['wp_registered_widgets']  = $this->saved_registered_widgets;
			$GLOBALS['wp_registered_sidebars'] = $this->saved_registered_sidebars;
			$this->fake_widget_globals_saved   = false;
		}
		delete_option( 'widget_transposh' );
		delete_option( 'sidebars_widgets' );
	}

	/**
	 * @return array Clean language record matching what create_widget_args() produces
	 */
	private static function clean_lang_record(): array {
		return array(
			'lang'     => 'English',
			'langorig' => 'English',
			'flag'     => 'en',
			'isocode'  => 'en',
			'url'      => 'https://example.com/en/',
			'active'   => true,
		);
	}

	/**
	 * @return array Poisoned language record that must come out escaped
	 */
	private static function poisoned_lang_record(): array {
		return array(
			'lang'     => 'English<script>',
			'langorig' => '<b>English</b>',
			'flag'     => 'en" onload="alert(1)',
			'isocode'  => 'en',
			'url'      => 'https://example.com/en/?a=1&b=" onx="y',
			'active'   => true,
		);
	}

	public function testCollectorsFallBackToDefaultWidgetOnEmptySavedWidgetFile() {
		$widget = $this->get_widget();
		$this->set_fake_active_widget( array( 'title' => 'Translation', 'widget_file' => '' ) );

		[ , $errors ] = $this->call_capturing_errors( static function () use ( $widget ): void {
			$widget->add_transposh_widget_css();
			$widget->add_transposh_widget_js();
		} );

		$this->assertEmpty( $errors, 'Empty widget_file must not raise warnings, got: ' . implode( ', ', $errors ) );
		$this->assertTrue( class_exists( 'tpw_default' ), 'The default widget fallback should have been loaded' );
	}

	public function testCollectorsFallBackToDefaultWidgetOnMissingWidgetFileKey() {
		$widget = $this->get_widget();
		$this->set_fake_active_widget( array( 'title' => 'Translation' ) );

		[ , $errors ] = $this->call_capturing_errors( static function () use ( $widget ): void {
			$widget->add_transposh_widget_css();
			$widget->add_transposh_widget_js();
		} );

		$this->assertEmpty( $errors, 'Missing widget_file key must not raise warnings, got: ' . implode( ', ', $errors ) );
	}

	public function testCollectorsKeepNonEmptyWidgetFileUntouched() {
		$widget = $this->get_widget();
		$this->set_fake_active_widget( array( 'title' => 'Translation', 'widget_file' => 'flags/tpw_flags_css.php' ) );

		[ , $errors ] = $this->call_capturing_errors( static function () use ( $widget ): void {
			$widget->add_transposh_widget_css();
			$widget->add_transposh_widget_js();
		} );

		$this->assertEmpty( $errors, 'Non-empty widget_file must not raise warnings, got: ' . implode( ', ', $errors ) );
		$this->assertTrue( class_exists( 'tpw_flags_css' ), 'The selected subwidget should have been loaded' );
	}

	/**
	 * @return array [ template class, widget file path ] pairs to exercise
	 */
	public static function widget_template_classes_provider(): iterable {
		$widgets_dir = dirname( __DIR__, 2 ) . '/widgets/';

		yield 'default' => [ 'tpw_default', $widgets_dir . 'default/tpw_default.php' ];
		yield 'flags' => [ 'tpw_flags', $widgets_dir . 'flags/tpw_flags.php' ];
		yield 'flags css' => [ 'tpw_flags_css', $widgets_dir . 'flags/tpw_flags_css.php' ];
		yield 'list with flags css' => [ 'tpw_list_with_flags_css', $widgets_dir . 'flagslist/tpw_list_with_flags_css.php' ];
		yield 'select2' => [ 'tpw_select2', $widgets_dir . 'select2/tpw_select2.php' ];
	}

	/**
	 * Runs a callback with the $my_transposh_plugin global seeded for widget templates
	 *
	 * The tests bootstrap requires the plugin inside a closure, so its globals never
	 * reach global scope; widget templates expect the global to exist.
	 *
	 * @param callable $callback
	 *
	 * @return array [ mixed result, string[] error messages ]
	 */
	private function with_transposh_plugin_global( callable $callback ): array {
		$saved_plugin_global                 = $GLOBALS['my_transposh_plugin'] ?? null;
		$GLOBALS['my_transposh_plugin']      = $saved_plugin_global ?? Plugin::get_instance();

		try {
			return $this->call_capturing_errors( $callback );
		} finally {
			if ( null === $saved_plugin_global ) {
				unset( $GLOBALS['my_transposh_plugin'] );
			} else {
				$GLOBALS['my_transposh_plugin'] = $saved_plugin_global;
			}
		}
	}

	#[DataProvider( 'widget_template_classes_provider' )]
	public function testWidgetTemplatesEscapePoisonedLanguageRecords( string $template_class, string $template_file ) {
		require_once $template_file;

		[ $output, $errors ] = $this->with_transposh_plugin_global( static function () use ( $template_class ): string {
			ob_start();
			$template_class::tp_widget_do( array( self::poisoned_lang_record() ) );

			return (string) ob_get_clean();
		} );

		$this->assertEmpty( $errors, 'Template rendering must not raise warnings, got: ' . implode( ', ', $errors ) );
		$this->assertStringNotContainsString( '<b>', $output, 'Visible text must be entity-escaped' );
		$this->assertStringNotContainsString( '<script>', $output, 'Visible text must be entity-escaped' );
		$this->assertStringNotContainsString( ' onload="', $output, 'Attribute breakout via flag must be escaped' );
		$this->assertStringNotContainsString( ' onx="', $output, 'Attribute breakout via url must be escaped' );
		$this->assertStringNotContainsString( '" onx="', $output, 'Raw quotes must not survive inside attributes' );
		$this->assertStringContainsString( '&lt;b&gt;English&lt;/b&gt;', $output, 'Escaped visible text is expected' );
	}

	#[DataProvider( 'widget_template_classes_provider' )]
	public function testWidgetTemplatesKeepCleanAsciiOutputByteIdentical( string $template_class, string $template_file ) {
		require_once $template_file;

		$no_translate_class = constant( 'NO_TRANSLATE_CLASS' );
		$plugin_path = untrailingslashit( parse_url( Plugin::get_instance()->transposh_plugin_url, PHP_URL_PATH ) );
		$url                = 'https://example.com/en/';

		$expected = array(
			'tpw_default' => '<span class="' . $no_translate_class . '"><select name="lang" onchange="document.location.href=this.options[this.selectedIndex].value;"><option value="' . $url . '" selected="selected">English</option></select><br/></span>',
			'tpw_flags' => '<div class="' . $no_translate_class . ' transposh_flags" ><a href="' . $url . '" class="tr_active"><img src="' . $plugin_path . '/img/flags/en.png" title="English" alt="English"/></a></div>',
			'tpw_flags_css' => '<div class="' . $no_translate_class . ' transposh_flags" ><a href="' . $url . '" class="tr_active"><span title="English" class="trf trf-en"></span></a></div>',
			'tpw_list_with_flags_css' => '<div class="' . $no_translate_class . ' transposh_flags" ><a href="' . $url . '" class="tr_active"><span title="English" class="trf trf-en"></span></a><a href="' . $url . '" class="tr_active">English</a><br/></div>',
			'tpw_select2' => '<span class="' . $no_translate_class . '"><select style="width:100%" name="lang" class="tp_lang2" onchange="document.location.href=this.options[this.selectedIndex].value;"><option value="' . $url . '" data-flag="en" data-lang="English" selected="selected">English</option></select><br/></span>',
		);

		[ $output, $errors ] = $this->with_transposh_plugin_global( static function () use ( $template_class ): string {
			ob_start();
			$template_class::tp_widget_do( array( self::clean_lang_record() ) );

			return (string) ob_get_clean();
		} );

		$this->assertEmpty( $errors, 'Template rendering must not raise warnings, got: ' . implode( ', ', $errors ) );
		$this->assertSame( $expected[ $template_class ], $output, 'Clean ASCII output must stay byte-identical' );
	}
}

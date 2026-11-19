<?php

namespace OpenTransposh;

use OpenTransposh;
use OpenTransposh\Core\Constants;
use OpenTransposh\Logging\LogService;

/**
 * Used properties for code completion - we'll try to keep them in same order as admin screens
 *
 * Language tab
 * @property string $default_language      Option defining the default language
 * @property Option $default_language_o
 * @property string $viewable_languages    Option defining the list of currently viewable languages
 * @property Option $viewable_languages_o
 * @property string $sorted_languages      Option defining the ordered list of languages @since 0.3.9
 * @property Option $sorted_languages_o
 *
 * Settings
 * @property bool $enable_default_translate      Option to enable/disable default language translation
 * @property Option $enable_default_translate_o
 * @property bool $enable_search_translate       Option to enable/disable default language translation @since 0.3.6
 * @property Option $enable_search_translate_o
 * @property bool $transposh_gettext_integration Make the gettext interface optional (@since 0.6.4)
 * @property Option $transposh_gettext_integration_o
 * @property bool $transposh_locale_override     Allow override for default locale (@since 0.7.5)
 * @property Option $transposh_locale_override_o
 *
 * @property bool $enable_permalinks             Option to enable/disable rewrite of permalinks
 * @property Option $enable_permalinks_o
 * @property bool $enable_footer_scripts         Option to enable/disable footer scripts (2.8 and up)
 * @property Option $enable_footer_scripts_o
 * @property bool $enable_detect_redirect        Option to enable detect and redirect language @since 0.3.8
 * @property Option $enable_detect_redirect_o
 * @property bool $enable_geoip_redirect         Option to enable language redirection based on geoip detection @since 1.0.2
 * @property Option $enable_geoip_redirect_o
 * @property bool $transposh_collect_stats       Should I allow collecting of anonymous stats (@since 0.7.6)
 * @property Option $transposh_collect_stats_o
 * @property string $mail_to                    Option defining recipient (Admin if empty) (@since 1.0.3)
 * @property Option $mail_to_o
 * @property bool $mail_ontranslate           Should I send mail immediately on human translation (@since 1.0.3)
 * @property Option $mail_ontranslate_o
 * //** FULL VERSION
 * @property bool $mail_ontranslate_buffer    Should I buffer immediate human translation (@since 1.0.3)
 * @property Option $mail_ontranslate_buffer_o
 * @property bool $mail_digest                Should I send a daily digest of translations today (@since 1.0.3)
 * @property Option $mail_digest_o
 * @property bool $mail_ignore_admin          Ignore translations made by the admin (@since 1.0.3)
 * @property Option $mail_ignore_admin_o
 * //** FULLSTOP
 *
 * @property int $transposh_backup_schedule     Stores the schedule for the backup service, 0-none, 1-daily, 2-live (backup @since 0.5.0)
 * @property Option $transposh_backup_schedule_o
 * @property string $transposh_key                 Stores the site key to transposh services (backup @since 0.5.0)
 * @property Option $transposh_key_o
 * //** FULL VERSION
 * @property bool $enable_superproxy             Enable superproxy
 * @property Option $enable_superproxy_o
 * @property string $superproxy_key                Stores the superproxy key
 * @property Option $superproxy_key_o
 * @property string $superproxy_ips                Stores the site allow proxy ips
 * @property Option $superproxy_ips_o
 * //** FULLSTOP
 *
 *  Engines
 *
 * @property bool $enable_autotranslate          Option to enable/disable auto translation
 * @property Option $enable_autotranslate_o
 * @property bool $enable_autoposttranslate      Option to enable/disable auto translation of posts
 * @property Option $enable_autoposttranslate_o
 * @property string $msn_key                       Option to store the msn API key
 * @property Option $msn_key_o
 * @property string $google_key                    Option to store the Google API key
 * @property Option $google_key_o
 * @property string $yandex_key                    Option to store the Yandex API key
 * @property Option $yandex_key_o
 * @property string $preferred_translators         Option to store translator preference @since 0.4.2 (changed to string and plural @since 0.9.8)
 * @property Option $preferred_translators_o
 * @property string $oht_id                        Option to store the oht ID
 * @property Option $oht_id_o
 * @property string $oht_key                       Option to store the oht key;
 * @property Option $oht_key_o
 *
 * Widget
 *
 * @property bool $widget_progressbar            Option allowing progress bar display
 * @property Option $widget_progressbar_o
 * @property bool $widget_allow_set_deflang      Allows user to set his default language per #63 @since 0.3.8
 * @property Option $widget_allow_set_deflang_o
 * @property string $widget_theme                  Allows theming of the progressbar and edit window @since 0.7.0
 * @property Option $widget_theme_o
 *
 * Advanced
 *
 * @property bool $enable_url_translate          Option to enable/disable url translation @since 0.5.3
 * @property Option $enable_url_translate_o
 * @property bool $dont_add_rel_alternate        Option to disable the rel=alternate adding to the page @since 0.9.2
 * @property Option $dont_add_rel_alternate_o
 * //** FULL VERSION
 * @property bool $full_rel_alternate            Option to create fully qualified rel=alternate @since 1.0.1
 * @property Option $full_rel_alternate_o
 * //** FULLSTOP
 * @property bool $parser_dont_break_puncts      Option to allow punctuations such as , . ( not to break @since 0.9.0
 * @property Option $parser_dont_break_puncts_o
 * @property bool $parser_dont_break_numbers     Option to allow numbers not to break @since 0.9.0
 * @property Option $parser_dont_break_numbers_o
 * @property bool $parser_dont_break_entities    Option to allow html entities not to break @since 0.9.0
 * @property Option $parser_dont_break_entities_o
 * @property bool $debug_enable Option to enable debug
 * @property Option $debug_enable_o
 * @property int $debug_loglevel Option holding the level of logging
 * @property Option $debug_loglevel_o
 * @property string $debug_logfile Option holding a filename to store debugging into
 * @property Option $debug_logfile_o
 * @property string $debug_remoteip Option that limits remote firePhp debug to a certain IP
 * @property Option $debug_remoteip_o
 *
 * Hidden
 *
 * @property Option $transposh_admin_hide_warnings Stores hidden warnings (@since 0.7.6)
 * //** FULL VERSION
 * @property Option $transposh_last_mail_digest Stores date of last digest (@since 1.0.3)
 * //** FULLSTOP
 *
 */
class Plugin_Options {

	/** @var array storing all our options */
	private $options = array();

	/** @var bool set to true if any option was changed */
	private $changed = false;
	private $vars = array();

	public function set_default_option_value( $option, $value = '' ) {
		if ( ! isset( $this->options[ $option ] ) ) {
			$this->options[ $option ] = $value;
		}
	}

	// private $vars array() = (1,2,3);

	public function register_option( $name, $type, $default_value = '' ) {
		if ( ! isset( $this->options[ $name ] ) ) {
			$this->options[ $name ] = $default_value;
		}
		// can't log...     OpenTransposh\Logging\Logger($name . ' ' . $this->options[$name]);
		$this->vars[ $name ] = new OpenTransposh\Option( $name, $this->options[ $name ], $type );
	}

	public function __get( $name ) {
		if ( str_ends_with( $name, "_o" ) ) {
			return $this->vars[ substr( $name, 0, - 2 ) ];
		}

		// can't!? OpenTransposh\Logging\Logger($this->vars[$name]->get_value(), 5);
		return $this->vars[ $name ]->get_value();
	}

	public function __set( $name, $value ) {
		if ( $value == TP_FROM_POST ) {
			$value = $_POST[ $name ] ?? '';
		}

		if ( TP_OPT_BOOLEAN == $this->vars[ $name ]->get_type() ) {
			$value = ( $value ) ? 1 : 0;
		}

		if ( $this->vars[ $name ]->get_value() !== $value ) {
			LogService::legacy_log( "option '$name' value set: $value" );
			$this->vars[ $name ]->set_value( $value );
			$this->changed = true;
		}
	}

	public function __construct() {

		// can't      OpenTransposh\Logging\Logger("creating options");
		// load them here
		$this->options = get_option( TRANSPOSH_OPTIONS );
		// get_option() returns false when the option does not exist yet (fresh install).
		if ( ! is_array( $this->options ) ) {
			$this->options = array();
		}
//        OpenTransposh\Logging\Logger($this->options);

		$this->register_option( 'default_language', TP_OPT_STRING ); // default?
		$this->register_option( 'viewable_languages', TP_OPT_STRING );
		$this->register_option( 'sorted_languages', TP_OPT_STRING );

		$this->register_option( 'enable_default_translate', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'enable_search_translate', TP_OPT_BOOLEAN, 1 );
		$this->register_option( 'transposh_gettext_integration', TP_OPT_BOOLEAN, 1 );
		$this->register_option( 'transposh_locale_override', TP_OPT_BOOLEAN, 1 );

		$this->register_option( 'enable_permalinks', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'enable_footer_scripts', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'enable_detect_redirect', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'enable_geoip_redirect', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'transposh_collect_stats', TP_OPT_BOOLEAN, 1 );

		$this->register_option( 'mail_to', TP_OPT_STRING );
		$this->register_option( 'mail_ontranslate', TP_OPT_BOOLEAN, 0 );
		//** FULL VERSION
		$this->register_option( 'mail_ontranslate_buffer', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'mail_digest', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'mail_ignore_admin', TP_OPT_BOOLEAN, 0 );
		//** FULLSTOP

		$this->register_option( 'transposh_backup_schedule', TP_OPT_OTHER, 2 );
		$this->register_option( 'transposh_key', TP_OPT_STRING );
		$this->register_option( 'enable_superproxy', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'superproxy_key', TP_OPT_STRING );
		$this->register_option( 'superproxy_ips', TP_OPT_STRING );

		$this->register_option( 'enable_autotranslate', TP_OPT_BOOLEAN, 1 );
		$this->register_option( 'enable_autoposttranslate', TP_OPT_BOOLEAN, 1 );
		$this->register_option( 'msn_key', TP_OPT_STRING );
		$this->register_option( 'google_key', TP_OPT_STRING );
		$this->register_option( 'yandex_key', TP_OPT_STRING );
		$this->register_option( 'baidu_key', TP_OPT_STRING );
		$this->register_option( 'preferred_translators', TP_OPT_STRING, 'g,b,y,a,u' );
		$this->register_option( 'oht_id', TP_OPT_STRING );
		$this->register_option( 'oht_key', TP_OPT_STRING );


		$this->register_option( 'widget_progressbar', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'widget_allow_set_deflang', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'widget_theme', TP_OPT_STRING, 'ui-lightness' );
		$this->register_option( 'enable_url_translate', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'dont_add_rel_alternate', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'full_rel_alternate', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'parser_dont_break_puncts', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'parser_dont_break_numbers', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'parser_dont_break_entities', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'debug_enable', TP_OPT_BOOLEAN, 0 );
		$this->register_option( 'debug_loglevel', TP_OPT_OTHER, 3 );
		$this->register_option( 'debug_logfile', TP_OPT_STRING, '' );
		$this->register_option( 'debug_remoteip', TP_OPT_IP, '' );


		$this->register_option( 'transposh_admin_hide_warnings', TP_OPT_OTHER );
		//** FULL VERSION
		$this->register_option( 'transposh_last_mail_digest', TP_OPT_OTHER );
		//** FULLSTOP


		// Fix default language if needed, only done once now, and since this was being done constantly, we gain
		//OpenTransposh\Logging\Logger($this->default_language->get_value());

		if ( ! isset( Constants::$languages[ $this->default_language ] ) ) {
			if ( defined( 'WPLANG' ) && isset( Constants::$languages[ WPLANG ] ) ) {
				$this->default_language = WPLANG;
			} else {
				$this->default_language = "en";
			}
		}

		// can't log...   OpenTransposh\Logging\Logger($this->options, 4);
	}

	/**
	 * Get a user sorted language list
	 * @return array sorted list of languages, pointing to names and flags
	 * @since 0.3.9
	 */
	public function get_sorted_langs() {
		if ( $this->sorted_languages ) {
			LogService::legacy_log( $this->sorted_languages, 5 );

			return array_merge( array_flip( explode( ",", $this->sorted_languages ) ), Constants::$languages );
		}

		return Constants::$languages;
	}

	/**
	 * Get a user sorted translation engines list
	 * @return array sorted list of translation engines
	 * @since 0.9.8
	 */
	public function get_sorted_engines() {
		if ( $this->preferred_translators ) {
			LogService::legacy_log( $this->preferred_translators, 3 );

			return array_merge( array_flip( explode( ",", $this->preferred_translators ) ), Constants::$engines );
		}

		return Constants::$engines;
	}

	public function get_transposh_admin_hide_warning( $id ) {
		return str_contains( $this->transposh_admin_hide_warnings, $id . ',' );
	}

	public function set_transposh_admin_hide_warning( $id ) {
		if ( ! $this->get_transposh_admin_hide_warning( $id ) ) {
			$this->transposh_admin_hide_warnings = $this->transposh_admin_hide_warnings . $id . ',';
		}
	}

	/**
	 * Updates options at the wordpress options table if there was a change
	 */
	public function update_options() {
		if ( $this->changed ) {
			foreach ( $this->vars as $name => $var ) {
				$this->options[ $name ] = $var->get_value();
			}
			update_option( TRANSPOSH_OPTIONS, $this->options );
			$this->changed = false;
		} else {
			LogService::legacy_log( "no changes and no updates done", 3 );
		}
	}

	/**
	 * Resets all options except keys
	 */
	public function reset_options() {
		$this->options = array();
		foreach ( array( 'msn_key', 'google_key', 'oht_id', 'oht_key', 'transposh_key' ) as $key ) {
			$this->options[ $key ] = $this->vars[ $key ]->get_value();
		}
		update_option( TRANSPOSH_OPTIONS, $this->options );
	}

	/**
	 * Determine if the given language code is the default language
	 *
	 * @param string $language
	 *
	 * @return bool Is this the default language?
	 */
	public function is_default_language( $language ) { // XXXX
		return ( $this->default_language == $language || '' == $language );
	}

	/**
	 * Determine if the given language in on the list of active languages
	 * @return bool Is this language viewable?
	 */
	public function is_active_language( $language ) {
		if ( $this->is_default_language( $language ) ) {
			return true;
		}

		return ( str_contains( $this->viewable_languages . ',', $language . ',' ) );
	}

}

<?php

namespace OpenTransposh;

use OpenTransposh\Core\{Constants, Parser, Utilities};
use JsonException;
use OpenTransposh\Logging\{Logger, LogService, Query_Monitor_Logger, NullLogger};
use OpenTransposh\Widgets\Plugin_Widget;
use stdClass;
use WP;
use WP_Error;
use WP_Query;

/**
 * This class represents the complete plugin
 */
class Plugin {
	use Traits\Static_Instance_Trait;
	// List of contained objects

	/** @var Plugin_Options An options object */
	public $options;

	/** @var Plugin_Admin Admin page */
	private $admin;

	/** @var Plugin_Widget Widget control */
	public $widget;

	/** @var Database The database class */
	public $database;

	/** @var Post_Publish Happens after editing */
	public $postpublish;

	/** @var Integrations Happens after editing */
	private $third_party;

	/** @var Mail Mail handler */
	public $mail;

	/** @var Ajax_Controller Ajax controller */
	public $ajax_controller;
	// list of properties

	/** @var string The site url */
	public $home_url;

	/** @var a url of the request, assuming there was no language */
	private $clean_url;

	/** @var string The url to the plugin directory */
	public $transposh_plugin_url;

	/** @var string The directory of the plugin */
	public $transposh_plugin_dir;

	/** @var string Plugin main file and dir */
	public $transposh_plugin_basename;

	/** @var bool Enable rewriting of URLs */
	public $enable_permalinks_rewrite;

	/** @var string The language to translate the page to, from params */
	public $target_language;

	/** @var string The language extracted from the url */
	public $tgl;

	/** @var bool Are we currently editing the page? */
	public $edit_mode;

	/** @var string Error message displayed for the admin in case of failure */
	private $admin_msg;

	/** @var string Saved search variables */
	private $search_s;

	/** @var bool variable to make sure we only attempt to fix the url once, could have used remove_filter */
	private $got_request = false;

	/** @var bool might be that page is json... */
	private $attempt_json = false;

	/** @var bool Is the wp_redirect being called by transposh? */
	private $transposh_redirect = false;

	/** @var bool Did we get to process but got an empty buffer with no language? (someone flushed us) */
	private $tried_buffer = false;

	/** @var bool Do I need to check for updates by myself? After wordpress checked his */
	private $do_update_check = false;
	private Logger|NullLogger|Query_Monitor_Logger $logger;

	/**
	 * class constructor
	 */
	public function __construct(string $plugin_file) {
		// "global" vars
		$this->home_url = get_option( 'home' );

		$this->transposh_plugin_url = plugin_dir_url( $plugin_file );
		$this->transposh_plugin_dir = plugin_dir_path( $plugin_file );
		$this->transposh_plugin_basename = plugin_basename( $plugin_file );

		$null_logger = new NullLogger();
		LogService::set_instance( $null_logger );

		// create and initialize sub-objects
		$this->logger          = $null_logger;
		$this->options         = new Plugin_Options();
		$this->database        = new Database( $this );
		$this->admin           = new Plugin_Admin( $this );
		$this->widget          = new Plugin_Widget( $this );
		$this->postpublish     = new Post_Publish( $this );
		$this->third_party     = new Integrations( $this );
		$this->mail            = new Mail( $this );
		$this->ajax_controller = new Ajax_Controller( $this );

		// TODO: get_class_methods to replace said mess, other way?
		add_filter( 'plugin_action_links_' . $this->transposh_plugin_basename, [ &$this, 'plugin_action_links' ] );
		add_filter( 'query_vars', [ &$this, 'parameter_queryvars' ] );
		add_filter( 'rewrite_rules_array', [ &$this, 'update_rewrite_rules' ] );
		if ( $this->options->enable_url_translate ) {
			add_filter( 'request', [ &$this, 'request_filter' ] );
		}
		add_filter( 'comment_post_redirect', [ &$this, 'comment_post_redirect_filter' ] );
		add_filter( 'comment_text', [ &$this, 'comment_text_wrap' ], 9999 ); // this is a late filter...
		add_action( 'init', [ &$this, 'on_init' ], 0 ); // really high priority
		add_action( 'admin_init', [ &$this, 'on_admin_init' ] );
//        add_action('admin_init', array(&$this, 'on_admin_init')); might use to mark where not to work?
		add_action( 'parse_request', [ &$this, 'on_parse_request' ], 0 ); // should have high enough priority
		add_action( 'plugins_loaded', [ &$this, 'plugin_loaded' ] );
		add_action( 'shutdown', [ &$this, 'on_shutdown' ] );
		add_action( 'wp_print_styles', [ &$this, 'add_transposh_css' ] );
		add_action( 'wp_print_scripts', [ &$this, 'add_transposh_js' ] );
		if ( ! $this->options->dont_add_rel_alternate ) {
			add_action( 'wp_head', [ &$this, 'add_rel_alternate' ] );
		}
//        add_action('wp_head', array(&$this,'add_transposh_async'));
		add_action( 'transposh_backup_event', [ &$this, 'run_backup' ] );
		add_action( 'transposh_oht_event', [ &$this, 'run_oht' ] );
		add_action( 'comment_post', [ &$this, 'add_comment_meta_settings' ], 1 );
		// our translation proxy
//        add_action('wp_ajax_tp_gp', array(&$this, 'on_ajax_nopriv_tp_gp'));
//        add_action('wp_ajax_nopriv_tp_gp', array(&$this, 'on_ajax_nopriv_tp_gp'));
		add_action( 'wp_ajax_tp_tp', [ &$this, 'on_ajax_nopriv_tp_tp' ] ); // translate suggest proxy
		add_action( 'wp_ajax_nopriv_tp_tp', [ &$this, 'on_ajax_nopriv_tp_tp' ] );
		add_action( 'wp_ajax_tp_oht', [ &$this, 'on_ajax_nopriv_tp_oht' ] );
		add_action( 'wp_ajax_nopriv_tp_oht', [ &$this, 'on_ajax_nopriv_tp_oht' ] );
		// ajax actions in editor
		// TODO - remove some for non translators
		add_action( 'wp_ajax_tp_ohtcallback', [ &$this, 'on_ajax_nopriv_tp_ohtcallback' ] );
		add_action( 'wp_ajax_nopriv_tp_ohtcallback', [ &$this, 'on_ajax_nopriv_tp_ohtcallback' ] );
		add_action( 'wp_ajax_tp_trans_alts', [ &$this, 'on_ajax_nopriv_tp_trans_alts' ] );
		add_action( 'wp_ajax_nopriv_tp_trans_alts', [ &$this, 'on_ajax_nopriv_tp_trans_alts' ] );
		add_action( 'wp_ajax_tp_cookie', [ &$this, 'on_ajax_nopriv_tp_cookie' ] );
		add_action( 'wp_ajax_nopriv_tp_cookie', [ &$this, 'on_ajax_nopriv_tp_cookie' ] );
		add_action( 'wp_ajax_tp_cookie_bck', [ &$this, 'on_ajax_nopriv_tp_cookie_bck' ] );
		add_action( 'wp_ajax_nopriv_tp_cookie_bck', [ &$this, 'on_ajax_nopriv_tp_cookie_bck' ] );

		// For super proxy
		add_action( 'superproxy_reg_event', [ &$this, 'superproxy_reg' ] );
		if ( $this->options->enable_superproxy ) {
			add_action( 'wp_ajax_proxy', [ &$this, 'on_ajax_nopriv_proxy' ] );
			add_action( 'wp_ajax_nopriv_proxy', [ &$this, 'on_ajax_nopriv_proxy' ] );
		}
		// comment_moderation_text - future filter TODO
		// full post wrapping (should happen late)
		add_filter( 'the_content', [ &$this, 'post_content_wrap' ], 9999 );
		add_filter( 'the_excerpt', [ &$this, 'post_content_wrap' ], 9999 );
		add_filter( 'the_title', [ &$this, 'post_wrap' ], 9999, 2 );

		// allow to mark the language?
//        add_action('admin_menu', array(&$this, 'transposh_post_language'));
//        add_action('save_post', array(&$this, 'transposh_save_post_language'));
		//TODO add_action('manage_comments_nav', array(&$this,'manage_comments_nav'));
		//TODO comment_row_actions (filter)
		// Intergrating with the gettext interface
		if ( $this->options->transposh_gettext_integration ) {
			add_filter( 'gettext', [ &$this, 'transposh_gettext_filter' ], 10, 3 );
			add_filter( 'gettext_with_context', [ &$this, 'transposh_gettext_filter' ], 10, 3 );
			add_filter( 'ngettext', [ &$this, 'transposh_ngettext_filter' ], 10, 4 );
			add_filter( 'ngettext_with_context', [ &$this, 'transposh_ngettext_filter' ], 10, 4 );
			add_filter( 'locale', [ &$this, 'transposh_locale_filter' ] );
		}

		// debug function for bad redirects
		add_filter( 'wp_redirect', [ &$this, 'on_wp_redirect' ], 10, 2 );
		add_filter( 'redirect_canonical', [ &$this, 'on_redirect_canonical' ], 10, 2 );

		// support shortcodes
		add_shortcode( 'tp', [ &$this, 'tp_shortcode' ] );
		add_shortcode( 'tpe', [ &$this, 'tp_shortcode' ] );
		//
		// FUTURE add_action('update-custom_transposh', array(&$this, 'update'));
		// CHECK TODO!!!!!!!!!!!!
		$this->tgl = Utilities::get_language_from_url( Utilities::get_clean_server_var( 'REQUEST_URI' ), $this->home_url );
		if ( ! $this->options->is_active_language( $this->tgl ) ) {
			$this->tgl = '';
		}

		register_activation_hook( __FILE__, [ &$this, 'plugin_activate' ] );
		register_deactivation_hook( __FILE__, [ &$this, 'plugin_deactivate' ] );
	}

	/**
	 * Attempt to fix a wp_redirect being called by someone else to include the language
	 * hoping for no cycles
	 *
	 * @param string $location
	 * @param int $status
	 *
	 * @return string
	 */
	public function on_wp_redirect( $location, $status ) {
		// no point in mangling redirection if its our own or its the default language
		if ( $this->transposh_redirect || $this->options->is_default_language( $this->target_language ) ) {
			return $location;
		}
		LogService::legacy_log( $status . ' ' . $location );
		// $trace = debug_backtrace();
		// OpenTransposh\Logging\Logger($trace);
		// OpenTransposh\Logging\Logger($this->target_language);
		return $this->rewrite_url( $location );
	}

	/**
	 * Internally used by transposh redirection, to avoid being rewritten by self
	 * assuming we know what we are doing when redirecting
	 *
	 * @param string $location
	 * @param int $status
	 */
	public function tp_redirect( $location, $status = 302 ) {
		$this->transposh_redirect = true;
		wp_redirect( $location, $status );
	}

	/**
	 * Function to fix canonical redirection for some translated urls (such as tags with params)
	 *
	 * @param string $red - url wordpress assumes it will redirect to
	 * @param string $req - url that was originally requested
	 *
	 * @return mixed false if redirect unneeded - new url if we think we should
	 */
	public function on_redirect_canonical( $red, $req ) {
		LogService::legacy_log( "$red .. $req", 4 );
		// if the urls are actually the same, don't redirect (same - if it had our proper take care of)
		if ( $this->rewrite_url( $red ) == urldecode( $req ) ) {
			return false;
		}
		// if this is not the default language, we need to make sure it redirects to what we believe is the proper url
		if ( ! $this->options->is_default_language( $this->target_language ) ) {
			$red = str_replace( [ '%2F', '%3A', '%3B', '%3F', '%3D', '%26' ], [
				'/',
				':',
				';',
				'?',
				'=',
				'&'
			], urlencode( $this->rewrite_url( $red ) ) );
		}

		return $red;
	}

	public function get_clean_url() {
		if ( isset( $this->clean_url ) ) {
			return $this->clean_url;
		}
		//remove any language identifier and find the "clean" url, used for posting and calculating urls if needed
		$this->clean_url = Utilities::cleanup_url( Utilities::get_clean_server_var( 'REQUEST_URI' ), $this->home_url, true );
		// we need this if we are using url translations
		if ( $this->options->enable_url_translate ) {
			$this->clean_url = Utilities::get_original_url( $this->clean_url, '', $this->target_language, [
				$this->database,
				'fetch_original'
			] );
		}

		return $this->clean_url;
	}

//    function update() {file_location
//        require_once('./admin-header.php');

	/* 	$nonce = 'upgrade-plugin_' . $plugin;
      $url = 'update.php?action=upgrade-plugin&plugin=' . $plugin;

      $upgrader = new Plugin_Upgrader( new Plugin_Upgrader_Skin( compact('title', 'nonce', 'url', 'plugin') ) );
      $upgrader->upgrade($plugin);
     */
//        include('./admin-footer.php');
//    }

	/**
	 * Check if page is special (one that we normally should not touch
	 *
	 * @param string $url Url to check
	 *
	 * @return bool Is it a special page?
	 */
	public function is_special_page( $url ) {
		return ( stripos( $url, '/wp-login.php' ) !== false ||
		         stripos( $url, '/robots.txt' ) !== false ||
		         stripos( $url, '/wp-json/' ) !== false ||
		         stripos( $url, '/wp-admin/' ) !== false ||
		         stripos( $url, '/wp-comments-post' ) !== false ||
		         stripos( $url, '/main-sitemap.xsl' ) !== false || //YOAST?
		         stripos( $url, '.xsl' ) !== false || //YOAST?
		         stripos( $url, '.xml' ) !== false || //YOAST?
		         stripos( $url, '/xmlrpc.php' ) !== false );
	}

	/**
	 * Called when the buffer containing the original page is flushed. Triggers the translation process.
	 *
	 * @param string $buffer Original page
	 *
	 * @return string Modified page buffer
	 */
	public function process_page( $buffer ) { //php7?
		/*        if (!$this->target_language) {
          global $wp;
          $this->on_parse_request($wp);
          } */
		LogService::legacy_log( 'processing page hit with language:' . $this->target_language, 1 );
		$bad_content = false;
		foreach ( headers_list() as $header ) {
			if ( stripos( $header, 'Content-Type:' ) !== false ) {
				LogService::legacy_log( $header );
				if ( stripos( $header, 'text' ) === false && stripos( $header, 'json' ) === false && stripos( $header, 'rss' ) === false ) {
					LogService::legacy_log( "won't do that - $header" );
					$bad_content = true;
				}
			}
		}
		$start_time = microtime( true );

		// Refrain from touching the administrative interface and important pages
		if ( $this->is_special_page( Utilities::get_clean_server_var( 'REQUEST_URI' ) ) && ! $this->attempt_json ) {
			LogService::legacy_log( "Skipping translation for admin pages", 3 );
		} elseif ( $bad_content ) {
			LogService::legacy_log( "Seems like content we should not handle" );
		}
		// This one fixed a bug transposh created with other pages (xml generator for other plugins - such as the nextgen gallery)
		// TODO: need to further investigate (will it be needed?)
		elseif ( $this->target_language == '' ) {
			LogService::legacy_log( "Skipping translation where target language is unset", 3 );
			if ( ! $buffer ) {
				LogService::legacy_log( "seems like we had a premature flushing" );
				$this->tried_buffer = true;
			}
		} // Don't translate the default language unless specifically allowed to...
		elseif ( $this->options->is_default_language( $this->target_language ) && ! $this->options->enable_default_translate ) {
			LogService::legacy_log( "Skipping translation for default language {$this->target_language}", 3 );
		} else {
			// This one allows to redirect to a static element which we can find, since the redirection will remove
			// the target language, we are able to avoid nasty redirection loops
			if ( is_404() ) {
				global $wp;
				if ( isset( $wp->query_vars['pagename'] ) && file_exists( ABSPATH . $wp->query_vars['pagename'] ) ) { // Hmm
					LogService::legacy_log( 'Redirecting a static file ' . $wp->query_vars['pagename'], 1 );
					$this->tp_redirect( '/' . $wp->query_vars['pagename'], 301 );
				}
			}

			LogService::legacy_log( "Translating " . Utilities::get_clean_server_var( 'REQUEST_URI' ) . " to: {$this->target_language} for: " . Utilities::get_clean_server_var( 'REMOTE_ADDR' ),
				1 );

			//translate the entire page
			$parse                          = new Parser();
			$parse->fetch_translate_func    = [ &$this->database, 'fetch_translation' ];
			$parse->prefetch_translate_func = [ &$this->database, 'prefetch_translations' ];
			$parse->url_rewrite_func        = [ &$this, 'rewrite_url' ];
			$parse->split_url_func          = [ &$this, 'split_url' ];
			$parse->dir_rtl                 = ( in_array( $this->target_language, Constants::$rtl_languages ) );
			$parse->lang                    = $this->target_language;
			$parse->default_lang            = $this->options->is_default_language( $this->target_language );
			$parse->is_edit_mode            = $this->edit_mode;
			$parse->might_json              = $this->attempt_json;
			$parse->is_auto_translate       = $this->is_auto_translate_permitted();
			// TODO - check this!
			if ( stripos( Utilities::get_clean_server_var( 'REQUEST_URI' ), '/feed/' ) !== false ) {
				LogService::legacy_log( "in rss feed!", 2 );
				$parse->is_auto_translate = false;
				$parse->is_edit_mode      = false;
				$parse->feed_fix          = true;
			}
			$parse->change_parsing_rules( ! $this->options->parser_dont_break_puncts, ! $this->options->parser_dont_break_numbers, ! $this->options->parser_dont_break_entities );
			$buffer = $parse->fix_html( $buffer );

			$end_time = microtime( true );
			LogService::legacy_log( 'Translation completed in ' . ( $end_time - $start_time ) . ' seconds', 1 );
		}

		return $buffer;
	}

	/**
	 * Setup a buffer that will contain the contents of the html page.
	 * Once processing is completed the buffer will go into the translation process.
	 */
	public function on_init() {
		LogService::legacy_log( 'init ' . Utilities::get_clean_server_var( 'REQUEST_URI' ), 4 );

		// the wp_rewrite is not available earlier so we can only set the enable_permalinks here
		if ( is_object( $GLOBALS['wp_rewrite'] ) && $GLOBALS['wp_rewrite']->using_permalinks() && $this->options->enable_permalinks ) {
			LogService::legacy_log( "enabling permalinks" );
			$this->enable_permalinks_rewrite = true;
		}

		LogService::legacy_log( Utilities::get_clean_server_var( 'REQUEST_URI' ), 5 );
		if ( strpos( Utilities::get_clean_server_var( 'REQUEST_URI' ), '/wpv-ajax-pagination/' ) === true ) {
			LogService::legacy_log( 'wpv pagination', 5 );
			$this->target_language = Utilities::get_language_from_url(
				Utilities::get_clean_server_var( 'HTTP_REFERER' ),
				$this->home_url
			);
		}

		// load translation files for transposh
		load_plugin_textdomain( TRANSPOSH_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/langs' );

		//set the callback for translating the page when it's done
		ob_start( [ &$this, "process_page" ] );
	}

	public function on_admin_init() {
		//alm news
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'alm_query_posts' ) {
			$this->target_language = Utilities::get_language_from_url(
				Utilities::get_clean_server_var( 'HTTP_REFERER' ),
				$this->home_url
			);
		}

		if ( isset( $_GET['wc-ajax'] ) && $_GET['wc-ajax'] === 'update_order_review' ) {
			$this->target_language = Utilities::get_language_from_url(
				Utilities::get_clean_server_var( 'HTTP_REFERER' ),
				$this->home_url
			);
			$this->attempt_json    = true;
		}

		if ( isset( $_POST['action'] ) ) {
			switch ( $_POST['action'] ) {
				case 'activity_get_older_updates': //buddypress old activity
				case 'woocommerce_add_to_cart':
				case 'woocommerce_get_refreshed_fragments':
				case 'woocommerce_update_order_review':
					$this->target_language = Utilities::get_language_from_url(
						Utilities::get_clean_server_var( 'HTTP_REFERER' ),
						$this->home_url
					);
					$this->attempt_json    = true;
					break;
			}
		}
	}

	/**
	 * Page generation completed - flush buffer.
	 */
	public function on_shutdown() {
		//TODO !!!!!!!!!!!! ob_flush();
	}

	/**
	 * Update the url rewrite rules to include language identifier
	 *
	 * @param array $rules Old rewrite rules
	 *
	 * @return array New rewrite rules
	 */
	public function update_rewrite_rules( $rules ) {
		LogService::legacy_log( "Enter update_rewrite_rules", 2 );

		if ( ! $this->options->enable_permalinks ) {
			LogService::legacy_log( "Not touching rewrite rules - permalinks modification disabled by admin", 2 );

			return $rules;
		}

		$newRules    = [];
		$lang_prefix = "(" . str_replace( ',', '|', $this->options->viewable_languages ) . ")/";

		$lang_parameter = "&" . LANG_PARAM . '=$matches[1]';

		//catch the root url
		$newRules[ $lang_prefix . "?$" ] = "index.php?lang=\$matches[1]";
		LogService::legacy_log( "\t {$lang_prefix} ?$  --->  index.php?lang=\$matches[1]", 4 );

		foreach ( $rules as $key => $value ) {
			$original_key   = $key;
			$original_value = $value;

			$key = $lang_prefix . $key;

			//Shift existing matches[i] a step forward as we pushed new elements
			//in the beginning of the expression
			for ( $i = 9; $i > 0; $i -- ) {
				$value = str_replace( '[' . $i . ']', '[' . ( $i + 1 ) . ']', $value );
			}

			$value .= $lang_parameter;

			LogService::legacy_log( "\t $key ---> $value", 2 );

			$newRules[ $key ]          = $value;
			$newRules[ $original_key ] = $original_value;

			LogService::legacy_log( ": \t{$original_key} ---> {$original_value}", 4 );
		}

		LogService::legacy_log( "Exit update_rewrite_rules", 2 );

		return $newRules;
	}

	//function flush_transposh_rewrite_rules() {
	//add_filter('rewrite_rules_array', array(&$this, 'update_rewrite_rules'));
//        $GLOBALS['wp_rewrite']->flush_rules();
	//}

	/**
	 * Let WordPress know which parameters are of interest to us.
	 *
	 * @param array $vars Original queried variables
	 *
	 * @return array Modified array
	 */
	public function parameter_queryvars( $vars ) {
		LogService::legacy_log( 'inside query vars', 4 );
		$vars[] = LANG_PARAM;
		$vars[] = EDIT_PARAM;
		LogService::legacy_log( $vars, 4 );

		return $vars;
	}

	/**
	 * Grabs and set the global language and edit params, they should be here
	 *
	 * @param WP $wp - here we get the WP class
	 */
	public function on_parse_request( $wp ) {
		LogService::legacy_log( 'on_parse_req', 3 );
		LogService::legacy_log( $wp->query_vars );

		// fix for custom-permalink (and others that might be double parsing?)
		if ( $this->target_language ) {
			return;
		}

		// first we get the target language
		/*        $this->target_language = (isset($wp->query_vars[LANG_PARAM])) ? $wp->query_vars[LANG_PARAM] : '';
          if (!$this->target_language)
          $this->target_language = $this->options->default_language;
          OpenTransposh\Logging\Logger("requested language: {$this->target_language}"); */
		// TODO TOCHECK!!!!!!!!!!!!!!!!!!!!!!!!!!1
		$this->target_language = $this->tgl;
		if ( ! $this->target_language ) {
			$this->target_language = $this->options->default_language;
		}
		LogService::legacy_log( "requested language: {$this->target_language}", 3 );

		if ( $this->tried_buffer ) {
			LogService::legacy_log( "we will retrigger the output buffering" );
			ob_start( [ &$this, "process_page" ] );
		}

		// make themes that support rtl - go rtl http://wordpress.tv/2010/05/01/yoav-farhi-right-to-left-themes-sf10
		if ( in_array( $this->target_language, Constants::$rtl_languages ) ) {
			global $wp_locale;
			$wp_locale->text_direction = 'rtl';
		}

		// we'll go into this code of redirection only if we have options that need it (and no bot is involved, for the non-cookie)
		//  and this is not a special page or one that is refered by our site
		// bots can skip this altogether
		if ( ( $this->options->enable_detect_redirect || $this->options->widget_allow_set_deflang || $this->options->enable_geoip_redirect ) &&
		     ! ( $this->is_special_page( Utilities::get_clean_server_var( 'REQUEST_URI' ) ) || ( Utilities::get_clean_server_var( 'HTTP_REFERER' ) != null && str_contains( Utilities::get_clean_server_var( 'HTTP_REFERER' ), $this->home_url ) ) ) &&
		     ! ( Utilities::is_bot() ) ) {
			// we are starting a session if needed
			if ( ! session_id() ) {
				session_start();
			}
			// no redirections if we already redirected in this session or we suspect cyclic redirections
			if ( ! isset( $_SESSION['TR_REDIRECTED'] ) && ! ( Utilities::get_clean_server_var( 'HTTP_REFERER' ) == Utilities::get_clean_server_var( 'REQUEST_URI' ) ) ) {
				LogService::legacy_log( 'session redirection never happened (yet)', 2 );
				// we redirect once per session
				$_SESSION['TR_REDIRECTED'] = true;
				// redirect according to stored lng cookie, and than according to detection
				if ( isset( $_COOKIE['TR_LNG'] ) && $this->options->widget_allow_set_deflang ) {
					if ( $_COOKIE['TR_LNG'] != $this->target_language ) {
						$url = Utilities::rewrite_url_lang_param( Utilities::get_clean_server_var( "REQUEST_URI" ), $this->home_url, $this->enable_permalinks_rewrite, $_COOKIE['TR_LNG'], $this->edit_mode );
						if ( $this->options->is_default_language( $_COOKIE['TR_LNG'] ) ) //TODO - fix wrt translation
						{
							$url = Utilities::cleanup_url( Utilities::get_clean_server_var( "REQUEST_URI" ), $this->home_url );
						}
						LogService::legacy_log( "redirected to $url because of cookie", 2 );
						$this->tp_redirect( $url );
						exit;
					}
				} else {
					//**
					if ( $this->options->enable_detect_redirect ) {
						$bestlang = Utilities::prefered_language( explode( ',', $this->options->viewable_languages ), $this->options->default_language );
						// we won't redirect if we should not, or this is a presumable bot
					} elseif ( $this->options->enable_geoip_redirect ) {
						$country  = geoip_detect2_get_info_from_current_ip()->country->isoCode;
						$bestlang = Utilities::language_from_country( explode( ',', $this->options->viewable_languages ), $country, $this->options->default_language );
					}
					if ( $bestlang && $bestlang != $this->target_language ) {
						$url = Utilities::rewrite_url_lang_param( Utilities::get_clean_server_var( 'REQUEST_URI' ), $this->home_url, $this->enable_permalinks_rewrite, $bestlang, $this->edit_mode );
						if ( $this->options->is_default_language( $bestlang ) ) //TODO - fix wrt translation
						{
							$url = Utilities::cleanup_url( Utilities::get_clean_server_var( 'REQUEST_URI' ), $this->home_url );
						}
						LogService::legacy_log( "redirected to $url because of bestlang", 2 );
						$this->tp_redirect( $url );
						exit;
					}
				}
			} else {
				LogService::legacy_log( 'session was already redirected', 2 );
			}
		}
		// this method allows posts from the search box to maintain the language,
		// TODO - it has a bug of returning to original language following search, which can be resolved by removing search from widget urls, but maybe later...
		if ( isset( $wp->query_vars['s'] ) ) {
			if ( $this->options->enable_search_translate ) {
				add_action( 'pre_get_posts', [ &$this, 'pre_post_search' ] );
				add_action( 'posts_where_request', [ &$this, 'posts_where_request' ] );
			}
			if ( Utilities::get_language_from_url( Utilities::get_clean_server_var( 'HTTP_REFERER' ), $this->home_url ) && ! Utilities::get_language_from_url( Utilities::get_clean_server_var( 'REQUEST_URI' ), $this->home_url ) ) {
				$this->tp_redirect( Utilities::rewrite_url_lang_param( Utilities::get_clean_server_var( "REQUEST_URI" ), $this->home_url, $this->enable_permalinks_rewrite, Utilities::get_language_from_url( Utilities::get_clean_server_var( 'HTTP_REFERER' ), $this->home_url ), false ) ); //."&stop=y");
				exit;
			}
		}
		if ( isset( $wp->query_vars[ EDIT_PARAM ] ) && $wp->query_vars[ EDIT_PARAM ] && $this->is_editing_permitted() ) {
			$this->edit_mode = true;
			// redirect bots away from edit pages to avoid double indexing
			if ( Utilities::is_bot() ) {
				$this->tp_redirect( Utilities::rewrite_url_lang_param( Utilities::get_clean_server_var( "REQUEST_URI" ), $this->home_url, $this->enable_permalinks_rewrite, Utilities::get_language_from_url( Utilities::get_clean_server_var( "REQUEST_URI" ), $this->home_url ), false ), 301 );
				exit;
			}
		} else {
			$this->edit_mode = false;
		}
		// We are removing our query vars since they are no longer needed and also make issues when a user select a static page as his home
		unset( $wp->query_vars[ LANG_PARAM ], $wp->query_vars[ EDIT_PARAM ] );
		LogService::legacy_log( "edit mode: " . ( ( $this->edit_mode ) ? 'enabled' : 'disabled' ), 2 );
	}

	// TODO ? move to options?

	/**
	 * Determine if the current user is allowed to translate.
	 * @return bool Is allowed to translate?
	 */
	public function is_translator() {
		return is_user_logged_in() && current_user_can( TRANSLATOR );
	}

	/**
	 * Plugin activation
	 */
	public function plugin_activate() {
		LogService::legacy_log( "plugin_activate enter: " . __DIR__, 1 );

		$this->database->setup_db();
		// this handles the permalink rewrite
		$GLOBALS['wp_rewrite']->flush_rules();

		// attempt to remove old files
		@unlink( $this->transposh_plugin_dir . 'widgets/tpw_default.php' );
		@unlink( $this->transposh_plugin_dir . 'core/globals.php' );

		//** FULL VERSION
		// create directories in upload folder, for use with third party widgets
		$upload               = wp_upload_dir();
		$upload_dir           = $upload['basedir'];
		$transposh_upload_dir = $upload_dir . '/' . TRANSPOSH_DIR_UPLOAD;
		if ( ! is_dir( $transposh_upload_dir ) ) {
			mkdir( $transposh_upload_dir, 0700 );
		}
		$transposh_upload_widgets_dir = $transposh_upload_dir . '/' . TRANSPOSH_DIR_WIDGETS;
		if ( ! is_dir( $transposh_upload_widgets_dir ) ) {
			mkdir( $transposh_upload_widgets_dir, 0700 );
		}
		//** FULLSTOP

		LogService::legacy_log( "plugin_activate exit: " . __DIR__, 1 );
		LogService::legacy_log( "testing name:" . plugin_basename( __FILE__ ), 4 );
		// OpenTransposh\Logging\Logger("testing name2:" . $this->get_plugin_name(), 4);
		//activate_plugin($plugin);
	}

	/**
	 * Plugin deactivation
	 */
	public function plugin_deactivate() {
		LogService::legacy_log( "plugin_deactivate enter: " . __DIR__, 2 );

		// this handles the permalink rewrite
		$GLOBALS['wp_rewrite']->flush_rules();

		LogService::legacy_log( "plugin_deactivate exit: " . __DIR__, 2 );
	}

	/**
	 * Callback from admin_notices - display error message to the admin.
	 */
	public function plugin_install_error() {
		LogService::legacy_log( "install error!", 1 );

		echo '<div class="updated"><p>';
		echo 'Error has occured in the installation process of the translation plugin: <br>';
		echo $this->admin_msg;

		if ( function_exists( 'deactivate_plugins' ) ) {
			// FIXME :wtf?
			//deactivate_plugins(array(&$this, 'get_plugin_name'), "translate.php");
			////!!!   deactivate_plugins($this->transposh_plugin_basename, "translate.php");
			echo '<br> This plugin has been automatically deactivated.';
		}

		echo '</p></div>';
	}

	/**
	 * Callback when all plugins have been loaded. Serves as the location
	 * to check that the plugin loaded successfully else trigger notification
	 * to the admin and deactivate plugin.
	 * TODO - needs revisiting!
	 */
	public function plugin_loaded() {
		$this->initialize_logger();

		LogService::legacy_log( "Enter", 4 );

		//TODO: fix this...
		$db_version = get_option( TRANSPOSH_DB_VERSION );

		if ( $db_version != DB_VERSION ) {
			$this->database->setup_db();
			//$this->admin_msg = "Translation database version ($db_version) is not comptabile with this plugin (". DB_VERSION . ")  <br>";

			LogService::legacy_log( "Updating database in plugin loaded", 1 );
			//Some error occured - notify admin and deactivate plugin
			//add_action('admin_notices', 'plugin_install_error');
		}

		//TODO: fix this too...
		$db_version = get_option( TRANSPOSH_DB_VERSION );

		if ( $db_version != DB_VERSION ) {
			$this->admin_msg = "Failed to locate the translation table  <em> " . TRANSLATIONS_TABLE . "</em> in local database. <br>";

			LogService::legacy_log( "Messsage to admin: {$this->admin_msg}", 1 );
			//Some error occured - notify admin and deactivate plugin
			add_action( 'admin_notices', [ &$this, 'plugin_install_error' ] );
		}
	}

	/**
	 * Gets the plugin name to be used in activation/decativation hooks.
	 * Keep only the file name and its containing directory. Don't use the full
	 * path as it will break when using symbollic links.
	 * TODO - check!!!
	 * @return string
	 */
	/* function get_plugin_name() {
      $file = __FILE__;
      $file = str_replace('\\', '/', $file); // sanitize for Win32 installs
      $file = preg_replace('|/+|', '/', $file); // remove any duplicate slash
      //keep only the file name and its parent directory
      $file = preg_replace('/.*\/([^\/]+\/[^\/]+)$/', '$1', $file);
      OpenTransposh\Logging\Logger("Plugin path - $file", 4);
      return $file;
      } */

	/**
	 * Add custom css, i.e. transposh.css
	 */
	public function add_transposh_css() {
		//translation not allowed - no need for the transposh.css
		if ( ! $this->is_editing_permitted() && ! $this->is_auto_translate_permitted() ) {
			return;
		}
		// actually - this is only needed when editing
		if ( ! $this->edit_mode ) {
			return;
		}

		//include the transposh.css
		wp_enqueue_style( 'transposh', $this->transposh_plugin_url . TRANSPOSH_DIR_CSS . '/transposh.css', [], TRANSPOSH_PLUGIN_VER );

		LogService::legacy_log( 'Added transposh_css', 4 );
	}

	/**
	 * Insert references to the javascript files used in the translated version of the page.
	 */
	public function add_transposh_js() {
		//not in any translation mode - no need for any js.
		if ( ! ( $this->edit_mode || $this->is_auto_translate_permitted() || is_admin() || $this->options->widget_allow_set_deflang ) ) // TODO: need to include if allowing of setting default language - but smaller!
		{
			return;
		} // TODO, check just for settings page admin and pages with our translate
		wp_register_script( 'transposh', $this->transposh_plugin_url . TRANSPOSH_DIR_JS . '/transposh.js', [ 'jquery' ], TRANSPOSH_PLUGIN_VER, $this->options->enable_footer_scripts );
		// true -> 1, false -> nothing
		$script_params = [
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'plugin_url' => rtrim($this->transposh_plugin_url, '/'),
			'lang'       => $this->target_language,
			'olang'      => $this->options->default_language,
			// those two options show if the script can support said engines
			'prefix'     => SPAN_PREFIX,
			'preferred'  => array_keys( $this->options->get_sorted_engines() )
		];

		$script_params['engines'] = new stdClass();
		if ( in_array( $this->target_language, Constants::$engines['a']['langs'] ) ) {
			$script_params['engines']->a = 1;
		}
		if ( in_array( $this->target_language, Constants::$engines['b']['langs'] ) ) {
			$script_params['engines']->b = 1;
//            $script_params['engines'][] = 'b';
			if ( isset( Constants::$engines['b']['langconv'][ $this->target_language ] ) ) {
				$script_params['blang'] = Constants::$engines['b']['langconv'][ $this->target_language ];
			}
		}
		if ( in_array( $this->target_language, Constants::$engines['g']['langs'] ) ) {
			$script_params['engines']->g = 1;
		}
		if ( in_array( $this->target_language, Constants::$engines['y']['langs'] ) ) {
			$script_params['engines']->y = 1;
		}
		if ( in_array( $this->target_language, Constants::$engines['u']['langs'] ) ) {
			$script_params['engines']->u = 1;
		}
		if ( $this->options->oht_id && $this->options->oht_key && in_array( $this->target_language, Constants::$oht_languages ) && current_user_can( 'manage_options' ) ) {
			$script_params['engines']->o = 1;
		}
		if ( ! $this->options->enable_autotranslate ) {
			$script_params['noauto'] = 1;
		}

		// load translations needed for edit interface
		if ( $this->edit_mode ) {
			$script_params['edit'] = 1;
			if ( file_exists( $this->transposh_plugin_dir . TRANSPOSH_DIR_JS . '/l/' . $this->target_language . '.js' ) ) {
				$script_params['locale'] = 1;
			}
		}
		// set theme when it is needed
		if ( $this->edit_mode ) {
			$script_params['theme'] = $this->options->widget_theme;
			$script_params['jQueryUI'] = $this->transposh_plugin_url . 'jquery-ui/';
		}

//          'l10n_print_after' => 'try{convertEntities(inlineEditL10n);}catch(e){};'
		wp_localize_script( 'transposh', 't_jp', $script_params );
		// only enqueue on real pages, for real people, other admin scripts that need this will register a dependency
		if ( ( $this->edit_mode || $this->is_auto_translate_permitted() || $this->options->widget_allow_set_deflang ) && ! is_admin() && ! Utilities::is_bot() ) {
			wp_enqueue_script( 'transposh' );
		}
		LogService::legacy_log( 'Added transposh_js', 4 );
	}

	/**
	 * Implements - http://googlewebmastercentral.blogspot.com/2010/09/unifying-content-under-multilingual.html
	 */
	public function add_rel_alternate() {
		if ( is_404() ) {
			return;
		}
		$widget_args = $this->widget->create_widget_args( $this->get_clean_url() );
		LogService::legacy_log( $widget_args, 4 );
		foreach ( $widget_args as $lang ) {
			if ( ! $lang['active'] ) {
				$url = $lang['url'];
				if ( $this->options->full_rel_alternate ) {
					$current_url = ( is_ssl() ? 'https://' : 'http://' ) . Utilities::get_clean_server_var( 'HTTP_HOST' ) . Utilities::get_clean_server_var( 'REQUEST_URI' );
					$url         = Utilities::rewrite_url_lang_param( $current_url, $this->home_url, $this->enable_permalinks_rewrite, $lang['isocode'], $this->edit_mode );
					if ( $this->options->is_default_language( $lang['isocode'] ) ) {
						$url = Utilities::cleanup_url( $url, $this->home_url );
					}
				}
				echo "<link rel='alternate' hreflang='{$lang['isocode']}' href='$url' />";
			}
		}
	}

	/**
	 * Determine if the currently selected language (taken from the query parameters) is in the admin's list
	 * of editable languages and the current user is allowed to translate.
	 * @return bool Is translation allowed?
	 */
	// TODO????
	public function is_editing_permitted() {
		// editing is permitted for translators only
		if ( ! $this->is_translator() ) {
			return false;
		}
		// and only on the non-default lang (unless strictly specified)
		if ( ! $this->options->enable_default_translate && $this->options->is_default_language( $this->target_language ) ) {
			return false;
		}

		return $this->options->is_active_language( $this->target_language );
	}

	/**
	 * Determine if the currently selected language (taken from the query parameters) is in the admin's list
	 * of editable languages and that automatic translation has been enabled.
	 * Note that any user can auto translate. i.e. ignore permissions.
	 * @return bool Is automatic translation allowed?
	 * TODO: move to options
	 */
	public function is_auto_translate_permitted() {
		LogService::legacy_log( "checking auto translatability", 4 );

		if ( ! $this->options->enable_autotranslate ) {
			return false;
		}
		// auto translate is not enabled for default target language when enable default is disabled
		if ( ! $this->options->enable_default_translate && $this->options->is_default_language( $this->target_language ) ) {
			return false;
		}

		return $this->options->is_active_language( $this->target_language );
	}

	/**
	 * Splits a url to translatable segments
	 *
	 * @param string $href
	 *
	 * @return array parts that may be translated
	 */
	public function split_url( $href ) {
		$ret = [];
		// Ignore urls not from this site
		if ( ! Utilities::is_rewriteable_url( $href, $this->home_url ) ) {
			return $ret;
		}

		// don't fix links pointing to real files as it will cause that the
		// web server will not be able to locate them
		if ( stripos( $href, '/wp-admin' ) !== false ||
		     stripos( $href, WP_CONTENT_URL ) !== false ||
		     stripos( $href, '/wp-login' ) !== false ||
		     stripos( $href, '/.php' ) !== false ) /* ??? */ {
			return $ret;
		}

		// todo - check query part... sanitize
		//if (strpos($href, '?') !== false) {
		//    list ($href, $querypart) = explode('?', $href);
		//}
		//$href = substr($href, strlen($this->home_url));
		// this might include the sub directory for non rooted sites, but its not that important to avoid
		$href  = parse_url( $href, PHP_URL_PATH );
		$parts = explode( '/', $href );
		foreach ( $parts as $part ) {
			if ( ! $part || is_numeric( $part ) ) {
				continue;
			}
			$ret[] = $part;
			if ( $part != str_replace( '-', ' ', $part ) ) {
				$ret[] = str_replace( '-', ' ', $part );
			}
		}

		return $ret;
	}

	/**
	 * Callback from parser allowing to overide the global setting of url rewriting using permalinks.
	 * Some urls should be modified only by adding parameters and should be identified by this
	 * function.
	 *
	 * @param $href Original href
	 *
	 * @return bool Modified href
	 */
	public function rewrite_url( $href ) {
		LogService::legacy_log( "got: $href", 4 );
		////$href = str_replace('&#038;', '&', $href);
		// fix what might be messed up -- TODO
		$href = Utilities::clean_breakers( $href );

		// Ignore urls not from this site
		LogService::legacy_log( "homeurl: {$this->home_url} ", 4 );
		if ( ! Utilities::is_rewriteable_url( $href, $this->home_url ) ) {
			return $href;
		}

		// don't fix links pointing to real files as it will cause that the
		// web server will not be able to locate them
		if ( stripos( $href, '/wp-admin' ) !== false ||
		     stripos( $href, WP_CONTENT_URL ) !== false ||
		     stripos( $href, '/wp-login' ) !== false /* ||
          stripos($href, '/.php') !== FALSE */ ) /* ??? */ {
			return $href;
		}
		$use_params = ! $this->enable_permalinks_rewrite;

		// we don't really know, but we sometime rewrite urls when we are in the default language (canonicals?), so just clean them up
		//       if ($this->target_language == $this->options->default_language)
		if ( $this->options->is_default_language( $this->target_language ) ) {
			$href = Utilities::cleanup_url( $href, $this->home_url );
			LogService::legacy_log( "cleaned up: $href", 4 );

			return $href;
		}
		// some hackery needed for url translations
		// first cut home
		if ( $this->options->enable_url_translate ) {
			$href = Utilities::translate_url( $href, $this->home_url, $this->target_language, [
				&$this->database,
				'fetch_translation'
			] );
		}
		$href = Utilities::rewrite_url_lang_param( $href, $this->home_url, $this->enable_permalinks_rewrite, $this->target_language, $this->edit_mode, $use_params );
		LogService::legacy_log( "rewritten: $href", 4 );

		return $href;
	}

	/**
	 * This function adds the word setting in the plugin list page
	 *
	 * @param array $links Links that appear next to the plugin
	 *
	 * @return array Now with settings
	 */
	public function plugin_action_links( $links ) {
		LogService::legacy_log( 'in plugin action', 5 );

		return array_merge( [ '<a href="' . admin_url( 'admin.php?page=tp_main' ) . '">' . __( 'Settings' ) . '</a>' ], $links );
	}

	/**
	 * We use this to "steal" the search variables
	 *
	 * @param WP_Query $query
	 */
	public function pre_post_search( $query ) {
		LogService::legacy_log( 'pre post', 4 );
		LogService::legacy_log( $query->query_vars, 4 );
		// we hide the search query var from further proccesing, because we do this later
		if ( $query->query_vars['s'] ) {
			$this->search_s         = $query->query_vars['s'];
			$query->query_vars['s'] = '';
		}
	}

	/**
	 * This is where we change the logic to include originals for search translation
	 *
	 * @param string $where Original where clause for getting posts
	 *
	 * @return string Modified where
	 */
	public function posts_where_request( $where ) {

		LogService::legacy_log( $where, 3 );
		// from query.php line 1742 (v2.8.6)
		// If a search pattern is specified, load the posts that match
		$q = &$GLOBALS['wp_query']->query_vars;
		// returning the saved query strings
		$q['s'] = $this->search_s;
        $search    = '';
		if ( ! empty( $q['s'] ) ) {
			// added slashes screw with quote grouping when done early, so done later
			$q['s'] = stripslashes( $q['s'] );
			if ( ! empty( $q['sentence'] ) ) {
				$q['search_terms'] = [ $q['s'] ];
			} else {
				preg_match_all( '/".*?("|$)|((?<=[\\s",+])|^)[^\\s",+]+/', $q['s'], $matches );
				$q['search_terms'] = array_map( static function ( $a ) {
					return trim( $a, "\"'\\n\\r " );
				}, $matches[0] );
			}
			$n         = ! empty( $q['exact'] ) ? '' : '%';
			$searchand = '';
			foreach ( (array) $q['search_terms'] as $term ) {
				// now we'll get possible translations for this term
				$possible_original_terms = $this->database->get_orignal_phrases_for_search_term( $term, $this->target_language );
				$term                    = addslashes_gpc( $term );
				$search                  .= "{$searchand}(({$GLOBALS['wpdb']->posts}.post_title LIKE '{$n}{$term}{$n}') OR ({$GLOBALS['wpdb']->posts}.post_content LIKE '{$n}{$term}{$n}')";
				foreach ( (array) $possible_original_terms as $term ) {
					$term   = addslashes_gpc( $term );
					$search .= " OR ({$GLOBALS['wpdb']->posts}.post_title LIKE '{$n}{$term}{$n}') OR ({$GLOBALS['wpdb']->posts}.post_content LIKE '{$n}{$term}{$n}')";
				}
				// we moved this to here, so it really closes all of them
				$search    .= ")";
				$searchand = ' AND ';
			}
			$term = esc_sql( $q['s'] );
			if ( empty( $q['sentence'] ) && count( $q['search_terms'] ) > 1 && $q['search_terms'][0] != $q['s'] ) {
				$search .= " OR ({$GLOBALS['wpdb']->posts}.post_title LIKE '{$n}{$term}{$n}') OR ({$GLOBALS['wpdb']->posts}.post_content LIKE '{$n}{$term}{$n}')";
			}

			if ( ! empty( $search ) ) {
				$search = " AND ({$search}) ";
				if ( ! is_user_logged_in() ) {
					$search .= " AND ({$GLOBALS['wpdb']->posts}.post_password = '') ";
				}
			}
		}
		LogService::legacy_log( $search, 3 );

		return $search . $where;
	}

	/**
	 * Runs a scheduled backup
	 */
	public function run_backup(): array {
		LogService::legacy_log( 'backup run..', 2 );

		return ( new Backup( $this ) )->do_backup();
	}

	//** FULL VERSION

	/**
	 * Register for superproxy
	 */
	public function superproxy_reg() {
		$url = "http://superproxy.transposh.net/?action=register&version=0.1&entry_url=" . admin_url( 'admin-ajax.php' );

		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			Logging\Query_Monitor_Logger::error( $response );

			wp_send_json_error( $response );
			exit();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$error = new WP_Error( 'super_proxy_error', 'Super Proxy returned a non 200 response code', [
				'code' => $code,
			] );
			Logging\Query_Monitor_Logger::error( $error );

			wp_send_json_error( $response );
			exit();
		}

		$content = wp_remote_retrieve_body( $response );
		if ( empty( $content ) ) {
			$error = new WP_Error( 'super_proxy_error', 'Super Proxy returned an empty response', [
				'code' => $code,
			] );
			Logging\Query_Monitor_Logger::error( $error );

			wp_send_json_error( $response );
			exit();
		}

		try {
			$json = json_decode( $content, false, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			Logging\Query_Monitor_Logger::error( $e );

			wp_send_json_error( $e->getCode() );
			exit();
		}

		if ( isset( $json->id ) ) {
			$this->options->superproxy_key = $json->id;
			$this->options->update_options();
		}
		if ( isset( $json->ips ) ) {
			try {
				$this->options->superproxy_ips = json_encode( $json->ips, JSON_THROW_ON_ERROR );
			} catch ( JsonException $e ) {
				Logging\Query_Monitor_Logger::error( $e );

				wp_send_json_error( $e->getCode() );
				exit();
			}
			$this->options->update_options();
		}

		wp_send_json_success();
		exit();
	}

	//** FULLSTOP

	/**
	 * Runs a restore
	 */
	public function run_restore() {
		LogService::legacy_log( 'restoring..', 2 );
		$my_transposh_backup = new Backup( $this );
		$my_transposh_backup->do_restore();
	}

	/**
	 * Adding the comment meta language, for later use in display
	 * TODO: can use the language detection feature of some translation engines
	 *
	 * @param int $post_id
	 */
	public function add_comment_meta_settings( $post_id ) {
		if ( Utilities::get_language_from_url( Utilities::get_clean_server_var( 'HTTP_REFERER' ), $this->home_url ) ) {
			add_comment_meta( $post_id, 'tp_language', Utilities::get_language_from_url( Utilities::get_clean_server_var( 'HTTP_REFERER' ), $this->home_url ), true );
		}
	}

	/**
	 * After a user adds a comment, makes sure he gets back to the proper language
	 * TODO - check the three other params
	 *
	 * @param string $url
	 *
	 * @return string fixed url
	 */
	public function comment_post_redirect_filter( $url ) {
		$lang = Utilities::get_language_from_url( Utilities::get_clean_server_var( 'HTTP_REFERER' ), $this->home_url );
		if ( $lang ) {
			$url = Utilities::rewrite_url_lang_param( $url, $this->home_url, $this->enable_permalinks_rewrite, $lang, $this->edit_mode );
		}

		return $url;
	}

	/**
	 * Modify comments to include the relevant language span
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function comment_text_wrap( $text ) {
		$comment_lang = get_comment_meta( get_comment_ID(), 'tp_language', true );
		if ( $comment_lang ) {
			$text = "<span lang =\"$comment_lang\">" . $text . "</span>";
			if ( str_contains( $text, '<a href="' . $this->home_url ) ) {
				$text = str_replace( '<a href="' . $this->home_url, '<a lang="' . $this->options->default_language . '" href="' . $this->home_url, $text );
			}
		}
		LogService::legacy_log( "$comment_lang " . get_comment_ID(), 4 );

		return $text;
	}

	/**
	 * Modify posts to have language wrapping
	 *
	 * @param string $text the post text (or title text)
	 *
	 * @return string wrapped text
	 * @global int $id the post id
	 */
	public function post_content_wrap( $text ) {
		if ( ! isset( $GLOBALS['id'] ) ) {
			return $text;
		}
		$lang = get_post_meta( $GLOBALS['id'], 'tp_language', true );
		if ( $lang ) {
			$text = "<span lang =\"$lang\">" . $text . "</span>";
			if ( str_contains( $text, '<a href="' . $this->home_url ) ) {
				$text = str_replace( '<a href="' . $this->home_url, '<a lang="' . $this->options->default_language . '" href="' . $this->home_url, $text );
			}
		}

		return $text;
	}

	/**
	 * Modify post title to have language wrapping
	 *
	 * @param string $text the post title text
	 *
	 * @return string wrapped text
	 */
	public function post_wrap( $text, $id = 0 ) {
		$id = ( is_object( $id ) ) ? $id->ID : $id;
		if ( ! $id ) {
			return $text;
		}
		$lang = get_post_meta( $id, 'tp_language', true );
		if ( $lang ) {
			if ( str_contains( Utilities::get_clean_server_var( 'REQUEST_URI' ), 'wp-admin/edit' ) ) {
				LogService::legacy_log( 'iamhere?' . strpos( Utilities::get_clean_server_var( 'REQUEST_URI' ),
						'wp-admin/edit' ) );
				$plugpath = @parse_url( $this->transposh_plugin_url, PHP_URL_PATH );
				[ $langeng, $langorig, $langflag ] = explode( ',', Constants::$languages[ $lang ] );
				//$text = OpenTransposh\Core\transposh_utils::display_flag("$plugpath/img/flags", $langflag, $langorig, false) . ' ' . $text;
				$text = "[$lang] " . $text;
			} else {
				$text = "<span lang =\"$lang\">" . $text . "</span>";
			}
		}

		return $text;
	}

	/**
	 * This function enables the correct parsing of translated URLs
	 *
	 * @param array $query
	 *
	 * @return $query
	 * @global object $wp the wordpress global
	 */
	public function request_filter( $query ) {
		//We only do this once, and if we have a lang
		$requri = Utilities::get_clean_server_var( 'REQUEST_URI' );
		$lang   = Utilities::get_language_from_url( $requri, $this->home_url );
		if ( $lang && ! $this->got_request ) {
			LogService::legacy_log( 'Trying to find original url' );
			$this->got_request = true;
			// the trick is to replace the URI and put it back afterwards
			$_SERVER['REQUEST_URI'] = Utilities::get_original_url( $requri, '', $lang, [
				$this->database,
				'fetch_original'
			] );
			global $wp;
			$wp->parse_request();
			$query                  = $wp->query_vars;
			$_SERVER['REQUEST_URI'] = $requri;
			LogService::legacy_log( 'new query vars are' );
			LogService::legacy_log( $query );
		}

		return $query;
	}

	/**
	 * This function adds our markings around gettext results
	 *
	 * @param string $translation
	 * @param string $orig
	 *
	 * @return string
	 */
	public function transposh_gettext_filter( $translation, $orig, $domain ) {
		if ( $this->is_special_page( Utilities::get_clean_server_var( 'REQUEST_URI' ) ) || ( $this->options->is_default_language( $this->tgl ) && ! $this->options->enable_default_translate ) ) {
			return $translation;
		}
		LogService::legacy_log( "($translation, $orig, $domain)", 5 );
		// HACK - TODO - FIX
		if ( in_array( $domain, Constants::$ignored_po_domains ) ) {
			return $translation;
		}
		if ( $translation != $orig && $translation != "'" ) { // who thought about this, causing apostrophes to break
			$translation = TP_GTXT_BRK . $translation . TP_GTXT_BRK_CLOSER;
		}

		return str_replace( [
			'%s',
			'%1$s',
			'%2$s',
			'%3$s',
			'%4$s',
			'%5$s'
		], [
			TP_GTXT_IBRK . '%s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%1$s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%2$s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%3$s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%4$s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%5$s' . TP_GTXT_IBRK_CLOSER
		], $translation );
	}

	/**
	 * This function adds our markings around ngettext results
	 *
	 * @param string $translation
	 * @param string $single
	 * @param string $plural
	 *
	 * @return string
	 */
	public function transposh_ngettext_filter( $translation, $single, $plural, $domain ) {
		if ( $this->is_special_page( Utilities::get_clean_server_var( 'REQUEST_URI' ) ) || ( $this->options->is_default_language( $this->tgl ) && ! $this->options->enable_default_translate ) ) {
			return $translation;
		}
		LogService::legacy_log( "($translation, $single, $plural, $domain)", 4 );
		if ( in_array( $domain, Constants::$ignored_po_domains ) ) {
			return $translation;
		}
		if ( $translation != $single && $translation != $plural ) {
			$translation = TP_GTXT_BRK . $translation . TP_GTXT_BRK_CLOSER;
		}

		return str_replace( [
			'%s',
			'%1$s',
			'%2$s',
			'%3$s',
			'%4$s',
			'%5$s'
		], [
			TP_GTXT_IBRK . '%s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%1$s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%2$s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%3$s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%4$s' . TP_GTXT_IBRK_CLOSER,
			TP_GTXT_IBRK . '%5$s' . TP_GTXT_IBRK_CLOSER
		], $translation );
	}

	/**
	 * This function makes sure wordpress sees the appropriate locale on translated pages for .po/.mo and mu integration
	 *
	 * @param string $locale
	 *
	 * @return string
	 */
	public function transposh_locale_filter( $locale ) {
		$lang = Utilities::get_language_from_url( Utilities::get_clean_server_var( 'REQUEST_URI' ), $this->home_url );
		if ( ! $this->options->is_active_language( $lang ) ) {
			$lang = '';
		}
		if ( ! $lang ) {
			if ( ! $this->options->transposh_locale_override ) {
				return $locale;
			}
			$lang = $this->options->default_language;
		}
		$locale = Constants::get_language_locale( $lang );

		return $locale ?: $lang;
	}

	/**
	 * @param array $atts
	 * @param string $content
	 *
	 * @return string
	 */
	public function tp_shortcode( $atts, $content = null ) {
		$only_class = '';
		$lang       = '';
		$nt_class   = '';

		if ( ! is_array( $atts ) ) { // safety check
			return do_shortcode( $content );
		}

		LogService::legacy_log( $atts );
		LogService::legacy_log( $content );

		if ( isset( $atts['not_in'] ) && $this->target_language && stripos( $atts['not_in'], $this->target_language ) !== false ) {
			return;
		}

		if ( isset( $atts['locale'] ) || in_array( 'locale', $atts ) ) {
			if ( isset( $atts['lang'] ) && stripos( $atts['lang'], $this->target_language ) === false ) {
				return;
			}

			return get_locale();
		}

		if ( isset( $atts['mylang'] ) || in_array( 'mylang', $atts ) ) {
			if ( isset( $atts['lang'] ) && stripos( $atts['lang'], $this->target_language ) === false ) {
				return;
			}

			return $this->target_language;
		}

		if ( isset( $atts['lang'] ) ) {
			$lang = ' lang="' . $atts['lang'] . '"';
		}

		if ( isset( $atts['only'] ) || in_array( 'only', $atts ) ) {
			$only_class = ' class="' . ONLY_THISLANGUAGE_CLASS . '"';
			LogService::legacy_log( $atts['lang'] . " " . $this->target_language );
//            if ($atts['lang'] != $this->target_language) {
//                return;
//            }
		}

		if ( isset( $atts['no_translate'] ) ) {
			$nt_class = ' class="' . NO_TRANSLATE_CLASS . '"';
		}

		if ( isset( $atts['widget'] ) ) {
			ob_start();
			$this->widget->widget( [
				'before_widget' => '',
				'before_title'  => '',
				'after_widget'  => '',
				'after_title'   => ''
			], [ 'title' => '', 'widget_file' => $atts['widget'] ], true );
			$widgetcontent = ob_get_contents();
			ob_end_clean();

			return $widgetcontent . do_shortcode( $content );
		}

		if ( $lang || $only_class || $nt_class ) {
			$newcontent = do_shortcode( $content );
			$newcontent = str_replace( '<p>', '<p><span' . $only_class . $nt_class . $lang . '>', $newcontent );
			$newcontent = str_replace( '</p>', '</span></p>', $newcontent );

			return '<span' . $only_class . $nt_class . $lang . '>' . $newcontent . '</span>';
		} else {
			return do_shortcode( $content );
		}
	}

	// Super Proxy
	public function on_ajax_nopriv_proxy() {
		// Check if enabled
		if ( ! $this->options->enable_superproxy ) {
			$errstr = "Error: 500: Not enabled";
			LogService::legacy_log( $errstr );
			die( $errstr );
		}

		// Check requester IP to be allowed
		$ips = json_decode( $this->options->superproxy_ips );
		if ( ! in_array( Utilities::get_clean_server_var( 'REMOTE_ADDR' ), $ips ) ) {
			$errstr = "Error: 503: Unauthorized " . Utilities::get_clean_server_var( 'REMOTE_ADDR' );
			LogService::legacy_log( $errstr );
			die( $errstr );
		}

		$encoded_url     = $_GET['url'];
		$url             = base64_decode( $encoded_url );
		$request_headers = getallheaders();

		unset( $request_headers['Host'], $request_headers['Content-Length'] );
		$headers = [];
		foreach ( $request_headers as $name => $value ) {
			$headers[] = "$name: $value";
		}

		$response = wp_remote_request( $url, [
			'method'  => Utilities::get_clean_server_var( 'REQUEST_METHOD' ),
			'headers' => $headers,
			'body'    => $_POST
		] );

		if ( is_wp_error( $response ) ) {
			Logging\Query_Monitor_Logger::error( $response );

			wp_send_json_error( $response );
			exit();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$error = new WP_Error( 'super_proxy_error', 'Super Proxy returned a non 200 response code', [
				'code' => $code,
			] );
			Logging\Query_Monitor_Logger::error( $error );

			wp_send_json_error( $response );
			exit();
		}

		$content = wp_remote_retrieve_body( $response );
		if ( empty( $content ) ) {
			$error = new WP_Error( 'super_proxy_error', 'Super Proxy returned an empty response', [
				'code' => $code,
			] );
			Logging\Query_Monitor_Logger::error( $error );

			wp_send_json_error( $response );
			exit();
		}

		echo $content;
		die();
	}

	// transposh translation proxy ajax wrapper

	public function on_ajax_nopriv_tp_tp() {
		// we need curl for this proxy
		if ( ! function_exists( 'curl_init' ) ) {
			return;
		}

		// we are permissive for sites using multiple domains and such
		Ajax_Controller::allow_cors();
		// get the needed params
		$tl = sanitize_text_field( $_GET['tl'] ?? '' );

		// avoid handling inactive languages
		if ( ! $this->options->is_active_language( $tl ) ) {
			return;
		}
		$sl = sanitize_text_field( $_GET['sl'] ?? '');

		$suggestmode = false; // the suggest mode takes one string only, and does not save to the database
		if ( isset( $_GET['m'] ) && $_GET['m'] === 's' ) {
			$suggestmode = true;
		}
		if ( $suggestmode ) {
			$q = urlencode( stripslashes( $_GET['q'] ) );
			if ( ! $q ) {
				return;
			}
		} else {
			// item count
			$i = 0;
			$q = [];
			foreach ( $_GET['q'] as $p ) {
				[ , $trans ] = $this->database->fetch_translation( stripslashes( $p ), $tl );
				if ( ! $trans ) {
					$q[] = urlencode( stripslashes( $p ) ); // fix for the + case?
				} else {
					$r[ $i ] = $trans;
				}
				$i ++;
			}
		}
		if ( $q ) {
			switch ( $_GET['e'] ) {
				case 'g': // google
					if ( ! $sl ) {
						$sl = 'auto';
					}
					if ( ! in_array( $tl, Constants::$engines['g']['langs'] ) ) // nope...
					{
						return;
					}
					$source = 1;
					$result = $this->get_google_translation( $tl, $sl, $q );
					break;
				case 'y': // yandex
					if ( ! in_array( $tl, Constants::$engines['y']['langs'] ) ) // nope...
					{
						return;
					}
					$source = 4;
					$result = $this->get_yandex_translation( $tl, $sl, $q );
					break;
				case 'u': // baidu
					if ( ! in_array( $tl, Constants::$engines['u']['langs'] ) ) // nope...
					{
						return;
					}
					$source = 5;
					$result = $this->get_baidu_translation( $tl, $sl, $q );
					break;

				default:
					die( 'engine not supported' );
			}

			if ( $result === false ) {
				echo 'Proxy attempt failed<br>';
				die();
			}
		}

		// encode results
		$jsonout = new stdClass();
		if ( $suggestmode ) {
			$jsonout->result = $result;
		} else {
			// here we match online results with cached ones
			$k = 0;
			for ( $j = 0; $j < $i; $j ++ ) {
				if ( isset( $r[ $j ] ) ) {
					$jsonout->results[] = $r[ $j ];
				} elseif ( isset( $result[ $k ] ) ) {
					// TODO: no value - original?
					// There are no results? need to check!
					$jsonout->results[] = $result[ $k ];
					$k ++;
				}
			}

			//  // we send here because update translation dies... TODO: fix this mess
			//          echo json_encode($jsonout);
//
			// do the db dance - a bit hackish way to insert downloaded translations directly to the db without having
			// to pass through the user and collect $200
			if ( $k ) {
				$_POST['items'] = $k;
				$_POST['ln0']   = $tl;
				$_POST['sr0']   = $source; // according to used engine
				$k              = 0;
				for ( $j = 0; $j < $i; $j ++ ) {
					if ( ! isset( $r[ $j ] ) && isset( $jsonout->results[ $j ] ) ) {
						$_POST["tk$k"] = stripslashes( $_GET['q'][ $j ] ); // stupid, but should work
						$_POST["tr$k"] = $jsonout->results[ $j ];
						$k ++;
					}
				}
				LogService::legacy_log( 'updating! :)' );
				LogService::legacy_log( $_POST );
				$this->database->update_translation();
			}
		}

		// send out result
		// fix CVE-2021-24910
		if ( isset( $jsonout->result ) ) {
			foreach ( $jsonout->result as $key => $result ) {
				$jsonout->result[ $key ] = esc_html( $result );
			}
		}
		echo json_encode( $jsonout );
		die();
	}

	// Proxied Yandex translate suggestions
	public function get_yandex_translation( $tl, $sl, $q ) {
		$sid       = '';
		$timestamp = 0;
		if ( get_option( TRANSPOSH_OPTIONS_YANDEXPROXY, [] ) ) {
			[ $sid, $timestamp ] = get_option( TRANSPOSH_OPTIONS_YANDEXPROXY, [] );
		}
		if ( ( $sid == '' ) && ( time() - TRANSPOSH_YANDEXPROXY_DELAY > $timestamp ) ) {
			$response = wp_remote_get( 'https://translate.yandex.com/', [
				'headers' => [
					'Referer'    => 'https://translate.yandex.com/',
					'User-Agent' => Utilities::get_clean_server_var( "HTTP_USER_AGENT" ),
				],
			] );

			if ( is_wp_error( $response ) ) {
				$response->add_data( 'yandex', 'engine' );
				Logging\Query_Monitor_Logger::error( $response );

				return false;
			}

			$content = wp_remote_retrieve_body( $response );
			if ( empty( $content ) ) {
				Logging\Query_Monitor_Logger::error( 'yandex response is empty' );

				return false;
			}

			$sidpos = strpos( $content, "SID: '" ) + 6;
			$newout = substr( $content, $sidpos );
			$sid    = substr( $newout, 0, strpos( $newout, "'," ) - 2 );

			update_option( TRANSPOSH_OPTIONS_YANDEXPROXY, [ $sid, time() ] );
		}

		if ( ! $sid ) {
			Logging\Query_Monitor_Logger::emergency( 'No SID for yandex, cannot proceed: at' . $timestamp );

			return false;
		}

		if ( $sl ) {
			$sl .= '-';
		}
		$qstr = '';
		if ( is_array( $q ) ) {
			foreach ( $q as $v ) {
				$qstr .= '&text=' . $v;
			}
		} else {
			$qstr = '&text=' . $q;
		}

		$url = 'https://translate.yandex.net/api/v1/tr.json/translate?lang=' . $sl . $tl . $qstr . '&srv=tr-url&id=' . $sid . '-0-0';

		$response = wp_remote_get(
			$url, [
			'headers' => [
				'Referer' => 'https://translate.yandex.com/',
				'User-Agent' => Utilities::get_clean_server_var( "HTTP_USER_AGENT" ),
			],
		] );

		if ( is_wp_error( $response ) ) {
			$response->add_data( 'yandex', 'engine' );
			$response->add_data( $url, 'url' );
			Logging\Query_Monitor_Logger::error( $response );

			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$error = new WP_Error( 'yandex_error', 'Yandex returned a non 200 response code', [
				'code' => $code,
				'url'  => $url,
			] );
			Logging\Query_Monitor_Logger::error( $error );

			return false;
		}

		$content = wp_remote_retrieve_body( $response );
		if ( empty( $content ) ) {
			$error = new WP_Error( 'yandex_error', 'Yandex returned no body content', [
				'url'     => $url,
			] );
			Logging\Query_Monitor_Logger::error( $error );

			return false;
		}

		try {
			$json = json_decode( $content, false, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			Logging\Query_Monitor_Logger::error( $e );

			return false;
		}


		if ( $json->code != 200 ) {
			Logging\Query_Monitor_Logger::error( "Yandex responded with an error code ($json->code) in body" );
			if ( $json->code == 406 || $json->code == 405 ) { //invalid session
				update_option( TRANSPOSH_OPTIONS_YANDEXPROXY, [ '', time() ] );
			}

			return false;
		}

		return $json->text;
	}

	// Proxied Baidu translate suggestions
	public function get_baidu_translation( $tl, $sl, $q ) {
		$qstr = 'to=' . ( Constants::$engines['u']['langconv'][ $tl ] ?? $tl );
		if ( $sl ) {
			$qstr .= '&from=' . ( ( isset( Constants::$engines['u']['langconv'][ $tl ] ) ) ? Constants::$engines['u']['langconv'][ $sl ] : $sl );
		}
		$qstr .= '&query=';
		if ( is_array( $q ) ) {
			foreach ( $q as $v ) {
				$qstr .= $v . "%0A";
			}
		} else {
			$qstr .= $q;
		}

		$response = wp_remote_post( 'https://fanyi.baidu.com/v2transapi', [
			'body'    => $qstr,
			'headers' => [
				'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With' => 'XMLHttpRequest',
				'Referer'          => 'https://fanyi.baidu.com/',
			],
		] );

		if ( is_wp_error( $response ) ) {
			$response->add_data( 'baidu', 'engine' );
			$response->add_data( $qstr, 'body' );
			Logging\Query_Monitor_Logger::error( $response );

			return false;
		}

		try {
			$json = json_decode( wp_remote_retrieve_body( $response ), false, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			Logging\Query_Monitor_Logger::error( $e );

			return false;
		}

		$result = [];
		foreach ( $json->trans_result->data as $val ) {
			$result[] = $val->dst;
		}

		return $result;
	}

	public function _bitwise_zfrs( $a, $b ) {
		if ( $b == 0 ) {
			return $a;
		}

		return ( $a >> $b ) & ~( 1 << ( 8 * PHP_INT_SIZE - 1 ) >> ( $b - 1 ) );
	}

	public function hq( $a, $chunk ) {
		for ( $offset = 0; $offset < strlen( $chunk ) - 2; $offset += 3 ) {
			$b = $chunk[ $offset + 2 ];
			$b = ( $b >= "a" ) ? ord( $b ) - 87 : (int) $b;
			$b = ( $chunk[ $offset + 1 ] == "+" ) ? $this->_bitwise_zfrs( $a, $b ) : $a << $b;
			$a = ( $chunk[ $offset ] == "+" ) ? $a + $b & 4294967295 : $a ^ $b;
		}

		return $a;
	}

	/**
	 * Hey googler, if you are reading this, it means that you are actually here, why won't we work together on this?
	 */
	public function iq( $input, $error ) {
		$e     = explode( ".", $error );
		$value = (int) $e[0];
		for ( $i = 0; $i < strlen( $input ); $i ++ ) {
			$value += ord( $input[ $i ] );
			$value = $this->hq( $value, "+-a^+6" );
		}
		$value = $this->hq( $value, "+-3^+b+-f" );
		$value ^= (int) $e[1];
		if ( 0 > $value ) {
			$value = $value & 2147483647 + 2147483648;
		}
		$x = $value % 1E6;

		return $x . "." . ( $x ^ $error );
	}

// Proxied translation for google translate
	public function get_google_translation( $tl, $sl, $q ) {
		if ( get_option( TRANSPOSH_OPTIONS_GOOGLEPROXY, [] ) ) {
			[ $googlemethod, $timestamp ] = get_option( TRANSPOSH_OPTIONS_GOOGLEPROXY, [] );
			//$googlemethod = 0;
			//$timestamp = 0;
			LogService::legacy_log( "Google method $googlemethod, " . date( DATE_RFC2822,
					$timestamp ) . ", current:" . date( DATE_RFC2822,
					time() ) . " Delay:" . TRANSPOSH_GOOGLEPROXY_DELAY, 1 );
		} else {
			LogService::legacy_log( "Google is clean", 1 );
			$googlemethod = 0;
		}
		// we preserve the method, and will ignore lower methods for the given delay period
		if ( isset( $timestamp ) && ( time() - TRANSPOSH_GOOGLEPROXY_DELAY > $timestamp ) ) {
			delete_option( TRANSPOSH_OPTIONS_GOOGLEPROXY );
		}
		LogService::legacy_log( 'Google proxy initiated', 1 );
		$qstr  = '';
		$iqstr = '';
		if ( is_array( $q ) ) {
			foreach ( $q as $v ) {
				$qstr  .= '&q=' . $v;
				$iqstr .= urldecode( $v );
			}
		} else {
			$qstr  = '&q=' . $q;
			$iqstr = urldecode( $q );
		}
		// we avoid curling we had all results prehand
		static $urls = [
			'https://translate.google.com',
			'https://translate.googleapis.com',
			'http://212.199.205.226',
			'http://74.125.195.138',
		];

		$attempt = 1;
		$failed  = true;
		foreach ( $urls as $gurl ) {
			if ( $googlemethod < $attempt && $failed ) {
				$failed = false;
				$url    = $gurl . '/translate_a/t?client=te&v=1.0&tl=' . $tl . '&sl=' . $sl . '&tk=' . $this->iq( $iqstr,
						'406448.272554134' );

				// replace curl with wp_remote_post
				$response = wp_remote_post( $url, [
					'body'    => $qstr,
					'headers' => [
						'User-Agent'             => Utilities::get_clean_server_var( "HTTP_USER_AGENT" ),
						'Accept'                 => '*/*',
						'Accept-Language'        => 'en-US,en;q=0.8',
						'Accept-Encoding'        => 'gzip,deflate,sdch',
						'Content-Type'           => 'application/x-www-form-urlencoded;charset=UTF-8',
						'X-HTTP-Method-Override' => 'GET',
						'Origin'                 => $gurl,
						'Referer'                => $gurl,
						'Accept-Charset'         => 'utf-8;',
						'Connection'             => 'keep-alive',
					],
					'timeout' => 10,
				] );

				if ( is_wp_error( $response ) ) {
					$response->add_data( $attempt, 'attempt' );
					$response->add_data( 'google', 'engine' );
					$response->add_data( $url, 'url' );
					$response->add_data( $q, 'query' );
					$response->add_data( $qstr, 'body' );
					Logging\Query_Monitor_Logger::error( $response );

					update_option( TRANSPOSH_OPTIONS_GOOGLEPROXY, [ $attempt, time() ] );
					$failed = true;
					continue;
				}

				$code = wp_remote_retrieve_response_code( $response );
				if ( 200 !== $code ) {
					$error = new WP_Error( 'google_error', 'Google returned a non 200 response code', [
						'code'    => $code,
						'attempt' => $attempt,
						'engine'  => 'google',
						'url'     => $url,
						'query'   => $q,
						'body'    => $qstr,
					] );
					Logging\Query_Monitor_Logger::error( $error );

					update_option( TRANSPOSH_OPTIONS_GOOGLEPROXY, [ $attempt, time() ] );
					$failed = true;
					continue;
				}

				$content = wp_remote_retrieve_body( $response );
				if ( empty( $content ) ) {
					$error = new WP_Error( 'google_error', 'Google returned no body content', [
						'code'    => $code,
						'attempt' => $attempt,
						'engine'  => 'google',
						'url'     => $url,
						'query'   => $q,
						'body'    => $qstr,
					] );
					Logging\Query_Monitor_Logger::error( $error );

					update_option( TRANSPOSH_OPTIONS_GOOGLEPROXY, [ $attempt, time() ] );
					$failed = true;
					continue;
				}
			}
			$attempt ++;
		}

		if ( $failed ) {
			Logging\Query_Monitor_Logger::emergency( 'Out of options to get Google translation' );

			return false;
		}

		try {
			$json = json_decode( $content, false, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$content = str_replace( ',,', ',', $content );

			try {
				$json = json_decode( $content, false, 512, JSON_THROW_ON_ERROR );
			} catch ( JsonException $e ) {
				Logging\Query_Monitor_Logger::error( $e );

				return false;
			}
		}

		$result = [];
		if ( is_array( $json ) ) {
			if ( is_array( $json[0] ) ) {
				foreach ( $json as $val ) {
					// need to drill
					while ( is_array( $val ) ) {
						$val = $val[0];
					}
					$result[] = $val;
				}
			} else {
				$result = $json;
			}
		} else {
			$result[] = $json;
		}

		return $result;
	}

	/**
	 * Queue for One Hour Translate
	 */
	public function on_ajax_nopriv_tp_oht() {
		// Admin access only
		if ( ! current_user_can( 'manage_options' ) ) {
			echo "only admin is allowed";
			die();
		}
		$oht     = get_option( TRANSPOSH_OPTIONS_OHT, [] );
		$orglang = isset( $_GET['orglang'] ) ? sanitize_text_field( $_GET['orglang'] ) : $this->options->default_language;
		$lang    = sanitize_text_field( $_GET['lang'] ?? '' );
		$token   = sanitize_text_field( $_GET['token'] ?? '' );
		$key     = $token . '@' . $lang . '@' . $orglang;
		if ( isset( $oht[ $key ] ) ) {
			unset( $oht[ $key ] );
			LogService::legacy_log( 'oht false' );
			echo json_encode( false );
		} else {
			$oht[ $key ] = [
				'q'  => $_GET['q'],
				'l'  => $lang,
				'ol' => $orglang,
				't'  => $token
			];
			LogService::legacy_log( 'oht true' );
			echo json_encode( true );
		}

		update_option( TRANSPOSH_OPTIONS_OHT, $oht );

		// we will make an oht send event in defined time
		wp_clear_scheduled_hook( 'transposh_oht_event' );
		wp_schedule_single_event( time() + TRANSPOSH_OHT_DELAY, 'transposh_oht_event' );

		die();
	}

	/**
	 * OHT event running
	 */
	public function run_oht() {
		LogService::legacy_log( "oht should run", 2 );
		$oht = get_option( TRANSPOSH_OPTIONS_OHT, [] );
		LogService::legacy_log( $oht, 3 );
		$ohtp      = get_option( TRANSPOSH_OPTIONS_OHT_PROJECTS, [] );
		$projectid = time();
		//send less data
		$ohtbody = [];
		$pcount  = 0;
		foreach ( $oht as $arr ) {
			$pcount ++;
			LogService::legacy_log( $arr );
			$ohtbody[ $arr['t'] ] = [ 'q' => $arr['q'], 'l' => $arr['l'], 'ol' => $arr['ol'] ];
		}
		$ohtbody['pid']      = $projectid;
		$ohtbody['id']       = $this->options->oht_id;
		$ohtbody['key']      = $this->options->oht_key;
		$ohtbody['callback'] = admin_url( 'admin-ajax.php' );
		$ohtbody['homeurl']  = $this->home_url;
		LogService::legacy_log( $ohtbody );
		// now we send this, add to log that it was sent to oht.. we'll also add a timer to make sure it gets back to us
		$ret = wp_remote_post( 'http://svc.transposh.org/oht.php', [ 'body' => $ohtbody ] );
		if ( $ret['response']['code'] == '200' ) {
			delete_option( TRANSPOSH_OPTIONS_OHT );
			$ohtp[ $projectid ] = $pcount;
			update_option( TRANSPOSH_OPTIONS_OHT_PROJECTS, $ohtp );
		} else {
			LogService::legacy_log( $ret, 1 );
		}
	}

	/**
	 * callback from one hour translation
	 */
	public function on_ajax_nopriv_tp_ohtcallback() {
		$ohtp = get_option( TRANSPOSH_OPTIONS_OHT_PROJECTS, [] );
		LogService::legacy_log( $ohtp );
		if ( $ohtp[ $_POST['projectid'] ] ) {
			LogService::legacy_log( $_POST['projectid'] . " was found and will be processed" );
			do_action( 'transposh_oht_callback' );
			LogService::legacy_log( $_POST );
			$ohtp[ $_POST['projectid'] ] -= $_POST['items'];
			if ( $ohtp[ $_POST['projectid'] ] <= 0 ) {
				unset( $ohtp[ $_POST['projectid'] ] );
			}
			LogService::legacy_log( $ohtp );
			update_option( TRANSPOSH_OPTIONS_OHT_PROJECTS, $ohtp );
			$this->database->update_translation( "OHT" );
		}
		die();
	}

	// getting translation alternates
	public function on_ajax_nopriv_tp_trans_alts() {
		Ajax_Controller::allow_cors();
		$this->database->get_translation_alt( $_GET['token'] );
		die();
	}

	// set the cookie with ajax, no redirect needed
	public function on_ajax_nopriv_tp_cookie() {
		setcookie( 'TR_LNG', Utilities::get_language_from_url( Utilities::get_clean_server_var( 'HTTP_REFERER' ), $this->home_url ), time() + 90 * 24 * 60 * 60, COOKIEPATH, COOKIE_DOMAIN );
		LogService::legacy_log( 'Cookie ' . Utilities::get_language_from_url( Utilities::get_clean_server_var( 'HTTP_REFERER' ),
				$this->home_url ) );
		die();
	}

	// Set our cookie and return (if no js works - or we are in the default language)
	public function on_ajax_nopriv_tp_cookie_bck() {
		global $my_transposh_plugin;
		setcookie( 'TR_LNG', Utilities::get_language_from_url( Utilities::get_clean_server_var( 'HTTP_REFERER' ), $this->home_url ), time() + 90 * 24 * 60 * 60, COOKIEPATH, COOKIE_DOMAIN );
		if ( Utilities::get_clean_server_var( 'HTTP_REFERER' ) ) {
			$this->tp_redirect( Utilities::get_clean_server_var( 'HTTP_REFERER' ) );
		} else {
			$this->tp_redirect( $my_transposh_plugin->home_url );
		}
		die();
	}

	/**
	 * @return void
	 */
	private function initialize_logger(): void {
		if ( $this->options->debug_enable ) {
			if ( defined( 'QM_VERSION' ) ) {
				$logger = new Query_Monitor_Logger();
			} else {
				$logger              = new Logger();
				$logger->show_caller = true;
				$logger->set_debug_level( $this->options->debug_loglevel );
				$logger->set_log_file( $this->options->debug_logfile );
				$logger->set_remoteip( $this->options->debug_remoteip );
			}

			$this->logger = $logger;
			LogService::set_instance( $logger );
		}
	}

	/**
	 * @return Logger|Query_Monitor_Logger|NullLogger
	 */
	public function get_logger(): Query_Monitor_Logger|NullLogger|Logger {
		return $this->logger;
	}
}

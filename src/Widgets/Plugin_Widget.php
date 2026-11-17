<?php

namespace OpenTransposh\Widgets;

use OpenTransposh\Core\Utilities;
use OpenTransposh\Logging\LogService;
use OpenTransposh\Plugin;
use WP_Widget;

/**
 * Provides the sidebar widget instance for selecting a language and switching between edit/view
 * mode.
 */
class Plugin_Widget extends WP_Widget {
	public const TRANSPOSH_WIDGET_PREFIX = 'tpw_';

	/** @var Plugin Container class */
	private $transposh;

	/** @staticvar boolean Contains the fact that this is our first run */
	public static $first_init = true;

	/** @staticvar int Counts call to the widget do to generate unique IDs */
	public static int $number_of_draw_calls = 0;

	public function __construct(?Plugin $transposh = null) {
		$this->transposh = $transposh ?? Plugin::get_instance();

		// Widget control defenitions
		$widget_ops  = array(
			'classname'   => 'widget_transposh',
			'description' => __( 'Open Transposh language selection widget', TRANSPOSH_TEXT_DOMAIN )
		);
		$control_ops = array( 'width' => 200, 'height' => 300 );
		parent::__construct( 'transposh', __( 'Open Transposh' ), $widget_ops, $control_ops );

		add_action( 'widgets_init', static function () {
			register_widget( Plugin_Widget::class );
		} );
		// We only need to add those actions once, makes life simpler
		if ( is_active_widget( false, false, $this->id_base ) && self::$first_init ) {
			self::$first_init = false;
			if ( ! is_admin() ) { // is admin page
				add_action( 'wp_print_styles', array( &$this, 'add_transposh_widget_css' ) );
				add_action( 'wp_print_scripts', array( &$this, 'add_transposh_widget_js' ) );
			}
		}
	}

	/**
	 * Saves the widgets settings. (override of wp_widget)
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		LogService::legacy_log( $instance );
		LogService::legacy_log( $new_instance );
		$instance['title']       = strip_tags( stripslashes( $new_instance['title'] ) );
		$instance['widget_file'] = strip_tags( stripslashes( $new_instance['widget_file'] ) );

		return $instance;
	}

	/**
	 * Creates the edit form for the widget. (override of wp_widget)
	 *
	 */
	public function form( $instance ) {
		// Defaults
		/* TRANSLATORS: this will be the default widget title */
		$instance = wp_parse_args( (array) $instance, array( 'title' => __( 'Translation', TRANSPOSH_TEXT_DOMAIN ) ) );

		// Output the options - title first
		$title = htmlspecialchars( $instance['title'] );

		echo '<p><label for="' . $this->get_field_name( 'title' ) . '">' . __( 'Title:', TRANSPOSH_TEXT_DOMAIN ) . ' <input class="widefat" id="' . $this->get_field_id( 'title' ) . '" name="' . $this->get_field_name( 'title' ) . '" type="text" value="' . $title . '" /></label></p>';

		// Followed by subwisgets selection
		$widgets = $this->get_widgets();
		if( defined( 'OPEN_TRANSPOSH_ENABLE_LEGACY_WIDGETS' ) ) {
			$widgets = array_merge( $widgets, $this->get_legacy_widgets() );
		}

		echo '<p><label for="' . $this->get_field_name( 'widget_file' ) . '">' . __( 'Style:', TRANSPOSH_TEXT_DOMAIN ) .
		     '<select id="' . $this->get_field_id( 'widget_file' ) . '" name="' . $this->get_field_name( 'widget_file' ) . '">';
		foreach ( $widgets as $file => $name ) {
			$selected = ( isset( $instance['widget_file'] ) && $instance['widget_file'] === $file ) ? ' selected="selected"' : '';
			echo "<option value=\"$file\"$selected>{$name}</option>";
		}
		echo '</select>' .
		     '</label></p>';
	}

	/*
	 * Make sure that this feature would not be used to include files in weird locations
	 * No more then one "/" no more than one "." - Also sanitize nonsense by WP
	 */

	public function sanitize_file( $file ) {
		$slashcount = substr_count( $file, '/' );
		if ( $slashcount > 1 ) {
			return ""; // We would not like more than one degree of recursion
		}
		if ( substr_count( $file, "." ) > 1 ) {
			return ""; // One dot should be enough for everyone - Bill Gates
		}
		if ( $slashcount == 1 ) {
			[ $dir, $filename ] = explode( "/", $file );
			$newfile = sanitize_file_name( $dir ) . "/" . sanitize_file_name( $filename );
		} else {
			$newfile = sanitize_file_name( $file );
		}

		return $newfile;
	}

	/**
	 * Loads the subwidget class code
	 */
	public function load_widget( $file = "" ) {
		LogService::legacy_log( "widget loaded: $file", 4 );
		// This is the support for user widgets that won't be deleted in newer versions
		if ( $file && $file[0] == '*' ) {
			$upload     = wp_upload_dir();
			$upload_dir = $upload['basedir'] . '/' . TRANSPOSH_DIR_UPLOAD . '/' . TRANSPOSH_DIR_WIDGETS;
			$widget_src = $upload_dir . '/' . $this->sanitize_file( substr( $file, 1 ) );
		} else {
			$widget_src = $this->transposh->transposh_plugin_dir . TRANSPOSH_DIR_WIDGETS . '/' . $this->sanitize_file( $file );
		}
		// Load default widget for non-working stuff
		if ( $file && is_file( $widget_src ) ) {
			include_once $widget_src;
		} else {
			$file = 'default/tpw_default.php';
			include_once $this->transposh->transposh_plugin_dir . TRANSPOSH_DIR_WIDGETS . '/' . $file;
		}

		// return just the file name, no extension
		return pathinfo( $file )['filename'];
	}

	/**
	 * Add custom css, i.e. transposh_widget.css, flags now override widget
	 */
	public function add_transposh_widget_css() {
		// first we discover all active widgets of ours, and aggregate the files
		$activewidgets = array();
		$settings      = $this->get_settings();
		foreach ( $settings as $key => $value ) {
			if ( is_active_widget( false, $this->id_base . '-' . $key, $this->id_base ) ) {
				// Mirror the load_widget() fallback: empty saved filenames load the default widget.
				$widget_file = $value['widget_file'] ?? '';
				if ( '' === $widget_file ) {
					$widget_file = 'default/tpw_default.php';
				}
				$activewidgets[ $widget_file ] = true;
			}
		}

		// we than load the classes and perform the css queueing
		foreach ( $activewidgets as $key => $v ) {
			$class = $this->load_widget( $key );
			if ( class_exists( $class ) ) {
				$tmpclass = new $class;
				if ( $key[0] == '*' ) {
					$upload = wp_upload_dir();
					$tmpclass->tp_widget_css( substr( $key, 1 ), $upload['basedir'] . '/' . TRANSPOSH_DIR_UPLOAD . '/', $upload['baseurl'] . '/' . TRANSPOSH_DIR_UPLOAD );
				} else {
					$tmpclass->tp_widget_css( $key, $this->transposh->transposh_plugin_dir, $this->transposh->transposh_plugin_url );
				}
			}
		}
		LogService::legacy_log( 'Added transposh_widget_css', 4 );
	}

	/**
	 * Add custom js, i.e. transposh_widget.js
	 */
	public function add_transposh_widget_js() {
		$activewidgets = array();
		$settings      = $this->get_settings();
		foreach ( $settings as $key => $value ) {
			if ( is_active_widget( false, $this->id_base . '-' . $key, $this->id_base ) ) {
				// Mirror the load_widget() fallback: empty saved filenames load the default widget.
				$widget_file = $value['widget_file'] ?? '';
				if ( '' === $widget_file ) {
					$widget_file = 'default/tpw_default.php';
				}
				$activewidgets[ $widget_file ] = true;
			}
		}

		// we than load the classes and perform the css queueing
		foreach ( $activewidgets as $key => $v ) {
			$class = $this->load_widget( $key );
			if ( class_exists( $class ) ) {
				$tmpclass = new $class;
				if ( $key[0] == '*' ) {
					$upload = wp_upload_dir();
					$tmpclass->tp_widget_js( substr( $key, 1 ), $upload['basedir'] . '/' . TRANSPOSH_DIR_UPLOAD . '/', $upload['baseurl'] . '/' . TRANSPOSH_DIR_UPLOAD );
				} else {
					$tmpclass->tp_widget_js( $key, $this->transposh->transposh_plugin_dir, $this->transposh->transposh_plugin_url );
				}
			}
		}
		LogService::legacy_log( 'Added transposh_widget_js', 4 );
	}

	/**
	 * Calculate arguments needed by subwidgets
	 *
	 * @param string $clean_page_url
	 *
	 * @return array
	 */
	public function create_widget_args( $clean_page_url ) {
		// only calculate urls once even for multiple instances
		static $widget_args;
		if ( is_array( $widget_args ) ) {
			return $widget_args;
		}
		$widget_args = array();
		$page_url    = '';
		if ( is_404() ) {
			$clean_page_url = Utilities::cleanup_url( $this->transposh->home_url, $this->transposh->home_url, true );
		}
		// loop on the languages
		foreach ( $this->transposh->options->get_sorted_langs() as $code => $langrecord ) {
			[ $langname, $language, $flag ] = explode( ',', $langrecord );

			// Only send languages which are active
			if ( $this->transposh->options->is_active_language( $code ) ||
			     ( $this->transposh->options->is_default_language( $code ) ) ) {
				// now we alway do this... maybe cache this to APC/Memcache
				if ( $this->transposh->options->enable_url_translate && ! $this->transposh->options->is_default_language( $code ) ) {
					$page_url = Utilities::translate_url( $clean_page_url, '', $code, array(
						&$this->transposh->database,
						'fetch_translation'
					) );
				} else {
					$page_url = $clean_page_url;
				}
				// clean $code in default lanaguge
				$page_url      = Utilities::rewrite_url_lang_param( $page_url, $this->transposh->home_url, $this->transposh->enable_permalinks_rewrite, $this->transposh->options->is_default_language( $code ) ? '' : $code, $this->transposh->edit_mode );
				$widget_args[] = array(
					'lang'     => $langname,
					'langorig' => $language,
					'flag'     => $flag,
					'isocode'  => $code,
					'url'      => htmlentities( $page_url ), // fix that XSS
					'active'   => ( $this->transposh->target_language == $code )
				);
			}
		}

		return $widget_args;
	}

	/**
	 * Creates the widget html
	 *
	 * @param array $args Contains such as $before_widget, $after_widget, $before_title, $after_title, etc
	 */
	public function widget( $args, $instance, $extcall = false ) {
		// extract args given by wordpress
		extract( $args );
		LogService::legacy_log( $args, 4 );

		// we load the class needed and get its base name for later
		if ( isset( $instance['widget_file'] ) ) {
			$class = $this->load_widget( $instance['widget_file'] );
		} else {
			$class = $this->load_widget();
		}
		if ( ! class_exists( $class ) ) {
			echo __( 'Open Transposh subwidget was not loaded correctly', TRANSPOSH_TEXT_DOMAIN ) . ": $class";

			return;
		}

		$clean_page = $this->transposh->get_clean_url();

		LogService::legacy_log( "WIDGET: clean page url: $clean_page", 4 );

		$widget_args = $this->create_widget_args( $clean_page );
		// at this point the widget args are ready

		LogService::legacy_log( 'Enter widget', 4 );

		// widget default title
		//echo $before_widget . $before_title . __('Translation', TRANSPOSH_TEXT_DOMAIN) . $after_title; - hmm? po/mo?
		if ( isset( $before_widget ) ) {
			echo $before_widget;
		}
		if ( isset( $instance['title'] ) && $instance['title'] ) {
			/* TRANSLATORS: no need to translate this string */
			echo $before_title . __( $instance['title'], TRANSPOSH_TEXT_DOMAIN ) . $after_title;
		}

		// actually run the external widget code
		//if (version_compare(PHP_VERSION, '5.3.0','gt')) { (for the future)
		//   $class::tp_widget_do($widget_args);
		//} else {
		$tmpclass = new $class;
		$tmpclass->tp_widget_do( $widget_args );
		if ( $extcall ) {
			$tmpclass->tp_widget_css( $instance['widget_file'], $this->transposh->transposh_plugin_dir, $this->transposh->transposh_plugin_url );
			$tmpclass->tp_widget_js( $instance['widget_file'], $this->transposh->transposh_plugin_dir, $this->transposh->transposh_plugin_url );
			// don't display edit and other things for shortcode embedding
			if ( isset( $after_widget ) ) {
				echo $after_widget;
			}
			// increase the number of calls for unique IDs
			self::$number_of_draw_calls ++;

			return;
		}
		//}
		//at least one language showing - add the edit box if applicable
		if ( ! empty( $widget_args ) ) {
			// this is the set default language line
			if ( $this->transposh->options->widget_allow_set_deflang ) {
				if ( ( isset( $_COOKIE['TR_LNG'] ) && $_COOKIE['TR_LNG'] != $this->transposh->target_language ) || ( ! isset( $_COOKIE['TR_LNG'] ) && ! $this->transposh->options->is_default_language( $this->transposh->target_language ) ) ) {
					echo '<a id="' . SPAN_PREFIX . 'setdeflang' . self::$number_of_draw_calls . '" class="' . SPAN_PREFIX . 'setdeflang' . '" onClick="return false;" href="' . admin_url( 'admin-ajax.php' ) . '?action=tp_cookie_bck">' . __( 'Set as default language', TRANSPOSH_TEXT_DOMAIN ) . '</a><br/>';
				}
			}
			// add the edit checkbox only for translators for languages marked as editable
			if ( $this->transposh->is_editing_permitted() ) {
				$ref = Utilities::rewrite_url_lang_param( Utilities::get_clean_server_var( 'REQUEST_URI' ), $this->transposh->home_url, $this->transposh->enable_permalinks_rewrite, ( $this->transposh->options->is_default_language( $this->transposh->target_language ) ? "" : $this->transposh->target_language ), ! $this->transposh->edit_mode );
				echo '<input type="checkbox" name="' . EDIT_PARAM . '" value="1" ' .
				     ( $this->transposh->edit_mode ? 'checked="checked" ' : '' ) .
				     ' onclick="document.location.href=\'' . $ref . '\';"/>&nbsp;Edit Translation';
			}
		} else {
			//no languages configured - error message
			echo '<p>No languages available for display. Check the Open Transposh settings (Admin).</p>';
		}

		echo '<div id="' . SPAN_PREFIX . 'credit' . self::$number_of_draw_calls . '">';

		echo '</div>';
		if ( isset( $after_widget ) ) {
			echo $after_widget;
		}
		// increase the number of calls for unique IDs
		self::$number_of_draw_calls ++;
	}


	private function get_widgets() {

		return [
			'default/tpw_default.php' => 'Default',
			'dropdown/tpw_image_dropdown.php' => 'Dropdown selection with image',
			'flags/tpw_flags.php' => 'Flags',
			'flags/tpw_flags_css.php' => 'Flags (With CSS)',
			'flagslist/tpw_list_with_flags.php' => 'List with flags',
			'flagslist/tpw_list_with_flags_css.php' => 'List with flags (CSS)',
			'select2/tpw_select2.php' => 'Select2 based Dropdown',
		];
	}

	/**
	 * Inspired (and used code) from the get_plugins function of wordpress
	 */
	private function get_legacy_widgets() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		get_plugins();

		// Upload dir widgets
		$upload     = wp_upload_dir();
		$widget_root = $upload['basedir'] . '/' . TRANSPOSH_DIR_UPLOAD . '/' . TRANSPOSH_DIR_WIDGETS;

		$tp_widgets  = array();

		// Files in wp-content/widgets directory
		$widgets_dir  = @opendir( $widget_root );
		$widget_files = array();
		if ( $widgets_dir ) {
			while ( ( $file = readdir( $widgets_dir ) ) !== false ) {
				if ( str_starts_with( $file, '.' ) ) {
					continue;
				}
				if ( is_dir( $widget_root . '/' . $file ) ) {
					$widgets_subdir = @ opendir( $widget_root . '/' . $file );
					if ( $widgets_subdir ) {
						while ( ( $subfile = readdir( $widgets_subdir ) ) !== false ) {
							if ( str_starts_with( $subfile, '.' ) ) {
								continue;
							}
							if ( substr( $subfile, 0, 4 ) == self::TRANSPOSH_WIDGET_PREFIX && str_ends_with( $subfile, '.php' ) ) {
								$widget_files[] = "$file/$subfile";
							}
						}
					}
				}
				if ( substr( $file, 0, 4 ) == self::TRANSPOSH_WIDGET_PREFIX && str_ends_with( $file, '.php' ) ) {
					$widget_files[] = $file;
				}
			}
		} else {
			return $tp_widgets;
		}

		// There was a closedir once here, but turned out it just caused strange issues

		if ( empty( $widget_files ) ) {
			return $tp_widgets;
		}

		foreach ( $widget_files as $widget_file ) {
			if ( ! is_readable( "$widget_root/$widget_file" ) ) {
				continue;
			}

			$widget_data = get_plugin_data( "$widget_root/$widget_file", false, false ); //Do not apply markup/translate as it'll be cached.

			if ( empty( $widget_data['Name'] ) ) {
				continue;
			}

			$tp_widgets[ plugin_basename( $widget_file ) ] = $widget_data;
		}
		uasort( $tp_widgets, static function ( $a, $b ) {
			return strnatcasecmp( $a["Name"], $b["Name"] );
		} );

		$widgets = array();
		foreach ( $tp_widgets as $file => $widget ) {
			$widgets[ '*' . $file ] = '(*) ' . $widget['Name'];
		}

		return $widgets;
	}

}


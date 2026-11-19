<?php

use OpenTransposh\Core\Utilities;
use OpenTransposh\Plugin;
use OpenTransposh\Widgets\Base_Widget;

class tpw_list_with_flags_css extends Base_Widget {

	/**
	 * Instructs usage of a different .css file
	 * @global Plugin $my_transposh_plugin
	 */
	static function tp_widget_css( $file, $dir, $url ) {
		wp_enqueue_style( "flags_tpw_flags_css", "$url/widgets/flags/tpw_flags_css.css", array(), TRANSPOSH_PLUGIN_VER );
	}

	/**
	 * Creates the list of flags (using css sprites) - followed by a language name link
	 *
	 * @param array $args
	 *
	 * @global Plugin $my_transposh_plugin
	 */
	static function tp_widget_do( $args ) {
		echo "<div class=\"" . NO_TRANSLATE_CLASS . " transposh_flags\" >";
		foreach ( $args as $langrecord ) {
			echo '<a href="' . esc_url( $langrecord['url'] ) . '"' . ( $langrecord['active'] ? ' class="tr_active"' : '' ) . '>' .
			     Utilities::display_flag( '', esc_attr( $langrecord['flag'] ), esc_attr( $langrecord['langorig'] ), true ) . '</a>';
			echo '<a href="' . esc_url( $langrecord['url'] ) . '"' . ( $langrecord['active'] ? ' class="tr_active"' : '' ) . '>' . esc_html( $langrecord['langorig'] ) . '</a><br/>';
		}
		echo "</div>";
	}

}

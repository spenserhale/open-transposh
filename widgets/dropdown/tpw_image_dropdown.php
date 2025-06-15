<?php

use OpenTransposh\Plugin;
use OpenTransposh\Widgets\Base_Widget;

class tpw_image_dropdown extends Base_Widget {

	/**
	 * This function makes sure that the jquery dependency will be met
	 * @global Plugin $my_transposh_plugin
	 */
	static function tp_widget_js( $file, $dir, $url ) {
		wp_enqueue_script( "transposh_widget", "$url/widgets/dropdown/tpw_image_dropdown.js", array( 'jquery' ), TRANSPOSH_PLUGIN_VER );
	}

	/**
	 * This function does the actual HTML for the widget
	 *
	 * @param array $args
	 */
	static function tp_widget_do( $args ) {
		global $my_transposh_plugin;
		// we calculate the plugin path part, so we can link the images there
		$plugin_path = untrailingslashit( parse_url( $my_transposh_plugin->transposh_plugin_url, PHP_URL_PATH ) );

		echo '<dl class="tp_dropdown dropdown">';
		/* TRANSLATORS: this is what appears in the select box in dropdown subwidget */
		echo '<dt><a href="#"><span>' . __( 'Select language', TRANSPOSH_TEXT_DOMAIN ) . '</span></a></dt><dd><ul class="' . NO_TRANSLATE_CLASS . '">';
		foreach ( $args as $language_record ) {
			// $is_selected = $langrecord['active'] ? " selected=\"selected\"" : "";
			echo '<li' . ( $language_record['active'] ? ' class="tr_active"' : '' ) . '><a href="#"><img class="flag" src="' . "$plugin_path/img/flags/{$language_record['flag']}" . '.png" alt="' . $language_record['langorig'] . '"/> ' . $language_record['langorig'] . '<span class="value">' . $language_record['url'] . '</span></a></li>';
		}
		echo '</ul></dd></dl>';
	}

}

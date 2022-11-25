<?php

use OpenTransposh\Core\Utilities;
use OpenTransposh\Plugin;
use OpenTransposh\Widgets\Base_Widget;

/**
 * This function allows the widget to tell the invoker if it needs to calculate different urls per language, here it is needed
 * @return bool
 */
class tpw_flags extends Base_Widget {

	/**
	 * Creates the list of flags
	 *
	 * @param array $args
	 *
	 * @global Plugin $my_transposh_plugin
	 */
	static function tp_widget_do( $args ) {
		global $my_transposh_plugin;
		// we calculate the plugin path part, so we can link the images there
		$plugpath = parse_url( $my_transposh_plugin->transposh_plugin_url, PHP_URL_PATH );

		echo "<div class=\"" . NO_TRANSLATE_CLASS . " transposh_flags\" >";
		foreach ( $args as $langrecord ) {
			echo "<a href=\"{$langrecord['url']}\"" . ( $langrecord['active'] ? ' class="tr_active"' : '' ) . '>' .
			     Utilities::display_flag( "$plugpath/img/flags", $langrecord['flag'], $langrecord['langorig'], false ) .
			     "</a>";
		}
		echo "</div>";
	}

}

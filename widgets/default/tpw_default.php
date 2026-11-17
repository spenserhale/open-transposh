<?php
/**
 * This widget is the default language list widget, the one which provides a drop down select box which allows to choose a new target language
 */

use OpenTransposh\Widgets\Base_Widget;

/**
 * This function does the actual HTML for the widget
 *
 * @param array $args
 */
class tpw_default extends Base_Widget {

	static function tp_widget_do( $args ) {
		echo '<span class="' . NO_TRANSLATE_CLASS . '">'; // wrapping in no_translate to avoid translation of this list

		echo '<select name="lang" onchange="document.location.href=this.options[this.selectedIndex].value;">'; // this is a select box which posts on change
		foreach ( $args as $langrecord ) {
			$is_selected = $langrecord['active'] ? " selected=\"selected\"" : "";
			echo '<option value="' . esc_url( $langrecord['url'] ) . "\"{$is_selected}>" . esc_html( $langrecord['langorig'] ) . '</option>';
		}
		echo "</select><br/>";

		echo "</span>";
	}

}

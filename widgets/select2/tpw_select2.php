<?php

use OpenTransposh\Widgets\Base_Widget;

class tpw_select2 extends Base_Widget {

	static function tp_widget_js( $file, $dir, $url ) {
		wp_enqueue_script( "select2", "$url/widgets/select2/select2.min.js", array( 'jquery' ), TRANSPOSH_PLUGIN_VER );
		wp_enqueue_script( "transposh_widget_select2", "$url/widgets/select2/tpw_select2.js", array( 'jquery' ), TRANSPOSH_PLUGIN_VER );
	}

	static function tp_widget_css( $file, $dir, $url ) {
		wp_enqueue_style( "flags_tpw_flags_css", "$url/widgets/flags/tpw_flags_css.css", array(), TRANSPOSH_PLUGIN_VER );
		wp_enqueue_style( "select2", "$url/widgets/select2/select2.css", array(), TRANSPOSH_PLUGIN_VER );
	}

	static function tp_widget_do( $args ) {
		echo '<span class="' . NO_TRANSLATE_CLASS . '">';

		echo '<select style="width:100%" name="lang" class="tp_lang2" onchange="document.location.href=this.options[this.selectedIndex].value;">';
		foreach ( $args as $langrecord ) {
			$is_selected = $langrecord['active'] ? " selected=\"selected\"" : "";
			echo '<option value="' . esc_url( $langrecord['url'] ) . '" data-flag="' . esc_attr( $langrecord['flag'] ) . '" data-lang="' . esc_attr( $langrecord['lang'] ) . "\"{$is_selected}>" . esc_html( $langrecord['langorig'] ) . '</option>';
		}
		echo "</select><br/>";

		echo "</span>";
	}

}

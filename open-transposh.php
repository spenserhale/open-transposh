<?php
/**
 * Open Transposh
 *
 * The Open Transposh Plugin is an open-source fork of the Transposh Plugin. The fork's existence stemmed from the
 * original Transposh author, who disagreed with security experts and chose not to fix the reported issues.
 * WordPress removed the plugin from their official plugin directory. This plugin resolves the reported issues to allow
 * the WordPress community to continue to use this plugin securely and access to download from the WordPress plugin
 * directory. The plugin is a drop in replacement and uses the same data identifiers for compatibility.
 *
 * @package           Open Transposh
 * @author            Spenser Ha;e
 * @copyright         2022 Spenser Hale
 * @license           GPL-3.0-or-later
 *
 * @credits
 * Copyright 2009 - 2022 by Transposh Team (Ofer Wald) https://transposh.org/
 *
 * @wordpress-plugin
 * Plugin Name:       Open Transposh
 * Plugin URI:        https://github.com/spenserhale/open-transposh
 * Description:       A Translation Filter Plugin for WordPress offers a unique approach to blog translation. It allows your blog to combine automatic and human translation aided by your users with an easy-to-use in-context interface.
 * Version:           2.1.7
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Spenser Hale
 * Author URI:        https://www.spenserhale.com/
 * Domain Path:       /langs
 * Text Domain:       transposh
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Simple HTML Dom Copyright
 *
 * Version: 1.11 ($Rev: 175 $)
 * Website: http://sourceforge.net/projects/simplehtmldom/
 * Author: S.C. Chen <me578022@gmail.com>
 * Acknowledge: Jose Solorzano (https://sourceforge.net/projects/php-html/)
 * Contributions by:
 * Yousuke Kumakura (Attribute filters)
 * Vadim Voituk (Negative indexes supports of "find" method)
 * Antcs (Constructor with automatically load contents either text or file/url)
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 **/

//avoid direct calls to this file where wp core files not present

defined( 'ABSPATH' ) or die();

if ( version_compare( $GLOBALS['wp_version'], '3.3', '<=' ) ) {
	/** @noinspection ForgottenDebugOutputInspection */
	error_log( 'Open Transposh requires WordPress 3.3 or newer.' );
	return;
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/constants.php';
require __DIR__ . '/legacy.php';
require __DIR__ . '/functions.php';

$open_transposh_plugin = OpenTransposh\Plugin::get_instance(__FILE__);

/** @var OpenTransposh\Plugin|transposh_plugin $my_transposh_plugin */
$my_transposh_plugin = new transposh_plugin($open_transposh_plugin);

/** @var OpenTransposh\Logging\Logger|tp_logger $tp_logger */
$tp_logger = new tp_logger(new OpenTransposh\Logging\Logger());

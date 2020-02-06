<?php
/**
 * Media Directory Arrange
 *
 * @package   Media_Directory_Arrange
 * @author    Yo Yamamoto <cross_sphere@hotmail.com>
 * @license   GPL-2.0+
 * @link      http://pulltab.info
 * @copyright 2017 Yo Yamamoto
 *
 * @wordpress-plugin
 * Plugin Name: Media Directory Arrange
 * Plugin URI:  -
 * Description: アップロードされているメディアファイルをuploads/ポストタイプ/投稿スラッグのディレクトリへ移動させます。
 * Version:     1.0.0
 * Author:      Yo Yamamoto
 * Author URI:  http://pulltab.info
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
} // end if


define( 'MDA_URL', plugin_dir_url( __FILE__ ) );
define( 'MDA_DIR', plugin_dir_path( __FILE__ ) );
define( 'MDA_VERSION', '1.0.0' );

require_once( plugin_dir_path( __FILE__ ) . '/app/main.php' );
require_once( plugin_dir_path( __FILE__ ) . '/app/setting.php' );
require_once( plugin_dir_path( __FILE__ ) . '/app/arrange.php' );

Media_Directory_Arrange::get_instance();
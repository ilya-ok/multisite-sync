<?php
/**
 * Plugin Name: Multisite Sync
 * Plugin URI:
 * Description: Синхронизация цен WooCommerce товаров между сайтами мультисайта по SKU. Массовое редактирование с автосинхронизацией.
 * Version: 1.2.0
 * Author: Your Name
 * Author URI:
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multisite-sync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Запрет прямого доступа
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Константы плагина
define( 'MS_VERSION', '1.2.0' );
define( 'MS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Проверка наличия мультисайта и WooCommerce
 */
function ms_check_requirements() {
	// Проверка мультисайта
	if ( ! is_multisite() ) {
		add_action( 'admin_notices', 'ms_multisite_notice' );
		return false;
	}

	// Проверка WooCommerce
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ms_woocommerce_notice' );
		return false;
	}

	return true;
}

/**
 * Уведомление об отсутствии мультисайта
 */
function ms_multisite_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Multisite Sync требует WordPress Multisite для работы.', 'multisite-sync' ); ?></p>
	</div>
	<?php
}

/**
 * Уведомление об отсутствии WooCommerce
 */
function ms_woocommerce_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Multisite Sync требует активный WooCommerce для работы.', 'multisite-sync' ); ?></p>
	</div>
	<?php
}

/**
 * Инициализация плагина
 */
function ms_init() {
	// Проверка требований
	if ( ! ms_check_requirements() ) {
		return;
	}

	// Загрузка классов
	require_once MS_PLUGIN_DIR . 'includes/class-mps-sync.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-admin.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-bulk-edit.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-copy.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-copy-pages.php';

	// Инициализация классов
	MS_Sync::get_instance();
	MS_Admin::get_instance();
	MS_Bulk_Edit::get_instance();
	MS_Copy::get_instance();
	MPS_Copy_Pages::get_instance();
}
add_action( 'plugins_loaded', 'ms_init' );

/**
 * Активация плагина
 */
function ms_activate() {
	// Проверка требований при активации
	if ( ! is_multisite() ) {
		deactivate_plugins( MS_PLUGIN_BASENAME );
		wp_die( esc_html__( 'Этот плагин требует WordPress Multisite.', 'multisite-sync' ) );
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( MS_PLUGIN_BASENAME );
		wp_die( esc_html__( 'Этот плагин требует активный WooCommerce.', 'multisite-sync' ) );
	}

	// Создание настроек по умолчанию
	add_site_option( 'ms_auto_sync', '1' );
	add_site_option( 'ms_sync_regular_price', '1' );
	add_site_option( 'ms_sync_sale_price', '1' );
}
register_activation_hook( __FILE__, 'ms_activate' );

/**
 * Деактивация плагина
 */
function ms_deactivate() {
	// Очистка при необходимости
}
register_deactivation_hook( __FILE__, 'ms_deactivate' );

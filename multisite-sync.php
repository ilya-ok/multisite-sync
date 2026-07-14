<?php
/**
 * Plugin Name: Multisite Sync
 * Plugin URI:
 * Description: Копирование товаров и страниц с главного сайта на дочерние сайты мультисайта.
 * Version: 1.4.0
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
define( 'MS_VERSION', '1.4.0' );
define( 'MS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * 301-редиректы, созданные на странице удаления товаров.
 * Хук работает на всех сайтах сети (плагин сетевой).
 * Редиректы хранятся в wp_options под ключом mps_redirects: [ '/from/' => '/to/' ]
 */
add_action( 'template_redirect', 'mps_handle_redirects', 1 );
function mps_handle_redirects() {
	$redirects = get_option( 'mps_redirects', array() );
	if ( empty( $redirects ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$path        = wp_parse_url( esc_url_raw( $request_uri ), PHP_URL_PATH );
	$path        = '/' . trim( (string) $path, '/' ) . '/';

	if ( isset( $redirects[ $path ] ) ) {
		wp_redirect( $redirects[ $path ], 301 );
		exit;
	}
}

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
	require_once MS_PLUGIN_DIR . 'includes/class-mps-copy.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-copy-pages.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-copy-posts.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-copy-portfolio.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-copy-galleries.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-delete.php';
	require_once MS_PLUGIN_DIR . 'includes/class-mps-redirects.php';

	// Инициализация классов
	MS_Copy::get_instance();
	MPS_Copy_Pages::get_instance();
	MPS_Copy_Posts::get_instance();
	MPS_Copy_Portfolio::get_instance();
	MPS_Copy_Galleries::get_instance();
	MPS_Delete::get_instance();
	MPS_Redirects::get_instance();
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

	// Создание настроек по умолчанию (нет)
}
register_activation_hook( __FILE__, 'ms_activate' );

/**
 * Деактивация плагина
 */
function ms_deactivate() {
	// Очистка при необходимости
}
register_deactivation_hook( __FILE__, 'ms_deactivate' );

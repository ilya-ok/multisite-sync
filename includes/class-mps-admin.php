<?php
/**
 * Класс настроек плагина
 *
 * @package Multisite_Price_Sync
 */

// Запрет прямого доступа
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс MS_Admin
 *
 * Страница настроек плагина
 */
class MS_Admin {

	/**
	 * Singleton instance
	 *
	 * @var MS_Admin|null
	 */
	private static $instance = null;

	/**
	 * Получение экземпляра класса (Singleton)
	 *
	 * @return MS_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Конструктор
	 */
	private function __construct() {
		// Добавляем страницу настроек (только для network admin)
		// Приоритет 11 - после создания главного меню (приоритет 10)
		add_action( 'network_admin_menu', array( $this, 'add_settings_page' ), 11 );

		// Регистрация настроек
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Обработка сохранения настроек
		add_action( 'admin_post_ms_save_settings', array( $this, 'save_settings' ) );

		// Уведомления
		add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Добавление страницы настроек
	 */
	public function add_settings_page() {
		add_submenu_page(
			'ms-bulk-edit',
			__( 'Настройки синхронизации цен', 'multisite-sync' ),
			__( 'Настройки', 'multisite-sync' ),
			'manage_network',
			'ms-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Регистрация настроек
	 */
	public function register_settings() {
		// Настройки сохраняются в site_options (для всей сети)
	}

	/**
	 * Отрисовка страницы настроек
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ms_save_settings">
				<?php wp_nonce_field( 'ms_settings', 'ms_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Автоматическая синхронизация', 'multisite-sync' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="ms_auto_sync" value="1" <?php checked( get_site_option( 'ms_auto_sync', '1' ), '1' ); ?>>
								<?php esc_html_e( 'Автоматически синхронизировать цены при сохранении товара', 'multisite-sync' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Если отключено, цены будут синхронизироваться только через страницу массового редактирования.', 'multisite-sync' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Синхронизировать обычную цену', 'multisite-sync' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="ms_sync_regular_price" value="1" <?php checked( get_site_option( 'ms_sync_regular_price', '1' ), '1' ); ?>>
								<?php esc_html_e( 'Синхронизировать обычную цену (Regular Price)', 'multisite-sync' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Синхронизировать цену со скидкой', 'multisite-sync' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="ms_sync_sale_price" value="1" <?php checked( get_site_option( 'ms_sync_sale_price', '1' ), '1' ); ?>>
								<?php esc_html_e( 'Синхронизировать цену со скидкой (Sale Price)', 'multisite-sync' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Отладочная информация', 'multisite-sync' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="ms_debug_mode" value="1" <?php checked( get_site_option( 'ms_debug_mode' ), '1' ); ?>>
								<?php esc_html_e( 'Включить режим отладки (логирование в debug.log)', 'multisite-sync' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Сохранить настройки', 'multisite-sync' ) ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Информация о плагине', 'multisite-sync' ); ?></h2>
			<table class="widefat">
				<tr>
					<th><?php esc_html_e( 'Версия плагина', 'multisite-sync' ); ?></th>
					<td><?php echo esc_html( MS_VERSION ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Количество сайтов в сети', 'multisite-sync' ); ?></th>
					<td><?php echo esc_html( get_blog_count() ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WooCommerce версия', 'multisite-sync' ); ?></th>
					<td><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Не установлен', 'multisite-sync' ) ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Сохранение настроек
	 */
	public function save_settings() {
		// Проверка nonce
		if ( ! isset( $_POST['ms_settings_nonce'] ) || ! wp_verify_nonce( $_POST['ms_settings_nonce'], 'ms_settings' ) ) {
			wp_die( esc_html__( 'Ошибка безопасности', 'multisite-sync' ) );
		}

		// Проверка прав
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'У вас нет прав для этого действия', 'multisite-sync' ) );
		}

		// Сохранение настроек
		update_site_option( 'ms_auto_sync', isset( $_POST['ms_auto_sync'] ) ? '1' : '0' );
		update_site_option( 'ms_sync_regular_price', isset( $_POST['ms_sync_regular_price'] ) ? '1' : '0' );
		update_site_option( 'ms_sync_sale_price', isset( $_POST['ms_sync_sale_price'] ) ? '1' : '0' );
		update_site_option( 'ms_debug_mode', isset( $_POST['ms_debug_mode'] ) ? '1' : '0' );

		// Редирект
		wp_redirect( add_query_arg(
			array(
				'page'    => 'ms-settings',
				'updated' => 1,
			),
			network_admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Уведомления админки
	 */
	public function admin_notices() {
		if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'ms-settings', 'ms-bulk-edit' ), true ) ) {
			return;
		}

		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					if ( isset( $_GET['success'] ) && isset( $_GET['failed'] ) ) {
						printf(
							/* translators: 1: количество успешных, 2: количество неудачных */
							esc_html__( 'Синхронизация завершена. Успешно: %1$d, Ошибок: %2$d', 'multisite-sync' ),
							absint( $_GET['success'] ),
							absint( $_GET['failed'] )
						);
					} else {
						esc_html_e( 'Настройки сохранены.', 'multisite-sync' );
					}
					?>
				</p>
			</div>
			<?php
		}
	}
}

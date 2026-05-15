<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Копирование страниц (с блоками SPB) с главного сайта на дочерние.
 * Страница идентифицируется по post_name (slug).
 * Мета _spb_blocks переносится без изменений — SPB хранит изображения по slug.
 */
class MPS_Copy_Pages {

	private static $instance = null;
	private $page_hook       = '';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'network_admin_menu',  array( $this, 'add_submenu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_mps_get_pages',  array( $this, 'ajax_get_pages' ) );
		add_action( 'wp_ajax_mps_copy_page',  array( $this, 'ajax_copy_page' ) );
	}

	// -------------------------------------------------------------------------
	// Меню
	// -------------------------------------------------------------------------

	public function add_submenu_page() {
		$this->page_hook = add_submenu_page(
			'ms-bulk-edit',
			__( 'Копирование страниц', 'multisite-sync' ),
			__( 'Копирование страниц', 'multisite-sync' ),
			'manage_network',
			'mps-copy-pages',
			array( $this, 'render_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Скрипты
	// -------------------------------------------------------------------------

	public function enqueue_scripts( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'ms-bulk-edit',
			MS_PLUGIN_URL . 'assets/css/bulk-edit.css',
			array(),
			MS_VERSION
		);

		wp_enqueue_style(
			'ms-copy-products',
			MS_PLUGIN_URL . 'assets/css/copy-products.css',
			array( 'ms-bulk-edit' ),
			MS_VERSION
		);

		wp_enqueue_script(
			'mps-copy-pages',
			MS_PLUGIN_URL . 'assets/js/copy-pages.js',
			array( 'jquery' ),
			MS_VERSION,
			true
		);

		$main_id    = get_main_site_id();
		$sites      = get_sites( array( 'number' => 0 ) );
		$sites_data = array();

		foreach ( $sites as $site ) {
			if ( (int) $site->blog_id === $main_id ) {
				continue;
			}
			$sites_data[] = array(
				'id'   => $site->blog_id,
				'name' => get_blog_option( $site->blog_id, 'blogname' ),
			);
		}

		wp_localize_script( 'mps-copy-pages', 'mpsCopyPagesData', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'mps_copy_pages_nonce' ),
			'sites'   => $sites_data,
		) );
	}

	// -------------------------------------------------------------------------
	// Страница
	// -------------------------------------------------------------------------

	public function render_page() {
		$main_id = get_main_site_id();
		$sites   = get_sites( array( 'number' => 0 ) );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Копирование страниц', 'multisite-sync' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Страницы главного сайта с блоками Page Builder. Нажмите «Копировать» — страница будет создана или обновлена на всех дочерних сайтах вместе с блоками.', 'multisite-sync' ); ?>
			</p>

			<div class="ms-section">
				<div class="ms-bulk-header">
					<button id="mps-load-pages" class="button button-primary">
						<?php esc_html_e( 'Загрузить страницы', 'multisite-sync' ); ?>
					</button>
					<span id="mps-page-count" class="ms-count"></span>
					<span class="mps-loading" style="display:none;">
						<span class="spinner is-active" style="float:none;margin:0;"></span>
						<?php esc_html_e( 'Загрузка...', 'multisite-sync' ); ?>
					</span>
				</div>
			</div>

			<div id="mps-pages-container" style="display:none;"></div>

			<div class="ms-section ms-sites-info">
				<h2><?php esc_html_e( 'Сайты мультисайта', 'multisite-sync' ); ?></h2>
				<ul class="ms-sites-list">
					<?php foreach ( $sites as $site ) : ?>
						<li>
							<?php if ( (int) $site->blog_id === $main_id ) : ?>
								<strong>★ <?php echo esc_html( get_blog_option( $site->blog_id, 'blogname' ) ); ?></strong>
								<span class="ms-site-url">(<?php esc_html_e( 'главный сайт — источник', 'multisite-sync' ); ?>)</span>
							<?php else : ?>
								<strong><?php echo esc_html( get_blog_option( $site->blog_id, 'blogname' ) ); ?></strong>
								<span class="ms-site-url">(<?php echo esc_url( $site->domain . $site->path ); ?>)</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX: список страниц
	// -------------------------------------------------------------------------

	public function ajax_get_pages() {
		check_ajax_referer( 'mps_copy_pages_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$main_id = get_main_site_id();
		switch_to_blog( $main_id );

		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		if ( empty( $pages ) ) {
			restore_current_blog();
			wp_send_json_success( array(
				'html'  => '<p class="ms-empty">' . esc_html__( 'Страниц нет.', 'multisite-sync' ) . '</p>',
				'count' => 0,
			) );
		}

		// Собираем slugs страниц
		$page_slugs = array();
		foreach ( $pages as $page ) {
			$page_slugs[ $page->ID ] = $page->post_name;
		}

		restore_current_blog();

		// Проверяем наличие страниц на дочерних сайтах
		$all_sites   = get_sites( array( 'number' => 0 ) );
		$child_sites = array();
		foreach ( $all_sites as $site ) {
			if ( (int) $site->blog_id !== $main_id ) {
				$child_sites[] = $site;
			}
		}

		$site_status = array();

		foreach ( $child_sites as $site ) {
			switch_to_blog( $site->blog_id );
			global $wpdb;

			$slugs_to_find = array_values( array_filter( $page_slugs ) );
			$found_slugs   = array();

			if ( ! empty( $slugs_to_find ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $slugs_to_find ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_name FROM {$wpdb->posts}
						 WHERE post_name IN ($placeholders)
						 AND post_type = 'page'
						 AND post_status != 'trash'",
						...$slugs_to_find
					)
				);
				foreach ( $rows as $row ) {
					$found_slugs[ $row->post_name ] = true;
				}
			}

			restore_current_blog();

			foreach ( $page_slugs as $post_id => $slug ) {
				$site_status[ $post_id ][ $site->blog_id ] = isset( $found_slugs[ $slug ] );
			}
		}

		// Рендер таблицы на контексте главного сайта
		switch_to_blog( $main_id );
		$html = $this->build_pages_table( $pages, $child_sites, $site_status );
		restore_current_blog();

		wp_send_json_success( array(
			'html'  => $html,
			'count' => count( $pages ),
		) );
	}

	private function build_pages_table( array $pages, array $child_sites, array $site_status ): string {
		$html  = '<table class="widefat ms-products-table ms-copy-table">';
		$html .= '<thead><tr>';
		$html .= '<th class="ms-col-actions">' . esc_html__( 'Действия', 'multisite-sync' ) . '</th>';
		$html .= '<th class="ms-col-name">'    . esc_html__( 'Страница', 'multisite-sync' ) . '</th>';
		$html .= '<th class="ms-col-sku">'     . esc_html__( 'Slug', 'multisite-sync' ) . '</th>';

		foreach ( $child_sites as $site ) {
			$name  = get_blog_option( $site->blog_id, 'blogname' );
			$html .= '<th class="ms-col-site" title="' . esc_attr( $site->domain . $site->path ) . '">';
			$html .= esc_html( $name );
			$html .= '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( $pages as $page ) {
			$has_blocks = (bool) get_post_meta( $page->ID, '_spb_blocks', true );

			$html .= '<tr class="ms-product-row" data-page-id="' . esc_attr( $page->ID ) . '">';

			$html .= '<td class="ms-col-actions">';
			$html .= '<button class="mps-copy-page-btn button button-primary" data-page-id="' . esc_attr( $page->ID ) . '">';
			$html .= esc_html__( 'Копировать', 'multisite-sync' );
			$html .= '</button>';
			$html .= '<span class="ms-copy-status"></span>';
			$html .= '</td>';

			$html .= '<td class="ms-col-name">';
			$html .= '<strong>' . esc_html( $page->post_title ) . '</strong>';
			if ( $has_blocks ) {
				$html .= ' <span class="ms-badge" title="' . esc_attr__( 'Есть блоки Page Builder', 'multisite-sync' ) . '">SPB</span>';
			}
			$html .= ' <span class="ms-status-' . esc_attr( $page->post_status ) . '">' . esc_html( $page->post_status ) . '</span>';
			$html .= '</td>';

			$html .= '<td class="ms-col-sku">' . esc_html( $page->post_name ) . '</td>';

			foreach ( $child_sites as $site ) {
				$exists = $site_status[ $page->ID ][ $site->blog_id ] ?? false;
				if ( $exists ) {
					$html .= '<td class="ms-col-site ms-site-status-ok" data-site-id="' . esc_attr( $site->blog_id ) . '">✓</td>';
				} else {
					$html .= '<td class="ms-col-site ms-site-status-missing" data-site-id="' . esc_attr( $site->blog_id ) . '">—</td>';
				}
			}

			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		return $html;
	}

	// -------------------------------------------------------------------------
	// AJAX: копирование страницы
	// -------------------------------------------------------------------------

	public function ajax_copy_page() {
		check_ajax_referer( 'mps_copy_pages_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$page_id        = isset( $_POST['page_id'] )  ? absint( $_POST['page_id'] )  : 0;
		$target_site_id = isset( $_POST['site_id'] )  ? absint( $_POST['site_id'] )  : 0;

		if ( ! $page_id ) {
			wp_send_json_error( __( 'Неверный ID страницы', 'multisite-sync' ) );
		}

		$main_id = get_main_site_id();

		// ── Шаг 1: собираем данные с главного сайта ──────────────────────────
		switch_to_blog( $main_id );

		$source = get_post( $page_id );
		if ( ! $source || 'page' !== $source->post_type ) {
			restore_current_blog();
			wp_send_json_error( __( 'Страница не найдена', 'multisite-sync' ) );
		}

		$data = array(
			'title'       => $source->post_title,
			'slug'        => $source->post_name,
			'content'     => $source->post_content,
			'status'      => $source->post_status,
			'spb_blocks'  => get_post_meta( $page_id, '_spb_blocks', true ),
		);

		restore_current_blog();

		// ── Шаг 2: копируем на дочерние сайты ────────────────────────────────
		$all_sites = get_sites( array( 'number' => 0 ) );
		$results   = array();

		foreach ( $all_sites as $site ) {
			if ( (int) $site->blog_id === $main_id ) {
				continue;
			}
			if ( $target_site_id && (int) $site->blog_id !== $target_site_id ) {
				continue;
			}

			switch_to_blog( $site->blog_id );

			$existing = get_page_by_path( $data['slug'], OBJECT, 'page' );

			$post_data = array(
				'post_title'   => $data['title'],
				'post_name'    => $data['slug'],
				'post_content' => $data['content'],
				'post_status'  => $data['status'],
				'post_type'    => 'page',
			);

			if ( $existing ) {
				$post_data['ID'] = $existing->ID;
				$new_id          = wp_update_post( $post_data, true );
			} else {
				$new_id = wp_insert_post( $post_data, true );
			}

			if ( is_wp_error( $new_id ) ) {
				$results[ $site->blog_id ] = array(
					'success' => false,
					'error'   => $new_id->get_error_message(),
				);
				restore_current_blog();
				continue;
			}

			// Копируем блоки — JSON переносится без изменений
			if ( '' !== $data['spb_blocks'] ) {
				update_post_meta( $new_id, '_spb_blocks', $data['spb_blocks'] );
			}

			$results[ $site->blog_id ] = array( 'success' => true );

			restore_current_blog();
		}

		wp_send_json_success( array(
			'results' => $results,
			'message' => __( 'Копирование завершено', 'multisite-sync' ),
		) );
	}
}

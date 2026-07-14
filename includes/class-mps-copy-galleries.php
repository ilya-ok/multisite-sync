<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Копирование галерей (post type ss_gallery) с главного сайта на дочерние.
 * Галерея идентифицируется по post_name (slug).
 * Мета-поле _gallery_images: JSON-массив имён файлов из wp-content/galleries/.
 * Файлы не копируются — папка galleries/ общая для всех поддоменов на одном сервере.
 */
class MPS_Copy_Galleries {

	private static $instance = null;
	private $page_hook       = '';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'network_admin_menu',    array( $this, 'add_submenu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_mps_get_galleries',  array( $this, 'ajax_get_galleries' ) );
		add_action( 'wp_ajax_mps_copy_gallery',   array( $this, 'ajax_copy_gallery' ) );
	}

	// -------------------------------------------------------------------------
	// Меню
	// -------------------------------------------------------------------------

	public function add_submenu_page() {
		$this->page_hook = add_submenu_page(
			'ms-bulk-edit',
			__( 'Копирование галерей', 'multisite-sync' ),
			__( 'Копирование галерей', 'multisite-sync' ),
			'manage_network',
			'mps-copy-galleries',
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
			'mps-copy-galleries',
			MS_PLUGIN_URL . 'assets/js/copy-galleries.js',
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

		wp_localize_script( 'mps-copy-galleries', 'mpsCopyGalleriesData', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'mps_copy_galleries_nonce' ),
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
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Копирование галерей', 'multisite-sync' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Галереи главного сайта. Нажмите «Копировать» — галерея будет создана или обновлена на всех дочерних сайтах. Файлы изображений не копируются — они общие для всех поддоменов (wp-content/galleries/).', 'multisite-sync' ); ?>
			</p>

			<div class="ms-section">
				<div class="ms-bulk-header">
					<button id="mps-load-galleries" class="button button-primary">
						<?php esc_html_e( 'Загрузить галереи', 'multisite-sync' ); ?>
					</button>
					<span id="mps-galleries-count" class="ms-count"></span>
					<span class="mps-galleries-loading" style="display:none;">
						<span class="spinner is-active" style="float:none;margin:0;"></span>
						<?php esc_html_e( 'Загрузка...', 'multisite-sync' ); ?>
					</span>
				</div>
			</div>

			<div id="mps-galleries-container" style="display:none;"></div>

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
	// AJAX: список галерей
	// -------------------------------------------------------------------------

	public function ajax_get_galleries() {
		check_ajax_referer( 'mps_copy_galleries_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$main_id = get_main_site_id();
		switch_to_blog( $main_id );

		$posts = get_posts( array(
			'post_type'      => 'ss_gallery',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 500,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		if ( empty( $posts ) ) {
			restore_current_blog();
			wp_send_json_success( array(
				'html'  => '<p class="ms-empty">' . esc_html__( 'Галерей нет.', 'multisite-sync' ) . '</p>',
				'count' => 0,
			) );
		}

		$post_slugs = array();
		foreach ( $posts as $post ) {
			$post_slugs[ $post->ID ] = $post->post_name;
		}

		restore_current_blog();

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

			$slugs_to_find = array_values( array_filter( $post_slugs ) );
			$found_slugs   = array();

			if ( ! empty( $slugs_to_find ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $slugs_to_find ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_name FROM {$wpdb->posts}
						 WHERE post_name IN ($placeholders)
						 AND post_type = 'ss_gallery'
						 AND post_status != 'trash'",
						...$slugs_to_find
					)
				);
				foreach ( $rows as $row ) {
					$found_slugs[ $row->post_name ] = true;
				}
			}

			restore_current_blog();

			foreach ( $post_slugs as $post_id => $slug ) {
				$site_status[ $post_id ][ $site->blog_id ] = isset( $found_slugs[ $slug ] );
			}
		}

		switch_to_blog( $main_id );
		$html = $this->build_table( $posts, $child_sites, $site_status );
		restore_current_blog();

		wp_send_json_success( array(
			'html'  => $html,
			'count' => count( $posts ),
		) );
	}

	private function build_table( array $posts, array $child_sites, array $site_status ): string {
		$html  = '<table class="widefat ms-products-table ms-copy-table">';
		$html .= '<thead><tr>';
		$html .= '<th class="ms-col-actions">' . esc_html__( 'Действия', 'multisite-sync' ) . '</th>';
		$html .= '<th class="ms-col-name">'    . esc_html__( 'Галерея', 'multisite-sync' ) . '</th>';
		$html .= '<th class="ms-col-sku">'     . esc_html__( 'Slug', 'multisite-sync' ) . '</th>';

		foreach ( $child_sites as $site ) {
			$name  = get_blog_option( $site->blog_id, 'blogname' );
			$html .= '<th class="ms-col-site" title="' . esc_attr( $site->domain . $site->path ) . '">';
			$html .= esc_html( $name );
			$html .= '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( $posts as $post ) {
			$html .= '<tr class="ms-product-row" data-post-id="' . esc_attr( $post->ID ) . '">';

			$html .= '<td class="ms-col-actions">';
			$html .= '<button class="mps-copy-gallery-btn button button-primary" data-post-id="' . esc_attr( $post->ID ) . '">';
			$html .= esc_html__( 'Копировать', 'multisite-sync' );
			$html .= '</button>';
			$html .= '<span class="ms-copy-status"></span>';
			$html .= '</td>';

			$html .= '<td class="ms-col-name">';
			$html .= '<strong>' . esc_html( $post->post_title ) . '</strong>';
			$html .= ' <span class="ms-status-' . esc_attr( $post->post_status ) . '">' . esc_html( $post->post_status ) . '</span>';
			$html .= '</td>';

			$html .= '<td class="ms-col-sku">' . esc_html( $post->post_name ) . '</td>';

			foreach ( $child_sites as $site ) {
				$exists = $site_status[ $post->ID ][ $site->blog_id ] ?? false;
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
	// AJAX: копирование галереи
	// -------------------------------------------------------------------------

	public function ajax_copy_gallery() {
		check_ajax_referer( 'mps_copy_galleries_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$post_id        = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$target_site_id = isset( $_POST['site_id'] ) ? absint( $_POST['site_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( __( 'Неверный ID галереи', 'multisite-sync' ) );
		}

		$main_id = get_main_site_id();
		switch_to_blog( $main_id );

		$source = get_post( $post_id );
		if ( ! $source || 'ss_gallery' !== $source->post_type ) {
			restore_current_blog();
			wp_send_json_error( __( 'Галерея не найдена', 'multisite-sync' ) );
		}

		$gallery_images  = get_post_meta( $post_id, '_gallery_images', true );
		$gallery_columns = get_post_meta( $post_id, '_gallery_columns', true );

		$data = array(
			'title'           => $source->post_title,
			'slug'            => $source->post_name,
			'status'          => $source->post_status,
			'gallery_images'  => $gallery_images,
			'gallery_columns' => $gallery_columns,
		);

		restore_current_blog();

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

			global $wpdb;
			$existing_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_name = %s AND post_type = 'ss_gallery' AND post_status != 'trash'
				 LIMIT 1",
				$data['slug']
			) );

			$post_data = array(
				'post_title'  => $data['title'],
				'post_name'   => $data['slug'],
				'post_status' => $data['status'],
				'post_type'   => 'ss_gallery',
			);

			if ( $existing_id ) {
				$post_data['ID'] = (int) $existing_id;
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

			if ( '' !== $data['gallery_images'] ) {
				update_post_meta( $new_id, '_gallery_images', $data['gallery_images'] );
			} else {
				delete_post_meta( $new_id, '_gallery_images' );
			}

			if ( '' !== $data['gallery_columns'] ) {
				update_post_meta( $new_id, '_gallery_columns', $data['gallery_columns'] );
			} else {
				delete_post_meta( $new_id, '_gallery_columns' );
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

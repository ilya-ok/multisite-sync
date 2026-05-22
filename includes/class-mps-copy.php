<?php
/**
 * Класс копирования товаров между сайтами мультисайта
 *
 * @package Multisite_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс MS_Copy
 *
 * Страница копирования товаров с главного сайта на все дочерние
 */
class MS_Copy {

	private static $instance = null;
	private $page_hook = '';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'network_admin_menu', array( $this, 'add_submenu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_ms_get_copy_products', array( $this, 'ajax_get_products' ) );
		add_action( 'wp_ajax_ms_copy_product', array( $this, 'ajax_copy_product' ) );
	}

	public function add_submenu_page() {
		$this->page_hook = add_menu_page(
			__( 'Синхронизация Multisite', 'multisite-sync' ),
			__( 'Синхронизация Multisite', 'multisite-sync' ),
			'manage_network',
			'ms-bulk-edit',
			array( $this, 'render_page' ),
			'dashicons-update',
			30
		);

		// Переименовать авто-созданный первый подпункт меню
		add_submenu_page(
			'ms-bulk-edit',
			__( 'Копирование товаров', 'multisite-sync' ),
			__( 'Копирование товаров', 'multisite-sync' ),
			'manage_network',
			'ms-bulk-edit',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_scripts( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'ms-copy-products',
			MS_PLUGIN_URL . 'assets/css/copy-products.css',
			array( 'ms-bulk-edit' ),
			MS_VERSION
		);

		// Также подключаем bulk-edit.css если ещё не подключён
		wp_enqueue_style(
			'ms-bulk-edit',
			MS_PLUGIN_URL . 'assets/css/bulk-edit.css',
			array(),
			MS_VERSION
		);

		wp_enqueue_script(
			'ms-copy-products',
			MS_PLUGIN_URL . 'assets/js/copy-products.js',
			array( 'jquery' ),
			MS_VERSION,
			true
		);

		$main_id = get_main_site_id();
		$sites   = get_sites( array( 'number' => 0 ) );
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

		wp_localize_script(
			'ms-copy-products',
			'msCopyData',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ms_copy_nonce' ),
				'sites'   => $sites_data,
			)
		);
	}

	public function render_page() {
		$categories = $this->get_categories_tree();
		$main_id    = get_main_site_id();
		$sites      = get_sites( array( 'number' => 0 ) );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Копирование товаров', 'multisite-sync' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Выберите категорию, чтобы увидеть товары главного сайта и их наличие на дочерних сайтах. Нажмите «Копировать» — товар будет создан или обновлён на всех городах.', 'multisite-sync' ); ?>
			</p>

			<div class="ms-section">
				<div class="ms-bulk-header">
					<label for="ms-copy-category-select">
						<?php esc_html_e( 'Категория товаров:', 'multisite-sync' ); ?>
					</label>
					<select id="ms-copy-category-select">
						<option value="">— <?php esc_html_e( 'Выберите категорию', 'multisite-sync' ); ?> —</option>
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat['id'] ); ?>">
								<?php echo esc_html( $cat['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span id="ms-copy-product-count" class="ms-count"></span>
					<span class="ms-copy-loading" style="display:none;">
						<span class="spinner is-active" style="float:none;margin:0;"></span>
						<?php esc_html_e( 'Загрузка товаров...', 'multisite-sync' ); ?>
					</span>
				</div>
			</div>

			<div id="ms-copy-products-container" style="display:none;"></div>

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
	// Категории
	// -------------------------------------------------------------------------

	private function get_categories_tree() {
		// Загружаем категории главного сайта
		$main_id = get_main_site_id();
		switch_to_blog( $main_id );

		$all_terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'orderby'    => 'name',
			'hide_empty' => false,
			'number'     => 0,
		) );

		restore_current_blog();

		if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
			return array();
		}

		$children_map = array();
		foreach ( $all_terms as $term ) {
			$children_map[ $term->parent ][] = $term;
		}

		$result = array();
		$this->build_tree( 0, $children_map, $result, 0 );
		return $result;
	}

	private function build_tree( $parent_id, &$children_map, &$result, $depth ) {
		if ( ! isset( $children_map[ $parent_id ] ) ) {
			return;
		}
		foreach ( $children_map[ $parent_id ] as $term ) {
			$prefix   = str_repeat( '— ', $depth );
			$result[] = array(
				'id'    => $term->term_id,
				'label' => $prefix . $term->name,
			);
			$this->build_tree( $term->term_id, $children_map, $result, $depth + 1 );
		}
	}

	private function get_subcategory_ids( $cat_id ) {
		$ids      = array( (int) $cat_id );
		$children = get_terms( array(
			'taxonomy' => 'product_cat',
			'parent'   => $cat_id,
			'number'   => 0,
		) );
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child ) {
				$ids = array_merge( $ids, $this->get_subcategory_ids( $child->term_id ) );
			}
		}
		return $ids;
	}

	// -------------------------------------------------------------------------
	// AJAX: загрузка товаров
	// -------------------------------------------------------------------------

	public function ajax_get_products() {
		check_ajax_referer( 'ms_copy_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
		if ( ! $category_id ) {
			wp_send_json_error( 'Invalid category' );
		}

		$main_id = get_main_site_id();
		switch_to_blog( $main_id );

		$cat_ids  = $this->get_subcategory_ids( $category_id );
		$products = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'terms'    => $cat_ids,
					'operator' => 'IN',
				),
			),
		) );

		if ( empty( $products ) ) {
			restore_current_blog();
			wp_send_json_success( array(
				'html'  => '<p class="ms-empty">' . esc_html__( 'Товаров в этой категории нет.', 'multisite-sync' ) . '</p>',
				'count' => 0,
			) );
		}

		// Получаем статус товаров на дочерних сайтах
		$sites          = get_sites( array( 'number' => 0 ) );
		$child_sites    = array();
		foreach ( $sites as $site ) {
			if ( (int) $site->blog_id !== $main_id ) {
				$child_sites[] = $site;
			}
		}

		// Собираем SKU всех товаров
		$product_skus = array();
		foreach ( $products as $p ) {
			$product = wc_get_product( $p->ID );
			if ( $product ) {
				$product_skus[ $p->ID ] = $product->get_sku();
			}
		}

		restore_current_blog();

		// Проверяем наличие на каждом дочернем сайте — один запрос на сайт
		$site_status  = array();
		$skus_to_find = array_filter( array_unique( array_values( $product_skus ) ) );

		foreach ( $child_sites as $site ) {
			$found_skus = array();

			if ( ! empty( $skus_to_find ) ) {
				global $wpdb;
				switch_to_blog( $site->blog_id );

				$placeholders = implode( ',', array_fill( 0, count( $skus_to_find ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT pm.meta_value AS sku FROM {$wpdb->postmeta} pm
						INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
						WHERE pm.meta_key = '_sku'
						AND pm.meta_value IN ($placeholders)
						AND p.post_type = 'product'
						AND p.post_status != 'trash'",
						...$skus_to_find
					)
				);

				restore_current_blog();

				foreach ( $rows as $row ) {
					$found_skus[ $row->sku ] = true;
				}
			}

			foreach ( $product_skus as $product_id => $sku ) {
				if ( empty( $sku ) ) {
					$site_status[ $product_id ][ $site->blog_id ] = null;
				} else {
					$site_status[ $product_id ][ $site->blog_id ] = isset( $found_skus[ $sku ] );
				}
			}
		}

		// Снова переключаемся на главный для рендера таблицы
		switch_to_blog( $main_id );
		$html = $this->build_products_table( $products, $child_sites, $site_status, $product_skus );
		restore_current_blog();

		wp_send_json_success( array(
			'html'  => $html,
			'count' => count( $products ),
		) );
	}

	private function build_products_table( $products, $child_sites, $site_status, $product_skus ) {
		$html  = '<table class="widefat ms-products-table ms-copy-table">';
		$html .= '<thead><tr>';
		$html .= '<th class="ms-col-actions">' . esc_html__( 'Действия', 'multisite-sync' ) . '</th>';
		$html .= '<th class="ms-col-img"></th>';
		$html .= '<th class="ms-col-name">' . esc_html__( 'Товар', 'multisite-sync' ) . '</th>';
		$html .= '<th class="ms-col-sku">' . esc_html__( 'SKU', 'multisite-sync' ) . '</th>';

		foreach ( $child_sites as $site ) {
			$name  = get_blog_option( $site->blog_id, 'blogname' );
			$html .= '<th class="ms-col-site" title="' . esc_attr( $site->domain . $site->path ) . '">';
			$html .= esc_html( $name );
			$html .= '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( $products as $product_post ) {
			$product = wc_get_product( $product_post->ID );
			if ( ! $product ) {
				continue;
			}

			$id         = $product->get_id();
			$image_html = $product->get_image( 'woocommerce_thumbnail' );
			$sku        = $product_skus[ $id ] ?? '';

			$html .= '<tr class="ms-product-row" data-product-id="' . esc_attr( $id ) . '">';

			$html .= '<td class="ms-col-actions">';
			if ( ! empty( $sku ) ) {
				$html .= '<button class="ms-copy-btn button button-primary" data-product-id="' . esc_attr( $id ) . '">';
				$html .= esc_html__( 'Копировать', 'multisite-sync' );
				$html .= '</button>';
			} else {
				$html .= '<span class="ms-no-sku">' . esc_html__( 'Нет SKU', 'multisite-sync' ) . '</span>';
			}
			$html .= '<span class="ms-copy-status"></span>';
			$html .= '</td>';

			$html .= '<td class="ms-col-img"><div class="ms-product-img">' . $image_html . '</div></td>';
			$html .= '<td class="ms-col-name"><strong>' . esc_html( $product->get_name() ) . '</strong></td>';
			$html .= '<td class="ms-col-sku">' . esc_html( $sku ?: '—' ) . '</td>';

			foreach ( $child_sites as $site ) {
				$status = $site_status[ $id ][ $site->blog_id ] ?? null;
				if ( null === $status ) {
					$html .= '<td class="ms-col-site ms-site-status-nosku" title="' . esc_attr__( 'Нет SKU', 'multisite-sync' ) . '">?</td>';
				} elseif ( $status ) {
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
	// AJAX: копирование товара
	// -------------------------------------------------------------------------

	public function ajax_copy_product() {
		check_ajax_referer( 'ms_copy_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$product_id     = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$target_site_id = isset( $_POST['site_id'] ) ? intval( $_POST['site_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( __( 'Неверный ID товара', 'multisite-sync' ) );
		}

		$main_id = get_main_site_id();

		// -----------------------------------------------------------------------
		// Шаг 1: Собираем все данные с главного сайта ДО переключения
		// -----------------------------------------------------------------------
		switch_to_blog( $main_id );

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			restore_current_blog();
			wp_send_json_error( __( 'Товар не найден на главном сайте', 'multisite-sync' ) );
		}

		$sku = $product->get_sku();
		if ( empty( $sku ) ) {
			restore_current_blog();
			wp_send_json_error( __( 'Товар не имеет SKU. Копирование невозможно.', 'multisite-sync' ) );
		}

		$data = array(
			'title'              => $product->get_name(),
			'slug'               => get_post_field( 'post_name', $product_id ),
			'content'            => $product->get_description(),
			'excerpt'            => $product->get_short_description(),
			'status'             => $product->get_status(),
			'sku'                => $sku,
			'regular_price'      => $product->get_regular_price(),
			'sale_price'         => $product->get_sale_price(),
			'stock_status'       => $product->get_stock_status(),
			'manage_stock'       => $product->get_manage_stock(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'weight'             => $product->get_weight(),
			'length'             => $product->get_length(),
			'width'              => $product->get_width(),
			'height'             => $product->get_height(),
			'tax_class'          => $product->get_tax_class(),
			'tax_status'         => $product->get_tax_status(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'featured'           => $product->get_featured(),
			'virtual'            => $product->get_virtual(),
			'downloadable'       => $product->get_downloadable(),
			'menu_order'         => $product->get_menu_order(),
			'purchase_note'      => $product->get_purchase_note(),
		);

		// Мета-поля проекта (_product_attributes НЕ здесь — обрабатывается через set_attributes)
		$meta_keys = array(
			'_custom_price_type',
			'_price_otrez',
			'_price_opt',
			'_units',
			'_min_order',
			'_step_quantity',
			'_volume_unit',
			'fw_options',
			'_yoast_wpseo_primary_product_cat',
		);
		$data['meta'] = array();
		foreach ( $meta_keys as $key ) {
			$data['meta'][ $key ] = get_post_meta( $product_id, $key, true );
		}

		// Категории — по slug
		$data['category_slugs'] = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
		$data['tag_slugs']      = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'slugs' ) );

		// Атрибуты: taxonomy и custom
		$data['attributes'] = array();
		foreach ( $product->get_attributes() as $attr_key => $attr ) {
			if ( $attr->is_taxonomy() ) {
				$raw_terms = wc_get_product_terms( $product_id, $attr->get_name(), array( 'fields' => 'all' ) );
				$terms_data = array();
				foreach ( $raw_terms as $t ) {
					$terms_data[] = array( 'slug' => $t->slug, 'name' => $t->name );
				}
				$data['attributes'][ $attr_key ] = array(
					'name'         => $attr->get_name(),
					'is_taxonomy'  => true,
					'terms'        => $terms_data,
					'position'     => $attr->get_position(),
					'is_visible'   => $attr->get_visible(),
					'is_variation' => $attr->get_variation(),
				);
			} else {
				$data['attributes'][ $attr_key ] = array(
					'name'         => $attr->get_name(),
					'is_taxonomy'  => false,
					'options'      => $attr->get_options(),
					'position'     => $attr->get_position(),
					'is_visible'   => $attr->get_visible(),
					'is_variation' => $attr->get_variation(),
				);
			}
		}

		// Изображения — URL + post_name + alt ДО переключения
		$featured_id          = $product->get_image_id();
		$data['featured_image'] = null;
		if ( $featured_id ) {
			$data['featured_image'] = array(
				'url'       => wp_get_attachment_url( $featured_id ),
				'post_name' => get_post_field( 'post_name', $featured_id ),
				'title'     => get_post_field( 'post_title', $featured_id ),
				'alt'       => get_post_meta( $featured_id, '_wp_attachment_image_alt', true ),
			);
		}

		$gallery_ids          = $product->get_gallery_image_ids();
		$data['gallery_images'] = array();
		foreach ( $gallery_ids as $img_id ) {
			$url = wp_get_attachment_url( $img_id );
			if ( ! $url ) {
				continue;
			}
			$data['gallery_images'][] = array(
				'url'       => $url,
				'post_name' => get_post_field( 'post_name', $img_id ),
				'title'     => get_post_field( 'post_title', $img_id ),
				'alt'       => get_post_meta( $img_id, '_wp_attachment_image_alt', true ),
			);
		}

		restore_current_blog();

		// -----------------------------------------------------------------------
		// Шаг 2: Копируем на дочерние сайты
		// -----------------------------------------------------------------------
		$all_sites = get_sites( array( 'number' => 0 ) );
		$results   = array();

		// Если передан конкретный site_id — копируем только на него
		$sites = array();
		foreach ( $all_sites as $site ) {
			if ( (int) $site->blog_id === $main_id ) {
				continue;
			}
			if ( $target_site_id && (int) $site->blog_id !== $target_site_id ) {
				continue;
			}
			$sites[] = $site;
		}

		foreach ( $sites as $site ) {

			switch_to_blog( $site->blog_id );

			// Поиск существующего товара по SKU через прямой SQL (надёжнее wc_get_product_id_by_sku)
			global $wpdb;
			$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
				 AND p.post_type = 'product' AND p.post_status != 'trash'
				 LIMIT 1",
				$sku
			) );

			// Категории: ищем по slug на текущем сайте
			$cat_ids = array();
			if ( ! empty( $data['category_slugs'] ) ) {
				foreach ( $data['category_slugs'] as $slug ) {
					$term = get_term_by( 'slug', $slug, 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						$cat_ids[] = $term->term_id;
					}
				}
			}

			// Теги: ищем по slug
			$tag_ids = array();
			if ( ! empty( $data['tag_slugs'] ) ) {
				foreach ( $data['tag_slugs'] as $slug ) {
					$term = get_term_by( 'slug', $slug, 'product_tag' );
					if ( $term && ! is_wp_error( $term ) ) {
						$tag_ids[] = $term->term_id;
					}
				}
			}

			// Атрибуты: строим WC_Product_Attribute объекты
			$wc_attributes = array();
			foreach ( $data['attributes'] as $attr_key => $attr_data ) {
				$attribute = new WC_Product_Attribute();
				$attribute->set_name( $attr_data['name'] );
				$attribute->set_position( $attr_data['position'] );
				$attribute->set_visible( $attr_data['is_visible'] );
				$attribute->set_variation( $attr_data['is_variation'] );

				if ( $attr_data['is_taxonomy'] ) {
					$tax_id = wc_attribute_taxonomy_id_by_name( $attr_data['name'] );
					$attribute->set_id( $tax_id );

					$term_ids = array();
					foreach ( $attr_data['terms'] as $term_data ) {
						$existing_term = get_term_by( 'slug', $term_data['slug'], $attr_data['name'] );
						if ( $existing_term && ! is_wp_error( $existing_term ) ) {
							$term_ids[] = $existing_term->term_id;
						} else {
							$inserted = wp_insert_term( $term_data['name'], $attr_data['name'], array( 'slug' => $term_data['slug'] ) );
							if ( ! is_wp_error( $inserted ) ) {
								$term_ids[] = $inserted['term_id'];
							}
						}
					}
					$attribute->set_options( $term_ids );
				} else {
					$attribute->set_options( $attr_data['options'] );
				}

				$wc_attributes[ $attr_key ] = $attribute;
			}

			// Создаём или обновляем пост товара
			$post_data = array(
				'post_title'   => wp_strip_all_tags( $data['title'] ),
				'post_name'    => $data['slug'],
				'post_content' => $data['content'],
				'post_excerpt' => $data['excerpt'],
				'post_status'  => $data['status'],
				'post_type'    => 'product',
			);

			if ( $existing_id ) {
				$post_data['ID'] = $existing_id;
				$new_product_id  = wp_update_post( $post_data, true );
			} else {
				$new_product_id = wp_insert_post( $post_data, true );
			}

			if ( is_wp_error( $new_product_id ) ) {
				$results[ $site->blog_id ] = array(
					'success' => false,
					'error'   => $new_product_id->get_error_message(),
				);
				restore_current_blog();
				continue;
			}

			// WooCommerce-поля
			$new_product = wc_get_product( $new_product_id );
			if ( ! $new_product ) {
				$results[ $site->blog_id ] = array(
					'success' => false,
					'error'   => 'wc_get_product failed',
				);
				restore_current_blog();
				continue;
			}

			$new_product->set_sku( $sku );
			$new_product->set_regular_price( $data['regular_price'] );
			$new_product->set_sale_price( $data['sale_price'] );
			$new_product->set_stock_status( $data['stock_status'] );
			$new_product->set_manage_stock( $data['manage_stock'] );
			if ( null !== $data['stock_quantity'] ) {
				$new_product->set_stock_quantity( $data['stock_quantity'] );
			}
			$new_product->set_weight( $data['weight'] );
			$new_product->set_length( $data['length'] );
			$new_product->set_width( $data['width'] );
			$new_product->set_height( $data['height'] );
			$new_product->set_tax_class( $data['tax_class'] );
			$new_product->set_tax_status( $data['tax_status'] );
			$new_product->set_catalog_visibility( $data['catalog_visibility'] );
			$new_product->set_featured( $data['featured'] );
			$new_product->set_virtual( $data['virtual'] );
			$new_product->set_downloadable( $data['downloadable'] );
			$new_product->set_menu_order( $data['menu_order'] );
			$new_product->set_purchase_note( $data['purchase_note'] );

			// Атрибуты через WC API (должно быть ДО save)
			if ( ! empty( $wc_attributes ) ) {
				$new_product->set_attributes( $wc_attributes );
			}

			// Категории и теги
			wp_set_post_terms( $new_product_id, $cat_ids, 'product_cat' );
			wp_set_post_terms( $new_product_id, $tag_ids, 'product_tag' );

			// Изображения
			if ( ! empty( $data['featured_image'] ) ) {
				$featured_target_id = $this->get_or_sideload_image( $data['featured_image'], $new_product_id );
				if ( $featured_target_id ) {
					$new_product->set_image_id( $featured_target_id );
				}
			}

			$gallery_target_ids = array();
			foreach ( $data['gallery_images'] as $img_data ) {
				$img_id = $this->get_or_sideload_image( $img_data, $new_product_id );
				if ( $img_id ) {
					$gallery_target_ids[] = $img_id;
				}
			}
			$new_product->set_gallery_image_ids( $gallery_target_ids );

			$new_product->save();

			// Кастомные мета-поля — ПОСЛЕ save(), чтобы save() их не перезаписал
			foreach ( $data['meta'] as $key => $value ) {
				if ( '' !== $value && null !== $value && false !== $value ) {
					update_post_meta( $new_product_id, $key, $value );
				}
			}

			$results[ $site->blog_id ] = array( 'success' => true );

			restore_current_blog();
		}

		wp_send_json_success( array(
			'results' => $results,
			'message' => __( 'Копирование завершено', 'multisite-sync' ),
		) );
	}

	// -------------------------------------------------------------------------
	// Вспомогательный метод: найти или подгрузить изображение
	// -------------------------------------------------------------------------

	private function get_or_sideload_image( array $img_data, int $post_id ): int {
		if ( empty( $img_data['url'] ) || empty( $img_data['post_name'] ) ) {
			return 0;
		}

		global $wpdb;

		// Ищем по post_name — уникальный slug вложения
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_name = %s AND post_type = 'attachment'
			 LIMIT 1",
			$img_data['post_name']
		) );

		if ( $existing ) {
			return (int) $existing;
		}

		// Подгружаем изображение с источника
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$new_id = media_sideload_image( $img_data['url'], 0, $img_data['title'], 'id' );

		if ( is_wp_error( $new_id ) ) {
			return 0;
		}

		// Фиксируем post_name чтобы в следующий раз нашли без sideload
		wp_update_post( array(
			'ID'        => $new_id,
			'post_name' => $img_data['post_name'],
		) );

		if ( ! empty( $img_data['alt'] ) ) {
			update_post_meta( $new_id, '_wp_attachment_image_alt', $img_data['alt'] );
		}

		return (int) $new_id;
	}
}

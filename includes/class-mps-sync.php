<?php
/**
 * Класс синхронизации цен между сайтами мультисайта
 *
 * @package Multisite_Price_Sync
 */

// Запрет прямого доступа
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Класс MS_Sync
 *
 * Отслеживает изменения цен товаров и синхронизирует их на все сайты мультисайта
 */
class MS_Sync {

	/**
	 * Singleton instance
	 *
	 * @var MS_Sync|null
	 */
	private static $instance = null;

	/**
	 * Флаг для предотвращения рекурсии
	 *
	 * @var bool
	 */
	private $is_syncing = false;

	/**
	 * Получение экземпляра класса (Singleton)
	 *
	 * @return MS_Sync
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
		// Хук на сохранение товара
		add_action( 'woocommerce_update_product', array( $this, 'sync_product_price' ), 10, 1 );
		add_action( 'woocommerce_new_product', array( $this, 'sync_product_price' ), 10, 1 );

		// Хук на обновление мета-данных (для вариаций)
		add_action( 'updated_post_meta', array( $this, 'sync_on_meta_update' ), 10, 4 );
	}

	/**
	 * Синхронизация цены товара при сохранении
	 *
	 * @param int $product_id ID товара
	 */
	public function sync_product_price( $product_id ) {
		// Предотвращение рекурсии
		if ( $this->is_syncing ) {
			return;
		}

		// Проверка настройки автосинхронизации
		if ( ! get_site_option( 'ms_auto_sync', '1' ) ) {
			return;
		}

		// Получаем товар
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		// Получаем SKU
		$sku = $product->get_sku();
		if ( empty( $sku ) ) {
			// Пропускаем товары без SKU
			return;
		}

		// Получаем цены
		$regular_price = $product->get_regular_price();
		$sale_price = $product->get_sale_price();

		// Получаем дополнительные мета-данные
		$meta_fields = array(
			'_custom_price_type',
			'_price_otrez',
			'_price_opt',
			'_units',
			'_min_order',
			'_step_quantity',
			'_volume_unit',
		);

		$meta_data = array();
		foreach ( $meta_fields as $meta_key ) {
			$value = get_post_meta( $product_id, $meta_key, true );
			if ( '' !== $value ) {
				$meta_data[ $meta_key ] = $value;
			}
		}

		// Синхронизируем на все сайты
		$this->sync_to_all_sites( $sku, $regular_price, $sale_price, $product_id, $meta_data );
	}

	/**
	 * Синхронизация при обновлении мета-данных
	 *
	 * Для отслеживания изменений через быстрое/массовое редактирование
	 *
	 * @param int    $meta_id     ID мета-данных
	 * @param int    $object_id   ID объекта (товара)
	 * @param string $meta_key    Ключ мета-данных
	 * @param mixed  $meta_value  Значение мета-данных
	 */
	public function sync_on_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Проверяем только изменения цен
		if ( ! in_array( $meta_key, array( '_regular_price', '_sale_price', '_price' ), true ) ) {
			return;
		}

		// Проверяем, что это товар
		if ( 'product' !== get_post_type( $object_id ) && 'product_variation' !== get_post_type( $object_id ) ) {
			return;
		}

		// Синхронизируем товар
		$this->sync_product_price( $object_id );
	}

	/**
	 * Синхронизация цен на все сайты мультисайта
	 *
	 * @param string $sku           SKU товара
	 * @param string $regular_price Обычная цена
	 * @param string $sale_price    Цена со скидкой
	 * @param int    $exclude_id    ID товара для исключения (текущий сайт)
	 * @param array  $meta_data     Дополнительные мета-данные для синхронизации
	 */
	public function sync_to_all_sites( $sku, $regular_price, $sale_price, $exclude_id = 0, $meta_data = array() ) {
		// Включаем флаг синхронизации
		$this->is_syncing = true;

		// Получаем текущий сайт
		$current_site_id = get_current_blog_id();

		// Получаем все сайты мультисайта
		$sites = get_sites( array(
			'number' => 0, // Без ограничений
		) );

		foreach ( $sites as $site ) {
			// Пропускаем текущий сайт
			if ( (int) $site->blog_id === $current_site_id ) {
				continue;
			}

			// Переключаемся на другой сайт
			switch_to_blog( $site->blog_id );

			// Ищем товар с таким же SKU
			$product_id = wc_get_product_id_by_sku( $sku );

			if ( $product_id ) {
				// Обновляем цены и мета-данные
				$this->update_product_prices( $product_id, $regular_price, $sale_price, $meta_data );
			}

			// Возвращаемся на исходный сайт
			restore_current_blog();
		}

		// Отключаем флаг синхронизации
		$this->is_syncing = false;
	}

	/**
	 * Обновление цен товара
	 *
	 * @param int    $product_id    ID товара
	 * @param string $regular_price Обычная цена
	 * @param string $sale_price    Цена со скидкой
	 * @param array  $meta_data     Дополнительные мета-данные
	 */
	private function update_product_prices( $product_id, $regular_price, $sale_price, $meta_data = array() ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		// Обновляем обычную цену
		if ( get_site_option( 'ms_sync_regular_price', '1' ) ) {
			$product->set_regular_price( $regular_price );
		}

		// Обновляем цену со скидкой
		if ( get_site_option( 'ms_sync_sale_price', '1' ) ) {
			$product->set_sale_price( $sale_price );
		}

		// Сохраняем товар
		$product->save();

		// Обновляем дополнительные мета-данные
		if ( ! empty( $meta_data ) ) {
			foreach ( $meta_data as $meta_key => $meta_value ) {
				update_post_meta( $product_id, $meta_key, $meta_value );
			}
		}
	}

	/**
	 * Массовая синхронизация цен
	 *
	 * Используется для страницы массового редактирования
	 *
	 * @param array $price_updates Массив обновлений: [ sku => [ 'regular' => price, 'sale' => price, 'meta' => [...] ] ]
	 * @return array Результат синхронизации
	 */
	public function bulk_sync_prices( $price_updates ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $price_updates as $sku => $prices ) {
			$regular_price = isset( $prices['regular'] ) ? $prices['regular'] : '';
			$sale_price    = isset( $prices['sale'] ) ? $prices['sale'] : '';
			$meta_data     = isset( $prices['meta'] ) ? $prices['meta'] : array();

			// Проверяем наличие товара с таким SKU на текущем сайте
			$product_id = wc_get_product_id_by_sku( $sku );

			if ( ! $product_id ) {
				$results['failed']++;
				$results['errors'][] = sprintf(
					/* translators: %s: SKU товара */
					__( 'Товар с SKU "%s" не найден', 'multisite-sync' ),
					$sku
				);
				continue;
			}

			// Обновляем цены на текущем сайте
			$this->update_product_prices( $product_id, $regular_price, $sale_price, $meta_data );

			// Синхронизируем на другие сайты
			$this->sync_to_all_sites( $sku, $regular_price, $sale_price, $product_id, $meta_data );

			$results['success']++;
		}

		return $results;
	}
}

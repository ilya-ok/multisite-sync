# Процессы синхронизации и копирования

## Автосинхронизация цен (MS_Sync)

```
Пользователь сохраняет товар на сайте A
  → хук woocommerce_update_product
  → MS_Sync::sync_product_price($product_id)
  → проверка $is_syncing и ms_auto_sync
  → получаем SKU (если пусто → STOP)
  → получаем regular_price, sale_price, мета-поля
  → $is_syncing = true
  → для каждого сайта кроме A:
       switch_to_blog($site_id)
       wc_get_product_id_by_sku($sku)
       если найден → update_product_prices()
       restore_current_blog()
  → $is_syncing = false
```

## Массовое редактирование цен (MS_Bulk_Edit)

```
Network Admin → Цены Multisite
  → выбор категории → AJAX ms_get_products
  → get_posts(tax_query по category + subcategories, limit 200)
  → build_products_table() → HTML таблица

Пользователь меняет цены → кнопка Сохранить
  → AJAX ms_save_product
  → проверка SKU (если пусто → ошибка)
  → $product->set_regular_price() / set_sale_price() / save()
  → update_post_meta() для кастомных полей
  → MS_Sync::sync_to_all_sites($sku, $regular, $sale, $product_id, $meta_data)
  → ✓ статус в UI
```

## Копирование товаров (MS_Copy)

```
Network Admin → Копирование товаров
  → выбор категории → AJAX ms_get_copy_products
  → switch_to_blog(main_site_id)
  → get_posts(tax_query, limit 200)
  → для каждого товара: wc_get_product_id_by_sku на каждом дочернем сайте
  → restore_current_blog()
  → build_products_table() с колонками [Сайт1 ✓/—] [Сайт2 ✓/—] ...

Нажать Копировать
  → AJAX ms_copy_product($product_id)

  ШАГ 1 — сбор данных (switch_to_blog(main)):
    - все поля WC-продукта (set_* методы)
    - мета-поля: _product_attributes, fw_options, кастомные цены и т.д.
    - категории/теги → slugs
    - taxonomy-атрибуты → [tax => [term_slugs]]
    - featured_image → { url, post_name, title, alt }
    - gallery_images  → [{ url, post_name, title, alt }]
    restore_current_blog()

  ШАГ 2 — для каждого дочернего сайта:
    switch_to_blog($site_id)
    wc_get_product_id_by_sku($sku) → $existing_id (или 0)
    найти категории по slug: get_term_by('slug', ...)
    создать отсутствующие термины атрибутов: wp_insert_term()
    wp_update_post / wp_insert_post → $new_product_id
    wc_get_product($new_product_id) → set_* → wp_set_post_terms → update_post_meta
    get_or_sideload_image(featured) → set_image_id()
    get_or_sideload_image(gallery[]) → set_gallery_image_ids()
    $new_product->save()
    restore_current_blog()

  → JS обновляет ячейки ✓/✗ без перезагрузки
```

## Копирование страниц (MPS_Copy_Pages)

```
Network Admin → Копирование страниц
  → кнопка «Загрузить страницы» → AJAX mps_get_pages
  → switch_to_blog(main_site_id)
  → get_posts(post_type=page, limit 200, publish+draft)
  → для каждого дочернего сайта: SQL поиск по post_name
  → restore_current_blog()
  → build_pages_table() с колонками [Сайт1 ✓/—] [Сайт2 ✓/—] ...

Нажать Копировать
  → AJAX mps_copy_page($page_id, $site_id)

  ШАГ 1 — сбор данных (switch_to_blog(main)):
    - post_title, post_name, post_content, post_status
    - get_post_meta($id, '_spb_blocks', true)
    restore_current_blog()

  ШАГ 2 — для каждого дочернего сайта:
    switch_to_blog($site_id)
    get_page_by_path($slug) → существует? wp_update_post : wp_insert_post
    update_post_meta($new_id, '_spb_blocks', $json)  ← без изменений
    restore_current_blog()

  → JS обновляет ячейки ✓/✗ без перезагрузки
```

## Идентификация изображений

Идентификатор: `post_name` (slug) вложения. Генерируется WP из имени файла при загрузке.

```sql
SELECT ID FROM wp_N_posts
WHERE post_name = 'filename-slug' AND post_type = 'attachment'
LIMIT 1
```

Если не найдено → `media_sideload_image($url, 0, $title, 'id')` → `wp_update_post(['post_name' => $slug])`.

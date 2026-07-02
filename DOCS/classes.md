# Классы плагина

## MS_Sync (class-mps-sync.php)

Автоматическая синхронизация цен между сайтами при сохранении товара.

**Хуки:**
```php
add_action('woocommerce_update_product', 'sync_product_price');
add_action('woocommerce_new_product',    'sync_product_price');
add_action('updated_post_meta',          'sync_on_meta_update'); // для быстрого редактирования
```

**Ключевые методы:**

`sync_product_price($product_id)` — вызывается при сохранении товара. Проверяет флаг `$is_syncing` и настройку `ms_auto_sync`, получает SKU и цены, вызывает `sync_to_all_sites()`.

`sync_to_all_sites($sku, $regular, $sale, $exclude_id, $meta_data)` — обходит все сайты сети через `switch_to_blog()`, ищет товар по SKU, обновляет цены. Флаг `$is_syncing = true` предотвращает рекурсию.

`update_product_prices($product_id, $regular, $sale, $meta_data)` — обновляет цены и мета-поля товара. Учитывает настройки `ms_sync_regular_price` и `ms_sync_sale_price`.

`bulk_sync_prices($price_updates)` — массовая синхронизация, принимает `[sku => [regular, sale, meta]]`.

**Мета-поля, которые синхронизируются:**
```
_custom_price_type, _price_otrez, _price_opt, _units,
_min_order, _step_quantity, _volume_unit
```

---

## MS_Bulk_Edit (class-mps-bulk-edit.php)

Страница массового редактирования цен товаров.

**Расположение:** Network Admin → Синхронизация Multisite (главная страница меню)

**AJAX actions:**
- `ms_get_products` — загрузка товаров категории, возвращает HTML таблицы
- `ms_save_product` — сохранение полей одного товара + sync на все сайты

**Метод `build_products_table()`** — генерирует таблицу с колонками:
- Фото, Название, SKU
- Обычная цена, Цена со скидкой, Тип цены (select: стандартная/опт/отрез)
- Цена отрез, Цена опт, Ед. изм., Мин. заказ, Шаг кол-во, Объём ед.
- Кнопка Сохранить + статус

**Nonce:** `ms_bulk_edit_nonce`
**JS объект:** `msData = { ajaxurl, nonce }`

---

## MS_Admin (class-mps-admin.php)

Страница настроек плагина.

**Расположение:** Network Admin → Синхронизация Multisite → Настройки (slug: `ms-settings`)

**Настройки:** `ms_auto_sync`, `ms_sync_regular_price`, `ms_sync_sale_price`, `ms_debug_mode`

Сохранение через POST-форму с nonce `ms_save_settings`.

**UI попапы:** у каждой настройки есть кнопка `?` — по клику открывается карточка с описанием настройки и ссылкой на соответствующий раздел главного сайта (`get_admin_url(get_main_site_id(), ...)`). Реализовано inline CSS/JS в `render_settings_page()`.

---

## MPS_Copy_Pages (class-mps-copy-pages.php)

Копирование страниц (с блоками SPB) с главного сайта на дочерние.

**Расположение:** Network Admin → Синхронизация Multisite → Копирование страниц (slug: `mps-copy-pages`)

**AJAX actions:**
- `mps_get_pages` — загружает все страницы главного сайта (publish + draft) + статус на каждом дочернем сайте
- `mps_copy_page` — копирует одну страницу на **один** указанный сайт (параметр `site_id`)

**Nonce:** `mps_copy_pages_nonce`
**JS объект:** `mpsCopyPagesData = { ajaxurl, nonce, sites: [{id, name}] }`

**Что копируется:**
- `post_title`, `post_name` (slug), `post_content`, `post_status`
- Мета `_spb_blocks` — блоки декодируются через `spb_get_blocks()` в PHP-массив, затем сохраняются через `spb_save_blocks()` (с `wp_slash` + `json_encode`). Прямая передача «сырой» строки не используется — MySQL убирает `\"` при вставке, что ломает JSON для блоков с HTML-атрибутами (цвет, выравнивание).

**Идентификатор страницы:** `post_name` (slug). Если страница с таким slug уже есть → `wp_update_post`, нет → `wp_insert_post`.

**Поиск существующей страницы:** `get_page_by_path($slug, OBJECT, 'page')`.

**Таблица — колонки:**
Действия (кнопка) | Название + бейдж SPB + статус | Slug | [Сайт1] [Сайт2] ...

**JS (copy-pages.js):** кнопка «Загрузить страницы» → AJAX → таблица. Кнопка «Копировать» → последовательный обход сайтов, ячейки обновляются в реальном времени (✓/✗).

---

## MPS_Copy_Posts (class-mps-copy-posts.php)

Копирование записей (post type `post`) с главного сайта на дочерние.

**Расположение:** Network Admin → Синхронизация Multisite → Копирование записей (slug: `mps-copy-posts`)

**AJAX actions:**
- `mps_get_posts` — загружает записи главного сайта (publish + draft, до 500) + статус на каждом дочернем сайте
- `mps_copy_post` — копирует одну запись на **один** указанный сайт (параметр `site_id`)

**Nonce:** `mps_copy_posts_nonce`
**JS объект:** `mpsCopyPostsData = { ajaxurl, nonce, sites: [{id, name}] }`

**Что копируется:**
- `post_title`, `post_name` (slug), `post_content`, `post_excerpt`, `post_status`
- Категории: поиск по slug на дочернем сайте, создание через `wp_insert_term` если не найдена
- Мета-поля: `_post_city`, `_post_area`, `_post_product`, `_post_link`

**Идентификатор записи:** `post_name` (slug). Если запись с таким slug уже есть → `wp_update_post`, нет → `wp_insert_post`.

**Поиск существующей записи:** прямой SQL по `post_name` и `post_type = 'post'`.

**Таблица — колонки:**
Действия (кнопка) | Название + категории + статус | Slug | [Сайт1] [Сайт2] ...

**JS (copy-posts.js):** кнопка «Загрузить записи» → AJAX → таблица. Кнопка «Копировать» → последовательный обход сайтов, ячейки обновляются в реальном времени (✓/✗).

---

## MS_Copy (class-mps-copy.php)

Копирование товаров с главного сайта (`get_main_site_id()`) на все дочерние сайты.

**Расположение:** Network Admin → Синхронизация Multisite → Копирование товаров (slug: `ms-copy-products`)

**AJAX actions:**
- `ms_get_copy_products` — загружает товары главного сайта + статус по каждому дочернему сайту
- `ms_copy_product` — копирует один товар на **один** указанный сайт (параметр `site_id`)

**Nonce:** `ms_copy_nonce`
**JS объект:** `msCopyData = { ajaxurl, nonce, sites: [{id, name}] }`

**Что копируется:**
- Заголовок, slug, описание, краткое описание, статус
- SKU, цены (regular, sale), склад, габариты, налоги
- Видимость каталога, featured, virtual, downloadable
- Мета (обновляется ПОСЛЕ `save()`): `_custom_price_type`, `_price_otrez`, `_price_opt`, `_units`, `_min_order`, `_step_quantity`, `_volume_unit`, `fw_options`, `_yoast_wpseo_primary_product_cat`
- Категории и теги (поиск по slug)
- Атрибуты: taxonomy (через `set_attributes()` + `WC_Product_Attribute`) и custom (через options)
- Главное изображение + галерея

**Важно — порядок сохранения атрибутов:**
1. На главном сайте собираем `{slug, name}` каждого термина и options кастомных атрибутов
2. На дочернем сайте строим `WC_Product_Attribute` объекты, находим/создаём термины по slug (с правильным именем!)
3. Вызываем `$product->set_attributes($wc_attributes)` ДО `save()`
4. `save()` сам записывает `_product_attributes` — ручное `update_post_meta` для этого ключа не нужно
5. Кастомные мета-поля обновляем ПОСЛЕ `save()` — иначе `save()` их перезапишет

**Поиск существующего товара — прямой SQL:**
```php
SELECT pm.post_id FROM {$wpdb->postmeta} pm
INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
AND p.post_type = 'product' AND p.post_status != 'trash'
```
Используется вместо `wc_get_product_id_by_sku()` — та может давать ложный 0 из-за кеша lookup-таблицы WooCommerce.

**Загрузка статусов (`ajax_get_products`) — оптимизация:**
Один SQL-запрос на дочерний сайт возвращает все найденные SKU сразу (вместо N запросов `wc_get_product_id_by_sku` × 29 сайтов).

**Таблица — порядок колонок:**
Действия (кнопка) | Фото | Название | SKU | [Сайт1] [Сайт2] ...

**Логика изображений (`get_or_sideload_image()`):**
1. Поиск по `post_name` вложения в `wp_posts` — `WHERE post_name = %s AND post_type = 'attachment'`
2. Если найдено → используем существующее (без повторной загрузки)
3. Если нет → `media_sideload_image($url, 0, $title, 'id')`, затем `wp_update_post(['post_name' => ...])` для фиксации slug

**Логика копирования:**
```
Шаг 1 (switch_to_blog(main)): собрать ВСЕ данные с главного сайта:
       поля WC, мета, категории/теги (slugs),
       атрибуты: taxonomy → [{slug, name}], custom → options[],
       featured_image + gallery → {url, post_name, title, alt}
       restore_current_blog()

Шаг 2 (один запрос = один site_id):
       switch_to_blog($site_id)
       → прямой SQL поиск по SKU → wp_update_post / wp_insert_post
       → найти/создать термины категорий, тегов, атрибутов
       → set_* методы WC + set_attributes($wc_attributes)
       → wp_set_post_terms (категории, теги)
       → get_or_sideload_image (featured + gallery)
       → save()
       → update_post_meta (кастомные мета — ПОСЛЕ save)
       restore_current_blog()
```

# Классы плагина


## MS_Bulk_Edit (class-mps-bulk-edit.php)

Страница массового редактирования цен товаров.

**Расположение:** Network Admin → Синхронизация Multisite (главная страница меню)

**AJAX actions:**
- `ms_get_products` — загрузка товаров категории, возвращает HTML таблицы
- `ms_save_product` — сохранение полей одного товара + sync на все сайты

**Nonce:** `ms_bulk_edit_nonce`
**JS объект:** `msData = { ajaxurl, nonce }`

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

## MPS_Copy_Portfolio (class-mps-copy-portfolio.php)

Копирование записей типа `portfolio` с главного сайта на дочерние.

**Расположение:** Network Admin → Синхронизация Multisite → Копирование портфолио (slug: `mps-copy-portfolio`)

**AJAX actions:**
- `mps_get_portfolio` — загружает записи portfolio главного сайта (publish + draft, до 500) + статус на каждом дочернем сайте
- `mps_copy_portfolio` — копирует одну запись на **один** указанный сайт (параметр `site_id`)

**Nonce:** `mps_copy_portfolio_nonce`  
**JS объект:** `mpsCopyPortfolioData = { ajaxurl, nonce, sites: [{id, name}] }`  
**JS файл:** `assets/js/copy-portfolio.js`

**Что копируется:**
- `post_title`, `post_name` (slug), `post_content`, `post_excerpt`, `post_status`
- `post_date`, `post_date_gmt` — дата публикации
- Мета-поля: `_portfolio_image`, `_post_city`, `_post_area`, `_post_product`, `_post_link`, `_post_work_name`, `_post_work_link`

> **Примечание:** `_portfolio_gallery` удалено из списка копируемых мета-полей — галереи вынесены в отдельный CPT `ss_gallery`.

**Изображения — подход без дублирования:**  
`_portfolio_image` хранит **только имя файла**. Файлы лежат в `wp-content/portfolio/` — общей папке, доступной по URL любого поддомена. Файлы не копируются.

**Идентификатор записи:** `post_name` (slug). Если запись с таким slug уже есть → `wp_update_post`, нет → `wp_insert_post`.

**Поиск существующей записи:** прямой SQL по `post_name` и `post_type = 'portfolio'`.

**JS:** последовательный обход сайтов `copySite()` → `next()`, счётчик «Сайт X из N: название», итог «✓ Готово: N, ошибок: N», таймаут 60 сек на запрос.

**Таблица — колонки:**  
Действия (кнопка) | Название + статус | Slug | [Сайт1] [Сайт2] ...

---

## MPS_Copy_Galleries (class-mps-copy-galleries.php)

Копирование галерей (CPT `ss_gallery`) с главного сайта на дочерние.

**Расположение:** Network Admin → Синхронизация Multisite → Копирование галерей (slug: `mps-copy-galleries`)

**AJAX actions:**
- `mps_get_galleries` — загружает все галереи главного сайта (publish + draft, до 500) + статус на каждом дочернем сайте
- `mps_copy_gallery` — копирует одну галерею на **один** указанный сайт (параметр `site_id`)

**Nonce:** `mps_copy_galleries_nonce`  
**JS объект:** `mpsCopyGalleriesData = { ajaxurl, nonce, sites: [{id, name}] }`  
**JS файл:** `assets/js/copy-galleries.js`

**Что копируется:**
- `post_title`, `post_name` (slug), `post_status`
- Мета-поле `_gallery_images` — JSON-массив имён файлов
- Мета-поле `_gallery_columns` — JSON настроек колонок по брейкпоинтам

**Файлы не копируются:** `wp-content/galleries/` — общая папка для всех поддоменов на одном сервере. Нужны только имена файлов.

**Идентификатор галереи:** `post_name` (slug). Если галерея с таким slug уже есть → `wp_update_post`, нет → `wp_insert_post`.

**Поиск существующей галереи:** прямой SQL по `post_name` и `post_type = 'ss_gallery'`.

**JS:** последовательный обход сайтов `copySite()` → `next()`, счётчик «Сайт X из N: название», итог «✓ Готово: N, ошибок: N», таймаут 60 сек на запрос.

**Таблица — колонки:**  
Действия (кнопка) | Галерея + статус | Slug | [Сайт1] [Сайт2] ...

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

---

## MPS_Delete (class-mps-delete.php)

Удаление и восстановление товаров на всех сайтах сети + создание 301-редиректов.

**Расположение:** Network Admin → Синхронизация Multisite → Удаление товаров (slug: `ms-delete-products`)

**AJAX actions:**
- `ms_get_delete_products` — загружает все товары категории **включая корзину** (`post_status IN (publish, draft, private, pending, trash)`), возвращает HTML таблицы с 3-состоянием на каждый сайт
- `ms_delete_product` — перемещает товар в корзину на **одном** сайте (`wp_trash_post`)
- `ms_restore_product` — восстанавливает товар из корзины на **одном** сайте (`wp_untrash_post`)
- `ms_create_redirect` — сохраняет 301-редирект в `mps_redirects` для списка сайтов

**Nonce:** `ms_delete_nonce`
**JS объект:** `msDeleteData = { ajaxurl, nonce, sites: [{id, name}] }`

**3-состояния ячейки сайта:**
- `active` — ✓ зелёный (товар опубликован / в черновике)
- `trashed` — 🗑 жёлтый (товар в корзине)
- `missing` — — серый (товар на главном сайте, но не скопирован)

**Ключевые методы:**

`ajax_get_products()` — главный сайт: загружает товары + собирает SKU, slug, статус, категории-предложения для редиректа. Для каждого дочернего сайта — один SQL-запрос на все SKU сразу.

`get_original_path($product_id)` — возвращает относительный URL товара. Для товаров в корзине WordPress добавляет суффикс `__trashed` к slug — метод убирает его через `preg_replace('/__trashed\/$/', '/', $path)`.

`build_products_table()` — рендерит HTML таблицы. Для уже трашнутых товаров сразу вставляет форму редиректа (`build_redirect_form_html()`).

`build_redirect_form_html($slug_path, $cats_json, $trashed_sites_json)` — HTML блока редиректа: ссылка на удалённый URL (красная), поле FROM (монопространственное), поле TO (input), select категорий-предложений, кнопка «Создать редирект».

`ajax_delete_product()` / `ajax_restore_product()` — **один сайт за вызов** (параметр `site_id`). Прямой SQL поиск по SKU + `wp_trash_post` / `wp_untrash_post`. Один запрос на сайт предотвращает PHP-таймаут (WooCommerce-хуки при trash/untrash обновляют lookup-таблицы и кеши на каждом сайте).

`ajax_create_redirect()` — нормализует пути (`'/' . trim($path, '/') . '/'`), сохраняет `['from' => 'to']` в `mps_redirects` option для каждого `site_id` из списка.

**Важно — data-sku на кнопках:**
Кнопки «Удалить» и «Восстановить» должны иметь атрибут `data-sku`. Без него JS читает `undefined`, PHP возвращает «Неверные параметры» для каждого сайта. Атрибут задаётся в `$common_attrs` внутри `build_products_table()`.

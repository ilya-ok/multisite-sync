# История изменений

## [1.5.0] - 2026-07-14

### Добавлено
- Класс `MPS_Copy_Galleries` (`includes/class-mps-copy-galleries.php`) — копирование CPT `ss_gallery` с главного сайта на дочерние
- AJAX `mps_get_galleries` — загрузка таблицы галерей со статусом по дочерним сайтам
- AJAX `mps_copy_gallery` — копирование одной галереи на один сайт (title, slug, status, `_gallery_images`, `_gallery_columns`)
- `assets/js/copy-galleries.js` — последовательный обход сайтов, счётчик «Сайт X из N: название», итог «✓ Готово: N, ошибок: N», таймаут 60 сек
- Файлы не копируются — `wp-content/galleries/` общая папка на сервере

### Изменено
- `MPS_Copy_Portfolio` — удалено `_portfolio_gallery` из списка копируемых мета-полей (галереи вынесены в `ss_gallery`)

---

## [1.4.0] - 2026-07-09

### Добавлено
- Страница «Удаление товаров» (`MPS_Delete`, `class-mps-delete.php`, slug: `ms-delete-products`)
- Показывает все товары включая корзину (`post_status IN (publish,draft,private,pending,trash)`)
- 3-состояние ячейки сайта: `active` (✓) / `trashed` (🗑) / `missing` (—)
- AJAX `ms_get_delete_products` — загрузка таблицы с метаданными по сайтам
- AJAX `ms_delete_product` — перемещение в корзину (`wp_trash_post`), **один сайт за запрос**
- AJAX `ms_restore_product` — восстановление из корзины (`wp_untrash_post`), **один сайт за запрос**
- AJAX `ms_create_redirect` — сохранение 301-редиректа в `mps_redirects` option
- Хук `mps_handle_redirects` (`template_redirect`, prio 1) в `multisite-sync.php` — применяет редиректы на всех сайтах сети
- Форма редиректа после удаления: ссылка на удалённый URL, поле TO, select категорий-предложений
- `delete-products.js` — последовательный обход сайтов (один AJAX на сайт), прогресс «Сайт X из N»
- `delete-products.css` — стили таблицы удаления и блока редиректа
- `get_original_path()` — убирает суффикс `__trashed/` из slug при отображении URL

### Исправлено
- Отсутствующий атрибут `data-sku` на кнопках «Удалить»/«Восстановить» — JS читал `undefined`, PHP отклонял все запросы с «Неверные параметры» (Ошибок: N, где N = число сайтов)

---

## [1.3.0] - 2026-06-09

### Добавлено
- Страница «Копирование записей» (`MPS_Copy_Posts`, `class-mps-copy-posts.php`)
- AJAX `mps_get_posts` — загрузка записей главного сайта со статусом по дочерним сайтам
- AJAX `mps_copy_post` — копирование записи (заголовок, контент, slug, статус, категории, мета-поля `_post_city`, `_post_area`, `_post_product`, `_post_link`)
- `copy-posts.js` — AJAX и обновление статусов ✓/— без перезагрузки
- Категории создаются на дочернем сайте если отсутствуют (по slug)

---

## [1.2.0] - 2026-05-06

### Исправлено
- Меню переименовано в «Синхронизация Multisite» (конфликт с `custom-prices-woocommerce`, который тоже регистрировал «Цены Multisite»)
- `enqueue_scripts` теперь использует возвращаемое значение `add_menu_page()` / `add_submenu_page()` вместо хардкода строки хука — скрипты не ломаются при переименовании меню
- Дублирование товаров при повторном «Копировать» — `wc_get_product_id_by_sku()` заменён на прямой SQL (lookup-таблица WC кешировала 0)
- Атрибуты не копировались — теперь используются `WC_Product_Attribute` + `set_attributes()` до `save()`; ручной `update_post_meta('_product_attributes')` убран
- Имена терминов атрибутов транслитерировались — при создании нового термина берётся оригинальное `name` с главного сайта, а не slug
- Кастомные мета-поля перенесены ПОСЛЕ `save()` — иначе `save()` их перезаписывал

### Изменено
- Кнопка «Копировать» перенесена в первую (левую) колонку таблицы
- Копирование разбито на последовательные запросы по одному `site_id`: ячейки обновляются сразу, таймаут одного города не блокирует остальные
- `ms_get_copy_products`: один SQL-запрос на дочерний сайт вместо N × 29 вызовов `wc_get_product_id_by_sku`

---

## [1.1.0] - 2026-05-05

### Добавлено
- Страница «Копирование товаров» (MS_Copy, class-mps-copy.php)
- AJAX `ms_get_copy_products` — загрузка товаров главного сайта со статусом по сайтам
- AJAX `ms_copy_product` — полное копирование товара (данные, изображения, категории, мета)
- `copy-products.js` — AJAX и обновление статусов ✓/— без перезагрузки
- `copy-products.css` — стили таблицы копирования
- Идентификация изображений по `post_name` + `media_sideload_image()` при отсутствии
- Документация разбита на файлы в папке `DOCS/`

---

## [1.0.0] - 2026-02-09

### Добавлено
- Автоматическая синхронизация цен при сохранении товара (MS_Sync)
- Массовое редактирование цен через интерфейс (MS_Bulk_Edit)
- Страница настроек (MS_Admin)
- AJAX загрузка товаров по категории
- Индивидуальное сохранение каждого товара с синхронизацией по SKU

### Архитектура
- Singleton паттерн для всех классов
- WordPress Multisite API: `switch_to_blog`, `restore_current_blog`
- WooCommerce API интеграция
- AJAX без перезагрузки страницы

### Переименовано (с multisite-price-sync)
- Классы: `MPS_*` → `MS_*`
- Константы: `MPS_VERSION` → `MS_VERSION`, `MPS_PLUGIN_URL` → `MS_PLUGIN_URL`
- Опции БД: `mps_*` → `ms_*`
- AJAX actions: `mps_*` → `ms_*`
- CSS/JS: `.mps-*` → `.ms-*`, `mpsData` → `msData`
- Slug меню: `mps-bulk-edit` → `ms-bulk-edit`, `mps-settings` → `ms-settings`

### Исправлено после активации
- "Class not found" — не все классы были переименованы
- JavaScript не грузил assets — использовались старые константы `MPS_*`
- AJAX не работал — actions и JS-селекторы не совпадали

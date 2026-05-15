# Multisite Sync — Индекс документации

**Версия:** 1.2.0 | **Обновлено:** 2026-05-06

Плагин для WordPress Multisite + WooCommerce:
- **Синхронизация цен** между всеми сайтами по SKU
- **Копирование товаров** с главного сайта на дочерние (с изображениями)

## Документация (читай по задаче)

| Файл | Когда читать |
|------|-------------|
| [DOCS/architecture.md](DOCS/architecture.md) | Структура файлов, константы, меню, настройки |
| [DOCS/classes.md](DOCS/classes.md) | API классов: MS_Sync, MS_Bulk_Edit, MS_Admin, MS_Copy |
| [DOCS/sync-flow.md](DOCS/sync-flow.md) | Пошаговые процессы синхронизации и копирования |
| [DOCS/frontend.md](DOCS/frontend.md) | JavaScript, CSS-классы |
| [DOCS/dev-guide.md](DOCS/dev-guide.md) | Частые задачи, шаблоны, ограничения, TODO |
| [DOCS/changelog.md](DOCS/changelog.md) | История изменений |

## Ключевые факты (всегда актуально)

- **Идентификатор товара** — SKU. Без SKU → синхронизация и копирование пропускают товар.
- **Идентификатор изображения** — `post_name` вложения (slug). Поиск по нему; если не найдено → `media_sideload_image()`.
- **Главный сайт** — `get_main_site_id()`. Копирование работает от него к дочерним.
- **Рекурсия** — предотвращается флагом `MS_Sync::$is_syncing`.
- **Права** — только `manage_network` (суперадмин).

## Файлы, которые меняются чаще всего

```
includes/class-mps-bulk-edit.php   ← таблица цен, AJAX save
includes/class-mps-copy.php        ← копирование товаров
includes/class-mps-copy-pages.php  ← копирование страниц (SPB)
includes/class-mps-sync.php        ← логика синхронизации
assets/js/bulk-edit.js             ← AJAX цены
assets/js/copy-products.js         ← AJAX копирование товаров
assets/js/copy-pages.js            ← AJAX копирование страниц
```

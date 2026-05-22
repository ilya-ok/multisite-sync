# Multisite Sync — Индекс документации

**Версия:** 1.2.0 | **Обновлено:** 2026-05-22

Плагин для WordPress Multisite + WooCommerce:
- **Копирование товаров** с главного сайта на дочерние (с изображениями, атрибутами, мета-полями)
- **Копирование страниц** (SPB-страниц) с главного сайта на дочерние

> **Важно:** синхронизация цен и кастомных мета-полей (`_custom_price_type`, `_price_otrez` и др.)
> намеренно удалена из этого плагина — она принадлежит плагину `custom-prices-woocommerce`.
> Классы `MS_Sync`, `MS_Bulk_Edit`, `MS_Admin` удалены в версии 1.2.0 (2026-05-22).

## Документация (читай по задаче)

| Файл | Когда читать |
|------|-------------|
| [DOCS/architecture.md](DOCS/architecture.md) | Структура файлов, константы, меню |
| [DOCS/classes.md](DOCS/classes.md) | API классов: MS_Copy, MPS_Copy_Pages |
| [DOCS/sync-flow.md](DOCS/sync-flow.md) | Пошаговый процесс копирования |
| [DOCS/frontend.md](DOCS/frontend.md) | JavaScript, CSS-классы |
| [DOCS/dev-guide.md](DOCS/dev-guide.md) | Частые задачи, шаблоны, ограничения |
| [DOCS/changelog.md](DOCS/changelog.md) | История изменений |

## Ключевые факты (всегда актуально)

- **Идентификатор товара** — SKU. Без SKU → копирование пропускает товар.
- **Идентификатор изображения** — `post_name` вложения (slug). Поиск по нему; если не найдено → `media_sideload_image()`.
- **Главный сайт** — `get_main_site_id()`. Копирование работает только от него к дочерним.
- **Права** — только `manage_network` (суперадмин).

## Файлы, которые меняются чаще всего

```
includes/class-mps-copy.php        ← копирование товаров
includes/class-mps-copy-pages.php  ← копирование страниц (SPB)
assets/js/copy-products.js         ← AJAX копирование товаров
assets/js/copy-pages.js            ← AJAX копирование страниц
```

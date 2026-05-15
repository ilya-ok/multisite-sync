# Архитектура плагина

## Структура файлов

```
multisite-sync/
├── multisite-sync.php          # Точка входа: константы, require, init, активация
├── CLAUDE.md                   # Индекс документации
├── README.md                   # Пользовательская документация
├── DOCS/                       # Документация для Claude AI
│   ├── architecture.md         # Этот файл
│   ├── classes.md              # Описание всех PHP-классов
│   ├── frontend.md             # JS и CSS
│   ├── sync-flow.md            # Процессы синхронизации и копирования
│   ├── dev-guide.md            # Руководство разработчика + TODO
│   └── changelog.md            # История изменений
├── includes/
│   ├── class-mps-sync.php      # MS_Sync — автосинхронизация цен
│   ├── class-mps-admin.php     # MS_Admin — страница настроек
│   ├── class-mps-bulk-edit.php # MS_Bulk_Edit — массовое редактирование цен
│   └── class-mps-copy.php      # MS_Copy — копирование товаров между сайтами
└── assets/
    ├── css/
    │   ├── bulk-edit.css       # Стили страницы редактирования цен
    │   └── copy-products.css   # Стили страницы копирования товаров
    └── js/
        ├── bulk-edit.js        # AJAX для редактирования цен
        └── copy-products.js    # AJAX для копирования товаров
```

## Принципы

1. **Singleton** — все классы используют паттерн Singleton (`get_instance()`)
2. **WordPress Multisite API** — `switch_to_blog()` / `restore_current_blog()`
3. **WooCommerce API** — `wc_get_product()`, `wc_get_product_id_by_sku()`
4. **AJAX без перезагрузки** — все операции через `admin-ajax.php`
5. **Идентификация по SKU** — товары с одинаковым SKU считаются идентичными
6. **Изображения по post_name** — slug вложения используется как идентификатор при копировании

## Константы (multisite-sync.php)

```php
MS_VERSION          // '1.0.0'
MS_PLUGIN_DIR       // Абсолютный путь к папке плагина (с /)
MS_PLUGIN_URL       // URL к папке плагина (с /)
MS_PLUGIN_BASENAME  // 'multisite-sync/multisite-sync.php'
```

## Требования

- WordPress Multisite
- WooCommerce 5.0+
- PHP 7.4+

## Права доступа

Все страницы плагина доступны только пользователям с `manage_network` (суперадмин).

## Настройки (site_option)

```php
ms_auto_sync           // Автосинхронизация при сохранении товара (1/0)
ms_sync_regular_price  // Синхронизировать обычную цену (1/0)
ms_sync_sale_price     // Синхронизировать цену со скидкой (1/0)
ms_debug_mode          // Режим отладки (1/0)
```

## Меню Network Admin

```
Синхронизация Multisite  (ms-bulk-edit)        ← MS_Bulk_Edit
├── Копирование товаров (ms-copy-products) ← MS_Copy
└── Настройки (ms-settings)                ← MS_Admin
```

> Название «Синхронизация Multisite» выбрано чтобы не конфликтовать с плагином `custom-prices-woocommerce`, который тоже регистрирует пункт «Цены Multisite».

## Хук подключения скриптов

`enqueue_scripts` в MS_Bulk_Edit и MS_Copy НЕ использует хардкод строки хука. Вместо этого возвращаемое значение `add_menu_page()` / `add_submenu_page()` сохраняется в `$this->page_hook` и используется для сравнения. Это защищает от слома при переименовании меню.

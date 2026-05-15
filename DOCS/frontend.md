# Frontend: JavaScript и CSS

## bulk-edit.js

Страница «Цены Multisite». Данные от WP: `msData = { ajaxurl, nonce }`.

**Функции:**
- `loadProducts()` — по change на `#ms-category-select`, AJAX `ms_get_products`, вставляет HTML таблицы
- `saveProduct()` — по click на `.ms-save-btn`, собирает 9 полей строки, AJAX `ms_save_product`

**Поля строки:**
```
ms_regular_price, ms_sale_price, ms_type (select),
ms_price_otrez, ms_price_opt, ms_units,
ms_min_order, ms_step_qty, ms_volume_unit
```

**UX:** кнопка блокируется во время сохранения; статус ✓ (зелёный) / ✗ (красный) на 3 сек; Enter в input → сохранение.

---

## copy-products.js

Страница «Копирование товаров». Данные от WP: `msCopyData = { ajaxurl, nonce, sites: [{id, name}] }`.

**Функции:**
- `loadProducts()` — по change на `#ms-copy-category-select`, AJAX `ms_get_copy_products`
- `copyProduct()` — по click на `.ms-copy-btn`; делает **последовательные** AJAX-запросы `ms_copy_product` по одному сайту за раз (передаёт `site_id`), таймаут 120 сек на каждый запрос

**Прогресс:** в `.ms-copy-status` отображается «Сайт X из N: Название…» во время копирования.

**После каждого сайта:** обновляет ячейку `.ms-col-site[data-site-id]` — переключает класс `ms-site-status-missing` → `ms-site-status-ok` (✓) или `ms-site-status-error` (✗, с title-подсказкой об ошибке).

**Итог:** когда все сайты обработаны — показывает «✓ Готово: N» или «✗ Готово: N, ошибок: M» на 6 сек.

---

## CSS классы (bulk-edit.css + copy-products.css)

**Общие (bulk-edit.css):**
```
.ms-section          — секция с рамкой
.ms-bulk-header      — строка с выбором категории
.ms-products-table   — таблица товаров
.ms-col-img          — колонка изображения
.ms-col-name         — колонка названия
.ms-col-sku          — колонка SKU
.ms-col-num          — колонка числового поля
.ms-col-txt          — колонка текстового поля
.ms-col-type         — колонка select (тип цены)
.ms-col-actions      — колонка кнопок
.ms-save-status      — статус сохранения
.ms-sites-list       — список сайтов внизу страницы
.ms-count            — счётчик товаров
.ms-loading          — индикатор загрузки
.ms-empty            — сообщение «нет товаров»
```

**Копирование (copy-products.css):**
```
.ms-copy-table          — таблица копирования (table-layout: auto)
.ms-col-site            — ячейка статуса сайта (60px, text-align: center)
.ms-site-status-ok      — зелёный ✓ (товар есть)
.ms-site-status-missing — серый — (товара нет)
.ms-site-status-error   — красный ✗ (ошибка при копировании)
.ms-site-status-nosku   — жёлтый ? (нет SKU)
.ms-copy-btn            — кнопка Копировать
.ms-copy-status         — статус результата копирования
.ms-no-sku              — подпись вместо кнопки (нет SKU)
```

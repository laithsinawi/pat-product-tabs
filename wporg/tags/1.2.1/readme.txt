=== PAT Product Tabs for WooCommerce ===
Contributors: laith3
Tags: woocommerce, product-tabs, product-page, per-product
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPL-2.0-or-later

== Description ==
PAT Product Tabs for WooCommerce adds lightweight, per-product custom tabs to WooCommerce product pages.

Each product can define its own tab labels, tab content, order, and enabled state. The admin UI uses a repeater so you only add the tabs you want for that product. The plugin stores tab data in product post meta and renders only the tabs that contain content for that specific product.

This is intentionally not a global tab plugin and does not assign tabs by category.

== Installation ==
1. Copy the `pat-product-tabs` folder into `wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Make sure WooCommerce is active.
4. Edit any product and open the `Product Tabs` metabox.

== Usage ==
1. Open a WooCommerce product for editing.
2. Click `Add Tab` for each tab you want to create.
3. Enable the row.
4. Enter the tab label.
5. Enter rich WYSIWYG content, shortcodes, or embed HTML.
6. Drag rows to sort them, or set the order number manually.
7. Update the product.

Tabs only appear on the frontend when they are enabled and have content.

== Example ==
To add a product-specific `FAQ` tab:
1. Edit the product.
2. Click `Add Tab`.
3. Change the label to `FAQ` or `Common Questions` if desired.
4. Add your FAQ content.
5. Set the order number to `90` or another value you prefer.

== Extending ==
Developers can extend the repeater behavior with filters such as:

- `pat_product_tabs_loaded_rows`
- `pat_product_tabs_sanitized_rows`
- `pat_product_tabs_frontend_tabs`

Example:

```php
add_filter('pat_product_tabs_frontend_tabs', function ($tabs, $product_id) {
    $tabs['pat_product_tab_custom_note'] = [
        'title' => 'Custom Note',
        'priority' => 5,
        'callback' => function () {
            echo '<p>Injected from a custom filter.</p>';
        },
        'content' => '<p>Injected from a custom filter.</p>',
    ];

    return $tabs;
}, 10, 2);
```

The stored product meta key is `_pat_product_tabs`.

== Notes ==
- If WooCommerce is inactive, the plugin degrades gracefully and shows an admin notice.
- Standard WooCommerce tabs remain available when no custom tabs are configured.
- Content uses a WYSIWYG editor, so HTML and shortcode-based layouts are supported.
- Video content can be provided as an embed URL, shortcode, or iframe embed.
- This package is prepared for the WordPress.org free plugin directory.

== Changelog ==
= 1.2.1 =
- WYSIWYG editor initialization fix.
- Drag-and-drop fallback move buttons.

= 1.1.0 =
- WYSIWYG editor for tab content.
- Drag-and-drop sorting for repeater rows.

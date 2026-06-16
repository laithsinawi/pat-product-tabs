<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PAT_Product_Tabs {
    private const META_KEY = '_pat_product_tabs';

    private static ?self $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', [$this, 'render_woocommerce_notice']);

            return;
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('add_meta_boxes_product', [$this, 'register_metabox']);
        add_action('save_post_product', [$this, 'save_product_tabs'], 10, 2);
        add_filter('woocommerce_product_tabs', [$this, 'inject_product_tabs'], 50);
    }

    public function enqueue_admin_assets(string $hook_suffix): void {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || 'product' !== $screen->post_type) {
            return;
        }

        wp_enqueue_editor();
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'pat-product-tabs-admin',
            PAT_PRODUCT_TABS_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-sortable', 'wp-editor'],
            PAT_PRODUCT_TABS_VERSION,
            true
        );
        wp_enqueue_style(
            'pat-product-tabs-admin',
            PAT_PRODUCT_TABS_URL . 'assets/css/admin.css',
            [],
            PAT_PRODUCT_TABS_VERSION
        );
    }

    private function is_woocommerce_active(): bool {
        return class_exists('WooCommerce');
    }

    public function render_woocommerce_notice(): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('PAT Product Tabs for WooCommerce requires WooCommerce to be active. The plugin is loaded, but tab fields and frontend tabs are disabled until WooCommerce is available.', 'pat-product-tabs');
        echo '</p></div>';
    }

    public function register_metabox(): void {
        add_meta_box(
            'pat-product-tabs-metabox',
            __('Product Tabs', 'pat-product-tabs'),
            [$this, 'render_metabox'],
            'product',
            'normal',
            'default'
        );
    }

    public function render_metabox(\WP_Post $post): void {
        wp_nonce_field('pat_product_tabs_save', 'pat_product_tabs_nonce');

        $tabs = $this->get_tabs_for_editor($post->ID);
        ?>
        <p>
            <?php esc_html_e('Add only the tabs this product needs. Tabs are ordered by the numeric order field, and only tabs with content are shown on the frontend.', 'pat-product-tabs'); ?>
        </p>
        <div id="pat-product-tabs-repeater">
            <table class="widefat striped" style="margin-top: 12px;">
                <thead>
                    <tr>
                        <th style="width: 36px;"></th>
                        <th style="width: 110px;"><?php esc_html_e('Move', 'pat-product-tabs'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Enabled', 'pat-product-tabs'); ?></th>
                        <th style="width: 160px;"><?php esc_html_e('Label', 'pat-product-tabs'); ?></th>
                        <th style="width: 90px;"><?php esc_html_e('Order', 'pat-product-tabs'); ?></th>
                        <th><?php esc_html_e('Content', 'pat-product-tabs'); ?></th>
                        <th style="width: 70px;"><?php esc_html_e('Remove', 'pat-product-tabs'); ?></th>
                    </tr>
                </thead>
                <tbody id="pat-product-tabs-rows">
                    <?php foreach ($tabs as $index => $tab) : ?>
                        <?php $this->render_repeater_row($index, $tab); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 12px;">
                <button type="button" class="button button-primary" id="pat-product-tabs-add">
                    <?php esc_html_e('Add Tab', 'pat-product-tabs'); ?>
                </button>
            </p>
        </div>
        <p class="description" style="margin-top: 12px;">
            <?php esc_html_e('Tip: drag rows to sort them, or edit the order field manually. Empty or disabled rows are ignored on the frontend.', 'pat-product-tabs'); ?>
        </p>
        <script type="text/template" id="pat-product-tabs-row-template">
            <?php echo $this->get_repeater_row_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </script>
        <?php
    }

    public function save_product_tabs(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['pat_product_tabs_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pat_product_tabs_nonce'])), 'pat_product_tabs_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $incoming = isset($_POST['pat_product_tabs']) && is_array($_POST['pat_product_tabs'])
            ? wp_unslash($_POST['pat_product_tabs'])
            : [];

        $sanitized = [];

        foreach ($incoming as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = isset($row['label']) ? sanitize_text_field((string) $row['label']) : '';
            $content = isset($row['content']) ? $this->sanitize_tab_content((string) $row['content']) : '';
            $order = isset($row['order']) ? absint($row['order']) : 0;
            $enabled = !empty($row['enabled']) ? 1 : 0;

            if ('' === trim($label) && '' === $this->normalize_tab_content($content)) {
                continue;
            }

            $sanitized[] = [
                'enabled' => $enabled,
                'label' => $label,
                'content' => $content,
                'order' => $order > 0 ? $order : 10,
            ];
        }

        /**
         * Filter the sanitized tab rows before they are stored in post meta.
         *
         * @param array<int, array<string, mixed>> $sanitized
         * @param array<int|string, mixed> $incoming
         * @param int $post_id
         */
        $sanitized = apply_filters('pat_product_tabs_sanitized_rows', $sanitized, $incoming, $post_id);

        usort($sanitized, static function (array $a, array $b): int {
            $result = $a['order'] <=> $b['order'];
            if (0 !== $result) {
                return $result;
            }

            return strcmp($a['label'], $b['label']);
        });

        update_post_meta($post_id, self::META_KEY, $sanitized);
    }

    public function inject_product_tabs(array $tabs): array {
        if (!function_exists('is_product') || !is_product()) {
            return $tabs;
        }

        $product_id = get_the_ID();
        if (!$product_id) {
            return $tabs;
        }

        $stored_tabs = $this->get_product_tabs($product_id);
        if ([] === $stored_tabs) {
            return $tabs;
        }

        foreach ($stored_tabs as $tab_index => $tab) {
            if (empty($tab['enabled']) || '' === $this->normalize_tab_content((string) $tab['content'])) {
                continue;
            }

            $tab_id = 'pat_product_tab_' . absint($tab_index);

            $tabs[$tab_id] = [
                'title' => '' !== trim((string) $tab['label']) ? $tab['label'] : __('Product Tab', 'pat-product-tabs'),
                'priority' => (int) $tab['order'],
                'callback' => [$this, 'render_product_tab'],
                'content' => $tab['content'],
            ];
        }

        /**
         * Filter the frontend tabs that will be passed to WooCommerce.
         *
         * @param array<string, array<string, mixed>> $tabs
         * @param int $product_id
         */
        return apply_filters('pat_product_tabs_frontend_tabs', $tabs, $product_id);
    }

    public function render_product_tab(string $tab_key, array $tab): void {
        if (empty($tab['content'])) {
            return;
        }

        echo apply_filters('the_content', $tab['content']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function get_tabs_for_editor(int $post_id): array {
        $stored_tabs = $this->get_product_tabs($post_id);

        if ([] === $stored_tabs) {
            return [];
        }

        $tabs = [];
        foreach ($stored_tabs as $tab) {
            $tabs[] = [
                'enabled' => isset($tab['enabled']) ? (int) $tab['enabled'] : 0,
                'label' => isset($tab['label']) ? (string) $tab['label'] : '',
                'content' => isset($tab['content']) ? (string) $tab['content'] : '',
                'order' => isset($tab['order']) ? absint($tab['order']) : 10,
            ];
        }

        usort($tabs, static function (array $a, array $b): int {
            $result = $a['order'] <=> $b['order'];
            if (0 !== $result) {
                return $result;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $tabs;
    }

    private function get_product_tabs(int $post_id): array {
        $tabs = get_post_meta($post_id, self::META_KEY, true);

        if (!is_array($tabs)) {
            return [];
        }

        $normalized = [];

        foreach ($tabs as $tab_key => $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $normalized[] = [
                'enabled' => !empty($tab['enabled']) ? 1 : 0,
                'label' => isset($tab['label']) ? sanitize_text_field((string) $tab['label']) : '',
                'content' => isset($tab['content']) ? (string) $tab['content'] : '',
                'order' => isset($tab['order']) ? absint($tab['order']) : 0,
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            $result = $a['order'] <=> $b['order'];
            if (0 !== $result) {
                return $result;
            }

            return strcmp($a['label'], $b['label']);
        });

        /**
         * Filter normalized product tab rows after loading from post meta.
         *
         * @param array<int, array<string, mixed>> $normalized
         * @param int $post_id
         */
        return apply_filters('pat_product_tabs_loaded_rows', $normalized, $post_id);
    }

    private function sanitize_tab_content(string $content): string {
        $allowed_html = wp_kses_allowed_html('post');
        $allowed_html['iframe'] = [
            'src' => true,
            'width' => true,
            'height' => true,
            'frameborder' => true,
            'allow' => true,
            'allowfullscreen' => true,
            'loading' => true,
            'referrerpolicy' => true,
            'title' => true,
        ];

        return wp_kses($content, $allowed_html);
    }

    private function normalize_tab_content(string $content): string {
        return trim($content);
    }

    private function render_repeater_row(int $index, array $tab): void {
        $enabled = !empty($tab['enabled']);
        $label = isset($tab['label']) ? (string) $tab['label'] : '';
        $content = isset($tab['content']) ? (string) $tab['content'] : '';
        $order = isset($tab['order']) ? absint($tab['order']) : 10;
        $editor_id = 'pat_product_tabs_' . $index . '_content';
        ?>
        <tr data-row-index="<?php echo esc_attr((string) $index); ?>" data-editor-id="<?php echo esc_attr($editor_id); ?>">
            <td class="pat-product-tabs-drag-cell">
                <button type="button" class="pat-product-tabs-drag-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'pat-product-tabs'); ?>">
                    <span class="dashicons dashicons-menu"></span>
                </button>
            </td>
            <td>
                <button type="button" class="button-link pat-product-tabs-move" data-pat-move-row="up">
                    <?php esc_html_e('Up', 'pat-product-tabs'); ?>
                </button>
                <br>
                <button type="button" class="button-link pat-product-tabs-move" data-pat-move-row="down">
                    <?php esc_html_e('Down', 'pat-product-tabs'); ?>
                </button>
            </td>
            <td>
                <label>
                    <input type="checkbox" name="pat_product_tabs[<?php echo esc_attr((string) $index); ?>][enabled]" value="1" <?php checked($enabled); ?>>
                    <?php esc_html_e('On', 'pat-product-tabs'); ?>
                </label>
            </td>
            <td>
                <input
                    type="text"
                    class="widefat"
                    name="pat_product_tabs[<?php echo esc_attr((string) $index); ?>][label]"
                    value="<?php echo esc_attr($label); ?>"
                    placeholder="<?php esc_attr_e('Tab label', 'pat-product-tabs'); ?>"
                >
            </td>
            <td>
                <input
                    type="number"
                    class="small-text"
                    min="1"
                    step="1"
                    name="pat_product_tabs[<?php echo esc_attr((string) $index); ?>][order]"
                    value="<?php echo esc_attr((string) $order); ?>"
                >
            </td>
            <td>
                <textarea
                    id="<?php echo esc_attr($editor_id); ?>"
                    name="pat_product_tabs[<?php echo esc_attr((string) $index); ?>][content]"
                    rows="8"
                    style="width: 100%;"
                ><?php echo esc_textarea($content); ?></textarea>
            </td>
            <td>
                <button type="button" class="button-link-delete" data-pat-remove-row="1">
                    <?php esc_html_e('Remove', 'pat-product-tabs'); ?>
                </button>
            </td>
        </tr>
        <?php
    }

    private function get_repeater_row_template(): string {
        ob_start();
        ?>
        <tr data-row-index="__INDEX__" data-editor-id="pat_product_tabs___INDEX___content">
            <td class="pat-product-tabs-drag-cell">
                <button type="button" class="pat-product-tabs-drag-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'pat-product-tabs'); ?>">
                    <span class="dashicons dashicons-menu"></span>
                </button>
            </td>
            <td>
                <button type="button" class="button-link pat-product-tabs-move" data-pat-move-row="up">
                    <?php esc_html_e('Up', 'pat-product-tabs'); ?>
                </button>
                <br>
                <button type="button" class="button-link pat-product-tabs-move" data-pat-move-row="down">
                    <?php esc_html_e('Down', 'pat-product-tabs'); ?>
                </button>
            </td>
            <td>
                <label>
                    <input type="checkbox" name="pat_product_tabs[__INDEX__][enabled]" value="1">
                    <?php esc_html_e('On', 'pat-product-tabs'); ?>
                </label>
            </td>
            <td>
                <input
                    type="text"
                    class="widefat"
                    name="pat_product_tabs[__INDEX__][label]"
                    value=""
                    placeholder="<?php esc_attr_e('Tab label', 'pat-product-tabs'); ?>"
                >
            </td>
            <td>
                <input
                    type="number"
                    class="small-text"
                    min="1"
                    step="1"
                    name="pat_product_tabs[__INDEX__][order]"
                    value="10"
                >
            </td>
            <td>
                <textarea
                    id="pat_product_tabs___INDEX___content"
                    name="pat_product_tabs[__INDEX__][content]"
                    rows="8"
                    style="width: 100%;"
                ></textarea>
            </td>
            <td>
                <button type="button" class="button-link-delete" data-pat-remove-row="1">
                    <?php esc_html_e('Remove', 'pat-product-tabs'); ?>
                </button>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }
}

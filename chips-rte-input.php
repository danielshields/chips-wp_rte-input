<?php
/**
 * Plugin Name: CHIPS – Text Input with Format
 * Description: Adds a custom ACF field type (Floating RTE) with a minimal rich-text editor that shows a small tooltip on text selection (bold, italic, link, clear).
 * Version: 0.2.6
 * Author: CHIPS
 * GitHub Plugin URI: danielshields/chips-wp_rte-input
 * Primary Branch: main
 * Icon: icon-256x256.png
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Register the field when ACF is ready to include field types.
// Using `acf/include_field_types` prevents loading ACF’s text domain too early (WP 6.7+ warning).
add_action('acf/include_field_types', function() {
    if ( ! function_exists('acf_register_field_type') ) {
        return;
    }

    class CHIPS_ACF_Field_Floating_RTE extends acf_field {
        function __construct() {
            $this->name     = 'chips_floating_rte';
            $this->label    = __('CHIPS Text Input with Format', 'chips');
            $this->category = 'content';
            $this->defaults = array(
                'placeholder' => '',
                'height'      => 80,
            );
            parent::__construct();

            // Admin assets
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        }

        function render_field_settings( $field ) {
            acf_render_field_setting( $field, array(
                'label'        => __('Placeholder', 'chips'),
                'instructions' => __('Shown when empty.', 'chips'),
                'type'         => 'text',
                'name'         => 'placeholder',
            ));

            acf_render_field_setting( $field, array(
                'label'        => __('Editor height', 'chips'),
                'instructions' => __('Height in pixels of the editable area.', 'chips'),
                'type'         => 'number',
                'name'         => 'height',
                'append'       => 'px',
            ));
        }

        function render_field( $field ) {
            $value       = is_string($field['value']) ? $field['value'] : '';
            $placeholder = isset($field['placeholder']) ? esc_attr($field['placeholder']) : '';
            $height      = isset($field['height']) ? intval($field['height']) : 200;
            $id          = esc_attr($field['id']);
            $name        = esc_attr($field['name']);

            // Wrapper with contenteditable and a hidden input that stores HTML for ACF
            echo '<div class="chips-float-rte" data-field-id="' . $id . '" data-field-key="' . esc_attr($field['key']) . '" style="position:relative;">';
            echo '  <div class="chips-float-rte__editable" contenteditable="true" role="textbox" aria-multiline="true" data-input-name="' . $name . '" data-placeholder="' . $placeholder . '" style="min-height:' . $height . 'px;">' . $value . '</div>';
            echo '  <input type="hidden" class="chips-float-rte__input" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '">';
            echo '  <div class="chips-float-rte__tooltip" aria-hidden="true">'
                  . '    <button type="button" class="rte-btn rte-bold" data-cmd="bold" aria-label="Bold"><strong>B</strong></button>'
                  . '    <button type="button" class="rte-btn rte-italic" data-cmd="italic" aria-label="Italic"><em>I</em></button>'
                  . '    <button type="button" class="rte-btn rte-link" data-cmd="link" aria-label="Insert link">🔗</button>'
                  . '    <button type="button" class="rte-btn rte-clear" data-cmd="clear" aria-label="Clear formatting">❌</button>'
                  . '  </div>';
            echo '</div>';
        }

        function input_admin_head() {
            // No-op. We use enqueue_assets instead.
        }

        function enqueue_assets() {
            // Styles
            wp_register_style('chips-float-rte', false);
            wp_enqueue_style('chips-float-rte');
            $css = '/* Floating RTE styles */
                .chips-float-rte__editable {border:1px solid #c3c4c7; border-radius:4px; padding:10px; background:#fff; line-height:1.5;}
                .chips-float-rte__editable:focus {outline:2px solid #3582c4;}
                .chips-float-rte__editable.empty:before {content:attr(data-placeholder); color:#888;}
                .chips-float-rte__tooltip {position:absolute; left:0; top:-9999px; opacity:0; transform:translate(-50%, -8px); transition:opacity .12s ease; background:#111; color:#fff; border-radius:6px; padding:6px; display:flex; gap:6px; box-shadow:0 4px 18px rgba(0,0,0,.22); z-index:1000;}
                .chips-float-rte__tooltip[aria-hidden="false"] {opacity:1;}
                .chips-float-rte .rte-btn {border:0; background:transparent; color:#fff; cursor:pointer; padding:4px 6px; border-radius:4px;}
                .chips-float-rte .rte-btn:focus {outline:2px solid #7db4e6;}
                .chips-float-rte .rte-btn:hover {background:rgba(255,255,255,.1);}';
            wp_add_inline_style('chips-float-rte', $css);

            // Script
            $script_handle = 'chips-float-rte';
            $script_rel_path = 'assets/admin.js';
            $script_abs_path = plugin_dir_path(__FILE__) . $script_rel_path;
            $script_url      = plugins_url($script_rel_path, __FILE__);

            wp_register_script($script_handle, file_exists($script_abs_path) ? $script_url : false, array('jquery'), '0.2.6', true);
            wp_enqueue_script($script_handle);
            if ( ! file_exists($script_abs_path) ) {
            $js = <<<'JS'
    console.log("Didn't load JS properly");
JS;
            wp_add_inline_script('chips-float-rte', $js);
            }
        }
    }

    // Register the field type with ACF once it has finished bootstrapping.
    acf_register_field_type('CHIPS_ACF_Field_Floating_RTE');
});

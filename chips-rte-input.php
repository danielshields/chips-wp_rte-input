<?php
/**
 * Plugin Name: CHIPS – Text Input with Format
 * Description: Adds a custom ACF field type (Floating RTE) with a minimal rich-text editor that shows a small tooltip on text selection (bold, italic, link, clear).
 * Version: 0.2.5
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
            echo '<div class="chips-float-rte" data-field-id="' . $id . '" style="position:relative;">';
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
            wp_register_script('chips-float-rte', false, array('jquery'), '0.1.0', true);
            wp_enqueue_script('chips-float-rte');
            $js = '(function(){
                function closest(el, sel){
                    while (el && el.nodeType === 1){ if(el.matches(sel)) return el; el = el.parentElement; }
                    return null;
                }
                function updateHiddenInput(wrapper){
                    var editable = wrapper.querySelector(".chips-float-rte__editable");
                    var input = wrapper.querySelector(".chips-float-rte__input");
                    if (!editable || !input) return;
                    input.value = editable.innerHTML.trim();
                    editable.classList.toggle("empty", editable.textContent.trim().length === 0 && input.value.trim().length === 0);
                }
                function getSelectionRect(){
                    var sel = window.getSelection();
                    if(!sel || sel.rangeCount === 0) return null;
                    var range = sel.getRangeAt(0).cloneRange();
                    if(range.collapsed) return null;
                    var rect = range.getBoundingClientRect();
                    if(rect && rect.width && rect.height) return rect;
                    var span = document.createElement("span");
                    span.appendChild(document.createTextNode("\u200b"));
                    range.insertNode(span);
                    rect = span.getBoundingClientRect();
                    span.parentNode.removeChild(span);
                    return rect;
                }
                function positionTooltip(wrapper){
                    var tooltip = wrapper.querySelector(".chips-float-rte__tooltip");
                    var rect = getSelectionRect();
                    if(!tooltip) return;

                    // If no selection, hide
                    if(!rect){
                        tooltip.style.top = "-9999px";
                        tooltip.setAttribute("aria-hidden","true");
                        return;
                    }

                    // Compute coordinates relative to the wrapper (absolute positioned tooltip)
                    var wrapperRect = wrapper.getBoundingClientRect();
                    var scrollLeft = wrapper.scrollLeft || 0;
                    var scrollTop  = wrapper.scrollTop  || 0;

                    // Selection center X relative to wrapper
                    var left = (rect.left - wrapperRect.left) + (rect.width / 2) + scrollLeft;
                    // Default above the selection
                    var top  = (rect.top  - wrapperRect.top)  + scrollTop - tooltip.offsetHeight - 8;

                    // If not enough room above, place below
                    if (top < 0) {
                        top = (rect.bottom - wrapperRect.top) + scrollTop + 8;
                    }

                    // Apply and show
                    tooltip.style.left = left + "px";
                    tooltip.style.top  = top  + "px";
                    tooltip.setAttribute("aria-hidden","false");
                }
                function hideTooltip(wrapper){
                    var tooltip = wrapper.querySelector(".chips-float-rte__tooltip");
                    if(!tooltip) return;
                    tooltip.style.top = "-9999px";
                    tooltip.setAttribute("aria-hidden","true");
                }
                function applyCommand(cmd){
                    if(cmd === "link"){
                        var url = prompt("Enter URL:");
                        if(!url) return;
                        if(!/^https?:\/\//i.test(url)){ url = "https://" + url; }
                        document.execCommand("createLink", false, url);
                        return;
                    }

                    if(cmd === "clear"){
                        var sel = window.getSelection();
                        if(!sel || !sel.rangeCount) return;
                        var range = sel.getRangeAt(0);
                        if(range.collapsed) return;

                        // Get plain text of the selection
                        var plain = sel.toString();

                        // Replace selection with a single text node (ensures we control the selection next)
                        range.deleteContents();
                        var tn = document.createTextNode(plain);
                        range.insertNode(tn);

                        // Reselect the inserted text node so commands apply exactly to this text
                        sel.removeAllRanges();
                        var newRange = document.createRange();
                        newRange.selectNode(tn);
                        sel.addRange(newRange);

                        // Explicitly toggle off bold/italic if active (browsers will split wrappers as needed)
                        try { if (document.queryCommandState && document.queryCommandState("bold"))   document.execCommand("bold", false, null); } catch(e) {}
                        try { if (document.queryCommandState && document.queryCommandState("italic")) document.execCommand("italic", false, null); } catch(e) {}

                        // Remove other inline styles and links around the selected node
                        try { document.execCommand("removeFormat", false, null); } catch(e) {}
                        try { document.execCommand("unlink", false, null); } catch(e) {}

                        // Collapse caret to end of cleaned text
                        var endRange = document.createRange();
                        endRange.setStartAfter(tn);
                        endRange.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(endRange);
                        return;
                    }

                    document.execCommand(cmd, false, null);
                }

                function bindEditor(wrapper){
                    var editable = wrapper.querySelector(".chips-float-rte__editable");
                    var tooltip = wrapper.querySelector(".chips-float-rte__tooltip");
                    if(!editable || !tooltip) return;

                    // track last selection range inside this wrapper
                    wrapper.__chipsLastRange = null;

                    // Initial state
                    updateHiddenInput(wrapper);

                    editable.addEventListener("keyup", function(){
                        updateHiddenInput(wrapper);
                        positionTooltip(wrapper);
                    });
                    editable.addEventListener("mouseup", function(){
                        positionTooltip(wrapper);
                    });

                    function cacheRange(){
                        var sel = window.getSelection();
                        if(sel && sel.rangeCount){
                            var r = sel.getRangeAt(0);
                            if (editable.contains(r.startContainer) && editable.contains(r.endContainer)){
                                wrapper.__chipsLastRange = r.cloneRange();
                            }
                        }
                    }
                    editable.addEventListener("keyup", cacheRange);
                    editable.addEventListener("mouseup", cacheRange);

                    editable.addEventListener("blur", function(){
                        // Delay to allow tooltip button clicks
                        setTimeout(function(){ hideTooltip(wrapper); updateHiddenInput(wrapper); }, 150);
                    });
                    editable.addEventListener("paste", function(e){
                        e.preventDefault();
                        var text = (e.clipboardData || window.clipboardData).getData("text/plain");
                        document.execCommand("insertText", false, text);
                        updateHiddenInput(wrapper);
                    });

                    tooltip.addEventListener("mousedown", function(e){
                        // Prevent blur and cache selection before click collapses it
                        e.preventDefault();
                        cacheRange();
                    });
                    tooltip.addEventListener("click", function(e){
                        var btn = e.target.closest(".rte-btn");
                        if(!btn) return;
                        var cmd = btn.getAttribute("data-cmd");

                        // Restore selection into editable before applying command
                        editable.focus();
                        var sel = window.getSelection();
                        if(wrapper.__chipsLastRange){
                            sel.removeAllRanges();
                            sel.addRange(wrapper.__chipsLastRange);
                        }

                        applyCommand(cmd);
                        updateHiddenInput(wrapper);
                        positionTooltip(wrapper);
                    });

                    document.addEventListener("selectionchange", function(){
                        var sel = window.getSelection();
                        if(!sel || sel.rangeCount === 0) { hideTooltip(wrapper); return; }
                        var anchor = sel.anchorNode;
                        if(!anchor) { hideTooltip(wrapper); return; }
                        var inside = (anchor.nodeType === 1 ? anchor : anchor.parentElement).closest(".chips-float-rte__editable");
                        if(inside === editable){
                            positionTooltip(wrapper);
                            cacheRange();
                        } else {
                            hideTooltip(wrapper);
                        }
                    });
                }

                function init(scope){
                    (scope || document).querySelectorAll(".chips-float-rte").forEach(function(wrapper){
                        if(wrapper.__chipsRTEBound) return;
                        wrapper.__chipsRTEBound = true;
                        bindEditor(wrapper);
                    });
                }

                if(window.acf && acf.add_action){
                    acf.add_action("ready", function($el){ init($el && $el[0] ? $el[0] : document); });
                    acf.add_action("append", function($el){ init($el && $el[0] ? $el[0] : document); });
                } else {
                    document.addEventListener("DOMContentLoaded", function(){ init(document); });
                }
            })();';
            wp_add_inline_script('chips-float-rte', $js);
        }
    }

    // Register the field type with ACF once it has finished bootstrapping.
    acf_register_field_type('CHIPS_ACF_Field_Floating_RTE');
});

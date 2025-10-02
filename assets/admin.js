(function(){
console.debug('[chips-rte] init: script loaded (inline fallback).');
                function closest(el, sel){
                    while (el && el.nodeType === 1){ if(el.matches(sel)) return el; el = el.parentElement; }
                    return null;
                }
                function updateHiddenInput(wrapper){
                    var editable = wrapper.querySelector(".chips-float-rte__editable");
                    var input = wrapper.querySelector(".chips-float-rte__input");
                    if (!editable || !input) return;

                    var newVal = (editable.innerHTML || "").trim();
console.debug('[chips-rte] updateHiddenInput:newVal', newVal);
                    var prevVal = input.value;

                    // Toggle empty state class for placeholder styling
                    var isEmpty = (editable.textContent || "").trim().length === 0 && newVal.length === 0;
                    editable.classList.toggle("empty", isEmpty);

                    if (newVal === prevVal) return; // No change – avoid redundant events

                    input.value = newVal;
console.debug('[chips-rte] updateHiddenInput:set input.value', input.name, input.value);
                    syncAttachmentCompat(wrapper);

                    // IMPORTANT: notify ACF that this input changed.
                    // Media modal & attachment edit screens rely on change/input events to save via AJAX.
                    try {
                        var evInput  = new Event('input', { bubbles: true });
                        var evChange = new Event('change', { bubbles: true });
console.debug('[chips-rte] dispatch events for', input.name);
                        input.dispatchEvent(evInput);
                        input.dispatchEvent(evChange);
                        // If jQuery is present (it is, we depend on it), also trigger for ACF listeners using jQuery.
                        if (window.jQuery) { jQuery(input).trigger('input').trigger('change'); }
                        if (window.acf && acf.models && acf.models.Field) {
                            // Hint to ACF that input changed (helps in some contexts)
                            acf.doAction('change', jQuery(input));
                        }
                    } catch(e) {}
                }

                function syncAttachmentCompat(wrapper){
console.debug('[chips-rte] syncAttachmentCompat: checking compat form...');
                    // If we are inside the media modal compat form, mirror value into a hidden input
                    var compat = wrapper.closest('form.compat-item');
                    if(!compat) return;

                    // Extract attachment ID from any compat input name like attachments[123][post_title]
                    var probe = compat.querySelector('[name^="attachments["]');
                    if(!probe || !probe.name) return;
                    var m = probe.name.match(/^attachments\[(\d+)\]/);
                    if(!m) return;
                    var attId = m[1];
console.debug('[chips-rte] compat: attachment id', attId);

                    // Our ACF field key to save under attachments[ID][acf][FIELD_KEY]
                    var key = wrapper.getAttribute('data-field-key');
                    if(!key) return;

                    var shadowName = 'attachments['+attId+'][acf]['+key+']';
console.debug('[chips-rte] compat: shadow name', shadowName);
                    var shadow = compat.querySelector('input[name="'+shadowName.replace(/"/g,'\\"')+'"]');
                    if(!shadow){
                        shadow = document.createElement('input');
                        shadow.type = 'hidden';
                        shadow.name = shadowName;
                        compat.appendChild(shadow);
                    }

                    var input = wrapper.querySelector('.chips-float-rte__input');
                    if(input) shadow.value = input.value || '';
console.debug('[chips-rte] compat: set shadow value', shadow.name, shadow.value);
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

console.debug('[chips-rte] positionTooltip', {left:left, top:top});
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
console.debug('[chips-rte] bindEditor: initial sync');
                    syncAttachmentCompat(wrapper);

                    editable.addEventListener("keyup", function(){
console.debug('[chips-rte] keyup -> update');
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
console.debug('[chips-rte] blur -> schedule finalize');
                        // Delay to allow tooltip button clicks
                        setTimeout(function(){ updateHiddenInput(wrapper); hideTooltip(wrapper); }, 150);
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
            })();
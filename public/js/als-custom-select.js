/**
 * Custom dropdown: hidden input + button + listbox.
 * While open, the panel uses position:fixed so it is not clipped by overflow-x-auto / overflow-hidden ancestors.
 */
(function () {
    'use strict';

    var maxPanelH = 280;

    function rootFrom(node) {
        return node && node.closest ? node.closest('[data-als-custom-select]') : null;
    }

    function resetPanel(el) {
        var panel = el.querySelector('[data-als-cs-panel]');
        var btn = el.querySelector('[data-als-cs-trigger]');
        if (panel) {
            panel.style.cssText = '';
            panel.classList.add('hidden');
        }
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
        }
        el.classList.remove('als-cs-open');
    }

    function closeAll(except) {
        document.querySelectorAll('[data-als-custom-select]').forEach(function (el) {
            if (except && el === except) {
                return;
            }
            resetPanel(el);
        });
    }

    function findOptionEl(root, value) {
        var want = String(value);
        var opts = root.querySelectorAll('[data-als-cs-option]');
        for (var i = 0; i < opts.length; i++) {
            var o = opts[i];
            var dv = o.getAttribute('data-value');
            if (dv === null) {
                dv = '';
            }
            if (String(dv) === want) {
                return o;
            }
        }
        return null;
    }

    function syncLabel(root) {
        var hidden = root.querySelector('input[type="hidden"][data-als-cs-input]');
        var labelEl = root.querySelector('[data-als-cs-label]');
        if (!hidden || !labelEl) {
            return;
        }
        var opt = findOptionEl(root, hidden.value);
        if (opt) {
            labelEl.textContent = opt.textContent.trim();
        }
    }

    function setValue(root, value, labelText) {
        var hidden = root.querySelector('input[type="hidden"][data-als-cs-input]');
        var labelEl = root.querySelector('[data-als-cs-label]');
        if (!hidden) {
            return;
        }
        hidden.value = value;
        if (labelEl && labelText) {
            labelEl.textContent = labelText;
        } else if (labelEl) {
            syncLabel(root);
        }
        root.querySelectorAll('[data-als-cs-option]').forEach(function (o) {
            var sel = (o.getAttribute('data-value') || '') === String(value);
            o.setAttribute('aria-selected', sel ? 'true' : 'false');
            o.classList.toggle('bg-emerald-50', sel);
            o.classList.toggle('font-medium', sel);
            o.classList.toggle('text-emerald-900', sel);
        });
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
        if (root.getAttribute('data-submit-on-change') === '1') {
            var form = root.closest('form');
            if (form && typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            }
        }
    }

    function placePanel(root) {
        var panel = root.querySelector('[data-als-cs-panel]');
        var btn = root.querySelector('[data-als-cs-trigger]');
        if (!panel || !btn) {
            return;
        }
        var r = btn.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var pad = 8;
        var below = vh - r.bottom - pad;
        var above = r.top - pad;
        var openDown = below >= 120 || below >= above;
        var maxH = openDown ? Math.min(maxPanelH, Math.max(80, below)) : Math.min(maxPanelH, Math.max(80, above));
        var top = openDown ? r.bottom + 4 : r.top - maxH - 4;
        var left = r.left;
        var w = Math.max(r.width, 120);

        if (left + w > vw - pad) {
            left = Math.max(pad, vw - w - pad);
        }
        if (left < pad) {
            left = pad;
        }
        if (top + maxH > vh - pad) {
            top = Math.max(pad, vh - maxH - pad);
        }
        if (top < pad) {
            top = pad;
        }

        panel.classList.remove('hidden');
        panel.style.cssText =
            'position:fixed;left:' +
            left +
            'px;top:' +
            top +
            'px;min-width:' +
            w +
            'px;max-width:' +
            (vw - 2 * pad) +
            'px;max-height:' +
            maxH +
            'px;z-index:99999;margin:0;overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:0.5rem;box-shadow:0 10px 15px -3px rgb(0 0 0 / 0.08),0 4px 6px -4px rgb(0 0 0 / 0.06);padding:0.25rem 0;';
        btn.setAttribute('aria-expanded', 'true');
        root.classList.add('als-cs-open');
    }

    function toggle(root) {
        if (root.classList.contains('als-cs-open')) {
            closeAll(null);
            return;
        }
        closeAll(root);
        placePanel(root);
    }

    document.addEventListener('click', function (e) {
        var trig = e.target.closest('[data-als-cs-trigger]');
        if (trig) {
            e.preventDefault();
            var root = rootFrom(trig);
            if (root) {
                toggle(root);
            }
            return;
        }
        var opt = e.target.closest('[data-als-cs-option]');
        if (opt) {
            var root2 = rootFrom(opt);
            if (!root2) {
                return;
            }
            var val = opt.getAttribute('data-value');
            if (val === null) {
                val = '';
            }
            setValue(root2, val, opt.textContent.trim());
            closeAll(null);
            return;
        }
        if (!e.target.closest('[data-als-custom-select]')) {
            closeAll(null);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAll(null);
            return;
        }
        var trig = e.target.closest('[data-als-cs-trigger]');
        if (trig && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            var r = rootFrom(trig);
            if (r) {
                toggle(r);
            }
        }
    });

    window.addEventListener('resize', function () {
        closeAll(null);
    });
})();

/* ENOXX Elections — Settings page JS
 * Handles tab switching and the Test AI button.
 * Enqueued via admin_enqueue_scripts on the Settings page only.
 */
(function(){
    'use strict';

    function activateTab(id) {
        if (!id) id = 'sync';
        var tabs = document.querySelectorAll('.enx-tab-btn');
        var panels = document.querySelectorAll('.enx-tab-panel');
        for (var i = 0; i < tabs.length; i++) {
            var t = tabs[i];
            var on = (t.getAttribute('data-tab') === id);
            t.style.borderBottomColor = on ? '#f59e0b' : 'transparent';
            t.style.color             = on ? '#d97706' : '#333';
        }
        for (var j = 0; j < panels.length; j++) {
            panels[j].style.display = (panels[j].id === 'tab-' + id) ? 'block' : 'none';
        }
        try { window.localStorage.setItem('enx_active_tab', id); } catch (e) {}
        try { history.replaceState(null, '', '#' + id); } catch (e) {}
    }

    function bindTabs() {
        try {
            var tabs = document.querySelectorAll('.enx-tab-btn');
            if (!tabs.length) { return; }

            // Direct click handlers
            for (var k = 0; k < tabs.length; k++) {
                (function(btn){
                    if (btn.dataset.enxTabBound) return;
                    btn.dataset.enxTabBound = '1';
                    btn.addEventListener('click', function(ev){
                        ev.preventDefault();
                        activateTab(btn.getAttribute('data-tab'));
                    });
                })(tabs[k]);
            }

            // jQuery-delegated safety net (wp-admin always loads jQuery)
            if (window.jQuery && !window.jQuery.__enxTabBound) {
                window.jQuery.__enxTabBound = true;
                window.jQuery(document).on('click.enxTabs', '.enx-tab-btn', function(ev){
                    ev.preventDefault();
                    activateTab(this.getAttribute('data-tab'));
                });
            }

            // Set initial active tab
            var hash = (window.location.hash || '').replace('#', '');
            var stored = '';
            try { stored = window.localStorage.getItem('enx_active_tab') || ''; } catch (e) {}
            activateTab(hash || stored || 'sync');
        } catch (err) {
            if (window.console && console.error) console.error('ENOXX tabs init failed:', err);
        }
    }

    function bindTestAi() {
        try {
            var btn = document.getElementById('enx-test-ai');
            if (!btn || btn.dataset.enxBound) return;
            btn.dataset.enxBound = '1';
            btn.addEventListener('click', function(){
                var slots = [
                    {k:'enx_ai_key_1',p:'enx_ai_provider_1'},
                    {k:'enx_ai_key_2',p:'enx_ai_provider_2'},
                    {k:'enx_ai_key_3',p:'enx_ai_provider_3'},
                    {k:'enx_ai_key_4',p:'enx_ai_provider_4'},
                    {k:'enx_ai_key_5',p:'enx_ai_provider_5'}
                ];
                var testKey = '', testProv = 'openai';
                for (var i = 0; i < slots.length; i++) {
                    var ki = document.querySelector('[name="' + slots[i].k + '"]');
                    var pi = document.querySelector('[name="' + slots[i].p + '"]');
                    if (ki && ki.value && ki.value.trim()) {
                        testKey = ki.value.trim();
                        testProv = pi ? pi.value : 'openai';
                        break;
                    }
                }
                if (!testKey) {
                    var legK = document.querySelector('[name="enx_openai_key"]');
                    if (legK) testKey = legK.value.trim();
                }
                var res = document.getElementById('enx-ai-test-result');
                if (!res) return;
                if (!testKey) { res.textContent = '⚠ Enter at least one API key first'; return; }
                res.textContent = 'Testing key (' + testProv + ')...';

                var nonce = (window.ENXSettings && window.ENXSettings.nonce) ? window.ENXSettings.nonce : '';
                var ajaxurlLocal = (window.ENXSettings && window.ENXSettings.ajaxurl)
                    ? window.ENXSettings.ajaxurl
                    : (window.ajaxurl || '/wp-admin/admin-ajax.php');

                var body = new URLSearchParams();
                body.append('action', 'enx_test_ai');
                body.append('key', testKey);
                body.append('provider', testProv);
                body.append('nonce', nonce);

                fetch(ajaxurlLocal, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                }).then(function(r){ return r.json(); }).then(function(d){
                    res.textContent = d.success ? ('✅ Connected: ' + d.data) : ('❌ ' + d.data);
                }).catch(function(e){
                    res.textContent = '❌ ' + (e && e.message ? e.message : 'Network error');
                });
            });
        } catch (err) {
            if (window.console && console.error) console.error('ENOXX test-ai init failed:', err);
        }
    }

    function init() {
        bindTabs();
        bindTestAi();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

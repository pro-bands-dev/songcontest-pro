/**
 * Song Contest PRO – [scp_home_teaser]
 * Holt den wechselnden Teil frisch vom Server (unabhängig vom Seiten-Cache)
 * und aktualisiert in der Voting-Phase die Live-Charts regelmäßig.
 */
(function(){
    'use strict';

    function mayReload(){
        try {
            var last = parseInt(sessionStorage.getItem('scpHtReload') || '0', 10);
            if(Date.now() - last < 300000) return false;
            sessionStorage.setItem('scpHtReload', String(Date.now()));
            return true;
        } catch(e){ return false; }
    }

    function setup(box){

        var cfg;
        try { cfg = JSON.parse(box.getAttribute('data-config') || '{}'); } catch(e){ return; }
        if(!cfg.endpoint) return;

        var live = box.querySelector('[data-scp-ht-live]');
        if(!live) return;

        var url = new URL(cfg.endpoint, window.location.href);
        var args = cfg.args || {};
        Object.keys(args).forEach(function(k){ url.searchParams.set(k, args[k]); });

        var timer = null;
        var lastHtml = null;

        function load(){
            if(document.hidden) return; // im Hintergrund-Tab nicht abfragen

            fetch(url.toString(), { credentials: 'same-origin' })
                .then(function(r){ return r.ok ? r.json() : null; })
                .then(function(res){
                    if(!res || typeof res.html !== 'string') return;

                    // Phase hat gewechselt (z. B. Voting gestartet) → Seite einmal neu laden.
                    //    Höchstens einmal alle 5 Minuten, damit keine Neulade-Schleife entstehen kann.
                    if(res.state && cfg.state && res.state !== cfg.state && mayReload()){
                        window.location.reload();
                        return;
                    }

                    if(res.html === lastHtml) return;
                    var first = lastHtml === null;
                    lastHtml = res.html;
                    live.innerHTML = res.html;
                    if(!first){
                        box.classList.add('is-updated');
                        setTimeout(function(){ box.classList.remove('is-updated'); }, 1200);
                    }
                })
                .catch(function(){ /* still – nächster Versuch beim nächsten Intervall */ });
        }

        load();

        var poll = parseInt(cfg.poll, 10) || 0;
        if(poll > 0){
            timer = setInterval(load, poll * 1000);
            document.addEventListener('visibilitychange', function(){ if(!document.hidden) load(); });
        }
    }

    function init(){
        var boxes = document.querySelectorAll('.scp-home-teaser[data-config]');
        for(var i = 0; i < boxes.length; i++) setup(boxes[i]);
    }

    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

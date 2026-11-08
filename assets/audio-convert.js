/**
 * Song Contest PRO – WAV → MP3 im Browser der Band.
 *
 * Sobald im Bewerbungsformular eine WAV-Datei gewählt wird, wird sie hier
 * in MP3 umgewandelt (LAME, läuft in einem Web Worker, die Seite bleibt bedienbar).
 * Die MP3 ersetzt danach die gewählte Datei im Formular. Hochgeladen und
 * gespeichert wird nur die MP3.
 *
 * Konfiguration aus PHP: window.scpAudioConvert = { lameUrl, kbps }
 */
(function(){
    'use strict';

    var cfg = window.scpAudioConvert || {};
    var KBPS = parseInt(cfg.kbps, 10) || 192;
    var TARGET_RATE = 44100;

    function isWav(file){
        if(!file) return false;
        var type = (file.type || '').toLowerCase();
        return /\.wave?$/i.test(file.name || '') ||
               type === 'audio/wav' || type === 'audio/x-wav' || type === 'audio/wave' || type === 'audio/vnd.wave';
    }

    function formatMB(bytes){
        return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
    }

    function canConvert(){
        return !!(window.Worker && window.Blob && window.DataTransfer &&
                  (window.AudioContext || window.webkitAudioContext) && cfg.lameUrl);
    }

    /* ---------- Decodieren ---------- */

    function decode(file){
        return file.arrayBuffer().then(function(buf){
            var Ctx = window.AudioContext || window.webkitAudioContext;
            var ctx;
            try { ctx = new Ctx({ sampleRate: TARGET_RATE }); }
            catch(e){ ctx = new Ctx(); }

            return new Promise(function(resolve, reject){
                var done = false;
                var p = ctx.decodeAudioData(buf, function(b){ done = true; resolve(b); }, function(err){ if(!done) reject(err || new Error('decode')); });
                if(p && p.then){ p.then(function(b){ if(!done){ done = true; resolve(b); } }, function(err){ if(!done) reject(err); }); }
            }).then(function(audio){
                if(ctx.close) ctx.close();
                return resample(audio);
            }, function(err){
                if(ctx.close) ctx.close();
                throw err;
            });
        });
    }

    // MP3 kennt nur bestimmte Abtastraten – alles andere auf 44,1 kHz bringen.
    function resample(audio){
        if(audio.sampleRate === 44100 || audio.sampleRate === 48000) return Promise.resolve(audio);
        var Off = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        if(!Off) return Promise.resolve(audio);
        var len = Math.ceil(audio.duration * TARGET_RATE);
        var off = new Off(Math.min(audio.numberOfChannels, 2), len, TARGET_RATE);
        var src = off.createBufferSource();
        src.buffer = audio;
        src.connect(off.destination);
        src.start(0);
        return off.startRendering();
    }

    function toInt16(f32){
        var out = new Int16Array(f32.length);
        for(var i = 0; i < f32.length; i++){
            var s = f32[i];
            s = s < -1 ? -1 : (s > 1 ? 1 : s);
            out[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
        }
        return out;
    }

    /* ---------- Encoder im Worker ---------- */

    var workerSrc = [
        'self.onmessage = function(e){',
        '  var d = e.data;',
        '  try {',
        '    importScripts(d.lameUrl);',
        '    var enc = new lamejs.Mp3Encoder(d.channels, d.sampleRate, d.kbps);',
        '    var L = d.left, R = d.right, n = L.length, block = 1152 * 20, parts = [], last = -1;',
        '    for(var i = 0; i < n; i += block){',
        '      var l = L.subarray(i, i + block);',
        '      var chunk = d.channels === 2 ? enc.encodeBuffer(l, R.subarray(i, i + block)) : enc.encodeBuffer(l);',
        '      if(chunk.length) parts.push(chunk);',
        '      var pct = Math.floor(i / n * 100);',
        '      if(pct !== last){ last = pct; self.postMessage({ progress: pct }); }',
        '    }',
        '    var end = enc.flush();',
        '    if(end.length) parts.push(end);',
        '    self.postMessage({ done: true, parts: parts });',
        '  } catch(err){',
        '    self.postMessage({ error: String(err && err.message || err) });',
        '  }',
        '};'
    ].join('\n');

    function encode(audio, onProgress){
        return new Promise(function(resolve, reject){
            var channels = audio.numberOfChannels >= 2 ? 2 : 1;
            var left  = toInt16(audio.getChannelData(0));
            var right = channels === 2 ? toInt16(audio.getChannelData(1)) : null;

            var url = URL.createObjectURL(new Blob([workerSrc], { type: 'application/javascript' }));
            var worker = new Worker(url);

            worker.onmessage = function(e){
                var d = e.data;
                if(d.progress !== undefined){ onProgress(d.progress); return; }
                worker.terminate();
                URL.revokeObjectURL(url);
                if(d.error){ reject(new Error(d.error)); return; }
                resolve(new Blob(d.parts, { type: 'audio/mpeg' }));
            };
            worker.onerror = function(err){
                worker.terminate();
                URL.revokeObjectURL(url);
                reject(err);
            };

            var transfer = [left.buffer];
            if(right) transfer.push(right.buffer);

            worker.postMessage({
                lameUrl: new URL(cfg.lameUrl, window.location.href).href,
                channels: channels,
                sampleRate: audio.sampleRate,
                kbps: KBPS,
                left: left,
                right: right
            }, transfer);
        });
    }

    /* ---------- Formular ---------- */

    function setup(form){

        var input  = form.querySelector('input[type="file"][name="band_audio"]');
        if(!input) return;

        var status = form.querySelector('.scp-audio-status');
        if(!status){
            status = document.createElement('p');
            status.className = 'scp-audio-status';
            status.setAttribute('aria-live', 'polite');
            input.parentNode.insertBefore(status, input.nextSibling);
        }

        var state = { job: null, failed: false, ready: true };

        function show(msg, kind){
            status.hidden = !msg;
            status.textContent = msg || '';
            status.className = 'scp-audio-status' + (kind ? ' scp-audio-status--' + kind : '');
        }

        input.addEventListener('change', function(){

            var file = input.files && input.files[0];
            state.failed = false;

            if(!isWav(file)){
                state.job = null;
                state.ready = true;
                show('');
                return;
            }

            if(!canConvert()){
                state.ready = false;
                state.failed = true;
                show('Dein Browser kann die WAV-Datei nicht umwandeln. Bitte lade Deinen Song als MP3 hoch.', 'error');
                return;
            }

            state.ready = false;
            show('WAV wird in MP3 umgewandelt … 0 %', 'busy');

            var myJob = decode(file).then(function(audio){
                return encode(audio, function(p){
                    if(state.job === myJob) show('WAV wird in MP3 umgewandelt … ' + p + ' %', 'busy');
                });
            }).then(function(blob){
                if(state.job !== myJob) return; // inzwischen andere Datei gewählt
                var name = (file.name || 'song').replace(/\.wave?$/i, '') + '.mp3';
                var mp3  = new File([blob], name, { type: 'audio/mpeg', lastModified: Date.now() });
                var dt   = new DataTransfer();
                dt.items.add(mp3);
                input.files = dt.files;
                state.ready = true;
                show('Fertig: ' + name + ' (' + formatMB(mp3.size) + ', vorher ' + formatMB(file.size) + ')', 'ok');
            }).catch(function(err){
                if(state.job !== myJob) return;
                if(window.console) console.error('[SCP] WAV-Umwandlung fehlgeschlagen', err);
                state.failed = true;
                show('Die WAV-Datei konnte nicht umgewandelt werden. Bitte lade Deinen Song als MP3 hoch.', 'error');
            });

            state.job = myJob;
        });

        var submitting = false;

        form.addEventListener('submit', function(e){

            if(submitting) return;

            if(state.failed){
                e.preventDefault();
                show(status.textContent || 'Bitte lade Deinen Song als MP3 hoch.', 'error');
                input.focus();
                return;
            }

            if(state.ready) return; // nichts umzuwandeln → normal absenden

            // Umwandlung läuft noch: warten, dann absenden.
            e.preventDefault();
            var btn = form.querySelector('[name="apply"]');
            if(btn) btn.disabled = true;

            state.job.then(function(){
                if(!state.ready){
                    if(btn) btn.disabled = false;
                    return;
                }
                submitting = true;
                // Der Button-Wert "apply" fehlt bei form.submit() – daher als verstecktes Feld.
                if(!form.querySelector('input[type="hidden"][name="apply"]')){
                    var h = document.createElement('input');
                    h.type = 'hidden'; h.name = 'apply'; h.value = '1';
                    form.appendChild(h);
                }
                HTMLFormElement.prototype.submit.call(form);
            });
        });
    }

    function init(){
        var forms = document.querySelectorAll('form[data-scp-audio-convert]');
        for(var i = 0; i < forms.length; i++) setup(forms[i]);
    }

    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

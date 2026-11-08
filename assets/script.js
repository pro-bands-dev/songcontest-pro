jQuery(function($){
	
/* =========================
   REF AUS URL HOLEN
========================= */

function getRefFromUrl(){
    const params = new URLSearchParams(window.location.search);
    return params.get("ref") || 0;
}

/* Waveform Player */

$(".wave-player").each(function(){

let container = $(this);
let audio = container.data("audio");
let waveform = container.find(".waveform")[0];
let button = container.find(".play-btn");

let wavesurfer = WaveSurfer.create({

container: waveform,
waveColor: '#999999',
progressColor: '#e99a2c',
height: 80,
responsive: true,

/* ANDROID FIX */
interact: false,
dragToSeek: false

});
wavesurfer.load(audio);
button.on("click",function(){
wavesurfer.playPause();
});

});

/* FINGERPRINT INIT */

const fpPromise = (typeof FingerprintJS !== "undefined")
    ? FingerprintJS.load()
    : null;

if(fpPromise){
    console.log("FingerprintJS geladen");
}

async function getFingerprint(){

    if(!fpPromise){
        return "";
    }

    try{
        const fp = await fpPromise;
        const result = await fp.get();
        return result.visitorId || "";
    }catch(e){
        console.log("Fingerprint Fehler", e);
        return "";
    }
}
	
/* =========================
   VOTER LOGIN / VOTING
========================= */

let scpPendingVoteButton = null;
let scpAutoVoteInProgress = false;

function scpVoterLoggedIn(){
    return !!(
        window.scp_ajax
        && parseInt(scp_ajax.voter_logged_in,10) === 1
    );
}

function scpOpenVoterModal(button){

    scpPendingVoteButton = button || null;

    if(button && button.length){
        $("#scp-voter-artist").val(button.data("id") || "");
        $("#scp-voter-ref").val(button.data("ref") || getRefFromUrl());
    }

    $(".scp-voter-form-message")
        .removeClass("error success")
        .text("");

    $("#scp-voter-modal")
        .addClass("is-open")
        .attr("aria-hidden","false");

    setTimeout(function(){
        $("#scp-voter-email").trigger("focus");
    },100);
}

function scpCloseVoterModal(){
    $("#scp-voter-modal")
        .removeClass("is-open")
        .attr("aria-hidden","true");
}

$(document).on("click",".scp-voter-modal-close,.scp-voter-modal-backdrop",function(){
    scpCloseVoterModal();
});

$(document).on("keydown",function(e){
    if(e.key === "Escape"){
        scpCloseVoterModal();
    }
});

$(document).on("submit","#scp-voter-login-form",function(e){

    e.preventDefault();

    let form = $(this);
    let submit = form.find(".scp-voter-submit");
    let message = form.find(".scp-voter-form-message");

    let email = $.trim($("#scp-voter-email").val());
    let artist = parseInt($("#scp-voter-artist").val(),10) || 0;
    let ref = parseInt($("#scp-voter-ref").val(),10) || 0;

    if(!email){
        message.addClass("error").text("Bitte gib deine E-Mail-Adresse ein.");
        return;
    }

    submit.prop("disabled",true);
    message.removeClass("error success").text("Bestätigungslink wird gesendet …");

    $.post(
        scp_ajax.ajaxurl + '?t=' + Date.now(),
        {
            action: "scp_request_voter_login",
            nonce: scp_ajax.nonce,
            email: email,
            artist: artist,
            ref: ref,
            return_url: window.location.href
        }
    )
    .done(function(res){

        if(res && res.success){

            message
                .removeClass("error")
                .addClass("success")
                .text(
                    (res.data && res.data.message)
                    ? res.data.message
                    : "Bestätigungslink wurde gesendet."
                );

            submit.text("E-Mail gesendet");

        }else{

            let msg =
                res && res.data && res.data.message
                ? res.data.message
                : "Die E-Mail konnte nicht versendet werden.";

            message
                .removeClass("success")
                .addClass("error")
                .text(msg);

            submit.prop("disabled",false);
        }
    })
    .fail(function(xhr){

        let msg = "Die E-Mail konnte nicht versendet werden.";

        if(
            xhr.responseJSON
            && xhr.responseJSON.data
            && xhr.responseJSON.data.message
        ){
            msg = xhr.responseJSON.data.message;
        }

        message
            .removeClass("success")
            .addClass("error")
            .text(msg);

        submit.prop("disabled",false);
    });
});

$(document).on("click",".scp-vote-btn", async function(){

    let button = $(this);

    if(button.prop("disabled")) return;

    if(!scpVoterLoggedIn()){
        scpOpenVoterModal(button);
        return;
    }

    let id  = button.data("id");
    let ref = button.data("ref") || getRefFromUrl();

    button.prop("disabled",true);
    button.addClass("scp-vote-pending");

    let fingerprint = await getFingerprint();

    $.post(
        scp_ajax.ajaxurl + '?t=' + Date.now(),
        {
            action: "scp_vote",
            nonce: scp_ajax.nonce,
            artist: id,
            ref: ref,
            fingerprint: fingerprint
        }
    )
    .done(function(res){

        if(!res || !res.success){

            let msg =
                res && res.data && res.data.message
                ? res.data.message
                : "Die Stimme konnte nicht gespeichert werden.";

            if(
                res
                && res.data
                && res.data.code === "login_required"
            ){
                scp_ajax.voter_logged_in = 0;
                scpOpenVoterModal(button);
            }else{
                alert(msg);
            }

            button
                .prop("disabled",false)
                .removeClass("scp-vote-pending");

            return;
        }

        let scope = button.closest(".band-card,.band-profile-card");
        let voteEl = scope.find(".scp-vote-count .votes").first();

        if(voteEl.length){
            let current = parseInt(voteEl.text(),10) || 0;
            voteEl.text(current + 1);
        }

        button
            .removeClass("scp-vote-pending")
            .addClass("voted")
            .prop("disabled",true);

        alert("✅ Deine Stimme wurde gezählt!");

        /*
         * Nach einem Magic-Link-Auto-Vote die Seite einmal sauber vom
         * Server neu laden. Dadurch werden Login-Status und Session sofort
         * korrekt dargestellt und ein noch offener Login-Dialog kann nicht
         * erneut erscheinen. Die Auto-Vote-Parameter wurden vorher bereits
         * aus der URL entfernt, daher kann der Reload keine zweite Stimme
         * auslösen.
         */
        if(scpAutoVoteInProgress){
            scpAutoVoteInProgress = false;
            scpCloseVoterModal();
            window.location.replace(window.location.href);
            return;
        }

        button.after("<span class='vote-plus'>+1</span>");

        setTimeout(function(){
            button.siblings(".vote-plus").fadeOut();
        },1000);
    })
    .fail(function(xhr){

        let data =
            xhr.responseJSON
            && xhr.responseJSON.data
            ? xhr.responseJSON.data
            : null;

        if(data && data.code === "login_required"){

            scp_ajax.voter_logged_in = 0;
            scpOpenVoterModal(button);

        }else{

            alert(
                data && data.message
                ? data.message
                : "Die Stimme konnte nicht gespeichert werden."
            );
        }

        button
            .prop("disabled",false)
            .removeClass("scp-vote-pending");
    });
});

(function(){

    const params = new URLSearchParams(window.location.search);

    if(params.get("scp_voter") !== "verified"){
        return;
    }

    scp_ajax.voter_logged_in = 1;

    const bandId = parseInt(params.get("band"),10) || 0;
    const autoVote = params.get("scp_autovote") === "1";

    if(!bandId){
        return;
    }

    const target = $("#band-" + bandId);

    if(target.length){

        setTimeout(function(){

            target[0].scrollIntoView({
                behavior: "smooth",
                block: "center"
            });

            target.addClass("scp-login-return-highlight");

            setTimeout(function(){
                target.removeClass("scp-login-return-highlight");
            },2500);

            /*
             * Der Magic-Link gehoert immer zu genau der Band, fuer die
             * der Vote urspruenglich angefordert wurde. Nach erfolgreicher
             * E-Mail-Bestaetigung wird dieser Vote deshalb automatisch
             * ausgefuehrt. Vorher entfernen wir die Einmal-Parameter aus
             * der URL, damit ein Browser-Refresh keinen zweiten Auto-Vote
             * ausloest.
             */
            if(autoVote){
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete("scp_voter");
                cleanUrl.searchParams.delete("scp_autovote");
                cleanUrl.searchParams.delete("band");
                window.history.replaceState({}, document.title, cleanUrl.toString());

                const voteButton = target.find(".scp-vote-btn").first();

                if(voteButton.length && !voteButton.prop("disabled")){
                    setTimeout(function(){
                        scpAutoVoteInProgress = true;
                        scpCloseVoterModal();
                        voteButton.trigger("click");
                    },350);
                }
            }

        },250);
    }
})();


/* =========================
   🧑‍⚖️ Jury Voting
========================= */

$(document).on("change", ".jury-score", function(){

    let select = $(this);
    let band = select.data("band");
    let contest = select.data("contest") || 0;
    let points = select.val();

    if(points === "") return;

//    $.post(scp_ajax.ajaxurl, {
	$.post(scp_ajax.ajaxurl + '?t=' + Date.now(),{
        action: "scp_jury_vote",
        artist: band,
        contest_id: contest,
        points: points
    }, function(response){

        if(response.success){

            /* visuelles Feedback */
            select.css("border","2px solid green");

            setTimeout(function(){
                select.css("border","");
            }, 1500);

        } else {

            alert(response.data || "Fehler beim Speichern");

        }

    });

});

/* Jury Preselect */
$(document).on("click",".jury-pick",function(){
let btn = $(this);
let id  = btn.data("id");
//	$.post(scp_ajax.ajaxurl,{
		$.post(scp_ajax.ajaxurl + '?t=' + Date.now(),{
		action:"scp_toggle_jury_pick",
		artist:id
	},function(res){
        if(res.success){
            if(res.data === "added"){
                btn.addClass("active").text("✔ gewählt");
            }
            if(res.data === "removed"){
                btn.removeClass("active").text("Auswählen");
            }
        }else{
            alert("Maximale Auswahl erreicht");
        }
    });
});

/* =========================
   ADMIN JURY TOGGLE
========================= */

$(document).on("click",".scp-admin-pick",function(){

    let btn = jQuery(this);
    let id  = btn.data("id");

    jQuery.post(scp_ajax.ajaxurl + '?t=' + Date.now(),{
        action:"scp_admin_toggle_pick",
        artist:id
    },function(res){

        if(res.success){

            if(res.data === "added"){
                btn.text("❌ Entfernen");
                btn.closest("tr").css("background","#e6ffe6");
            }

            if(res.data === "removed"){
                btn.text("✔ In Top30");
                btn.closest("tr").css("background","");
            }

        }else{
            alert("Fehler: Keine Berechtigung oder Limit erreicht.");
        }

    });

});

/* Live Votes */

function updateVotes(){

    $.post(scp_ajax.ajaxurl + '?t=' + Date.now(),{
        action:"scp_get_votes"
    },function(data){

        $(".band-card").each(function(){

            let card = $(this);
            let id = card.data("band");

            if(data[id] !== undefined){

				card.find(".scp-vote-count .votes").text(data[id]);
			}

        });

    });

}
	
function updateSingleBand(){

    let profile = $(".band-profile-card");

    if(!profile.length) return;

    let button = profile.find(".scp-vote-btn");

    if(!button.length) return;

    let bandId = button.data("id");

    $.post(scp_ajax.ajaxurl + '?t=' + Date.now(),{
        action:"scp_get_votes"
    },function(data){

        if(data[bandId] !== undefined){

            /*
            Nur Votezahl im Profil aktualisieren
            */
            profile.find(".votes").text(data[bandId]);

        }

    });

}
/* =========================
   📊 LIVE CHARTS UPDATE
========================= */

function updateCharts(){

    $.post(scp_ajax.ajaxurl + '?t=' + Date.now(),{
        action:"scp_get_chart_votes"
    },function(response){

        let mode = "votes";
        let data = response;

        /*
         * Neue Response-Struktur:
         * {
         *   mode: "votes" | "score",
         *   data: { bandId: value }
         * }
         *
         * Fallback bleibt erhalten, falls ältere AJAX-Antworten genutzt werden.
         */
        if(response && response.data !== undefined){
            mode = response.mode || "votes";
            data = response.data;
        }

        $(".scp-charts").attr("data-chart-mode",mode);

        $(".scp-charts li").each(function(){

            let item = $(this);
            let id = item.data("band");

            if(data[id] !== undefined){

                item.find(".chart-votes").text(data[id]);
            }

        });

        sortCharts();

    });

}

/* =========================
   📋 COPY REF LINK
========================= */

$(document).on("click",".scp-copy-link",function(){

    let input = $(".scp-ref-link");

    input.select();
    input[0].setSelectionRange(0,99999);

    document.execCommand("copy");

    alert("Link kopiert! 🔥");

});

/* =========================
   🔄 SORTIERUNG
========================= */


	
function sortCharts(){

    /*
    Jede Chart-Liste einzeln sortieren.
    Funktioniert für Votes und Gesamtscore.
    */
    $(".scp-charts").each(function(){

        let list = $(this);

        let items = list.children("li").get();

        items.sort(function(a,b){

            let aValue = parseFloat(
                $(a).find(".chart-votes").text()
            ) || 0;

            let bValue = parseFloat(
                $(b).find(".chart-votes").text()
            ) || 0;

            return bValue - aValue;

        });

        $.each(items,function(idx,item){

            list.append(item);

        });

    });

}

/* =========================
   🚀 INIT
========================= */
/* =========================
   LIVE UPDATE INIT
========================= */
/* =========================
   BAND DIREKTLINK
========================= */

function scrollToBand(){

    const params = new URLSearchParams(window.location.search);

    const band = params.get("band");

    if(!band) return;

    const card = $("#band-" + band);

    if(!card.length) return;

    $("html, body").animate({

        scrollTop: card.offset().top - 80

    },700);

    card.addClass("band-highlight");

    setTimeout(function(){

        card.removeClass("band-highlight");

    },3000);

}

scrollToBand();
/*
Band Cards vorhanden
*/
if($(".band-card").length){

    setInterval(updateVotes,10000);
}

/*
Einzelprofil vorhanden
*/
if($(".band-profile-card").length){

    setInterval(updateSingleBand,10000);
}

/*
Charts vorhanden
*/
if($(".scp-charts").length){

    setInterval(updateCharts,5000);
}

});
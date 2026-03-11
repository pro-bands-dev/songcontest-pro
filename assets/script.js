jQuery(function($){

$(document).on("click",".vote",function(){

let id = $(this).data("id");

$.post(
scp_ajax.ajaxurl,
{
action:"scp_vote",
artist:id
},
function(res){

alert(res);

});

});


let el = document.getElementById("contest-countdown");

if(el){

let end = new Date(el.dataset.end).getTime();

setInterval(function(){

let now = new Date().getTime();
let dist = end-now;

let days = Math.floor(dist/(1000*60*60*24));

el.innerHTML = "Voting endet in "+days+" Tagen";

},1000);

}

});
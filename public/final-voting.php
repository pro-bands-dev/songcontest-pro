<?php
if (!defined('ABSPATH')) exit;
require_once dirname(__DIR__).'/includes/final-devices.php';
add_action('template_redirect','scp_final_virtual_screens');
function scp_final_virtual_screens(){
 $screen=sanitize_key($_GET['scp_final_screen']??''); if(!$screen) return;
 if(!in_array($screen,['qr','live','vote','jury','device','pair'],true)) return;
 nocache_headers(); status_header(200);
 echo '<!doctype html><html><head><meta charset="'.esc_attr(get_bloginfo('charset')).'"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PRO-BANDS Final-Voting</title><link rel="stylesheet" href="'.esc_url(SCP_URL.'assets/festival.css?ver='.SCP_VERSION).'"></head><body class="scp-final-screen">';
 if($screen==='pair') scp_final_pair_screen(); elseif($screen==='device') scp_final_device_screen(); elseif($screen==='qr') scp_final_qr_screen(); elseif($screen==='live') scp_final_live_screen(); elseif($screen==='jury') scp_final_jury_screen(); else scp_final_vote_screen();
 if(in_array($screen,['qr','device'],true)) echo '<button type="button" class="scp-display-fullscreen" id="scp-display-fullscreen" aria-label="Vollbild ein- oder ausschalten">⛶ Vollbild</button><script>(function(){var b=document.getElementById("scp-display-fullscreen");if(!b)return;b.addEventListener("click",function(){if(document.fullscreenElement){document.exitFullscreen?.();}else if(document.documentElement.requestFullscreen){document.documentElement.requestFullscreen().catch(function(){b.textContent="Zum Home-Bildschirm hinzufügen";});}else{b.textContent="Zum Home-Bildschirm hinzufügen";}});document.addEventListener("fullscreenchange",function(){b.textContent=document.fullscreenElement?"⛶ Vollbild verlassen":"⛶ Vollbild";});})();</script>';
 echo '</body></html>'; exit;
}
function scp_final_qr_screen(){
 $s=scp_final_session(); $artists=scp_finalists($s);
 echo '<main class="scp-final-stage scp-final-qr-stage scp-fd-display">
 <div class="scp-final-branding">
  <img class="scp-final-brand-logo scp-final-brand-neuendorf" src="https://pro-bands.de/wp-content/uploads/2026/10/Fransiger-Rocklogo-Rand-mit-Flammen-und-Gitarren-e1791231720717.png" alt="Neuendorf Rockt">
  <img class="scp-final-brand-logo scp-final-brand-contest" src="https://pro-bands.de/wp-content/uploads/2026/10/PRO-BANDS-Song-Contest-2027-–-Logo.png" alt="PRO-BANDS Song Contest 2027">
 </div>
 <div class="scp-final-display-title"><strong>LIVE-FINALE</strong><span>JETZT ABSTIMMEN!</span></div><div id="scp-final-qr-grid"></div>
 <div class="scp-final-callout"><strong>DEINE STIMME ZÄHLT!</strong><span>QR-Code deines Favoriten scannen und abstimmen.</span></div>
 </main>';
 ?>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>
 function escText(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));} function escAttr(v){return escText(v);} function draw(){var box=document.getElementById('scp-final-qr-grid');box.innerHTML='';var data=<?php echo wp_json_encode(['active'=>$s&&$s->status==='running','sid'=>$s?(int)$s->id:0,'artists'=>array_map(function($a)use($s){return ['id'=>(int)$a->id,'name'=>$a->name,'image'=>scp_image_url((string)($a->image??''),'250'),'token'=>$s?scp_final_token($s->id,$a->id):''];},$artists),'base'=>add_query_arg(['scp_final_screen'=>'vote','scp_final_contest_id'=>scp_final_contest_id()],home_url('/'))]); ?>;
 if(!data.active){box.innerHTML='<div class="scp-final-wait">Final-Voting ist noch nicht geöffnet.</div>';return;} data.artists.forEach(function(a){var c=document.createElement('div');c.className='scp-final-qr-card';c.innerHTML='<div class="scp-final-card-head">'+(a.image?'<img class="scp-final-band-thumb" src="'+escAttr(a.image)+'" alt="">':'<span class="scp-final-band-placeholder">'+escText(a.name).charAt(0)+'</span>')+'<h2>'+escText(a.name)+'</h2></div><div class="qr"></div><div class="scp-vote-cta">JETZT ABSTIMMEN!</div>';box.appendChild(c);var u=data.base+'&sid='+data.sid+'&artist='+a.id+'&token='+encodeURIComponent(a.token);new QRCode(c.querySelector('.qr'),{text:u,width:220,height:220,correctLevel:QRCode.CorrectLevel.M});});}
 draw();var qrDelay=30000-(Date.now()%30000)+100;setTimeout(function(){location.reload()},qrDelay);
 </script><?php
}
function scp_final_live_screen(){
 $session=scp_final_session();
 $ajax = esc_url_raw(add_query_arg(['action'=>'scp_final_live_data','scp_final_contest_id'=>scp_final_contest_id(),'scp_final_session_id'=>$session?(int)$session->id:0],admin_url('admin-ajax.php')));
 ?>
 <main class="scp-final-stage scp-final-live scp-final-festival-stage">
  <div class="scp-final-branding scp-final-live-branding">
   <img class="scp-final-brand-logo scp-final-brand-neuendorf" src="https://pro-bands.de/wp-content/uploads/2026/10/Fransiger-Rocklogo-Rand-mit-Flammen-und-Gitarren-e1791231720717.png" alt="Neuendorf Rockt">
   <img class="scp-final-brand-logo scp-final-brand-contest" src="https://pro-bands.de/wp-content/uploads/2026/10/PRO-BANDS-Song-Contest-2027-–-Logo.png" alt="PRO-BANDS Song Contest 2027">
  </div>
  <div class="scp-final-live-title">LIVE-FINALE</div>
  <div id="scp-final-live-content"><div class="scp-final-wait">Final-Voting läuft – Zwischenstand wird von der Bühne eingeblendet.</div></div>
 </main>
 <script>
 function esc(s){return String(s??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));}
 async function tick(){try{let r=await fetch(<?php echo wp_json_encode($ajax); ?>,{cache:"no-store"});let j=await r.json();if(!j.success)return;let d=j.data,el=document.getElementById("scp-final-live-content");if(d.mode==="standings"){el.innerHTML='<div class="scp-live-kicker">ZWISCHENSTAND</div><div class="scp-live-ranking">'+d.rows.map(x=>`<div class="scp-final-rank"><span>Platz ${x.position}</span><strong>${x.score}%</strong></div>`).join("")+'</div>';}else if(d.mode==="reveal"){let winner=d.rows.find(x=>x.position==1&&x.revealed);if(winner){let pic=winner.image?`<img class="scp-winner-photo" src="${esc(winner.image)}" alt="">`:`<div class="scp-winner-photo scp-photo-placeholder">${esc(winner.name).charAt(0)}</div>`;el.innerHTML=`<div class="scp-winner-screen"><div class="scp-winner-photo-wrap">${pic}</div><div class="scp-winner-copy"><div class="scp-winner-kicker">GEWINNER DES PRO-BANDS SONG CONTEST 2027</div><div class="scp-winner-place">PLATZ 1</div><div class="scp-winner-name">${esc(winner.name)}</div><div class="scp-winner-score">${winner.score}%</div></div></div>`;}else{el.innerHTML='<div class="scp-live-kicker">DAS ERGEBNIS</div><div class="scp-live-ranking scp-live-reveal">'+d.rows.map(x=>{let pic=x.revealed?(x.image?`<img class="scp-reveal-photo pos-${x.position}" src="${esc(x.image)}" alt="">`:`<span class="scp-reveal-photo scp-photo-placeholder pos-${x.position}">${esc(x.name).charAt(0)}</span>`):'';return `<div class="scp-final-rank ${x.revealed?"revealed":"covered"}">${pic}<span>Platz ${x.position}${x.revealed?" – "+esc(x.name):""}</span><strong>${x.revealed?x.score+"%":"—"}</strong></div>`;}).join("")+'</div>';}}else{el.innerHTML=`<div class="scp-final-wait">${esc(d.message)}</div>`;}}catch(e){}}
 tick();setInterval(tick,3000);
 </script>
 <?php
}

function scp_final_vote_screen(){
 $sid=(int)($_GET['sid']??0);$aid=(int)($_GET['artist']??0);$token=sanitize_text_field($_GET['token']??''); global $wpdb;
 $s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE id=%d",$sid)); $a=scp_final_artist($aid,$s);
 $valid=$s&&$s->status==='running'&&$a&&scp_final_token_valid($sid,$aid,$token);
 $ticket=$valid?scp_final_vote_ticket($sid,$aid):'';
 echo '<main class="scp-final-vote-page"><h1>Final-Voting</h1>';
 if(!$valid){echo '<div class="scp-final-vote-box"><h2>QR-Code nicht mehr gültig</h2><p>Bitte scanne den aktuell angezeigten QR-Code erneut.</p></div></main>';return;}
 echo '<div class="scp-final-vote-box"><h2>'.esc_html($a->name).'</h2><p>'.esc_html($a->title).'</p><form id="scp-final-vote-form"><label>E-Mail-Adresse<input required type="email" name="email" autocomplete="email"></label><button>Stimme abgeben</button></form><div id="scp-final-vote-msg"></div></div></main>';
 ?> <script>document.getElementById('scp-final-vote-form').addEventListener('submit',async function(e){e.preventDefault();let f=new FormData(this);f.append('action','scp_final_cast_vote');f.append('sid','<?php echo $sid;?>');f.append('artist','<?php echo $aid;?>');f.append('ticket','<?php echo esc_js($ticket);?>');let r=await fetch('<?php echo esc_url(admin_url('admin-ajax.php'));?>',{method:'POST',body:f});let j=await r.json();document.getElementById('scp-final-vote-msg').textContent=j.success?'Deine Stimme wurde gezählt.':(j.data&&j.data.message?j.data.message:'Stimme konnte nicht gezählt werden.');if(j.success)this.style.display='none';});</script><?php
}
add_action('wp_ajax_scp_final_live_data','scp_final_live_data'); add_action('wp_ajax_nopriv_scp_final_live_data','scp_final_live_data');
function scp_final_live_data(){
 global $wpdb;
 $sid=absint($_GET['scp_final_session_id']??0);
 $cid=absint($_GET['scp_final_contest_id']??0);
 $s=($sid&&$cid)?$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE id=%d AND contest_id=%d",$sid,$cid)):null;
 if(!$s && !$sid) $s=scp_final_session();
 if(!$s) wp_send_json_success(['mode'=>'idle','message'=>'Final-Voting ist noch nicht vorbereitet.']);
 $scores=$s->result_snapshot?json_decode($s->result_snapshot,true):scp_final_scores($s->id);
 if($s->status==='running' && !$s->show_standings) wp_send_json_success(['mode'=>'idle','message'=>'Final-Voting läuft – Zwischenstand wird von der Bühne eingeblendet.']);
 if($s->status==='running' && $s->show_standings) wp_send_json_success(['mode'=>'standings','rows'=>array_map(function($r){return ['position'=>$r['position'],'score'=>number_format_i18n($r['score'],1)];},$scores)]);
 if(in_array($s->status,['stopped','revealed'],true)){
   $rp=(int)$s->reveal_place; $rows=[]; foreach($scores as $r){$rev=$rp>0 && $r['position']>=$rp; $img=(string)($r['image']??''); if(!$img && !empty($r['artist_id'])){$fa=scp_final_artist((int)$r['artist_id'],$s); if($fa&&!empty($fa->image))$img=(string)$fa->image;} $rows[]=['position'=>$r['position'],'name'=>$r['name'],'image'=>$img,'score'=>number_format_i18n($r['score'],1),'revealed'=>$rev];} wp_send_json_success(['mode'=>'reveal','rows'=>$rows]);
 }
 wp_send_json_success(['mode'=>'idle','message'=>'Final-Voting ist geschlossen.']);
}
add_action('wp_ajax_scp_final_cast_vote','scp_final_cast_vote'); add_action('wp_ajax_nopriv_scp_final_cast_vote','scp_final_cast_vote');
function scp_final_cast_vote(){
 global $wpdb; $sid=(int)($_POST['sid']??0);$aid=(int)($_POST['artist']??0);$ticket=sanitize_text_field($_POST['ticket']??'');$email=sanitize_email($_POST['email']??'');
 if(!is_email($email)) wp_send_json_error(['message'=>'Bitte eine gültige E-Mail-Adresse eingeben.']);
 $s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE id=%d",$sid)); if(!$s||$s->status!=='running') wp_send_json_error(['message'=>'Das Final-Voting ist geschlossen.']);
 if(!scp_final_vote_ticket_valid($sid,$aid,$ticket)) wp_send_json_error(['message'=>'Voting-Zeitfenster abgelaufen. Bitte den aktuellen QR-Code erneut scannen.']);
 $artist_ok=!empty($s->is_test) ? (bool)scp_final_artist($aid,$s) : (bool)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}scp_artists WHERE id=%d AND contest_id=%d AND status='finale'",$aid,$s->contest_id)); if(!$artist_ok) wp_send_json_error(['message'=>'Finalist nicht gültig.']);
 $hash=hash('sha256',strtolower(trim($email))); $ip=sanitize_text_field($_SERVER['REMOTE_ADDR']??''); $ua=sanitize_text_field($_SERVER['HTTP_USER_AGENT']??'');
 $ok=$wpdb->insert($wpdb->prefix.'scp_final_votes',['session_id'=>$sid,'contest_id'=>$s->contest_id,'artist_id'=>$aid,'email_hash'=>$hash,'email'=>$email,'ip'=>$ip,'user_agent'=>$ua,'created_at'=>current_time('mysql')]);
 if($ok===false){ if(stripos($wpdb->last_error,'Duplicate')!==false) wp_send_json_error(['message'=>'Mit dieser E-Mail-Adresse wurde bereits im Finale abgestimmt.']); wp_send_json_error(['message'=>'Stimme konnte nicht gespeichert werden.']); }
 scp_final_vote_ticket_consume($ticket); scp_final_log($sid,'vote',['artist_id'=>$aid]); wp_send_json_success(['message'=>'Deine Stimme wurde gezählt.']);
}


function scp_final_jury_screen(){
 $s=scp_final_session(); echo '<main class="scp-final-vote-page"><h1>PRO-BANDS – Jury-Finalvoting</h1>';
 if(!$s || $s->jury_status!=='running'){ echo '<div class="scp-final-vote-box"><h2>Jury-Voting ist geschlossen</h2><p>Die Freigabe erfolgt durch die Bühnenleitung.</p></div></main>'; return; }
 $token=scp_final_jury_token($s->id); $login=add_query_arg(['scp_final_screen'=>'jury','scp_final_contest_id'=>scp_final_contest_id(),'sid'=>$s->id,'jt'=>$token],home_url('/'));
 $sid=(int)($_GET['sid']??0); $jt=sanitize_text_field($_GET['jt']??''); $ticket=sanitize_text_field($_GET['ticket']??''); $ju=(int)($_GET['ju']??0);
 if(!$sid || !$jt){ echo '<div class="scp-final-vote-box"><h2>Jury-Zugang</h2><div id="scp-jury-qr"></div><p>QR-Code scannen. Danach genügt die hinterlegte E-Mail-Adresse.</p></div></main><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>new QRCode(document.getElementById("scp-jury-qr"),{text:'.wp_json_encode($login).',width:260,height:260});</script>'; return; }
 if($sid!==(int)$s->id || !hash_equals(scp_final_jury_token($sid),$jt)){ echo '<div class="scp-final-vote-box"><h2>Jury-Zugang ungültig</h2></div></main>'; return; }
 if(!$ticket || !$ju || !scp_final_jury_login_valid($sid,$ju,$ticket)){
   echo '<div class="scp-final-vote-box"><h2>Jurymitglied anmelden</h2><form id="scp-jury-login"><label>E-Mail-Adresse<input required type="email" name="email" autocomplete="email"></label><button>Jury-Voting öffnen</button></form><div id="scp-jury-msg"></div></div></main>';
   echo '<script>document.getElementById("scp-jury-login").addEventListener("submit",async function(e){e.preventDefault();let f=new FormData(this);f.append("action","scp_final_jury_login");f.append("sid","'.$sid.'");f.append("jt",'.wp_json_encode($jt).');let r=await fetch('.wp_json_encode(admin_url('admin-ajax.php')).',{method:"POST",body:f});let j=await r.json();if(j.success)location.href=j.data.url;else document.getElementById("scp-jury-msg").textContent=j.data&&j.data.message?j.data.message:"Anmeldung nicht möglich.";});</script>'; return;
 }
 $members=scp_final_jury_members($s->contest_id,$s); if(!isset($members[$ju])){ echo '<div class="scp-final-vote-box"><h2>Kein Jury-Zugang</h2></div></main>'; return; }
 global $wpdb; $artists=scp_finalists($s); $total=count($artists); $done=0; foreach($artists as $a){$x=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}scp_final_jury_scores WHERE session_id=%d AND artist_id=%d AND jury_user=%d",$sid,$a->id,$ju));if($x)$done++;}
 echo '<div class="scp-jury-mobile-head"><div><strong>PRO-BANDS FINAL-JURY</strong><span>'.esc_html($members[$ju]->display_name?:$members[$ju]->user_email).'</span></div><div class="scp-jury-progress-pill"><b id="scp-jury-done">'.$done.'</b>/'.$total.' bewertet</div></div>';
 echo '<div class="scp-jury-progressbar"><i style="width:'.($total?round($done/$total*100):0).'%"></i></div><p class="scp-jury-intro">Bewertet wird jeweils der <strong>gesamte Live-Auftritt</strong> – nicht einzelne Titel.</p>';
 $fields=['song_arrangement'=>['Songauswahl & Arrangement','Set-Zusammenstellung, Dramaturgie und musikalische Arrangements.'],'musical'=>['Musikalische Umsetzung','Zusammenspiel, Timing, musikalische Qualität und Sicherheit.'],'vocals'=>['Gesang','Intonation, Ausdruck und Einbindung in den Gesamtauftritt.'],'performance'=>['Bühnenpräsenz & Live-Performance','Präsenz, Energie, Publikumswirkung und Show.'],'overall'=>['Gesamteindruck','Gesamtwirkung und Überzeugungskraft des kompletten Auftritts.']];
 foreach($artists as $i=>$a){ $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_jury_scores WHERE session_id=%d AND artist_id=%d AND jury_user=%d",$sid,$a->id,$ju));
   echo '<section class="scp-final-vote-box scp-jury-band" id="jury-band-'.($i+1).'"><div class="scp-jury-band-kicker">Finalist '.($i+1).' von '.$total.'</div><div class="scp-jury-band-head">'.(!empty($a->image)?'<img class="scp-jury-band-photo" src="'.esc_url(scp_image_url($a->image,'500')).'" alt="">':'<span class="scp-jury-band-photo scp-photo-placeholder">'.esc_html(mb_substr($a->name,0,1)).'</span>').'<h2>'.esc_html($a->name).'</h2></div><p class="scp-jury-whole">Bewertung des gesamten Live-Auftritts</p><form class="scp-jury-score-form" data-artist="'.(int)$a->id.'" data-index="'.($i+1).'">';
   foreach($fields as $key=>$meta){ $val=$row?(int)$row->$key:''; echo '<div class="scp-jury-criterion"><div><label>'.esc_html($meta[0]).'</label><small>'.esc_html($meta[1]).'</small></div><select required name="'.$key.'"><option value="">–</option>'; for($n=0;$n<=10;$n++) echo '<option value="'.$n.'" '.selected($val,$n,false).'>'.$n.'</option>'; echo '</select></div>'; }
   echo '<button class="scp-jury-save-next">Bewertung speichern'.($i+1<$total?' & nächster Finalist':'').'</button><div class="scp-jury-save-msg"></div></form></section>'; }
 echo '</main><script>document.querySelectorAll(".scp-jury-score-form").forEach(form=>form.addEventListener("submit",async function(e){e.preventDefault();let f=new FormData(this);f.append("action","scp_final_jury_save");f.append("sid","'.$sid.'");f.append("ju","'.$ju.'");f.append("ticket",'.wp_json_encode($ticket).');f.append("artist",this.dataset.artist);let r=await fetch('.wp_json_encode(admin_url('admin-ajax.php')).',{method:"POST",body:f});let j=await r.json();let m=this.querySelector(".scp-jury-save-msg");if(j.success){m.textContent="Gespeichert ✓";m.classList.add("ok");let all=[...document.querySelectorAll(".scp-jury-score-form")],complete=all.filter(x=>[...x.querySelectorAll("select")].every(s=>s.value!=="")).length;document.getElementById("scp-jury-done").textContent=complete;document.querySelector(".scp-jury-progressbar i").style.width=(complete/all.length*100)+"%";let ni=parseInt(this.dataset.index)+1, next=document.getElementById("jury-band-"+ni);if(next)setTimeout(()=>next.scrollIntoView({behavior:"smooth",block:"start"}),450);}else m.textContent=j.data&&j.data.message?j.data.message:"Speichern fehlgeschlagen.";}));</script>';
}
add_action('wp_ajax_scp_final_jury_login','scp_final_jury_login'); add_action('wp_ajax_nopriv_scp_final_jury_login','scp_final_jury_login');
function scp_final_jury_login(){
 global $wpdb; $sid=(int)($_POST['sid']??0); $jt=sanitize_text_field($_POST['jt']??''); $email=sanitize_email($_POST['email']??'');
 $s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE id=%d",$sid)); if(!$s||$s->jury_status!=='running'||!hash_equals(scp_final_jury_token($sid),$jt)) wp_send_json_error(['message'=>'Jury-Voting ist nicht freigegeben.']);
 if(!is_email($email)) wp_send_json_error(['message'=>'Bitte eine gültige E-Mail-Adresse eingeben.']); $found=0; foreach(scp_final_jury_members($s->contest_id,$s) as $id=>$u) if(strtolower($u->user_email)===strtolower($email)){$found=$id;break;}
 if(!$found) wp_send_json_error(['message'=>'Diese E-Mail-Adresse ist nicht als Jurymitglied hinterlegt.']); $ticket=scp_final_jury_login_ticket($sid,$found); $url=add_query_arg(['scp_final_screen'=>'jury','scp_final_contest_id'=>(int)$s->contest_id,'sid'=>$sid,'jt'=>$jt,'ju'=>$found,'ticket'=>$ticket],home_url('/')); scp_final_log($sid,'jury_login',['jury_user'=>$found]); wp_send_json_success(['url'=>$url]);
}
add_action('wp_ajax_scp_final_jury_save','scp_final_jury_save'); add_action('wp_ajax_nopriv_scp_final_jury_save','scp_final_jury_save');
function scp_final_jury_save(){
 global $wpdb; $sid=(int)($_POST['sid']??0); $ju=(int)($_POST['ju']??0); $aid=(int)($_POST['artist']??0); $ticket=sanitize_text_field($_POST['ticket']??'');
 $s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE id=%d",$sid)); if(!$s||$s->jury_status!=='running') wp_send_json_error(['message'=>'Jury-Voting ist geschlossen.']); if(!scp_final_jury_login_valid($sid,$ju,$ticket)) wp_send_json_error(['message'=>'Jury-Sitzung abgelaufen. Bitte QR-Code erneut scannen.']);
 $members=scp_final_jury_members($s->contest_id,$s); if(!isset($members[$ju])) wp_send_json_error(['message'=>'Kein Jury-Zugang.']); $okArtist=false; foreach(scp_finalists($s) as $a) if((int)$a->id===$aid){$okArtist=true;break;} if(!$okArtist) wp_send_json_error(['message'=>'Finalist ungültig.']);
 $keys=['song_arrangement','musical','vocals','performance','overall']; $v=[]; foreach($keys as $k){$v[$k]=isset($_POST[$k])?(int)$_POST[$k]:-1;if($v[$k]<0||$v[$k]>10) wp_send_json_error(['message'=>'Alle Kriterien müssen mit 0–10 Punkten bewertet werden.']);}
 $weighted=scp_final_jury_weighted($v['song_arrangement'],$v['musical'],$v['vocals'],$v['performance'],$v['overall']); $table=$wpdb->prefix.'scp_final_jury_scores'; $id=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE session_id=%d AND artist_id=%d AND jury_user=%d",$sid,$aid,$ju));
 $data=array_merge(['session_id'=>$sid,'contest_id'=>$s->contest_id,'artist_id'=>$aid,'jury_user'=>$ju],$v,['weighted_score'=>$weighted,'updated_at'=>current_time('mysql')]); if($id)$ok=$wpdb->update($table,$data,['id'=>$id]);else{$data['created_at']=current_time('mysql');$ok=$wpdb->insert($table,$data);} if($ok===false) wp_send_json_error(['message'=>'Bewertung konnte nicht gespeichert werden.']); scp_final_log($sid,'jury_score_saved',['jury_user'=>$ju,'artist_id'=>$aid]); wp_send_json_success(['message'=>'Bewertung gespeichert.']);
}

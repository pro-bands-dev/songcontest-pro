<?php
if (!defined('ABSPATH')) exit;
/* Isolated, read-only, session-bound display devices. No changes to voting or jury logic. */
function scp_fd_key($cid){return 'scp_fd_devices_'.absint($cid);}
function scp_fd_devices($cid){$v=get_option(scp_fd_key($cid),[]);return is_array($v)?$v:[];}
function scp_fd_pair_key($cid){return 'scp_fd_pair_'.absint($cid);}
function scp_fd_link($screen,$cid,$extra=[]){return add_query_arg(array_merge(['scp_final_screen'=>$screen,'scp_final_contest_id'=>(int)$cid],$extra),home_url('/'));}
function scp_fd_check($cid,$sid,$token){
 $token=(string)$token;if(strlen($token)!==64||!ctype_xdigit($token))return false;
 $devices=scp_fd_devices($cid);$key=hash('sha256',$token);
 return isset($devices[$key])&&(int)$devices[$key]['sid']===(int)$sid && (int)$devices[$key]['expires']>time();
}
function scp_fd_prune($cid){$v=scp_fd_devices($cid);$v=array_filter($v,function($d){return (int)($d['expires']??0)>time();});update_option(scp_fd_key($cid),$v,false);return $v;}
function scp_final_device_admin_panel($cid,$s,$nonce){
 $v=scp_fd_prune($cid);$sid=$s?(int)$s->id:0;$v=array_filter($v,function($d)use($sid){return (int)$d['sid']===$sid;});
 $html='<div class="scp-stage-panel scp-fd-admin"><h2>QR-Anzeigegeräte verbinden</h2><p>Alle verbundenen Geräte zeigen gleichzeitig die fünf Finalisten mit Bandfoto und aktuellem Abstimmungs-QR. Kein WordPress-Login erforderlich.</p>';
 if(!$sid){return $html.'<p>Bitte zuerst ein Finale vorbereiten.</p></div>';}
 $html.='<button type="button" class="button button-primary" id="scp-fd-pair">Verbindungs-QR erzeugen (5 Minuten)</button><div id="scp-fd-pair-result"></div><h3>Verbundene Geräte</h3><div id="scp-fd-devices">';
 if(!$v)$html.='<p>Noch keine Geräte verbunden.</p>';
 foreach($v as $hash=>$d){$html.='<p>'.esc_html($d['name']??'Anzeigegerät').' · gültig bis '.esc_html(wp_date('d.m.Y H:i',(int)$d['expires'])).' <button type="button" class="button scp-fd-revoke" data-key="'.esc_attr($hash).'">Trennen</button></p>';}
 $html.='</div></div>';
 $html.='<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>jQuery(function($){var api=ajaxurl,base={nonce:'.wp_json_encode($nonce).',scp_final_contest_id:'.(int)$cid.'};$("#scp-fd-pair").on("click",function(){$.post(api,Object.assign({},base,{action:"scp_fd_admin",op:"pair"}),function(r){if(!r.success){alert(r.data.message||"Fehler");return;}var el=$("#scp-fd-pair-result").empty();el.append($("<p>").text("Mit Tablet-Kamera scannen oder Link am Laptop öffnen (5 Minuten gültig):"));var qr=$("<div>").appendTo(el)[0];new QRCode(qr,{text:r.data.url,width:230,height:230});$("<p>").append($("<a>",{href:r.data.url,target:"_blank",text:"Verbindungslink öffnen"})).appendTo(el);});});$(".scp-fd-revoke").on("click",function(){$.post(api,Object.assign({},base,{action:"scp_fd_admin",op:"revoke",key:$(this).data("key")}),function(r){if(r.success)location.reload();});});});</script>';
 return $html;
}
add_action('wp_ajax_scp_fd_admin','scp_fd_admin');
function scp_fd_admin(){
 check_ajax_referer('scp_final_admin','nonce');if(!current_user_can('manage_options'))wp_send_json_error(['message'=>'Keine Berechtigung'],403);
 $cid=absint($_POST['scp_final_contest_id']??0);global $wpdb;$s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE contest_id=%d ORDER BY id DESC LIMIT 1",$cid));if(!$s)wp_send_json_error(['message'=>'Keine Final-Session vorhanden']);
 $op=sanitize_key($_POST['op']??'');
 if($op==='pair'){$code=bin2hex(random_bytes(24));set_transient(scp_fd_pair_key($cid),['code_hash'=>hash('sha256',$code),'sid'=>(int)$s->id],300);wp_send_json_success(['url'=>scp_fd_link('pair',$cid,['code'=>$code])]);}
 if($op==='revoke'){$key=sanitize_text_field($_POST['key']??'');$v=scp_fd_devices($cid);unset($v[$key]);update_option(scp_fd_key($cid),$v,false);wp_send_json_success(['message'=>'Gerät getrennt']);}
 wp_send_json_error(['message'=>'Unbekannte Aktion']);
}
function scp_final_pair_screen(){
 $cid=absint($_GET['scp_final_contest_id']??0);$code=sanitize_text_field($_GET['code']??'');$pair=get_transient(scp_fd_pair_key($cid));global $wpdb;
 $s=$pair?$wpdb->get_row($wpdb->prepare("SELECT id,contest_id FROM {$wpdb->prefix}scp_final_sessions WHERE id=%d AND contest_id=%d",(int)$pair['sid'],$cid)):null;
 if(!$s||strlen($code)!==48||!hash_equals((string)$pair['code_hash'],hash('sha256',$code))){echo '<main class="scp-final-stage"><h1>Verbindungscode abgelaufen oder ungültig</h1><p>Bitte im Backend einen neuen Verbindungs-QR erzeugen.</p></main>';return;}
 $v=scp_fd_prune($cid);if(count($v)>=20){echo '<main class="scp-final-stage"><h1>Maximal 20 Geräte möglich</h1></main>';return;}
 $token=bin2hex(random_bytes(32));$name='Anzeigegerät '.(count($v)+1);$v[hash('sha256',$token)]=['sid'=>(int)$s->id,'name'=>$name,'expires'=>time()+12*HOUR_IN_SECONDS];update_option(scp_fd_key($cid),$v,false);
 wp_safe_redirect(scp_fd_link('device',$cid,['sid'=>(int)$s->id,'device_token'=>$token]),302);exit;
}
function scp_final_device_screen(){
 $cid=absint($_GET['scp_final_contest_id']??0);$sid=absint($_GET['sid']??0);$token=sanitize_text_field($_GET['device_token']??'');
 if(!scp_fd_check($cid,$sid,$token)){echo '<main class="scp-final-stage"><h1>Gerät nicht verbunden oder Zugang abgelaufen</h1><p>Bitte über den Verbindungs-QR im Backend neu anmelden.</p></main>';return;}
 global $wpdb;$s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE id=%d AND contest_id=%d",$sid,$cid));
 if(!$s){echo '<h1>Final-Session nicht gefunden</h1>';return;}
 $artists=scp_finalists($s);
 echo '<main class="scp-final-stage scp-final-qr-stage scp-fd-display"><div class="scp-final-branding"><img class="scp-final-brand-logo scp-final-brand-contest" src="https://pro-bands.de/wp-content/uploads/2026/10/PRO-BANDS-Song-Contest-2027-–-Logo.png" alt="PRO-BANDS Song Contest"><img class="scp-final-brand-logo scp-final-brand-neuendorf" src="https://pro-bands.de/wp-content/uploads/2026/10/Fransiger-Rocklogo-Rand-mit-Flammen-und-Gitarren-e1791231720717.png" alt="Neuendorf Rockt 2027"></div><div class="scp-final-display-title"><strong>LIVE-FINALE</strong><span>JETZT ABSTIMMEN!</span></div><div id="scp-final-qr-grid"></div><div class="scp-final-callout"><strong>DEINE STIMME ZÄHLT!</strong><span>QR-Code deiner Lieblingsband scannen!</span></div></main>';
 $ajax=admin_url('admin-ajax.php');
 ?>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
 <script>
 const config=<?php echo wp_json_encode(['ajax'=>$ajax,'cid'=>$cid,'sid'=>$sid,'token'=>$token]); ?>;
 function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
 async function refresh(){try{let u=new URL(config.ajax);u.searchParams.set('action','scp_fd_data');u.searchParams.set('cid',config.cid);u.searchParams.set('sid',config.sid);u.searchParams.set('device_token',config.token);let r=await fetch(u,{cache:'no-store'}),j=await r.json();let box=document.getElementById('scp-final-qr-grid');if(!j.success){box.innerHTML='<div class="scp-final-wait">Gerät gesperrt oder Verbindung abgelaufen.</div>';return;}let d=j.data;if(!d.active){box.innerHTML='<div class="scp-final-wait">'+escapeHtml(d.message)+'</div>';return;}box.innerHTML='';d.artists.forEach(a=>{let c=document.createElement('div');c.className='scp-final-qr-card';c.innerHTML='<div class="scp-final-card-head">'+(a.image?'<img class="scp-final-band-thumb" src="'+escapeHtml(a.image)+'" alt="">':'<span class="scp-final-band-placeholder">'+escapeHtml(a.name).charAt(0)+'</span>')+'<h2>'+escapeHtml(a.name)+'</h2></div><div class="qr"></div><div class="scp-vote-cta">JETZT FÜR '+escapeHtml(a.name)+' ABSTIMMEN!</div>';box.appendChild(c);new QRCode(c.querySelector('.qr'),{text:a.url,width:230,height:230,correctLevel:QRCode.CorrectLevel.M});});}catch(e){document.getElementById('scp-final-qr-grid').innerHTML='<div class="scp-final-wait">Verbindung unterbrochen – erneuter Versuch …</div>';}}
 refresh();setInterval(refresh,5000);
 </script><?php
}
add_action('wp_ajax_scp_fd_data','scp_fd_data');add_action('wp_ajax_nopriv_scp_fd_data','scp_fd_data');
function scp_fd_data(){
 $cid=absint($_GET['cid']??0);$sid=absint($_GET['sid']??0);$token=sanitize_text_field($_GET['device_token']??'');if(!scp_fd_check($cid,$sid,$token))wp_send_json_error(['message'=>'Gerät nicht verbunden'],403);
 global $wpdb;$s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE id=%d AND contest_id=%d",$sid,$cid));
 if(!$s)wp_send_json_error(['message'=>'Session ungültig'],404);
 if($s->status!=='running')wp_send_json_success(['active'=>false,'message'=>in_array($s->status,['stopped','revealed'],true)?'DIE ABSTIMMUNG IST BEENDET':'DAS PUBLIKUMSVOTING IST NOCH NICHT GESTARTET']);
 $out=[];foreach(scp_finalists($s) as $a){$url=scp_fd_link('vote',$cid,['sid'=>$sid,'artist'=>(int)$a->id,'token'=>scp_final_token($sid,(int)$a->id)]);$out[]=['name'=>$a->name,'image'=>scp_image_url((string)($a->image??''),'250'),'url'=>$url];}
 wp_send_json_success(['active'=>true,'artists'=>$out]);
}

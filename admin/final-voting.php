<?php
if (!defined('ABSPATH')) exit;
function scp_final_voting_admin_page(){
 if(!current_user_can('manage_options')) return; global $wpdb;
 $cid=scp_final_contest_id(); $s=scp_final_session(); $scores=$s?scp_final_scores($s->id):[]; $nonce=wp_create_nonce('scp_final_admin');
 $progress=$s?scp_final_jury_progress($s):[];
 $complete=$s && count($progress)>0 && !array_filter($progress,function($r){return !$r['complete'];});
 $done_count=0; foreach($progress as $r){ if(!empty($r['complete'])) $done_count++; }
 $jury_total=count($progress);
 $available_jury=scp_final_available_jury_members();
 $selected_jury_ids=scp_final_selected_jury_ids(scp_final_contest_id());
 $stage=1;
 if($s){
   if($s->status==='revealed') $stage=5;
   elseif($s->status==='stopped') $stage=4;
   elseif($s->status==='running') $stage=3;
   elseif($s->jury_status==='running' || $s->status==='jury_running') $stage=2;
   elseif($s->jury_status==='closed_final' || $s->status==='jury_closed') $stage=3;
 }
 $status_map=[
   'prepared'=>'Finale vorbereitet – Jury-Voting kann freigegeben werden',
   'jury_running'=>'Jury-Voting läuft',
   'jury_closed'=>'Jurywertung abgeschlossen – Publikums-Voting kann gestartet werden',
   'running'=>'Publikums-Voting läuft',
   'stopped'=>'Voting beendet – Ergebnis ist eingefroren',
   'revealed'=>'Finale abgeschlossen – Gewinner aufgedeckt'
 ];
 $status_text=$s?($status_map[$s->status]??'Finale vorbereitet'):'Noch nicht vorbereitet';
 echo '<div class="wrap scp-stage-control"><h1>Final-Voting – Bühnensteuerung</h1>';
 $contests=$wpdb->get_results("SELECT id,name FROM {$wpdb->prefix}scp_contests ORDER BY id DESC");
 echo '<form method="get" style="margin:16px 0;padding:16px;background:#fff;border:1px solid #ccd0d4"><input type="hidden" name="page" value="'.esc_attr(sanitize_key($_GET['page']??'')).'"><label for="scp-final-contest"><strong>Contest auswählen: </strong></label><select id="scp-final-contest" name="scp_final_contest_id" onchange="this.form.submit()">';
 foreach($contests as $contest) echo '<option value="'.(int)$contest->id.'"'.selected($cid,(int)$contest->id,false).'>#'.(int)$contest->id.' – '.esc_html($contest->name).'</option>';
 echo '</select> <noscript><button class="button">Auswählen</button></noscript><p style="margin-bottom:0">Alle Aktionen und Anzeigen dieser Steuerung beziehen sich ausschließlich auf den gewählten Contest.</p></form>';
 if($s&&!empty($s->is_test)) echo '<div class="scp-stage-mode is-test"><strong>TESTMODUS</strong><span>Keine echten Finalisten, Stimmen oder Ergebnisse</span></div>';
 else echo '<div class="scp-stage-mode"><strong>LIVE-FINALE</strong><span>Produktivbetrieb</span></div>';
 echo '<div class="scp-stage-steps">';
 foreach([1=>'Finale vorbereiten',2=>'Jury-Voting',3=>'Publikums-Voting',4=>'Voting beendet',5=>'Ergebnis aufdecken'] as $n=>$label){
   $cl=$n<$stage?'done':($n===$stage?'active':'future');
   echo '<div class="scp-stage-step '.$cl.'"><span>'.$n.'</span><strong>'.esc_html($label).'</strong></div>';
 }
 echo '</div>';
 echo '<div class="scp-stage-panel scp-stage-status"><div><span class="scp-stage-eyebrow">Aktueller Status</span><strong>'.esc_html($status_text).'</strong></div><div class="scp-final-admin-actions">';
 if(!$s || in_array($s->status,['stopped','revealed'],true)){
   echo '<button class="button button-primary button-hero scp-fa" data-action="start">Finale vorbereiten</button> ';
   echo '<button class="button button-hero scp-fa" data-action="test_start">TESTMODUS starten</button> ';
 }
 if($s && $s->status==='running'){
   echo '<button class="button button-primary button-hero scp-fa" data-action="stop">Voting BEENDEN</button> ';
   echo '<button class="button scp-fa" data-action="show">Zwischenstand einblenden</button> <button class="button scp-fa" data-action="hide">Zwischenstand ausblenden</button> ';
   echo '<a class="button" target="_blank" href="'.esc_url(add_query_arg(['scp_final_screen'=>'qr','scp_final_contest_id'=>$cid],home_url('/'))).'">Publikums-QR öffnen</a> ';
 } else {
   echo '<button class="button" disabled title="Erst nach abgeschlossener Jurywertung verfügbar">Publikums-QR öffnen</button> ';
 }
 echo '<a class="button" target="_blank" href="'.esc_url(add_query_arg(['scp_final_screen'=>'live','scp_final_contest_id'=>$cid],home_url('/'))).'">Beamer / Live-Charts öffnen</a> ';
 echo scp_final_device_admin_panel($cid,$s,$nonce);
 if($s&&!empty($s->is_test)) echo '<button class="button scp-fa scp-reset-test" data-action="test_reset">Testlauf vollständig zurücksetzen</button>';
 echo '</div></div>';

 // Final-Jury selection is intentionally rendered only on this admin page.
 // It has no bootstrap hook, so a role/query problem can never take down WordPress globally.
 if(!$s){
   echo '<div class="scp-stage-panel scp-final-jury-selection"><span class="scp-stage-eyebrow">Final-Jury</span><h2>Jurymitglieder für das Live-Finale</h2>';
   echo '<p>WordPress-Benutzer mit einer Jury-Rolle werden automatisch erkannt. Die Auswahl betrifft nur das Live-Finale; die Jury-Vorauswahl bleibt unverändert.</p>';
   if(!$available_jury){
     echo '<p><strong>Keine WordPress-Benutzer mit Jury-Rolle gefunden.</strong></p>';
   } else {
     echo '<table class="widefat striped"><thead><tr><th style="width:70px">Finale</th><th>Name</th><th>E-Mail</th><th>Benutzer</th></tr></thead><tbody>';
     foreach($available_jury as $uid=>$u){
       $checked=in_array((int)$uid,$selected_jury_ids,true)?' checked':'';
       echo '<tr><td><input type="checkbox" class="scp-final-jury-check" value="'.intval($uid).'"'.$checked.'></td><td><strong>'.esc_html($u->display_name).'</strong></td><td>'.esc_html($u->user_email).'</td><td>'.esc_html($u->user_login).'</td></tr>';
     }
     echo '</tbody></table><p><button class="button button-primary scp-save-final-jury">Final-Jury speichern</button> <span id="scp-final-jury-msg"></span></p>';
   }
   echo '</div>';
 }

 if($s){
   echo '<div class="scp-stage-panel scp-jury-admin-panel"><div class="scp-panel-heading"><div><span class="scp-stage-eyebrow">Schritt 2</span><h2>Jury-Finalvoting</h2></div>';
   if($s->jury_status==='running') echo '<span class="scp-status-badge running">Jury-Voting läuft</span>';
   elseif($s->jury_status==='closed_final') echo '<span class="scp-status-badge done">Jurywertung abgeschlossen</span>';
   else echo '<span class="scp-status-badge">Noch nicht freigegeben</span>';
   echo '</div>';
   if($jury_total){
     $pct=(int)round(($done_count/$jury_total)*100);
     echo '<div class="scp-jury-overview"><div><strong>'.$done_count.' / '.$jury_total.'</strong><span>Juroren vollständig</span></div><div class="scp-admin-progress"><i style="width:'.$pct.'%"></i></div></div>';
   }
   echo '<div class="scp-jury-actions">';
   if(in_array($s->status,['prepared','jury_running','jury_closed'],true)){
     if($s->jury_status!=='running' && $s->jury_status!=='closed_final') echo '<button class="button button-primary button-hero scp-fa" data-action="jury_start">Jury-Voting FREIGEBEN</button> ';
     if($s->jury_status==='running'){
       if($complete) echo '<button class="button button-primary scp-fa" data-action="jury_stop">Jury-Voting BEENDEN</button> ';
       else echo '<button class="button button-primary" disabled title="Erst möglich, wenn alle Juroren 5/5 Finalisten bewertet haben">Jury-Voting BEENDEN</button> ';
     }
     echo '<a class="button" target="_blank" href="'.esc_url(add_query_arg(['scp_final_screen'=>'jury','scp_final_contest_id'=>$cid],home_url('/'))).'">Jury-QR anzeigen</a> ';
     if($s->jury_status==='closed_final' && $complete) echo '<button class="button button-primary button-hero scp-fa" data-action="public_start">Publikums-Voting STARTEN</button>';
   }
   echo '</div>';
   echo '<table class="widefat striped scp-jury-progress"><thead><tr><th>Jurymitglied</th><th>Fortschritt</th><th>Status</th></tr></thead><tbody>';
   if(!$progress) echo '<tr><td colspan="3">Keine Jurymitglieder aus Vorauswahl/Jurywertung gefunden.</td></tr>';
   foreach($progress as $r){
     $is_complete=!empty($r['complete']);
     echo '<tr><td><strong>'.esc_html($r['name']).'</strong></td><td>'.($is_complete?'5/5':intval($r['count']).'/'.intval($r['need'])).'</td><td><span class="scp-mini-status '.($is_complete?'done':'waiting').'">'.($is_complete?'Vollständig ✓':'Bewertung offen').'</span></td></tr>';
   }
   echo '</tbody></table></div>';
 }

 if($s && $s->status==='stopped'){
   echo '<div class="scp-stage-panel"><span class="scp-stage-eyebrow">Schritt 5</span><h2>Platzierungen aufdecken</h2><p>Die Wertung ist eingefroren. Bitte ausschließlich in der Reihenfolge Platz 5 → 1 aufdecken.</p>';
   for($p=5;$p>=1;$p--) echo '<button class="button '.($p===1?'button-primary':'').' scp-fa" data-action="reveal" data-place="'.$p.'">Platz '.$p.($p===1?' / GEWINNER':'').' aufdecken</button> ';
   echo '</div>';
 }
 if($scores){
   echo '<div class="scp-stage-panel scp-internal-check"><span class="scp-stage-eyebrow">Nur Bühnenleitung</span><h2>Interne Kontrolle</h2><p>Diese Werte erscheinen nicht auf der öffentlichen Anzeige.</p><table class="widefat"><thead><tr><th>Platz</th><th>Finalist</th><th>Gesamtwertung</th><th>Publikumsstimmen</th></tr></thead><tbody>';
   foreach($scores as $r) echo '<tr><td>'.$r['position'].'</td><td>'.esc_html($r['name']).'</td><td>'.number_format_i18n($r['score'],1).'%</td><td>'.$r['public_votes'].'</td></tr>';
   echo '</tbody></table></div>';
 }
 echo '<p id="scp-final-admin-msg"></p></div>';
 ?>
 <script>jQuery(function($){$('.scp-fa').on('click',function(){var b=$(this);if(b.prop('disabled'))return;if(b.data('action')==='stop'&&!confirm('Voting wirklich beenden und Ergebnis einfrieren?'))return;$.post(ajaxurl,{action:'scp_final_admin_action',nonce:'<?php echo esc_js($nonce); ?>',scp_final_contest_id:<?php echo (int)$cid; ?>,do:b.data('action'),place:b.data('place')||0},function(r){$('#scp-final-admin-msg').text(r.data&&r.data.message?r.data.message:(r.data||''));if(r.success)setTimeout(function(){location.reload()},400);});});$('.scp-save-final-jury').on('click',function(){var ids=[];$('.scp-final-jury-check:checked').each(function(){ids.push(parseInt(this.value,10));});$.post(ajaxurl,{action:'scp_final_admin_action',nonce:'<?php echo esc_js($nonce); ?>',scp_final_contest_id:<?php echo (int)$cid; ?>,do:'save_jury',jury_ids:ids},function(r){$('#scp-final-jury-msg').text(r.data&&r.data.message?r.data.message:(r.data||''));});});});</script>
 <?php
}

add_action('wp_ajax_scp_final_admin_action','scp_final_admin_action');
function scp_final_admin_action(){
 check_ajax_referer('scp_final_admin','nonce'); if(!current_user_can('manage_options')) wp_send_json_error(['message'=>'Keine Berechtigung'],403);
 global $wpdb; $do=sanitize_key($_POST['do']??''); $s=scp_final_session(); $cid=scp_final_contest_id();
 if($do==='save_jury'){
   if($s) wp_send_json_error(['message'=>'Final-Jury kann nur geändert werden, solange kein Finale vorbereitet ist.']);
   $available=scp_final_available_jury_members(); $raw=isset($_POST['jury_ids'])?(array)$_POST['jury_ids']:[];
   $ids=[]; foreach($raw as $id){ $id=absint($id); if($id && isset($available[$id])) $ids[]=$id; }
   $ids=array_values(array_unique($ids)); update_option(scp_final_jury_option_key($cid),$ids,false);
   wp_send_json_success(['message'=>count($ids).' Jurymitglied'.(count($ids)===1?'':'er').' für das Live-Finale gespeichert.']);
 }
 if($do==='test_start'){
   if($s && $s->status==='running') wp_send_json_error(['message'=>'Es läuft bereits ein Final-Voting.']);
   $ok=$wpdb->insert($wpdb->prefix.'scp_final_sessions',['contest_id'=>$cid,'status'=>'jury_running','jury_status'=>'running','show_standings'=>0,'reveal_place'=>0,'jury_started_at'=>current_time('mysql'),'jury_started_by'=>get_current_user_id(),'started_by'=>get_current_user_id(),'is_test'=>1,'created_at'=>current_time('mysql')]);
   if($ok===false || !$wpdb->insert_id) wp_send_json_error(['message'=>'Testmodus konnte nicht gestartet werden: '.($wpdb->last_error?:'Datenbankeintrag fehlgeschlagen.')]);
   $id=(int)$wpdb->insert_id; scp_final_log($id,'test_started'); wp_send_json_success(['message'=>'Isolierter Testmodus läuft. Zuerst Jurywertung testen.']);
 }
 if($do==='test_reset'){
   if(!$s || empty($s->is_test)) wp_send_json_error(['message'=>'Keine Test-Session vorhanden.']);
   $wpdb->delete($wpdb->prefix.'scp_final_votes',['session_id'=>$s->id]); $wpdb->delete($wpdb->prefix.'scp_final_jury_scores',['session_id'=>$s->id]); $wpdb->delete($wpdb->prefix.'scp_final_events',['session_id'=>$s->id]); $wpdb->delete($wpdb->prefix.'scp_final_sessions',['id'=>$s->id]);
   wp_send_json_success(['message'=>'Testlauf vollständig entfernt.']);
 }
 if($do==='start'){
   $finalists=scp_final_real_finalists(); if(count($finalists)!==5) wp_send_json_error(['message'=>'Es müssen genau 5 Finalisten mit Status „finale“ vorhanden sein.']);
   $members=scp_final_jury_members($cid); if(!$members) wp_send_json_error(['message'=>'Keine Jurymitglieder für das Live-Finale aktiviert.']);
   $ok=$wpdb->insert($wpdb->prefix.'scp_final_sessions',['contest_id'=>$cid,'status'=>'prepared','jury_status'=>'closed','show_standings'=>0,'reveal_place'=>0,'started_by'=>get_current_user_id(),'created_at'=>current_time('mysql')]);
   if($ok===false || !$wpdb->insert_id) wp_send_json_error(['message'=>'Finale konnte nicht vorbereitet werden: '.($wpdb->last_error?:'Datenbankeintrag fehlgeschlagen.')]);
   $id=(int)$wpdb->insert_id; scp_final_log($id,'prepared'); wp_send_json_success(['message'=>'Finale vorbereitet. Jetzt Jury-Voting freigeben.']);
 }
 if(!$s) wp_send_json_error(['message'=>'Keine Final-Voting-Session vorhanden.']);
 if($do==='jury_start' && in_array($s->status,['prepared','jury_closed'],true)){
   $wpdb->update($wpdb->prefix.'scp_final_sessions',['status'=>'jury_running','jury_status'=>'running','jury_started_at'=>current_time('mysql'),'jury_started_by'=>get_current_user_id(),'jury_snapshot'=>null],['id'=>$s->id]); scp_final_log($s->id,'jury_started'); wp_send_json_success(['message'=>'Jury-Voting ist freigegeben.']);
 }
 if($do==='jury_stop' && $s->jury_status==='running'){
   $progress=scp_final_jury_progress($s); if(!$progress || array_filter($progress,function($r){return !$r['complete'];})) wp_send_json_error(['message'=>'Jury-Voting kann erst beendet werden, wenn alle Jurymitglieder alle 5 Finalisten bewertet haben.']);
   $snapshot=scp_final_jury_snapshot($s); $wpdb->update($wpdb->prefix.'scp_final_sessions',['status'=>'jury_closed','jury_status'=>'closed_final','jury_stopped_at'=>current_time('mysql'),'jury_stopped_by'=>get_current_user_id(),'jury_snapshot'=>wp_json_encode($snapshot)],['id'=>$s->id]); scp_final_log($s->id,'jury_stopped'); wp_send_json_success(['message'=>'Jurywertung vollständig und eingefroren.']);
 }
 if($do==='public_start' && $s->status==='jury_closed' && $s->jury_status==='closed_final'){
   $wpdb->update($wpdb->prefix.'scp_final_sessions',['status'=>'running','started_at'=>current_time('mysql'),'started_by'=>get_current_user_id()],['id'=>$s->id]); scp_final_log($s->id,'public_started'); wp_send_json_success(['message'=>'Publikums-Voting läuft.']);
 }
 if($do==='stop' && $s->status==='running'){
   $snapshot=wp_json_encode(scp_final_scores($s->id));
   $wpdb->update($wpdb->prefix.'scp_final_sessions',['status'=>'stopped','show_standings'=>0,'stopped_at'=>current_time('mysql'),'stopped_by'=>get_current_user_id(),'result_snapshot'=>$snapshot],['id'=>$s->id]); scp_final_log($s->id,'stopped'); wp_send_json_success(['message'=>'Voting beendet und Ergebnis eingefroren.']);
 }
 if(in_array($do,['show','hide'],true) && $s->status==='running'){
   $show=$do==='show'?1:0; $wpdb->update($wpdb->prefix.'scp_final_sessions',['show_standings'=>$show],['id'=>$s->id]); scp_final_log($s->id,$show?'standings_shown':'standings_hidden'); wp_send_json_success(['message'=>$show?'Zwischenstand sichtbar.':'Zwischenstand verborgen.']);
 }
 if($do==='reveal' && $s->status==='stopped'){
   $p=(int)($_POST['place']??0); if($p<1||$p>5) wp_send_json_error(['message'=>'Ungültiger Platz.']);
   $current=(int)$s->reveal_place; $expected=$current===0?5:$current-1; if($p!==$expected) wp_send_json_error(['message'=>'Bitte in Reihenfolge Platz 5 → 1 aufdecken.']);
   $status=$p===1?'revealed':'stopped'; $wpdb->update($wpdb->prefix.'scp_final_sessions',['reveal_place'=>$p,'status'=>$status],['id'=>$s->id]); scp_final_log($s->id,'revealed',['place'=>$p]); wp_send_json_success(['message'=>'Platz '.$p.' aufgedeckt.']);
 }
 wp_send_json_error(['message'=>'Aktion in diesem Status nicht möglich.']);
}

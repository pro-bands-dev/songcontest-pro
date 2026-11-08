<?php
if (!defined('ABSPATH')) exit;

function scp_final_contest_id(){
    // Explicit contest selection is scoped to this request; never modify the global active contest.
    $requested=0;
    // Public live-chart AJAX requests must use their explicit contest ID,
    // regardless of WordPress is_admin() or the viewer's login state.
    if(wp_doing_ajax() && sanitize_key($_REQUEST['action'] ?? '')==='scp_final_live_data'){
        $requested=absint($_GET['scp_final_contest_id'] ?? 0);
    } elseif(isset($_GET['scp_final_screen']) && in_array(sanitize_key($_GET['scp_final_screen']),['qr','live','jury'],true)){
        $requested=absint($_GET['scp_final_contest_id'] ?? 0);
    } elseif(is_admin() && current_user_can('manage_options')){
        $requested=absint($_POST['scp_final_contest_id'] ?? $_GET['scp_final_contest_id'] ?? 0);
    }
    if($requested){
        global $wpdb;
        if($wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}scp_contests WHERE id=%d",$requested))) return $requested;
    }
    return function_exists('scp_get_current_contest_id') ? (int)scp_get_current_contest_id() : 1;
}
function scp_final_session(){
    global $wpdb; $cid=scp_final_contest_id();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE contest_id=%d ORDER BY id DESC LIMIT 1",$cid));
}
function scp_final_test_finalists(){
    $names=['Test-Finalist A','Test-Finalist B','Test-Finalist C','Test-Finalist D','Test-Finalist E'];
    $out=[]; foreach($names as $i=>$name) $out[]=(object)['id'=>900001+$i,'name'=>$name,'title'=>'Testtitel '.($i+1),'image'=>''];
    return $out;
}
function scp_final_real_finalists(){
    global $wpdb; $cid=scp_final_contest_id();
    return $wpdb->get_results($wpdb->prepare("SELECT id,name,title,image FROM {$wpdb->prefix}scp_artists WHERE contest_id=%d AND status='finale' ORDER BY name",$cid));
}
function scp_finalists($session=null){
    global $wpdb;
    if($session===null) $session=scp_final_session();
    if($session && !empty($session->is_test)) return scp_final_test_finalists();
    $cid=$session ? (int)$session->contest_id : scp_final_contest_id();
    return $wpdb->get_results($wpdb->prepare("SELECT id,name,title,image FROM {$wpdb->prefix}scp_artists WHERE contest_id=%d AND status='finale' ORDER BY name",$cid));
}
function scp_final_artist($artist_id,$session=null){
    global $wpdb; if($session===null) $session=scp_final_session();
    if($session && !empty($session->is_test)){ foreach(scp_final_test_finalists() as $a) if((int)$a->id===(int)$artist_id) return $a; return null; }
    return $wpdb->get_row($wpdb->prepare("SELECT id,name,title,image FROM {$wpdb->prefix}scp_artists WHERE id=%d",$artist_id));
}

function scp_final_token($session_id,$artist_id,$slot=null){
    // QR generation changes every 30 seconds.
    if($slot===null) $slot=(int)floor(time()/30);
    $payload=(int)$session_id.'|'.(int)$artist_id.'|'.(int)$slot;
    return $slot.'.'.hash_hmac('sha256',$payload,wp_salt('auth'));
}
function scp_final_token_valid($session_id,$artist_id,$token){
    $p=explode('.',(string)$token,2); if(count($p)!==2) return false;
    $slot=(int)$p[0]; $now=time(); $current=(int)floor($now/30);
    // Current generation is valid. The immediately previous generation gets
    // at most a five-second grace period for scans right on a QR transition.
    $allowed=($slot===$current) || ($slot===($current-1) && ($now%30)<5);
    if(!$allowed) return false;
    return hash_equals(scp_final_token($session_id,$artist_id,$slot),$token);
}
/**
 * Create a single-use voting ticket after a valid QR scan.
 * The ticket is bound to session + finalist, expires after two minutes and is
 * stored server-side so it can be consumed after one successful vote.
 */
function scp_final_vote_ticket($session_id,$artist_id,$expires=null){
    if($expires===null) $expires=time()+120;
    $nonce=wp_generate_password(24,false,false);
    $payload=(int)$session_id.'|'.(int)$artist_id.'|'.(int)$expires.'|'.$nonce;
    $sig=hash_hmac('sha256',$payload,wp_salt('auth'));
    $ticket=(int)$expires.'.'.$nonce.'.'.$sig;
    set_transient('scp_fvt_'.hash('sha256',$ticket),[(int)$session_id,(int)$artist_id,(int)$expires],130);
    return $ticket;
}
function scp_final_vote_ticket_valid($session_id,$artist_id,$ticket){
    $p=explode('.',(string)$ticket,3); if(count($p)!==3) return false;
    $expires=(int)$p[0]; $nonce=$p[1]; $sig=$p[2];
    if($expires<time() || $expires>time()+125) return false;
    $payload=(int)$session_id.'|'.(int)$artist_id.'|'.$expires.'|'.$nonce;
    $expected=hash_hmac('sha256',$payload,wp_salt('auth'));
    if(!hash_equals($expected,$sig)) return false;
    $stored=get_transient('scp_fvt_'.hash('sha256',$ticket));
    return is_array($stored) && (int)$stored[0]===(int)$session_id && (int)$stored[1]===(int)$artist_id && (int)$stored[2]===$expires;
}
function scp_final_vote_ticket_consume($ticket){
    delete_transient('scp_fvt_'.hash('sha256',(string)$ticket));
}

function scp_final_scores($session_id){
    global $wpdb; $cid=scp_final_contest_id(); $session=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}scp_final_sessions WHERE id=%d",$session_id)); $artists=scp_finalists($session);
    $pub=[];$jury=[];$pub_total=0;$jury_total=0;
    foreach($artists as $a){
        $p=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}scp_final_votes WHERE session_id=%d AND artist_id=%d",$session_id,$a->id));
        $snap=!empty($session->jury_snapshot)?json_decode($session->jury_snapshot,true):[]; $j=(float)($snap[$a->id]??0);
        $pub[$a->id]=$p; $jury[$a->id]=$j; $pub_total+=$p; $jury_total+=$j;
    }
    $out=[];
    foreach($artists as $a){
        $pp=$pub_total ? ($pub[$a->id]/$pub_total*100) : 0;
        $jp=$jury_total ? ($jury[$a->id]/$jury_total*100) : 0;
        $out[]=['artist_id'=>(int)$a->id,'name'=>$a->name,'title'=>$a->title,'image'=>$a->image,'public_votes'=>$pub[$a->id],'public_pct'=>$pp,'jury_pct'=>$jp,'score'=>$jp*.30+$pp*.70];
    }
    usort($out,function($a,$b){ return $b['score']<=>$a['score'] ?: strcmp($a['name'],$b['name']); });
    foreach($out as $i=>&$r) $r['position']=$i+1;
    return $out;
}
function scp_final_log($session_id,$event,$data=[]){
    global $wpdb; $wpdb->insert($wpdb->prefix.'scp_final_events',['session_id'=>$session_id,'event_type'=>$event,'event_data'=>wp_json_encode($data),'user_id'=>get_current_user_id(),'created_at'=>current_time('mysql')]);
}

/* dev7.7.0 – Live-Jury: Gesamtauftritt je Finalist */
function scp_final_available_jury_members(){
    // Only called from Final-Voting screens/actions, never during plugin bootstrap.
    if(!function_exists('wp_roles') || !function_exists('get_users')) return [];
    $roles=wp_roles(); $jury_roles=[];
    if($roles && is_array($roles->roles)){
        foreach($roles->roles as $slug=>$data){
            $name=(string)($data['name']??'');
            if(stripos((string)$slug,'jury')!==false || stripos($name,'jury')!==false) $jury_roles[]=(string)$slug;
        }
    }
    if(!$jury_roles) return [];
    $users=get_users(['role__in'=>array_values(array_unique($jury_roles)),'orderby'=>'display_name','order'=>'ASC']);
    $out=[]; foreach($users as $u){ if($u instanceof WP_User && is_email($u->user_email)) $out[(int)$u->ID]=$u; }
    return $out;
}
function scp_final_jury_option_key($contest_id){ return 'scp_final_jury_members_'.absint($contest_id); }
function scp_final_selected_jury_ids($contest_id){
    $available=scp_final_available_jury_members();
    $saved=get_option(scp_final_jury_option_key($contest_id),null);
    // First use: all existing Jury-Mitglied users are selected. Afterwards an empty array is a valid deliberate selection.
    if($saved===null) return array_keys($available);
    if(!is_array($saved)) return [];
    return array_values(array_intersect(array_map('intval',$saved),array_keys($available)));
}
function scp_final_jury_members($contest_id,$session=null){
    // Test- und Produktivmodus verwenden dieselbe gespeicherte Final-Jury.
    // Dadurch bildet ein Testlauf den späteren Live-Ablauf realistisch ab und
    // fällt nicht mehr auf den startenden Administrator als Einzeljuror zurück.
    $available=scp_final_available_jury_members(); $ids=scp_final_selected_jury_ids($contest_id); $out=[];
    foreach($ids as $id){ if(isset($available[$id])) $out[$id]=$available[$id]; }
    return $out;
}
function scp_final_jury_token($session_id){
    return hash_hmac('sha256','final-jury|'.(int)$session_id,wp_salt('auth'));
}
function scp_final_jury_login_ticket($session_id,$jury_user){
    $expires=time()+14400; $nonce=wp_generate_password(24,false,false); $payload="$session_id|$jury_user|$expires|$nonce";
    $sig=hash_hmac('sha256',$payload,wp_salt('auth')); $ticket="$expires.$nonce.$sig";
    set_transient('scp_fjt_'.hash('sha256',$ticket),[(int)$session_id,(int)$jury_user,(int)$expires],14500); return $ticket;
}
function scp_final_jury_login_valid($session_id,$jury_user,$ticket){
    $p=explode('.',(string)$ticket,3); if(count($p)!==3) return false; [$expires,$nonce,$sig]=$p; $expires=(int)$expires;
    if($expires<time()) return false; $payload="$session_id|$jury_user|$expires|$nonce";
    if(!hash_equals(hash_hmac('sha256',$payload,wp_salt('auth')),$sig)) return false;
    $v=get_transient('scp_fjt_'.hash('sha256',$ticket)); return is_array($v)&&(int)$v[0]===(int)$session_id&&(int)$v[1]===(int)$jury_user;
}
function scp_final_jury_weighted($a,$m,$v,$p,$o){ return round($a*.20+$m*.20+$v*.20+$p*.25+$o*.15,3); }
function scp_final_jury_progress($session){
    global $wpdb; $members=scp_final_jury_members($session->contest_id,$session); $finalists=scp_finalists($session); $need=count($finalists); $rows=[];
    foreach($members as $id=>$u){ $n=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}scp_final_jury_scores WHERE session_id=%d AND jury_user=%d",$session->id,$id)); $rows[]=['id'=>$id,'name'=>$u->display_name?:$u->user_email,'email'=>$u->user_email,'count'=>$n,'need'=>$need,'complete'=>$need>0&&$n===$need]; }
    return $rows;
}
function scp_final_jury_snapshot($session){
    global $wpdb; $artists=scp_finalists($session); $out=[];
    foreach($artists as $a){ $avg=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(AVG(weighted_score),0) FROM {$wpdb->prefix}scp_final_jury_scores WHERE session_id=%d AND artist_id=%d",$session->id,$a->id)); $out[(int)$a->id]=$avg; }
    return $out;
}

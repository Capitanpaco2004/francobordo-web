<?php
/**
 * indexnow_cron.php (2026-06-15)
 * Notifica a IndexNow (Bing/Copilot, Yandex, Naver, Seznam, Yep) los productos
 * creados/modificados. Lanzar por cron via curl HTTPS (web SAPI -> tep_href_link
 * genera las URLs canonicas SEO). Google NO usa IndexNow (no aplica).
 *
 *   delta (cada hora):  curl -s "https://www.francobordo.com/indexnow_cron.php?token=1c49353074ddb00f55b31ead"
 *   full  (seeding):    curl -s "https://www.francobordo.com/indexnow_cron.php?token=1c49353074ddb00f55b31ead&full=1"
 */

require 'includes/application_top.php';

const INDEXNOW_TOKEN    = '1c49353074ddb00f55b31ead';
const INDEXNOW_KEY      = '7aa0ad29648d79688660ab171091015c';
const INDEXNOW_HOST     = 'www.francobordo.com';
const INDEXNOW_CHUNK    = 9000;
const INDEXNOW_WM_FILE  = '/home/francobordo/logs/indexnow.watermark';
const INDEXNOW_LOG_FILE = '/home/francobordo/logs/indexnow.log';

header('Content-Type: text/plain; charset=utf-8');
if (($_GET['token'] ?? '') !== INDEXNOW_TOKEN) { http_response_code(403); echo "forbidden\n"; exit; }

function inow_log($m){ $l='['.date('Y-m-d H:i:s').'] '.$m."\n"; @file_put_contents(INDEXNOW_LOG_FILE,$l,FILE_APPEND); echo $l; }

function inow_submit(array $urls){
    if (!$urls) return [0,'empty'];
    $payload=json_encode(['host'=>INDEXNOW_HOST,'key'=>INDEXNOW_KEY,'keyLocation'=>'https://'.INDEXNOW_HOST.'/'.INDEXNOW_KEY.'.txt','urlList'=>array_values($urls)]);
    $ch=curl_init('https://api.indexnow.org/indexnow');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json; charset=utf-8'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
    curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch);
    return [$code,$err];
}

$full = (($_GET['full'] ?? '') === '1');

if ($full) {
    $res=tep_db_query("select products_id from ".TABLE_PRODUCTS." where products_status=1 order by products_id");
    $batch=[]; $sent=0; $okall=true;
    while($row=tep_db_fetch_array($res)){
        $batch[]=tep_href_link(FILENAME_PRODUCT_INFO,'products_id='.(int)$row['products_id']);
        if(count($batch)>=INDEXNOW_CHUNK){ list($code,$err)=inow_submit($batch); $sent+=count($batch); inow_log("FULL chunk HTTP $code ".($err?:'')." (acum $sent)"); if($code<200||$code>=300)$okall=false; $batch=[]; }
    }
    if($batch){ list($code,$err)=inow_submit($batch); $sent+=count($batch); inow_log("FULL chunk HTTP $code ".($err?:'')." (acum $sent)"); if($code<200||$code>=300)$okall=false; }
    inow_log("FULL done: $sent URLs ok=".($okall?'si':'NO'));
    exit;
}

// Modo delta
$wm=@file_get_contents(INDEXNOW_WM_FILE); $wm=$wm?trim($wm):date('Y-m-d H:i:s',strtotime('-26 hours'));
$now=date('Y-m-d H:i:s'); $wmEsc=tep_db_input($wm);
$res=tep_db_query("select products_id from ".TABLE_PRODUCTS."
    where products_status=1
      and ( (products_last_modified is not null and products_last_modified >= '$wmEsc') or products_date_added >= '$wmEsc' )
    order by products_id limit ".(INDEXNOW_CHUNK+1));
$urls=[];
while($row=tep_db_fetch_array($res)){ $urls[]=tep_href_link(FILENAME_PRODUCT_INFO,'products_id='.(int)$row['products_id']); }
$total=count($urls);
if($total===0){ inow_log("delta: sin cambios desde $wm"); @file_put_contents(INDEXNOW_WM_FILE,$now); exit; }
$capped=$total>INDEXNOW_CHUNK; if($capped)$urls=array_slice($urls,0,INDEXNOW_CHUNK);
list($code,$err)=inow_submit($urls);
if($code>=200&&$code<300){ inow_log("delta OK HTTP $code: ".count($urls)." URLs (cambios desde $wm)".($capped?" [CAP: lanza &full=1]":"")); @file_put_contents(INDEXNOW_WM_FILE,$now); }
else{ inow_log("delta ERROR HTTP $code $err: NO avanzo watermark"); }

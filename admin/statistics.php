<?php
require_once __DIR__.'/../includes/bootstrap.php'; require_login();
$id=(int)($_GET['id']??0); require_album_access($id);
$s=$pdo->prepare('SELECT title,artist FROM albums WHERE id=?');$s->execute([$id]);$album=$s->fetch();
if(!$album){http_response_code(404);exit(t('text.album.nicht.gefunden'));}
$period=(int)($_GET['days']??30); if(!in_array($period,[1,7,30,0],true))$period=30;
try{$totals=statistics_album_totals($id,$period);}
catch(Throwable $e){$totals=['album_view'=>0,'track_play'=>0,'track_download'=>0,'album_download'=>0];}
$where='s.album_id=?';$args=[$id];if($period>0){$where.=' AND s.event_date>=DATE_SUB(CURDATE(),INTERVAL ? DAY)';$args[]=$period-1;}
try{$q=$pdo->prepare("SELECT t.title,SUM(s.event_count) plays FROM statistics_daily s JOIN tracks t ON t.id=s.track_id WHERE {$where} AND s.event_type='track_play' AND s.track_id>0 GROUP BY s.track_id,t.title ORDER BY plays DESC,t.title LIMIT 10");$q->execute($args);$top=$q->fetchAll();}catch(Throwable $e){$top=[];}
$chart=[];
try{
    $chartWhere='album_id=?';$chartArgs=[$id];
    if($period>0){$chartWhere.=' AND event_date>=DATE_SUB(CURDATE(),INTERVAL ? DAY)';$chartArgs[]=$period-1;}
    $q=$pdo->prepare("SELECT event_date,
        SUM(CASE WHEN event_type='album_view' THEN event_count ELSE 0 END) views,
        SUM(CASE WHEN event_type='track_play' THEN event_count ELSE 0 END) plays,
        SUM(CASE WHEN event_type IN('track_download','album_download') THEN event_count ELSE 0 END) downloads
        FROM statistics_daily WHERE {$chartWhere} GROUP BY event_date ORDER BY event_date");
    $q->execute($chartArgs);
    $rows=$q->fetchAll();
    $byDate=[];foreach($rows as $r)$byDate[$r['event_date']]=$r;
    if($period>0){
        $days=max(1,$period);$start=(new DateTimeImmutable('today'))->modify('-'.($days-1).' days');
        for($i=0;$i<$days;$i++){
            $date=$start->modify("+{$i} days");$key=$date->format('Y-m-d');$r=$byDate[$key]??[];
            $chart[]=['label'=>$date->format('d.m.'),'views'=>(int)($r['views']??0),'plays'=>(int)($r['plays']??0),'downloads'=>(int)($r['downloads']??0)];
        }
    }else{
        foreach($rows as $r){$date=new DateTimeImmutable($r['event_date']);$chart[]=['label'=>$date->format('d.m.y'),'views'=>(int)$r['views'],'plays'=>(int)$r['plays'],'downloads'=>(int)$r['downloads']];}
    }
}catch(Throwable $e){$chart=[];}


$shareStats=[];
try{
 $q=$pdo->prepare("SELECT sh.id,sh.label,sh.token,
 SUM(CASE WHEN s.event_type='album_view' THEN s.event_count ELSE 0 END) views,
 SUM(CASE WHEN s.event_type='track_play' THEN s.event_count ELSE 0 END) plays,
 SUM(CASE WHEN s.event_type IN('track_download','album_download') THEN s.event_count ELSE 0 END) downloads
 FROM shares sh LEFT JOIN statistics_daily s ON s.share_id=sh.id".($period>0?" AND s.event_date>=DATE_SUB(CURDATE(),INTERVAL ".(int)($period-1)." DAY)":"")."
 WHERE sh.album_id=? GROUP BY sh.id,sh.label,sh.token ORDER BY views DESC,plays DESC");
 $q->execute([$id]);$shareStats=$q->fetchAll();
}catch(Throwable $e){}

render_header(t('stats.title'),true); ?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1"><?=e(t('stats.title'))?></h1><p class="text-body-secondary mb-0"><?=e($album['title'])?> · <?=e($album['artist'])?></p></div><a class="btn btn-outline-secondary" href="album_edit.php?id=<?=$id?>"><?=e(t('text.zuruck'))?></a></div>
<?php if(!statistics_enabled()):?><div class="alert alert-info"><?=e(t('stats.disabled_notice'))?></div><?php endif?>
<div class="btn-group mb-4"><?php foreach([1=>'stats.today',7=>'stats.7days',30=>'stats.30days',0=>'stats.all'] as $d=>$k):?><a class="btn btn-sm <?=$period===$d?'btn-primary':'btn-outline-primary'?>" href="?id=<?=$id?>&days=<?=$d?>"><?=e(t($k))?></a><?php endforeach?></div>
<div class="row g-3 mb-4">
<?php foreach([['album_view','stats.views','bi-eye'],['track_play','stats.plays','bi-play-circle'],['track_download','stats.track_downloads','bi-download'],['album_download','stats.album_downloads','bi-file-earmark-arrow-down']] as [$key,$label,$icon]):?>
<div class="col-6 col-lg-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-body-secondary small"><i class="bi <?=$icon?> me-1"></i><?=e(t($label))?></div><div class="display-6 fw-semibold mt-2"><?=number_format($totals[$key],0,',','.')?></div></div></div></div>
<?php endforeach?></div>

<div class="card shadow-sm mb-4"><div class="card-body">
<h2 class="h5 mb-1"><?=e(t('stats.activity'))?></h2>
<p class="text-body-secondary small mb-3"><?=e(t('stats.activity_help'))?></p>
<?php if($chart):?>
<div style="height:280px"><canvas id="statisticsChart"></canvas></div>
<?php else:?><div class="text-body-secondary"><?=e(t('stats.no_data'))?></div><?php endif?>
</div></div>
<?php if($chart):?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const canvas=document.getElementById('statisticsChart'); if(!canvas||typeof Chart==='undefined')return;
 const data=<?=json_encode($chart,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
 const css=getComputedStyle(document.documentElement);
 const textColor=css.getPropertyValue('--bs-body-color').trim()||'#212529';
 const borderColor=css.getPropertyValue('--bs-border-color').trim()||'#dee2e6';
 new Chart(canvas,{type:'line',data:{labels:data.map(x=>x.label),datasets:[
  {label:<?=json_encode(t('stats.views'))?>,data:data.map(x=>x.views),tension:.28,pointRadius:2},
  {label:<?=json_encode(t('stats.plays'))?>,data:data.map(x=>x.plays),tension:.28,pointRadius:2},
  {label:<?=json_encode(t('stats.downloads'))?>,data:data.map(x=>x.downloads),tension:.28,pointRadius:2}
 ]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom',labels:{color:textColor,usePointStyle:true,boxWidth:8}}},scales:{x:{ticks:{color:textColor,maxRotation:0,autoSkip:true,maxTicksLimit:10},grid:{display:false}},y:{beginAtZero:true,ticks:{color:textColor,precision:0},grid:{color:borderColor}}}}});
});
</script>
<?php endif?>

<div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5"><?=e(t('stats.by_share'))?></h2><p class="text-body-secondary small"><?=e(t('stats.by_share_help'))?></p>
<?php if($shareStats):?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th><?=e(t('text.bezeichnung'))?></th><th class="text-end"><?=e(t('stats.views'))?></th><th class="text-end"><?=e(t('stats.plays'))?></th><th class="text-end"><?=e(t('stats.downloads'))?></th></tr></thead><tbody><?php foreach($shareStats as $r):?><tr><td><?=e($r['label']?:$r['token'])?></td><td class="text-end"><?=(int)$r['views']?></td><td class="text-end"><?=(int)$r['plays']?></td><td class="text-end"><?=(int)$r['downloads']?></td></tr><?php endforeach?></tbody></table></div><?php else:?><div class="text-body-secondary"><?=e(t('stats.no_share_data'))?></div><?php endif?></div></div>
<div class="card shadow-sm"><div class="card-body"><h2 class="h5"><?=e(t('stats.top_tracks'))?></h2><?php if($top):?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th><?=e(t('text.titel'))?></th><th class="text-end"><?=e(t('stats.plays'))?></th></tr></thead><tbody><?php foreach($top as $r):?><tr><td><?=e($r['title'])?></td><td class="text-end"><?=number_format((int)$r['plays'],0,',','.')?></td></tr><?php endforeach?></tbody></table></div><?php else:?><div class="text-body-secondary"><?=e(t('stats.no_data'))?></div><?php endif?></div></div>
<?php render_footer();
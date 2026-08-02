<?php
require_once __DIR__.'/../includes/bootstrap.php'; require_login();
$period=(int)($_GET['days']??30);if(!in_array($period,[1,7,30,0],true))$period=30;
[$access,$accessArgs]=accessible_album_condition('a');
$dateWhere=$period>0?' AND s.event_date>=DATE_SUB(CURDATE(),INTERVAL ? DAY)':'';
$args=$accessArgs;if($period>0)$args[]=$period-1;
$totals=['album_view'=>0,'track_play'=>0,'track_download'=>0,'album_download'=>0];
try{
 $q=$pdo->prepare("SELECT s.event_type,SUM(s.event_count) total FROM statistics_daily s JOIN albums a ON a.id=s.album_id WHERE a.deleted_at IS NULL AND {$access}{$dateWhere} GROUP BY s.event_type");
 $q->execute($args);foreach($q->fetchAll() as $r)$totals[$r['event_type']]=(int)$r['total'];
}catch(Throwable $e){}
$topAlbums=[];$topTracks=[];$chart=[];
try{
 $q=$pdo->prepare("SELECT a.id,a.title,a.artist,SUM(s.event_count) activity,
 SUM(CASE WHEN s.event_type='album_view' THEN s.event_count ELSE 0 END) views,
 SUM(CASE WHEN s.event_type='track_play' THEN s.event_count ELSE 0 END) plays,
 SUM(CASE WHEN s.event_type IN('track_download','album_download') THEN s.event_count ELSE 0 END) downloads
 FROM statistics_daily s JOIN albums a ON a.id=s.album_id WHERE a.deleted_at IS NULL AND {$access}{$dateWhere}
 GROUP BY a.id,a.title,a.artist ORDER BY activity DESC LIMIT 10");
 $q->execute($args);$topAlbums=$q->fetchAll();
 $q=$pdo->prepare("SELECT t.title,a.title album_title,SUM(s.event_count) plays FROM statistics_daily s JOIN albums a ON a.id=s.album_id JOIN tracks t ON t.id=s.track_id WHERE a.deleted_at IS NULL AND {$access}{$dateWhere} AND s.event_type='track_play' AND s.track_id>0 GROUP BY t.id,t.title,a.title ORDER BY plays DESC LIMIT 10");
 $q->execute($args);$topTracks=$q->fetchAll();
 $q=$pdo->prepare("SELECT s.event_date,
 SUM(CASE WHEN s.event_type='album_view' THEN s.event_count ELSE 0 END) views,
 SUM(CASE WHEN s.event_type='track_play' THEN s.event_count ELSE 0 END) plays,
 SUM(CASE WHEN s.event_type IN('track_download','album_download') THEN s.event_count ELSE 0 END) downloads
 FROM statistics_daily s JOIN albums a ON a.id=s.album_id WHERE a.deleted_at IS NULL AND {$access}{$dateWhere} GROUP BY s.event_date ORDER BY s.event_date");
 $q->execute($args);$rows=$q->fetchAll();$by=[];foreach($rows as $r)$by[$r['event_date']]=$r;
 if($period>0){$start=(new DateTimeImmutable('today'))->modify('-'.($period-1).' days');for($i=0;$i<$period;$i++){$d=$start->modify("+{$i} days");$k=$d->format('Y-m-d');$r=$by[$k]??[];$chart[]=['label'=>$d->format('d.m.'),'views'=>(int)($r['views']??0),'plays'=>(int)($r['plays']??0),'downloads'=>(int)($r['downloads']??0)];}}
 else{foreach($rows as $r){$d=new DateTimeImmutable($r['event_date']);$chart[]=['label'=>$d->format('d.m.y'),'views'=>(int)$r['views'],'plays'=>(int)$r['plays'],'downloads'=>(int)$r['downloads']];}}
}catch(Throwable $e){}
render_header(t('stats.overview'),true);?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><h1 class="h2 mb-1"><?=e(t('stats.overview'))?></h1><p class="text-body-secondary mb-0"><?=e(t('stats.overview_help'))?></p></div></div>
<div class="btn-group mb-4"><?php foreach([1=>'stats.today',7=>'stats.7days',30=>'stats.30days',0=>'stats.all'] as $d=>$k):?><a class="btn btn-sm <?=$period===$d?'btn-primary':'btn-outline-primary'?>" href="?days=<?=$d?>"><?=e(t($k))?></a><?php endforeach?></div>
<div class="row g-3 mb-4"><?php foreach([['album_view','stats.views','bi-eye'],['track_play','stats.plays','bi-play-circle'],['track_download','stats.track_downloads','bi-download'],['album_download','stats.album_downloads','bi-file-earmark-arrow-down']] as [$key,$label,$icon]):?><div class="col-6 col-lg-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="small text-body-secondary"><i class="bi <?=$icon?> me-1"></i><?=e(t($label))?></div><div class="display-6 fw-semibold mt-2"><?=number_format($totals[$key],0,',','.')?></div></div></div></div><?php endforeach?></div>
<div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5"><?=e(t('stats.activity'))?></h2><?php if($chart):?><div style="height:280px"><canvas id="overviewChart"></canvas></div><?php else:?><p class="text-body-secondary"><?=e(t('stats.no_data'))?></p><?php endif?></div></div>
<div class="row g-4"><div class="col-lg-7"><div class="card shadow-sm h-100"><div class="card-body"><h2 class="h5"><?=e(t('stats.top_albums'))?></h2><?php if($topAlbums):?><div class="table-responsive"><table class="table align-middle"><thead><tr><th><?=e(t('text.albumtitel'))?></th><th class="text-end"><?=e(t('stats.views'))?></th><th class="text-end"><?=e(t('stats.plays'))?></th><th class="text-end"><?=e(t('stats.downloads'))?></th></tr></thead><tbody><?php foreach($topAlbums as $r):?><tr><td><a href="statistics.php?id=<?=$r['id']?>"><?=e($r['title'])?></a><div class="small text-body-secondary"><?=e($r['artist'])?></div></td><td class="text-end"><?=(int)$r['views']?></td><td class="text-end"><?=(int)$r['plays']?></td><td class="text-end"><?=(int)$r['downloads']?></td></tr><?php endforeach?></tbody></table></div><?php else:?><p class="text-body-secondary"><?=e(t('stats.no_data'))?></p><?php endif?></div></div></div>
<div class="col-lg-5"><div class="card shadow-sm h-100"><div class="card-body"><h2 class="h5"><?=e(t('stats.top_tracks'))?></h2><?php if($topTracks):?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th style="width:3rem">#</th><th><?=e(t('text.titel'))?></th><th class="text-end"><?=e(t('stats.plays'))?></th></tr></thead><tbody><?php foreach($topTracks as $index=>$r):?><tr><td class="text-body-secondary"><?=($index+1)?></td><td><?=e($r['title'])?><div class="small text-body-secondary"><?=e($r['album_title'])?></div></td><td class="text-end fw-semibold"><?=number_format((int)$r['plays'],0,',','.')?></td></tr><?php endforeach?></tbody></table></div><?php else:?><p class="text-body-secondary"><?=e(t('stats.no_data'))?></p><?php endif?></div></div></div></div>
<?php if($chart):?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script><script>document.addEventListener('DOMContentLoaded',()=>{const d=<?=json_encode($chart,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,c=document.getElementById('overviewChart');if(!c||typeof Chart==='undefined')return;new Chart(c,{type:'line',data:{labels:d.map(x=>x.label),datasets:[{label:<?=json_encode(t('stats.views'))?>,data:d.map(x=>x.views),tension:.3,pointRadius:2},{label:<?=json_encode(t('stats.plays'))?>,data:d.map(x=>x.plays),tension:.3,pointRadius:2},{label:<?=json_encode(t('stats.downloads'))?>,data:d.map(x=>x.downloads),tension:.3,pointRadius:2}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});});</script><?php endif?>
<?php render_footer();
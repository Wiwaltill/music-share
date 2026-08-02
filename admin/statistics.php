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
render_header(t('stats.title'),true); ?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1"><?=e(t('stats.title'))?></h1><p class="text-body-secondary mb-0"><?=e($album['title'])?> · <?=e($album['artist'])?></p></div><a class="btn btn-outline-secondary" href="album_edit.php?id=<?=$id?>"><?=e(t('text.zuruck'))?></a></div>
<?php if(!statistics_enabled()):?><div class="alert alert-info"><?=e(t('stats.disabled_notice'))?></div><?php endif?>
<div class="btn-group mb-4"><?php foreach([1=>'stats.today',7=>'stats.7days',30=>'stats.30days',0=>'stats.all'] as $d=>$k):?><a class="btn btn-sm <?=$period===$d?'btn-primary':'btn-outline-primary'?>" href="?id=<?=$id?>&days=<?=$d?>"><?=e(t($k))?></a><?php endforeach?></div>
<div class="row g-3 mb-4">
<?php foreach([['album_view','stats.views','bi-eye'],['track_play','stats.plays','bi-play-circle'],['track_download','stats.track_downloads','bi-download'],['album_download','stats.album_downloads','bi-file-earmark-arrow-down']] as [$key,$label,$icon]):?>
<div class="col-6 col-lg-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-body-secondary small"><i class="bi <?=$icon?> me-1"></i><?=e(t($label))?></div><div class="display-6 fw-semibold mt-2"><?=number_format($totals[$key],0,',','.')?></div></div></div></div>
<?php endforeach?></div>
<div class="card shadow-sm"><div class="card-body"><h2 class="h5"><?=e(t('stats.top_tracks'))?></h2><?php if($top):?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th><?=e(t('text.titel'))?></th><th class="text-end"><?=e(t('stats.plays'))?></th></tr></thead><tbody><?php foreach($top as $r):?><tr><td><?=e($r['title'])?></td><td class="text-end"><?=number_format((int)$r['plays'],0,',','.')?></td></tr><?php endforeach?></tbody></table></div><?php else:?><div class="text-body-secondary"><?=e(t('stats.no_data'))?></div><?php endif?></div></div>
<?php render_footer();
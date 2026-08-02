<?php
require_once __DIR__.'/../includes/bootstrap.php';require_admin();
$dbVersion='';try{$dbVersion=(string)$pdo->query('SELECT VERSION()')->fetchColumn();}catch(Throwable $e){}
$checks=[
 ['status.php_version',PHP_VERSION,version_compare(PHP_VERSION,'8.1.0','>=')],
 ['status.db_version',$dbVersion,$dbVersion!==''],
 ['status.upload_limit',(string)ini_get('upload_max_filesize'),true],
 ['status.post_limit',(string)ini_get('post_max_size'),true],
 ['status.zip',extension_loaded('zip')?t('status.available'):t('status.missing'),extension_loaded('zip')],
 ['status.curl',extension_loaded('curl')?t('status.available'):t('status.missing'),extension_loaded('curl')],
 ['status.uploads_writable',is_writable(dirname(__DIR__).'/uploads')?t('status.yes'):t('status.no'),is_writable(dirname(__DIR__).'/uploads')],
 ['status.storage_writable',is_writable(dirname(__DIR__).'/storage')?t('status.yes'):t('status.no'),is_writable(dirname(__DIR__).'/storage')],
 ['status.mail',get_setting('mail_method','mail')==='smtp'?'SMTP':'PHP mail()',filter_var(get_setting('mail_from_email',default_system_email()),FILTER_VALIDATE_EMAIL)!==false],
];
$uploadSize=human_bytes(directory_size(dirname(__DIR__).'/uploads'));
$backupSize=human_bytes(directory_size(dirname(__DIR__).'/storage/backups'));
render_header(t('status.title'),true);?>
<div class="d-flex justify-content-between align-items-start mb-4"><div><h1 class="h2 mb-1"><?=e(t('status.title'))?></h1><p class="text-body-secondary mb-0"><?=e(t('status.help'))?></p></div><a class="btn btn-outline-secondary" href="settings.php"><?=e(t('text.zuruck'))?></a></div>
<div class="row g-3 mb-4"><div class="col-md-6"><div class="card shadow-sm"><div class="card-body"><div class="text-body-secondary small"><?=e(t('status.upload_storage'))?></div><div class="h3 mt-2"><?=$uploadSize?></div></div></div></div><div class="col-md-6"><div class="card shadow-sm"><div class="card-body"><div class="text-body-secondary small"><?=e(t('status.backup_storage'))?></div><div class="h3 mt-2"><?=$backupSize?></div></div></div></div></div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th><?=e(t('status.check'))?></th><th><?=e(t('status.value'))?></th><th class="text-end"><?=e(t('status.state'))?></th></tr></thead><tbody><?php foreach($checks as [$label,$value,$ok]):?><tr><td><?=e(t($label))?></td><td><?=e((string)$value)?></td><td class="text-end"><span class="badge <?=$ok?'text-bg-success':'text-bg-danger'?>"><?=$ok?e(t('status.ok')):e(t('status.problem'))?></span></td></tr><?php endforeach?></tbody></table></div></div>
<?php render_footer();
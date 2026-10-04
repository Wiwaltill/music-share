<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    set_setting('listening_rooms_enabled', isset($_POST['listening_rooms_enabled']) ? '1' : '0');
    flash('success', t('rooms.saved'));
    redirect('admin/addons.php');
}
render_header(t('addons.title'), true);
?>
<h1 class="h2 mb-4"><?=e(t('addons.title'))?></h1>
<div class="card shadow-sm"><div class="card-body p-4">
<h2 class="h4">Listening Rooms</h2><p class="text-body-secondary"><?=e(t('rooms.addon_help'))?></p>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="listening_rooms_enabled" id="roomsEnabled" <?=get_setting('listening_rooms_enabled','0')==='1'?'checked':''?>><label class="form-check-label" for="roomsEnabled"><?=e(t('rooms.enable'))?></label></div>
<button class="btn btn-primary"><?=e(t('rooms.save'))?></button>
</form></div></div>
<?php render_footer();

<?php require_once __DIR__.'/../includes/bootstrap.php'; revoke_current_session(); session_destroy(); redirect('admin/login.php');

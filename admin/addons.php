<?php
// Keep existing bookmarks working after moving modules into settings.
require_once __DIR__.'/../includes/bootstrap.php';
require_admin();
redirect('admin/settings.php#modules');

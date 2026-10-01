<?php

declare(strict_types=1);

// Old admin URL - the admin area is now the single-page dashboard in admin/index.php.
header('Location: ./' . (basename(__FILE__) === 'users.php' ? '#/users' : ''), true, 301);
exit;

<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

logout_user();
redirect('login.php');

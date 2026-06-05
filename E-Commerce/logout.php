<?php
require_once __DIR__ . '/functions.php';
zera_destroy_session();
header('Location: index.php');
exit;

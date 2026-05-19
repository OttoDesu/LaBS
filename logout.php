<?php
require_once __DIR__ . '/init.php';

$_SESSION = [];
session_destroy();

header('Location: index.php');
exit;

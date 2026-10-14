<?php
require_once __DIR__ . '/includes/functions.php';
session_destroy();
$back = filter_var($_GET['back'] ?? '', FILTER_SANITIZE_URL);
if (!$back || !str_starts_with($back, '/')) $back = '/huella/';
header('Location: ' . $back);
exit;

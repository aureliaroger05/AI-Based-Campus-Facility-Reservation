<?php
require_once 'auth.php';
logout();
header('Location: welcome.php');
exit;

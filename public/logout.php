<?php
require_once __DIR__ . '/../app/Auth.php';

use App\Auth;

Auth::logout();
header('Location: login.php');
exit;

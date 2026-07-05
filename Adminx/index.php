<?php
require '../function/function_core.php';


if (empty($_SESSION['admin_username']) || empty($_SESSION['admin_id'])) {
    echo '没有登录:code:-1';
    header('Location: login.html');
    die();
}
if (!$admininfo = $db->select_first_row('sq_admin_1', '*', array('ID' => $_SESSION['admin_id'], 'username' => $_SESSION['admin_username']), 'AND')) {
    echo '没有登录:code:-2';
    header('Location: login.html');

    die();
}
if ($_SERVER['HTTP_USER_AGENT'] !== $_SESSION['admin_HTTP_USER_AGENT']) {
    echo '没有登录:code:-3';
    header('Location: login.html');
    die();
}
if (!empty($_GET['mod']) && $_GET['mod'] == 'loginout') {
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_qq']);
    unset($_SESSION['admin_type']);
    unset($_SESSION['admin_HTTP_USER_AGENT']);
    header('Location: login.html');
}
header('Location: admin.html');
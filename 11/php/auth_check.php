<?php
// auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 세션에 관리자 정보가 없으면 로그인 페이지로 쫓아냄
if (!isset($_SESSION['admin_username'])) {
    header("Location: login.php");
    exit;
}
?>
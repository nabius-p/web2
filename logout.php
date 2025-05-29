<?php
declare(strict_types=1);

// 1) Bắt đầu rồi hủy session hiện tại
session_start();

// Xóa tất cả biến session
$_SESSION = [];

// Nếu đang dùng cookie để lưu session, hủy cookie đó
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),       // tên cookie
        '',                   // giá trị rỗng
        time() - 42000,       // đã hết hạn
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Hủy session
session_destroy();

// 2) Tạo session mới để lưu flash message
session_start();
$_SESSION['flash_message'] = 'Bạn đã đăng xuất thành công.';

// 3) Chuyển hướng về login.php
header('Location: login.php');
exit();

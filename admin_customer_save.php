<?php
session_start();
include 'db.php';

// Bảo vệ trang
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Lấy dữ liệu từ form
$email = $_POST['email']         ?? '';
$eva   = $_POST['eva']           ?? 'NEW';

// Validate cơ bản
if (!$email || !in_array($eva, ['NEW','POTENTIAL','VIP'])) {
    $_SESSION['flash_error'] = 'Dữ liệu không hợp lệ.';
    header('Location: admin.php?page=revenue_customers');
    exit;
}

// Cập nhật eva_level cho tất cả hóa đơn của khách
$stmt = $conn->prepare("
  UPDATE invoices
     SET customer_type = ?
   WHERE customer_email = ?
");
$stmt->bind_param('ss', $eva, $email);
$stmt->execute();
$stmt->close();

// Tạo flash message (nếu cần)
$_SESSION['flash_success'] = 'Cập nhật khách hàng thành công.';

// Redirect về lại trang danh sách khách
header('Location: admin.php?page=revenue_customers');
exit;

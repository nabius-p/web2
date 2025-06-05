<?php
// admin_customer_apply_voucher.php
session_start();
require 'db.php'; // $conn

// 1) Bảo vệ trang
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// 2) Lấy và validate params
$email      = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$voucher_id = filter_input(INPUT_POST, 'voucher_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$email || !$voucher_id) {
    $_SESSION['flash_voucher_error'] = 'Yêu cầu không hợp lệ.';
    header("Location: admin.php?page=revenue_customer_detail&email=" . urlencode($_POST['email'] ?? ''));
    exit;
}

try {
    $conn->begin_transaction();

    // 3) Tìm hóa đơn mới nhất của khách
    $stmt = $conn->prepare("
        SELECT id 
          FROM invoices
         WHERE customer_email = ?
         ORDER BY created_at DESC
         LIMIT 1
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($invoice_id);
    if (!$stmt->fetch()) {
        throw new Exception("Không tìm thấy hóa đơn nào cho khách này.");
    }
    $stmt->close();

    // 4) Cập nhật voucher_id trên bản ghi invoices
    $upd = $conn->prepare("
        UPDATE invoices
           SET voucher_id = ?
         WHERE id = ?
    ");
    $upd->bind_param('ii', $voucher_id, $invoice_id);
    $upd->execute();
    if ($upd->affected_rows === 0) {
        throw new Exception("Áp voucher thất bại (không có bản ghi bị thay đổi).");
    }
    $upd->close();

    // 5) Giảm usage_limit nếu có
    $upd2 = $conn->prepare("
        UPDATE vouchers
           SET usage_limit = usage_limit - 1
         WHERE id = ?
           AND usage_limit IS NOT NULL
           AND usage_limit > 0
    ");
    $upd2->bind_param('i', $voucher_id);
    $upd2->execute();
    $upd2->close();

    $conn->commit();
    $_SESSION['flash_voucher_success'] = 'Áp dụng voucher thành công cho đơn #' . $invoice_id . '!';
} catch (Exception $e) {
    $conn->rollback();
    error_log("Voucher apply error: " . $e->getMessage());
    $_SESSION['flash_voucher_error'] = 'Áp voucher thất bại: ' . $e->getMessage();
}

// 6) Quay về trang chi tiết khách
header("Location: admin.php?page=revenue_customer_detail&email=" . urlencode($email));
exit;

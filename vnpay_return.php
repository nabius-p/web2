<?php

// Đặt timezone chuẩn Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Lấy các tham số trả về từ VNPay
$vnp_HashSecret = "V5UIW8PBO0BSCEHYSLXDNJKEKLIAHRSX"; // Giữ đúng như file payment
$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';
$data = $_GET;
unset($data['vnp_SecureHash']);
unset($data['vnp_SecureHashType']);
ksort($data);
$hashData = '';
$i = 0;
foreach ($data as $key => $value) {
    if ($i == 1) {
        $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}
$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

// Kiểm tra tính hợp lệ của dữ liệu trả về
$success = false;
$message = '';
if ($secureHash === $vnp_SecureHash) {
    if ($_GET['vnp_ResponseCode'] == '00') {
        $success = true;
        $message = "Thanh toán thành công!";

        // === CẬP NHẬT TRẠNG THÁI BÀN VỀ OPEN VÀ XÓA HÓA ĐƠN ===
        include 'db.php';
        // Lấy invoice_id từ vnp_OrderInfo (dạng "INV#123")
        $invoice_id = 0;
        if (isset($_GET['vnp_OrderInfo'])) {
            if (preg_match('/INV#(\d+)/', $_GET['vnp_OrderInfo'], $m)) {
                $invoice_id = intval($m[1]);
            }
        }
        if ($invoice_id > 0) {
            // Lấy table_id từ invoices
            $stmt = $conn->prepare("SELECT table_id FROM invoices WHERE id = ?");
            $stmt->bind_param('i', $invoice_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res && $res['table_id']) {
                $table_id = intval($res['table_id']);

                // Xóa invoice_items
                $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
                $stmt->bind_param('i', $invoice_id);
                $stmt->execute();
                $stmt->close();

                // Xóa invoices
                $stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
                $stmt->bind_param('i', $invoice_id);
                $stmt->execute();
                $stmt->close();

                // Cập nhật trạng thái bàn về open
                $stmt = $conn->prepare("UPDATE restaurant_tables SET status = 'open' WHERE id = ?");
                $stmt->bind_param('i', $table_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        // === END ===

    } else {
        $message = "Thanh toán thất bại! Mã lỗi: " . $_GET['vnp_ResponseCode'];
    }
} else {
    $message = "Dữ liệu không hợp lệ (sai checksum)!";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Kết quả thanh toán</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h2 class="mb-4"><?= $success ? '✅ Thành công' : '❌ Thất bại' ?></h2>
                <p><?= htmlspecialchars($message) ?></p>
                <p><b>Mã giao dịch:</b> <?= htmlspecialchars($_GET['vnp_TxnRef'] ?? '') ?></p>
                <p><b>Số tiền:</b> <?= isset($_GET['vnp_Amount']) ? number_format($_GET['vnp_Amount']/100, 0, ',', '.') . ' VND' : '' ?></p>
                <a href="admin.php" class="btn btn-primary mt-3">Quay về trang quản trị</a>
            </div>
        </div>
    </div>
</body>
</html>
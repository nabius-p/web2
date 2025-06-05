<?php
// Đặt timezone chuẩn Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Lấy dữ liệu từ table list gửi sang
$table_id   = $_POST['table_id']   ?? '';
$invoice_id = $_POST['invoice_id'] ?? '';
$amount     = $_POST['amount']     ?? '';
$order_desc = $_POST['order_desc'] ?? 'Thanh toán hóa đơn';

// Nếu thiếu dữ liệu thì báo lỗi
if (!$table_id || !$invoice_id || !$amount) {
    die('Thiếu thông tin hóa đơn!');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Xác nhận thanh toán</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 400px;">
        <div class="card-body">
            <h3 class="mb-3 text-center">Xác nhận thanh toán</h3>
            <ul class="list-group mb-3">
                <li class="list-group-item"><b>Bàn số:</b> <?= htmlspecialchars($table_id) ?></li>
                <li class="list-group-item"><b>Mã hóa đơn:</b> <?= htmlspecialchars($invoice_id) ?></li>
                <li class="list-group-item"><b>Tổng tiền:</b> <span class="text-danger fw-bold"><?= number_format($amount, 0, ',', '.') ?> ₫</span></li>
                <li class="list-group-item"><b>Ghi chú:</b> <?= htmlspecialchars($order_desc) ?></li>
            </ul>
            <form action="vnpay_payment.php" method="post">
                <input type="hidden" name="table_id" value="<?= htmlspecialchars($table_id) ?>">
                <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($invoice_id) ?>">
                <input type="hidden" name="total_vnpay" value="<?= (int)str_replace(['.', ','], '', $amount) ?>">
                <input type="hidden" name="order_desc" value="<?= htmlspecialchars($order_desc) ?>">
                <button type="submit" class="btn btn-success w-100">Thanh toán qua VNPAY</button>
            </form>
            <a href="admin.php?page=table_list" class="btn btn-link w-100 mt-2">Quay lại</a>
        </div>
    </div>
</div>
</body>
</html>
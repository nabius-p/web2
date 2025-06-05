<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Lấy dữ liệu từ form payment.php
$table_id   = $_POST['table_id']   ?? '';
$invoice_id = $_POST['invoice_id'] ?? '';
$total_vnpay = isset($_POST['total_vnpay']) ? intval($_POST['total_vnpay']) : 0;
$order_desc = "INV#" . $invoice_id; // Truyền invoice_id qua vnp_OrderInfo

if ($total_vnpay <= 0) {
    die('Số tiền không hợp lệ!');
}

// Cấu hình VNPAY (dùng thông tin bạn cung cấp)
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
$vnp_Returnurl = "http://localhost/shinhot%20pot/vnpay_return.php";
$vnp_TmnCode = "844RPNIZ"; // Mã website tại VNPAY
$vnp_HashSecret = "V5UIW8PBO0BSCEHYSLXDNJKEKLIAHRSX"; // Chuỗi bí mật

$vnp_TxnRef = time() . rand(1000,9999); // Mã đơn hàng duy nhất
$vnp_OrderInfo = $order_desc;
$vnp_OrderType = 'billpayment';
$vnp_Amount = $total_vnpay ;
$vnp_Locale = 'vn';
$vnp_IpAddr = ($_SERVER['REMOTE_ADDR'] == '::1') ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
$vnp_CreateDate = date('YmdHis');

$inputData = array(
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => $vnp_CreateDate,
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $vnp_IpAddr,
    "vnp_Locale" => $vnp_Locale,
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => $vnp_OrderType,
    "vnp_ReturnUrl" => $vnp_Returnurl,
    "vnp_TxnRef" => $vnp_TxnRef,
);

ksort($inputData);
$query = "";
$i = 0;
$hashdata = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashdata .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
    $query .= urlencode($key) . "=" . urlencode($value) . '&';
}

$vnp_Url = $vnp_Url . "?" . $query;
$vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
$vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;

// Chuyển hướng sang VNPAY
header('Location: ' . $vnp_Url);
exit;
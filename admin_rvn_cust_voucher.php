<?php
// admin_rvn_cust_voucher.php

include 'db.php'; // $conn -> kết nối đến hotpot_app1

// 1) Bảo vệ trang
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// 2) Lấy email và tên khách từ query string
$email = $_GET['email'] ?? '';
if (!$email) {
    header('Location: admin.php?page=revenue_customers');
    exit;
}
$stmt = $conn->prepare("
  SELECT customer_name 
    FROM invoices 
   WHERE customer_email = ? 
   LIMIT 1
");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($name);
if (!$stmt->fetch()) {
    header('Location: admin.php?page=revenue_customers');
    exit;
}
$stmt->close();

// 3) Lấy danh sách voucher mà khách đã sử dụng
//    Chúng ta truy vấn qua bảng invoices, join với vouchers, lọc theo email,
//    chỉ những invoice có voucher_id không NULL.
$sql = "
  SELECT DISTINCT 
         v.id, 
         v.code, 
         v.description, 
         v.discount_type, 
         v.discount_value,
         i.created_at AS used_at
    FROM invoices i
    JOIN vouchers v ON i.voucher_id = v.id
   WHERE i.customer_email = ?
   ORDER BY i.created_at DESC
";
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param('s', $email);
$stmt2->execute();
$res2 = $stmt2->get_result();
$usedVouchers = $res2->fetch_all(MYSQLI_ASSOC);
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Voucher used | ShinHot Pot</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f1f5f9; font-family:sans-serif; }
    .voucher-card {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: .5rem;
      padding: 1rem;
      margin-bottom: 1rem;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .voucher-code {
      font-size: 1.25rem;
      font-weight: 600;
      color: #eea617;
    }
    .voucher-label {
      font-size: 1rem;
      color: #333;
    }
    .voucher-description {
      font-size: .9rem;
      color: #666;
      margin-top: .25rem;
    }
    .voucher-used-at {
      font-size: .85rem;
      color: #999;
      margin-top: .5rem;
    }
    .back-link {
      background: #6c757d;
      color: #fff;
      text-decoration: none;
      padding: .5rem 1rem;
      border-radius: .25rem;
    }
    .back-link:hover {
      background: #5a6268;
      color: #fff;
    }
  </style>
</head>
<body>
<div class="container py-5">
  <!-- Tiêu đề -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Vouchers used by <strong><?= htmlspecialchars($name) ?></strong></h4>
    <a href="admin.php?page=revenue_customers" class="back-link">← Back</a>
  </div>

  <!-- Danh sách voucher đã sử dụng -->
  <?php if (count($usedVouchers) === 0): ?>
    <div class="alert alert-info">This customer has not used any vouchers yet.</div>
  <?php else: ?>
    <?php foreach ($usedVouchers as $v): 
      // Tạo label theo loại discount
      if ($v['discount_type'] === 'percent') {
          $label = "{$v['discount_value']}% OFF";
      } else {
          $label = number_format($v['discount_value'], 0, ',', '.') . "₫ OFF";
      }
      // Định dạng ngày sử dụng
      $usedAt = date('Y-m-d H:i:s', strtotime($v['used_at']));
    ?>
      <div class="voucher-card">
        <div class="voucher-code"><?= htmlspecialchars($v['code']) ?></div>
        <div class="voucher-label"><?= htmlspecialchars($label) ?></div>
        <?php if (!empty($v['description'])): ?>
          <div class="voucher-description"><?= htmlspecialchars($v['description']) ?></div>
        <?php endif; ?>
        <div class="voucher-used-at">Used on: <?= $usedAt ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

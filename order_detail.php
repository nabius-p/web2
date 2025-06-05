<?php
// order_detail.php

include 'db.php';

// 1) Bảo vệ trang
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// 2) Lấy ID hóa đơn từ query string
$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($invoice_id <= 0) {
    header('Location: admin.php?page=orders');
    exit;
}

// 3) Lấy thông tin đơn (nếu cần header nào khác, ví dụ customer_name...)
//    ở đây chỉ cần số hóa đơn, có thể in ra tiêu đề
// 4) Lấy chi tiết các món trong hóa đơn
$stmt = $conn->prepare("
    SELECT i.name AS dish_name,
           ii.quantity,
           ii.price
      FROM invoice_items ii
      JOIN items i ON ii.item_id = i.id
     WHERE ii.invoice_id = ?
");
$stmt->bind_param('i', $invoice_id);
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

// 5) Tính tổng
$total = 0;
while ($row = $items->fetch_assoc()) {
    $total += $row['quantity'] * $row['price'];
}
$items->data_seek(0); // reset result pointer
?>
<!-- Bắt đầu phần include chung của admin.php -->
<div class="container-fluid">
  <div class="row g-0">
   

    <main class="col-10 p-4">
      <h4 class="mb-4">Invoice #<?= $invoice_id ?></h4>
      <div class="card rounded-3 p-4 mb-4">
        <ul class="list-unstyled mb-0">
          <?php while ($row = $items->fetch_assoc()): ?>
            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
              <div>
                <strong><?= htmlspecialchars($row['dish_name']) ?></strong><br>
                <small>Qty: <?= intval($row['quantity']) ?></small>
              </div>
              <span><?= number_format($row['quantity'] * $row['price'],0,',','.') ?> ₫</span>
            </li>
          <?php endwhile; ?>
          <li class="d-flex justify-content-between align-items-center pt-3">
            <strong>Total</strong>
            <strong class="text-warning"><?= number_format($total,0,',','.') ?> ₫</strong>
          </li>
        </ul>
      </div>

      <a href="admin.php?page=orders" class="btn btn-outline-secondary">
        <i class="fa fa-arrow-left me-1"></i>Back
      </a>
    </main>
  </div>
</div>

<!-- Bootstrap & FontAwesome JS nếu cần -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>

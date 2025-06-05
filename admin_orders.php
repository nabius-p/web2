<?php

include 'db.php';

// 1) Bảo vệ trang
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// 2) Ngày (mặc định hôm nay)
$date = $_GET['date'] ?? date('Y-m-d');

// 3) Lấy 20 đơn gần nhất
$stmt = $conn->prepare("
  SELECT id, customer_name, customer_email, total_amount, created_at
  FROM invoices
  WHERE DATE(created_at)=?
  ORDER BY created_at DESC
  LIMIT 20
");
$stmt->bind_param("s", $date);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>All Orders | ShinHot Pot</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Font & Icon & Bootstrap -->
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Nunito', sans-serif;
      background: #f8f9fa;
    }
    .content-wrapper {
      max-width: 1000px;
      margin: 0 auto;
      padding: 2rem 1rem;
    }
    .btn-back {
      margin-left: .5rem;
    }
  </style>
</head>
<body>

  <div class="content-wrapper">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Recently Orders</h4>
      <input
        type="date"
        class="form-control w-auto"
        value="<?= htmlspecialchars($date) ?>"
        onchange="location.href='admin_orders.php?date='+this.value;"
      >
    </div>

    <!-- Orders Table -->
    <div class="card shadow-sm mb-4">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Number</th>
              <th>Customer</th>
              <th>Email</th>
              <th class="text-end">Total</th>
              <th>Date</th>
              <th>Status</th>
              <th>Checking</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($orders->num_rows === 0): ?>
            <tr>
              <td colspan="7" class="text-center py-4">No orders found.</td>
            </tr>
          <?php else: ?>
            <?php while ($r = $orders->fetch_assoc()): ?>
              <tr>
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['customer_name']) ?></td>
                <td><?= htmlspecialchars($r['customer_email']) ?></td>
                <td class="text-end"><?= number_format($r['total_amount'],0,',','.') ?>₫</td>
                <td><?= date('Y-m-d H:i:s', strtotime($r['created_at'])) ?></td>
                <td><?= htmlspecialchars($r['status'] ?? 'Done') ?></td>
                <td>
                  <a
                    href="admin.php?page=order_detail&detail=order&id=<?= $r['id'] ?>"
                    class="btn btn-sm btn-outline-secondary"
                  >
                    <i class="fa fa-chevron-down"></i> View
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Controls -->
    <div class="d-flex justify-content-center mb-5">
      <button id="btnLoadMore" class="btn btn-outline-primary">Load More</button>
      <a href="admin_revenue_sales.php" class="btn btn-outline-secondary btn-back">Back</a>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('btnLoadMore').addEventListener('click', () => {
      alert('Load thêm đơn hàng…');
    });
  </script>
</body>
</html>

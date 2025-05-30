<?php
session_start();
include 'db.php'; // kết nối CSDL

// --- AJAX handler cho chi tiết revenue_sales ---
if (($_GET['page'] ?? '') === 'revenue_sales' && isset($_GET['detail'])) {
    $date   = $_GET['date'] ?? date('Y-m-d');
    $detail = $_GET['detail'];

    header('Content-Type: text/html; charset=UTF-8');

    switch ($detail) {
        case 'Total Sales':
            // liệt kê tất cả đơn bán ra ngày
            $stmt = $conn->prepare("
              SELECT id, customer_name, table_number, total_price, status
              FROM orders
              WHERE DATE(order_date)=?
              ORDER BY order_date DESC
            ");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $rs = $stmt->get_result();
            echo '<table class="table"><thead><tr>
                    <th>#</th><th>Customer</th><th>Table</th><th>Amount</th><th>Status</th>
                  </tr></thead><tbody>';
            while($o = $rs->fetch_assoc()){
                $b = $o['status']==='paid'?'success':'warning';
                echo "<tr>
                        <td>{$o['id']}</td>
                        <td>{$o['customer_name']}</td>
                        <td>{$o['table_number']}</td>
                        <td>".number_format($o['total_price'],0,',','.')." ₫</td>
                        <td><span class='badge bg-{$b}'>".ucfirst($o['status'])."</span></td>
                      </tr>";
            }
            echo '</tbody></table>';
            break;

        case 'Dishes Sold':
            // tổng số lượng từng món trong ngày
            $stmt = $conn->prepare("
              SELECT i.name, SUM(oi.quantity) AS qty
              FROM orders o
              JOIN order_items oi ON oi.order_id=o.id
              JOIN items i       ON i.id=oi.item_id
              WHERE DATE(o.order_date)=?
              GROUP BY i.id
              ORDER BY qty DESC
            ");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $rs = $stmt->get_result();
            echo '<table class="table"><thead><tr>
                    <th>Dish</th><th>Qty Sold</th>
                  </tr></thead><tbody>';
            while($d = $rs->fetch_assoc()){
                echo "<tr>
                        <td>{$d['name']}</td>
                        <td>{$d['qty']}</td>
                      </tr>";
            }
            echo '</tbody></table>';
            break;

        case 'Total Orders':
            // thống kê số đơn & doanh thu
            $stmt = $conn->prepare("
              SELECT COUNT(*) AS cnt, SUM(total_price) AS sum
              FROM orders
              WHERE DATE(order_date)=?
            ");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $s = $stmt->get_result()->fetch_assoc();
            echo "<p>Total orders: <strong>{$s['cnt']}</strong></p>";
            echo "<p>Total revenue: <strong>".number_format($s['sum'],0,',','.')." ₫</strong></p>";
            break;

        case 'New Customers':
            // danh sách khách mới hôm nay
            $stmt = $conn->prepare("
              SELECT DISTINCT o.customer_name, o.customer_email
              FROM orders o
              WHERE DATE(o.order_date)=?
                AND NOT EXISTS(
                  SELECT 1 FROM orders o2
                  WHERE o2.customer_email=o.customer_email
                    AND o2.order_date < o.order_date
                )
            ");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $rs = $stmt->get_result();
            echo '<ul class="list-group">';
            while($c = $rs->fetch_assoc()){
                echo "<li class='list-group-item'>{$c['customer_name']} ({$c['customer_email']})</li>";
            }
            echo '</ul>';
            break;

        default:
            echo '<p class="text-danger">Unknown detail key.</p>';
    }
    exit;
}
// Bảo vệ trang
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// 1. Nếu đang ở trang Orders, xử lý confirm/cancel/undo ngay lập tức:
if (($_GET['page'] ?? '') === 'orders') {
    if (!empty($_GET['confirm_id'])) {
        $id = intval($_GET['confirm_id']);
        $conn->query("UPDATE orders SET status='paid' WHERE id={$id}");
        header('Location: admin.php?page=orders#orders');
        exit;
    }
    if (!empty($_GET['cancel_id'])) {
        $id = intval($_GET['cancel_id']);
        $conn->query("UPDATE orders SET status='cancelled' WHERE id={$id}");
        header('Location: admin.php?page=orders#orders');
        exit;
    }
    if (!empty($_GET['undo_id'])) {
        $id = intval($_GET['undo_id']);
        $conn->query("UPDATE orders SET status='pending' WHERE id={$id}");
        header('Location: admin.php?page=orders#orders');
        exit;
    }
}

// 2. Xác định module con cần include
$page    = $_GET['page'] ?? 'revenue_sales';
$allowed = ['revenue_sales','revenue_customers','revenue_topsold','inventory','orders'];
if (!in_array($page, $allowed)) {
    $page = 'revenue_sales';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard – ShinHot Pot</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Pacifico font + FontAwesome + Bootstrap -->
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .sidebar {
      min-height: 100vh;
      background: #fff;
      border-right: 1px solid #ddd;
      padding-top: 1rem;
    }
    .sidebar .logo {
      font-family: 'Pacifico', cursive;
      font-size: 1.75rem;
      color: #fea116;
      padding: .5rem 1rem;
    }
    .sidebar .nav-link {
      color: #333;
      padding: .75rem 1rem;
    }
    .sidebar .nav-link.active {
      background: #ffd54f;
      color: #fff;
      font-weight: 600;
      border-radius: .25rem;
    }
    .content {
      padding: 2rem;
    }
  </style>
</head>
<body>

<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <nav class="col-2 sidebar d-flex flex-column">
      <a href="admin.php" class="logo">ShinHot Pot</a>
      <hr>
      <h6 class="px-3 text-uppercase text-muted">Operations</h6>
      <ul class="nav flex-column mb-4">
        <li class="nav-item">
          <a class="nav-link <?= ($page==='revenue_sales')?'active':''?>"
             href="admin.php?page=revenue_sales">
            <i class="fas fa-chart-line me-2"></i>Sales
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($page==='revenue_customers')?'active':''?>"
             href="admin.php?page=revenue_customers">
            <i class="fas fa-user-friends me-2"></i>Customers
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($page==='revenue_topsold')?'active':''?>"
             href="admin.php?page=revenue_topsold">
            <i class="fas fa-fire me-2"></i>Top Sold
          </a>
        </li>
      </ul>

      <h6 class="px-3 text-uppercase text-muted">Inventory</h6>
      <ul class="nav flex-column mb-4">
        <li class="nav-item">
          <a class="nav-link <?= ($page==='inventory')?'active':''?>"
             href="admin.php?page=inventory">
            <i class="fas fa-boxes me-2"></i>Inventory Checking
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin.php?page=category">
            <i class="fas fa-list-alt me-2"></i>Category
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin.php?page=add_item">
            <i class="fas fa-plus-circle me-2"></i>Add New Items
          </a>
        </li>
      </ul>

      <h6 class="px-3 text-uppercase text-muted">Table</h6>
      <ul class="nav flex-column mb-4">
        <li class="nav-item">
          <a class="nav-link" href="admin.php?page=table_list">
            <i class="fas fa-table me-2"></i>Table List
          </a>
        </li>
      </ul>

      <div class="mt-auto px-3">
        <a class="nav-link text-danger" href="logout.php">
          <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
      </div>
    </nav>

    <!-- Main content -->
    <main class="col-10 content">
      <?php
        switch($page) {
          case 'orders':
            include 'admin_orders.php';
            break;
          case 'inventory':
            include 'admin_inventory.php';
            break;
          case 'revenue_customers':
            include 'admin_revenue_customers.php';
            break;
          case 'revenue_topsold':
            include 'admin_revenue_topsold.php';
            break;
          default:
            include 'admin_revenue_sales.php';
            break;
        }
      ?>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// admin_orders.php – phần module, không include session hay db nữa

// Bảo vệ trang (đã có session_start & include db.php trong admin.php)
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Xử lý confirm/undo (có thể bỏ nếu đã xử lý ở admin.php)
if (isset($_GET['confirm_id'])) {
    $oid = intval($_GET['confirm_id']);
    $conn->query("UPDATE orders SET status='confirmed' WHERE id={$oid}");
    header('Location: admin.php?page=orders#orders');
    exit();
}
if (isset($_GET['undo_id'])) {
    $uid = intval($_GET['undo_id']);
    $conn->query("UPDATE orders SET status='pending' WHERE id={$uid}");
    header('Location: admin.php?page=orders#orders');
    exit();
}

// Lọc theo trạng thái
$statusFilter = $_GET['status'] ?? '';

// Phân trang
$pageSize = 10;
$page     = max(1, intval($_GET['pg'] ?? 1));
$offset   = ($page - 1) * $pageSize;

// Đếm tổng số đơn (áp dụng filter nếu có)
if ($statusFilter) {
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
    $stmtCount->bind_param("s", $statusFilter);
} else {
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM orders");
}
$stmtCount->execute();
$totalOrders = $stmtCount->get_result()->fetch_row()[0];
$totalPages  = ceil($totalOrders / $pageSize);

// Lấy danh sách orders có phân trang
if ($statusFilter) {
    $stmt = $conn->prepare("
      SELECT * 
      FROM orders 
      WHERE status = ?
      ORDER BY order_date DESC
      LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii", $statusFilter, $pageSize, $offset);
} else {
    $stmt = $conn->prepare("
      SELECT *
      FROM orders
      ORDER BY order_date DESC
      LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $pageSize, $offset);
}
$stmt->execute();
$res_orders = $stmt->get_result();
?>

<h2 id="orders">Orders Management</h2>

<!-- Filter trạng thái -->
<form method="get" action="admin.php" class="row g-2 mb-3">
  <input type="hidden" name="page" value="orders">
  <div class="col-auto">
    <select name="status" class="form-select">
      <option value="" <?= $statusFilter===''?'selected':'' ?>>All Statuses</option>
      <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
      <option value="confirmed" <?= $statusFilter==='confirmed'?'selected':'' ?>>Confirmed</option>
    </select>
  </div>
  <div class="col-auto">
    <button class="btn btn-primary">Filter</button>
  </div>
</form>

<table class="table table-striped table-hover">
  <thead class="table-dark">
    <tr>
      <th>#</th><th>Table</th><th>Customer</th>
      <th>Email</th><th>Date</th><th>Total</th>
      <th>Status</th><th>Actions</th>
    </tr>
  </thead>
  <tbody id="orders-accordion">
    <?php while ($o = $res_orders->fetch_assoc()): ?>
    <tr>
      <td><?= $o['id'] ?></td>
      <td><?= htmlspecialchars($o['table_number']) ?></td>
      <td><?= htmlspecialchars($o['customer_name']) ?></td>
      <td><?= htmlspecialchars($o['customer_email']) ?></td>
      <td><?= $o['order_date'] ?></td>
      <td><?= number_format($o['total_price'],0,',','.') ?> ₫</td>
      <td>
        <?php if ($o['status']==='pending'): ?>
          <span class="badge bg-warning text-dark">Pending</span>
        <?php else: ?>
          <span class="badge bg-success">Confirmed</span>
        <?php endif; ?>
      </td>
      <td class="d-flex flex-wrap gap-1">
        <?php if ($o['status']==='pending'): ?>
          <a href="admin.php?page=orders&confirm_id=<?= $o['id'] ?>&pg=<?= $page ?>&status=<?= $statusFilter ?>"
             class="btn btn-sm btn-success">Confirm</a>
        <?php else: ?>
          <a href="admin.php?page=orders&undo_id=<?= $o['id'] ?>&pg=<?= $page ?>&status=<?= $statusFilter ?>"
             class="btn btn-sm btn-warning">Undo</a>
        <?php endif; ?>
        <button class="btn btn-sm btn-info"
                data-bs-toggle="collapse"
                data-bs-target="#details-<?= $o['id'] ?>"
                aria-expanded="false">
          View
        </button>
      </td>
    </tr>
    <tr>
      <td colspan="8" class="p-0 border-0">
        <div id="details-<?= $o['id'] ?>"
             class="collapse"
             data-bs-parent="#orders-accordion">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Dish</th><th>Qty</th>
                <th>Unit Price</th><th>Line Total</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $stmt2 = $conn->prepare("
                SELECT i.name, oi.quantity, i.price, oi.total_price
                FROM order_items oi
                JOIN items i ON oi.item_id=i.id
                WHERE oi.order_id=?
              ");
              $stmt2->bind_param("i", $o['id']);
              $stmt2->execute();
              $items = $stmt2->get_result();
              while ($it = $items->fetch_assoc()):
            ?>
              <tr>
                <td><?= htmlspecialchars($it['name']) ?></td>
                <td><?= intval($it['quantity']) ?></td>
                <td><?= number_format($it['price'],0,',','.') ?> ₫</td>
                <td><?= number_format($it['total_price'],0,',','.') ?> ₫</td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<!-- Pagination -->
<nav aria-label="Orders pagination">
  <ul class="pagination justify-content-center">
    <li class="page-item <?= $page<=1?'disabled':'' ?>">
      <a class="page-link"
         href="?page=orders&pg=<?= $page-1 ?>&status=<?= $statusFilter ?>">Previous</a>
    </li>
    <?php for($p=1; $p<=$totalPages; $p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>">
        <a class="page-link"
           href="?page=orders&pg=<?= $p ?>&status=<?= $statusFilter ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
      <a class="page-link"
         href="?page=orders&pg=<?= $page+1 ?>&status=<?= $statusFilter ?>">Next</a>
    </li>
  </ul>
</nav>

<!-- Chỉ include Bootstrap JS một lần trong admin.php -->

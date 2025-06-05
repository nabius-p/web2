<?php

include 'db.php';

// 1) Bảo vệ trang
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// 2) Phân trang & tìm kiếm
$page_size = 10;
$page_num  = max(1, intval($_GET['p'] ?? 1));
$offset    = ($page_num - 1) * $page_size;
$search    = trim($_GET['q'] ?? '');

// 3) Đếm tổng số khách duy nhất
if ($search !== '') {
    $like = '%' . $conn->real_escape_string($search) . '%';
    $qr = $conn->prepare("
      SELECT COUNT(DISTINCT customer_email)
        FROM invoices
       WHERE customer_name LIKE ?
    ");
    $qr->bind_param("s", $like);
    $qr->execute();
    $qr->bind_result($total_customers);
    $qr->fetch();
    $qr->close();
} else {
    $res            = $conn->query("SELECT COUNT(DISTINCT customer_email) FROM invoices");
    $total_customers = (int)$res->fetch_row()[0];
}

// 4) Lấy danh sách khách + aggregate, luôn lấy customer_type của hóa đơn mới nhất
$sql = "
  SELECT
    inv.customer_name,
    inv.customer_email,
    MAX(inv.created_at)   AS last_order,
    SUM(inv.total_amount) AS total_spent,
    SUBSTRING_INDEX(
      GROUP_CONCAT(inv.customer_type ORDER BY inv.created_at DESC SEPARATOR ','),
      ',', 1
    ) AS customer_type
  FROM invoices inv
";
if ($search !== '') {
    $sql .= " WHERE inv.customer_name LIKE ? ";
}
$sql .= "
  GROUP BY inv.customer_email
  ORDER BY last_order DESC
  LIMIT ?, ?
";

$stmt = $conn->prepare($sql);

// bind parameters
if ($search !== '') {
    $stmt->bind_param("sii", $like, $offset, $page_size);
} else {
    $stmt->bind_param("ii", $offset, $page_size);
}

$stmt->execute();
$customers = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin View Customers | ShinHot Pot</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   <style>
    body { font-family:'Nunito',sans-serif; background:#f8f9fa; }
    main { padding:2rem; /* đã bỏ max-width */ }
    .avatar-sm { /* … */ }
    .table thead th { /* … */ }
    .table tbody tr { /* … */ }
  </style>
</head>
<body>
  <div class="container-fluid px-4">
    <div class="row">
      <main class="col-12">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
      <div>
        <h6 class="text-muted mb-1">Total Customers</h6>
        <div class="d-flex align-items-center">
          <h1 class="me-3 mb-0"><?= $total_customers ?></h1>
          <span class="fs-1 text-warning"><i class="fa fa-user-friends"></i></span>
        </div>
        <small class="text-muted">Total: <?= $total_customers ?> customers</small>
      </div>
      <form class="d-flex" method="get" action="">
        <input type="hidden" name="page" value="revenue_customers">
        <input class="form-control form-control-sm me-2" 
               type="search" name="q" placeholder="Search by name"
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-sm btn-primary" type="submit">
          <i class="fa fa-search"></i>
        </button>
      </form>
    </div>

    <!-- Customers Table -->
    <div class="table-responsive mb-4">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th></th>
            <th>Name</th>
            <th>Email</th>
            <th>Last Order</th>
            <th class="text-end">Total Spent</th>
            <th>Type</th>
            <th>Edit</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($customers->num_rows === 0): ?>
            <tr>
              <td colspan="7" class="text-center py-4">No customers found.</td>
            </tr>
          <?php else: ?>
            <?php while ($c = $customers->fetch_assoc()): ?>
              <tr>
                <td><img src="https://via.placeholder.com/32" class="avatar-sm" alt=""></td>
                <td><?= htmlspecialchars($c['customer_name']) ?></td>
                <td><?= htmlspecialchars($c['customer_email']) ?></td>
                <td><?= date('d-m-Y H:i', strtotime($c['last_order'])) ?></td>
                <td class="text-end"><?= number_format($c['total_spent'],0,',','.') ?> ₫</td>
                <td>
                  <?php
                    $type = $c['customer_type'];
                    $badge = 'bg-secondary text-white';
                    if ($type === 'vip')       $badge = 'bg-warning text-dark';
                    elseif ($type === 'potential') $badge = 'bg-info text-dark';
                  ?>
                  <span class="badge <?= $badge ?>">
                    <?= strtoupper(htmlspecialchars($type)) ?>
                  </span>
                </td>
                <td>
                  <a href="admin.php?page=revenue_customer_detail&email=<?= urlencode($c['customer_email']) ?>"
                     class="text-secondary">
                    <i class="fa fa-edit"></i>
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Load More -->
    <?php if ($offset + $page_size < $total_customers): ?>
      <div class="text-center mb-5">
        <a href="?page=revenue_customers&p=<?= $page_num+1 ?>&q=<?= urlencode($search) ?>"
           class="btn btn-outline-primary">
          Load More
        </a>
      </div>
    <?php endif; ?>
  </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

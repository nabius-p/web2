<?php
session_start();
if (empty($_SESSION['staff_logged_in'])) {
    header('Location: login.php');
    exit();
}

// 1. KẾT NỐI DATABASE
require_once 'db.php';

// 2. KIỂM TRA SỰ TỒN TẠI CỦA BẢNG `items`
$hasItemsTable = false;
$checkItemsSql = "SHOW TABLES LIKE 'items'";
if ($checkResult = $conn->query($checkItemsSql)) {
    if ($checkResult->num_rows > 0) {
        $hasItemsTable = true;
    }
    $checkResult->close();
}

// 3. KIỂM TRA SỰ TỒN TẠI CỦA BẢNG `inventory_log` (dùng để tính amount + latest_date)
$hasInventoryLog = false;
$checkLogSql = "SHOW TABLES LIKE 'inventory_log'";
if ($checkResult2 = $conn->query($checkLogSql)) {
    if ($checkResult2->num_rows > 0) {
        $hasInventoryLog = true;
    }
    $checkResult2->close();
}

// 4. LẤY DỮ LIỆU TỪ BẢNG `items` (và `inventory_log` nếu có)
$items = [];
if ($hasItemsTable) {
    if ($hasInventoryLog) {
        // Nếu có bảng inventory_log, lấy cả amount (SUM(quantity)) và latest_date (MAX(imported_at))
        $sql = "
            SELECT
                i.id,
                i.name,
                i.category,
                i.price,
                i.image_url,
                COALESCE(SUM(il.quantity), 0) AS amount,
                MAX(il.imported_at) AS latest_date
            FROM items i
            LEFT JOIN inventory_log il ON il.item_id = i.id
            GROUP BY i.id
            ORDER BY i.name ASC
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            $stmt->close();
        }
    } else {
        // Nếu không có bảng inventory_log, chỉ lấy từ items, gán amount = 0, latest_date = NULL
        $sql = "SELECT id, name, category, price, image_url FROM items ORDER BY name ASC";
        if ($result = $conn->query($sql)) {
            while ($row = $result->fetch_assoc()) {
                $row['amount'] = 0;
                $row['latest_date'] = null;
                $items[] = $row;
            }
            $result->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Menu | ShinHot Pot</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Google Fonts & Font Awesome & Bootstrap CSS -->
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Nunito', sans-serif;
      background: #f8f9fa;
    }
    /* Sidebar */
    .sidebar {
      min-height: 100vh;
      background: #fff;
      border-right: 1px solid #ddd;
      padding-top: 1rem;
      position: fixed;
      left: 0;
      top: 0;
      width: 220px;
      z-index: 100;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .sidebar .logo {
      font-family: 'Pacifico', cursive;
      color: #fea116;
      padding: .5rem 1rem;
      font-size: 1.5rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .sidebar .nav-link {
      color: #333;
      padding: .75rem 1.5rem;
      display: flex;
      align-items: center;
      gap: .75rem;
      border-radius: .35rem;
      margin-bottom: .25rem;
      font-weight: 500;
      transition: background .2s;
    }
    .sidebar .nav-link.active,
    .sidebar .nav-link:hover {
      background: #ffd54f;
      color: #fff;
    }
    .sidebar .logout {
      color: #888;
      padding: .75rem 1.5rem;
      display: block;
      text-align: left;
      border: none;
      background: none;
      font-size: 1rem;
    }
    .sidebar .logout:hover {
      color: #d32f2f;
    }
    .sidebar .staff-avatar {
      width: 38px;
      height: 38px;
      background: #ffd54f;
      color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.2rem;
      margin: 0 1rem 1rem 1rem;
    }
    /* Main content */
    .main-content {
      margin-left: 220px;
      padding: 2.5rem 2rem;
    }
    /* Container bảng */
    .menu-table-container {
      background: #fff;
      border-radius: .75rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
      padding: 2rem 2rem 1.5rem 2rem;
    }
    /* Tiêu đề thead */
    .menu-table thead th {
      background: transparent;
      font-weight: 700;
      color: #888;
      border-bottom: 2px solid #eee;
      font-size: .98rem;
    }
    /* Dòng tbody */
    .menu-table tbody td {
      vertical-align: middle;
      font-size: .97rem;
    }
    .menu-table tbody tr {
      border-bottom: 1px solid #f1f1f1;
    }
    /* Status màu xanh/đỏ */
    .menu-table .status-in {
      color: #43a047;
      font-weight: 600;
    }
    .menu-table .status-out {
      color: #d32f2f;
      font-weight: 600;
    }
    /* Nút Edit */
    .menu-table .edit-btn {
      color: #fea116;
      font-size: 1.1rem;
      cursor: pointer;
      background: none;
      border: none;
    }
    .menu-table .edit-btn:hover {
      color: #d32f2f;
    }
    /* Search bar (tạm thời disabled) */
    .search-bar {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: 1.5rem;
    }
    .search-bar input {
      border-radius: .5rem;
      border: 1px solid #ddd;
      padding: .5rem 1rem;
      width: 260px;
      font-size: 1rem;
    }
    .search-bar button {
      border-radius: .5rem;
      background: #ffd54f;
      color: #222;
      font-weight: 600;
      border: none;
      padding: .5rem 1.5rem;
    }
    /* Thanh cuộn ngang */
    .table-responsive::-webkit-scrollbar {
      height: 8px;
    }
    .table-responsive::-webkit-scrollbar-thumb {
      background: #eee;
      border-radius: 4px;
    }
    /* Responsive */
    @media (max-width: 991px) {
      .main-content {
        margin-left: 0;
        padding: 1rem;
      }
      .sidebar {
        position: static;
        width: 100vw;
        min-height: auto;
      }
      .menu-table-container {
        padding: 1rem;
      }
    }
    /* Ảnh item */
    .menu-table img {
      width: 48px;
      height: 48px;
      object-fit: cover;
      border-radius: .5rem;
      border: 1px solid #eee;
    }
    /* Căn giữa text trong bảng, trừ cột đầu và cột 3 */
    .menu-table td,
    .menu-table th {
      text-align: center;
    }
    .menu-table td:first-child,
    .menu-table th:first-child {
      text-align: left;
    }
    .menu-table td:nth-child(3),
    .menu-table th:nth-child(3) {
      text-align: left;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div>
      <div class="logo"><i class="fas fa-user"></i> Staff</div>
      <div class="staff-avatar">S</div>
      <nav>
        <a href="staff_view.php" class="nav-link"><i class="fas fa-table"></i>Tables</a>
        <a href="staff_menu.php" class="nav-link active"><i class="fas fa-utensils"></i>Menu</a>
      </nav>
    </div>
    <form method="post" action="logout.php">
      <button class="logout"><i class="fas fa-sign-out-alt"></i> Log out</button>
    </form>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="menu-table-container">
      <!-- Search Bar (để disabled tạm) -->
      <div class="search-bar">
        <input type="text" class="form-control" placeholder="Search by name or category" disabled>
        <button disabled>Filter</button>
      </div>

      <!-- Bảng hiển thị items -->
      <div class="table-responsive">
        <table class="table menu-table align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Image</th>
              <th>Category</th>
              <th>Amount</th>
              <th>Price</th>
              <th>Latest Import</th>
              <th>Status</th>
              <th>Edit</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$hasItemsTable): ?>
              <!-- Nếu không có bảng items -->
              <tr>
                <td colspan="9" class="text-center text-muted py-4">
                  (Không tìm thấy bảng <strong>items</strong> trong cơ sở dữ liệu.)
                </td>
              </tr>
            <?php elseif (empty($items)): ?>
              <!-- Nếu bảng items tồn tại nhưng không có bản ghi -->
              <tr>
                <td colspan="9" class="text-center text-muted py-4">
                  Hiện chưa có item nào trong bảng <strong>items</strong>.
                </td>
              </tr>
            <?php else: ?>
              <?php
                $stt = 1;
                foreach ($items as $item):
                  // Xác định trạng thái dựa trên amount
                  $amt = intval($item['amount']);
                  if ($amt <= 0) {
                      $statusText  = 'Out of stock';
                      $statusClass = 'status-out';
                  } else {
                      $statusText  = 'In stock';
                      $statusClass = 'status-in';
                  }
                  // Ảnh item
                  $imgUrl = htmlspecialchars($item['image_url'] ?? '', ENT_QUOTES);
                  // Định dạng latest_date
                  $latestDate = $item['latest_date']
                                ? date('d/m/Y', strtotime($item['latest_date']))
                                : '-';
              ?>
                <tr>
                  <td><?= $stt++ ?></td>
                  <td><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></td>
                  <td>
                    <?php if (!empty($imgUrl)): ?>
                      <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>">
                    <?php else: ?>
                      <span class="text-muted">No Image</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($item['category'], ENT_QUOTES) ?></td>
                  <td><?= $amt ?></td>
                  <td><?= number_format($item['price'], 0, '.', ',') ?>₫</td>
                  <td><?= $latestDate ?></td>
                  <td>
                    <span class="<?= $statusClass ?>"><?= $statusText ?></span>
                  </td>
                  <td>
                    <!-- Nút Edit hiện đang disabled; nếu muốn, bạn có thể mở lại và dẫn tới trang chỉnh sửa -->
                    <button class="edit-btn" title="Edit" disabled>
                      <i class="fas fa-pen"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Footer: Hiển thị số lượng bản ghi & Pagination mẫu -->
      <div class="d-flex align-items-center justify-content-between mt-3">
        <div>
          <?php if ($hasItemsTable): ?>
            <?= count($items) ?> item<?= (count($items) > 1 ? 's' : '') ?> shown
          <?php endif; ?>
        </div>
        <nav>
          <ul class="pagination mb-0">
            <!-- Ví dụ phân trang tĩnh; nếu muốn phân trang thực, cần thêm LIMIT/OFFSET trong SQL -->
            <li class="page-item active"><span class="page-link">1</span></li>
            <li class="page-item"><a class="page-link" href="#">2</a></li>
            <li class="page-item"><a class="page-link" href="#">3</a></li>
            <li class="page-item"><a class="page-link" href="#">4</a></li>
          </ul>
        </nav>
        <button class="btn btn-light border px-4" onclick="window.history.back()">Back</button>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

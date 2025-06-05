<?php
session_start();
if (empty($_SESSION['staff_logged_in'])) {
    header('Location: login.php');
    exit();
}

require_once 'db.php'; // Kết nối database, biến $conn là mysqli

// --- 1. Lấy danh sách bàn đang occupied từ bảng `restaurant_tables` ---
$tables = [];
$sql_tables = "SELECT id, table_number FROM restaurant_tables WHERE status = 'occupied'";
if ($result_tables = $conn->query($sql_tables)) {
    while ($row = $result_tables->fetch_assoc()) {
        $tables[] = [
            'id'   => (int)$row['id'],
            'name' => 'Table #' . str_pad($row['table_number'], 2, '0', STR_PAD_LEFT)
        ];
    }
    $result_tables->free();
}

// --- 2. Kiểm tra xem bảng `invoice_items` có tồn tại không ---
// (vì chúng ta sẽ dùng invoice_items để thay cho order_items cũ)
$hasInvoiceItems = false;
$checkInvoiceItemsSql = "SHOW TABLES LIKE 'invoice_items'";
if ($resInvoiceCheck = $conn->query($checkInvoiceItemsSql)) {
    if ($resInvoiceCheck->num_rows > 0) {
        $hasInvoiceItems = true;
    }
    $resInvoiceCheck->free();
}

// --- 3. Với mỗi bàn, lấy order (invoice) mới nhất rồi lấy danh sách món từ invoice_items JOIN items ---
foreach ($tables as &$table) {
    $table_id = $table['id'];
    $dishes = [];

    // 3.1. Lấy order (invoice) mới nhất cho bàn này
    $sql_order = "SELECT id FROM invoices WHERE table_id = ? ORDER BY created_at DESC LIMIT 1";
    if ($stmt = $conn->prepare($sql_order)) {
        $stmt->bind_param("i", $table_id);
        $stmt->execute();
        $stmt->bind_result($invoice_id);
        if ($stmt->fetch()) {
            $stmt->close();

            // 3.2. Nếu có bảng invoice_items, lấy danh sách món từ đó
            if ($hasInvoiceItems) {
                $sql_items = "
                    SELECT i.name AS item_name
                    FROM invoice_items ii
                    JOIN items i ON ii.item_id = i.id
                    WHERE ii.invoice_id = ? AND ii.status = 'completed'
                ";
                if ($stmt2 = $conn->prepare($sql_items)) {
                    $stmt2->bind_param("i", $invoice_id);
                    $stmt2->execute();
                    $result_items = $stmt2->get_result();
                    while ($row2 = $result_items->fetch_assoc()) {
                        $dishes[] = $row2['item_name'];
                    }
                    $stmt2->close();
                }
            }
        } else {
            // Nếu không có order nào cho bàn, chỉ đóng và tiếp tục
            $stmt->close();
        }
    }
    $table['dishes'] = $dishes;
}
unset($table);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Tables | ShinHot Pot</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {font-family:'Nunito',sans-serif; background:#f8f9fa;}
    .sidebar{
      min-height:100vh;background:#fff;border-right:1px solid #ddd;padding-top:1rem;
      position:fixed;left:0;top:0;width:220px;z-index:100;
      display:flex;flex-direction:column;justify-content:space-between;
    }
    .sidebar .logo{
      font-family:'Pacifico',cursive;color:#fea116;padding:.5rem 1rem;font-size:1.5rem;
      display:flex;align-items:center;gap:.5rem;
    }
    .sidebar .nav-link{
      color:#333;padding:.75rem 1.5rem;display:flex;align-items:center;gap:.75rem;
      border-radius:.35rem;margin-bottom:.25rem;font-weight:500;
      transition:background .2s;
    }
    .sidebar .nav-link.active,.sidebar .nav-link:hover{
      background:#ffd54f;color:#fff;
    }
    .sidebar .logout{
      color:#888;padding:.75rem 1.5rem;display:block;text-align:left;
      border:none;background:none;font-size:1rem;
    }
    .sidebar .logout:hover{color:#d32f2f;}
    .sidebar .staff-avatar{
      width:38px;height:38px;background:#ffd54f;color:#fff;
      border-radius:50%;display:flex;align-items:center;justify-content:center;
      font-weight:700;font-size:1.2rem;margin:0 1rem 1rem 1rem;
    }
    .main-content{
      margin-left:220px;padding:2.5rem 2rem;
    }
    .table-card{
      background:#fff;border-radius:.75rem;box-shadow:0 2px 10px rgba(0,0,0,0.06);
      padding:1.5rem;margin-bottom:2rem;min-width:260px;
      display:flex;flex-direction:column;gap:.5rem;
    }
    .table-card .table-title{
      font-weight:600;font-size:1.1rem;margin-bottom:.5rem;
      display:flex;justify-content:space-between;align-items:center;
    }
    .dish-switch{
      display:flex;align-items:center;justify-content:space-between;
      margin-bottom:.5rem;
    }
    .dish-switch label{margin-bottom:0;}
    .ready-btn, .serving-btn{
      margin-top:.75rem;
      width:100%;border-radius:.5rem;
      background:#fea116;color:#fff;font-weight:600;
      border:none;padding:.6rem 0;transition:background .2s;
      font-size:1.1rem;
    }
    .ready-btn.ready{background:#43a047;}
    .ready-btn.done, .serving-btn.done{background:#d32f2f;}
    .ready-btn:active{transform:scale(.98);}
    @media (max-width:991px){
      .main-content{margin-left:0;padding:1rem;}
      .sidebar{position:static;width:100vw;min-height:auto;}
    }
    .form-check-input:checked {
      background-color: #43a047;
      border-color: #43a047;
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <div>
      <div class="logo"><i class="fas fa-user"></i> Staff</div>
      <div class="staff-avatar">S</div>
      <nav>
        <a href="staff_view.php" class="nav-link active"><i class="fas fa-table"></i>Tables</a>
        <a href="staff_menu.php" class="nav-link"><i class="fas fa-utensils"></i>Menu</a>
      </nav>
    </div>
    <form method="post" action="logout.php">
      <button class="logout"><i class="fas fa-sign-out-alt"></i> Log out</button>
    </form>
  </div>
  <div class="main-content">
    <div class="row g-4">
      <?php if (empty($tables)): ?>
        <div class="col-12">
          <div class="text-center text-muted py-5">
            Hiện không có bàn nào đang occupied.
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($tables as $table): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="table-card" data-table="<?= htmlspecialchars($table['id'], ENT_QUOTES) ?>">
            <div class="table-title">
              <?= htmlspecialchars($table['name'], ENT_QUOTES) ?>
              <select class="form-select form-select-sm w-auto" style="font-size:.95rem;">
                <option><?= htmlspecialchars($table['name'], ENT_QUOTES) ?></option>
              </select>
            </div>
            <?php if (!empty($table['dishes'])): ?>
              <div class="dishes-list">
                <?php foreach ($table['dishes'] as $dish): ?>
                <div class="dish-switch">
                  <span><?= htmlspecialchars($dish, ENT_QUOTES) ?></span>
                  <div class="form-check form-switch m-0">
                    <input class="form-check-input dish-checkbox" type="checkbox">
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <button class="ready-btn not-ready" type="button">Not Ready to Serve</button>
              <!-- Nút này chỉ để bạn test chức năng thêm món, khi thực tế sẽ tự động reload từ DB -->
              <button class="btn btn-link btn-sm add-dish-btn" type="button" style="color:#fea116;">+ Thêm món (test)</button>
            <?php else: ?>
              <div class="text-muted text-center py-4">Chưa có order nào</div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
document.querySelectorAll('.table-card').forEach(function(card) {
  const checkboxes = card.querySelectorAll('.dish-checkbox');
  const btn = card.querySelector('.ready-btn');
  const addDishBtn = card.querySelector('.add-dish-btn');
  // Ban đầu: tất cả switch đều chưa tick, nút là Not Ready to Serve
  checkboxes.forEach(cb => cb.checked = false);

  function updateButton() {
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    if (btn.classList.contains('done')) return; // Nếu đã done thì không đổi nữa
    if (allChecked) {
      btn.textContent = 'Ready to Serve';
      btn.classList.remove('not-ready');
      btn.classList.add('ready');
      btn.disabled = false;
    } else {
      btn.textContent = 'Not Ready to Serve';
      btn.classList.remove('ready');
      btn.classList.add('not-ready');
      btn.disabled = false;
    }
  }
  checkboxes.forEach(cb => cb.addEventListener('change', updateButton));
  updateButton();

  btn.addEventListener('click', function() {
    if (btn.classList.contains('ready')) {
      btn.textContent = 'Done';
      btn.classList.remove('ready', 'not-ready');
      btn.classList.add('done');
      btn.disabled = true;
    }
  });

  // Demo: Thêm món mới (giả lập khách order thêm)
  if (addDishBtn) {
    addDishBtn.addEventListener('click', function() {
      // Thêm một món mới vào danh sách
      const newDish = document.createElement('div');
      newDish.className = 'dish-switch';
      newDish.innerHTML = `
        <span>Món mới (test)</span>
        <div class="form-check form-switch m-0">
          <input class="form-check-input dish-checkbox" type="checkbox">
        </div>
      `;
      card.querySelector('.dishes-list').appendChild(newDish);
      // Cập nhật lại checkbox và trạng thái nút
      const newCb = newDish.querySelector('.dish-checkbox');
      newCb.checked = false;
      newCb.addEventListener('change', updateButton);
      // Nếu đang là done thì chuyển lại not ready
      btn.textContent = 'Not Ready to Serve';
      btn.classList.remove('done', 'ready');
      btn.classList.add('not-ready');
      btn.disabled = false;
    });
  }
});
  </script>
</body>
</html>

<?php
// admin_table_list.php
// Trang quản trị: Danh sách bàn và hiển thị đơn gọi món trên mỗi bàn

require 'db.php';
session_start();
// Kiểm tra phiên admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Lấy dữ liệu 20 bàn
$tables = [];
for ($i = 1; $i <= 20; $i++) {
    $tables[$i] = [
        'number' => $i,
        'status' => 'available',
        'invoice_id' => null,
        'items' => [],
        'total' => 0
    ];
    // Lấy hóa đơn mới nhất tại bàn này
    $stmtInv = $conn->prepare(
        "SELECT id, total_price FROM invoices WHERE table_number = ? ORDER BY id DESC LIMIT 1"
    );
    $stmtInv->bind_param('i', $i);
    $stmtInv->execute();
    $resInv = $stmtInv->get_result()->fetch_assoc();
    if ($resInv) {
        $tables[$i]['status'] = 'occupied';
        $tables[$i]['invoice_id'] = $resInv['id'];
        $tables[$i]['total'] = $resInv['total_price'];
        // Lấy các món cho invoice này
        $stmtItems = $conn->prepare(
            "SELECT it.name, oi.quantity, oi.unit_price
             FROM invoice_items oi
             JOIN items it ON oi.item_id = it.id
             WHERE oi.invoice_id = ?"
        );
        $stmtItems->bind_param('i', $resInv['id']);
        $stmtItems->execute();
        $resItems = $stmtItems->get_result();
        while ($row = $resItems->fetch_assoc()) {
            $tables[$i]['items'][] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Danh sách bàn</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
      .table-card { width: 100px; height: 100px; cursor: pointer; margin: 8px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
      .occupied { background-color: #FFA500; color: #fff; }
      .available { border: 2px dashed #FFA500; color: #555; }
    </style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <?php include 'admin_sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
      <h2>Table List</h2>

      <div class="d-flex flex-wrap">
        <?php foreach ($tables as $tbl): ?>
          <div class="table-card <?php echo $tbl['status']; ?>" onclick="showTableDetails(<?php echo $tbl['number']; ?>)">
            <?php echo str_pad($tbl['number'], 2, '0', STR_PAD_LEFT); ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div id="table-details" class="mt-4 px-3">
        <p>Chọn bàn để xem chi tiết đơn hàng.</p>
      </div>
    </main>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script>
  const tables = <?php echo json_encode($tables); ?>;
  function showTableDetails(tableNum) {
    const tbl = tables[tableNum];
    let html = `<h4>Bàn #${('0'+tableNum).slice(-2)}</h4>`;
    if (tbl.invoice_id) {
      html += '<ul class="list-group mb-2">';
      tbl.items.forEach(item => {
        html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                   ${item.name} x${item.quantity}
                   <span>${parseFloat(item.unit_price).toLocaleString('vi-VN')}₫</span>
                 </li>`;
      });
      html += '</ul>';
      html += `<p><strong>Tổng:</strong> ${parseFloat(tbl.total).toLocaleString('vi-VN')}₫</p>`;
      html += '<button class="btn btn-success">Serving</button>';
    } else {
      html += '<p>Bàn trống hoặc chưa có đơn hàng.</p>';
    }
    document.getElementById('table-details').innerHTML = html;
  }
</script>
</body>
</html>

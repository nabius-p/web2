<?php
// ──────────────────────────────────────────────────────────────────────────────
// File: admin_table_list.php
// Mục đích: Hiển thị lưới 12 bàn, 4 bàn mỗi hàng, với trạng thái “Available” hoặc “Occupied”
//          Khi bàn “Occupied” được click, hiển thị chi tiết order + nút “Payment” để đóng bàn.
// Được include bên trong admin.php?page=table_list
// ──────────────────────────────────────────────────────────────────────────────

include 'db.php';

// 1) Xử lý khi bấm nút “Payment” (đóng bàn)
//    Nếu có POST['close_table'], tức user muốn đóng bàn đó:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_table'])) {
    $tableId = intval($_POST['close_table']);

    // Lấy invoice gần nhất của bàn này (nếu có)
    $stmtInv = $conn->prepare("
        SELECT id
        FROM invoices
        WHERE table_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtInv->bind_param('i', $tableId);
    $stmtInv->execute();
    $resInv = $stmtInv->get_result();
    $inv = $resInv->fetch_assoc();
    $stmtInv->close();

    if ($inv) {
        $invoiceId = intval($inv['id']);

        // Xóa các hàng từ invoice_items cho invoiceId này
        $stmtDelItems = $conn->prepare("
            DELETE FROM invoice_items
            WHERE invoice_id = ?
        ");
        $stmtDelItems->bind_param('i', $invoiceId);
        $stmtDelItems->execute();
        $stmtDelItems->close();

        // Xóa bản ghi trong invoices
        $stmtDelInv = $conn->prepare("
            DELETE FROM invoices
            WHERE id = ?
        ");
        $stmtDelInv->bind_param('i', $invoiceId);
        $stmtDelInv->execute();
        $stmtDelInv->close();

         // ===> CHỈNH SỬA Ở ĐÂY: cập nhật trạng thái bàn trở lại 'open'
        $stmtUpdate = $conn->prepare("
            UPDATE restaurant_tables
            SET status = 'open'
            WHERE id = ?
        ");
        $stmtUpdate->bind_param('i', $tableId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }

    // Sau khi xóa xong, chuyển hướng về trang Table List để làm mới giao diện
    header('Location: admin.php?page=table_list');
    exit;
}

// 2) Kiểm tra login (session đã được start trong admin.php)
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// 3) Tạo mảng $tables cho 12 bàn
$tables = [];
for ($i = 1; $i <= 12; $i++) {
    $tables[$i] = [
        'number'      => $i,
        'status'      => 'available',  // mặc định “available”
        'invoice_id'  => null,
        'items'       => [],           // danh sách món (nếu có)
        'total'       => 0,
        'people'      => 0,
        'created_at'  => null,
    ];

    // Lấy hóa đơn gần nhất cho bàn $i
    $stmtInv = $conn->prepare("
        SELECT id, total_amount, created_at
        FROM invoices
        WHERE table_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtInv->bind_param('i', $i);
    $stmtInv->execute();
    $invResult = $stmtInv->get_result();
    $inv = $invResult->fetch_assoc();
    $stmtInv->close();

    if ($inv) {
        // Nếu có invoice, đánh dấu “occupied”
        $tables[$i]['status']     = 'occupied';
        $tables[$i]['invoice_id'] = $inv['id'];
        $tables[$i]['total']      = $inv['total_amount'];
        $tables[$i]['created_at'] = $inv['created_at'];

        // Lấy chi tiết món cho invoice đó
        $stmtItems = $conn->prepare("
            SELECT ii.quantity, ii.price, it.name
            FROM invoice_items AS ii
            JOIN items AS it ON ii.item_id = it.id
            WHERE ii.invoice_id = ?
              AND ii.status = 'completed'
        ");
        $stmtItems->bind_param('i', $inv['id']);
        $stmtItems->execute();
        $resItems = $stmtItems->get_result();

        $peopleCount = 0;
        while ($row = $resItems->fetch_assoc()) {
            $tables[$i]['items'][] = $row;
            $peopleCount += intval($row['quantity']);
        }
        $tables[$i]['people'] = $peopleCount;
        $stmtItems->close();
    }
}

// 4) Chuyển $tables sang JSON để JS sử dụng
$tables_json = json_encode($tables, JSON_UNESCAPED_UNICODE);
?>

<!--──────────────────────────────────────────────────────────────────────────────
  CSS chính
──────────────────────────────────────────────────────────────────────────────-->
<style>
  /* Font và màu nền chung */
  .table‐list‐container {
    font-family: 'Nunito', sans-serif;
    color: #333;
  }

  /* Legend “Serving” / “Available” */
  .legend-container {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  .legend-box {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
  }
  .legend-box .box {
    width: 20px;
    height: 20px;
    border-radius: 4px;
  }
  .legend-box .box.occupied {
    background: #FFA500; /* solid orange */
  }
  .legend-box .box.available {
    background: #fff;
    border: 2px dashed #FFA500;
  }

  /* Lưới 4 cột (mỗi cột 1/4 chiều rộng) */
  .table-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
  }

  /* Thẻ bàn chung: luôn vuông, bo góc */
  .table-card {
    position: relative;
    aspect-ratio: 1 / 1;       /* luôn 1:1 */
    border-radius: 8px;
    cursor: pointer;
    user-select: none;
    overflow: hidden;
    transition: transform 0.1s;
  }
  .table-card:hover {
    transform: scale(1.03);
  }

  /* Bàn “Available”: viền dashed, nền trắng */
  .table-card.available {
    background: #fff;
    border: 2px dashed #FFA500;
    color: #555;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
  }
  .table-card.available .badge-open {
    position: absolute;
    top: 6px;
    left: 50%;
    transform: translateX(-50%);
    background: #FFA500;
    color: #fff;
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 4px;
  }

  /* Bàn “Occupied”: nền cam, chia 3 cột nội bộ */
  .table-card.occupied {
    background: #FFA500;
    color: #fff;
    display: grid;
    grid-template-columns: 1fr 1px 1fr; /* [số bàn] | [divider] | [info] */
    grid-template-rows: 1fr;
  }
  .table-card.occupied .card-left {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
  }
  .table-card.occupied .card-divider {
    background: #fff;
    width: 1px;
    margin: 8px 0;
  }
  .table-card.occupied .card-right {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    line-height: 1.1;
  }
  .table-card.occupied .card-right .people {
    font-weight: bold;
  }
  .table-card.occupied .card-right .seated {
    margin-top: 2px;
  }
  .table-card.occupied .card-right .timer {
    margin-top: 4px;
    font-size: 0.60rem;
  }

  /* Sidebar chi tiết order */
  #table-details {
    background: #fff;
    padding: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    min-height: 300px;
  }
  #table-details h4 {
    margin-bottom: 1rem;
    font-weight: 600;
  }
  .order-item {
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.95rem;
  }
  #table-details .total-text {
    font-size: 1rem;
    margin-top: 1rem;
    font-weight: 500;
  }
  /* Nút Payment */
  #table-details .btn-payment {
    background: #FFA500;
    border: none;
    width: 100%;
    margin-top: 1rem;
    font-weight: 600;
    color: #fff;
  }
  #table-details .btn-payment:hover {
    background: #e59400;
  }
</style>

<!--──────────────────────────────────────────────────────────────────────────────
  Nội dung HTML + JS
──────────────────────────────────────────────────────────────────────────────-->
<div class="row table‐list‐container">
  <!-- Cột trái (8/12): lưới 4 cột -->
  <div class="col-lg-8">
    <h2 class="mb-4">TABLE LIST</h2>

    <!-- Legend -->
    <div class="legend-container">
      <div class="legend-box">
        <div class="box occupied"></div>
        <span>Serving</span>
      </div>
      <div class="legend-box">
        <div class="box available"></div>
        <span>Available</span>
      </div>
    </div>

    <!-- Grid 4 cột -->
    <div class="table-grid">
      <?php foreach ($tables as $tbl):
          $numStr = str_pad($tbl['number'], 2, '0', STR_PAD_LEFT);
          $isOccupied = ($tbl['status'] === 'occupied');
      ?>
        <?php if (!$isOccupied): ?>
          <!-- BÀN “Available” -->
          <div class="table-card available"
               onclick="showTableDetails(<?= $tbl['number'] ?>)">
            <div class="badge-open">Open</div>
            <span><?= $numStr ?></span>
          </div>
        <?php else: ?>
          <!-- BÀN “Occupied” -->
          <div class="table-card occupied"
               onclick="showTableDetails(<?= $tbl['number'] ?>)">
            <div class="card-left"><?= $numStr ?></div>
            <div class="card-divider"></div>
            <div class="card-right">
              <div class="people">People <?= $tbl['people'] ?></div>
              <div class="seated">Seated</div>
              <div class="timer" id="timer-<?= $tbl['number'] ?>">00:00:00</div>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Cột phải (4/12): sidebar chi tiết order -->
  <div class="col-lg-4">
    <div id="table-details">
      <p class="text-muted">Select a table to view order details.</p>
    </div>
  </div>
</div>

<script>
  // Chuyển PHP $tables → JS
  const tables = <?= $tables_json ?>;

  // Hàm format giây → "HH:MM:SS"
  function secondsToHHMMSS(sec) {
    let hrs = Math.floor(sec / 3600);
    let rem = sec % 3600;
    let mins = Math.floor(rem / 60);
    let secs = rem % 60;
    return String(hrs).padStart(2,'0') + ':' +
           String(mins).padStart(2,'0') + ':' +
           String(secs).padStart(2,'0');
  }

  // Khi DOM load, khởi tạo timer cho bàn “occupied”
  document.addEventListener('DOMContentLoaded', () => {
    const now = new Date();
    Object.values(tables).forEach(tbl => {
      if (tbl.status === 'occupied') {
        const createdAt = new Date(tbl.created_at);
        let elapsed = Math.floor((now - createdAt) / 1000);

        const timerEl = document.getElementById(`timer-${tbl.number}`);
        if (timerEl) {
          // Gán giá trị ban đầu
          timerEl.textContent = secondsToHHMMSS(elapsed);
          // Mỗi giây tăng lên 1
          setInterval(() => {
            elapsed++;
            timerEl.textContent = secondsToHHMMSS(elapsed);
          }, 1000);
        }
      }
    });
  });

  // Khi click vào một bàn, show chi tiết bên phải
  function showTableDetails(num) {
    const tbl = tables[num];
    let html = `<h4>Table #${String(num).padStart(2,'0')}</h4>`;

    if (tbl.invoice_id) {
      // Duyệt từng món
      tbl.items.forEach(it => {
        const priceFormatted = parseFloat(it.price).toLocaleString('vi-VN') + ' ₫';
        html += `
          <div class="order-item">
            <span>${it.name} ×${it.quantity}</span>
            <span>${priceFormatted}</span>
          </div>`;
      });

      // Tổng tiền
      const totalFormatted = parseFloat(tbl.total).toLocaleString('vi-VN') + ' ₫';
      html += `<div class="total-text">Total: ${totalFormatted}</div>`;

      // Nút thanh toán (redirect sang payment.php)
      html += `
        <form action="payment.php" method="post">
          <input type="hidden" name="table_id" value="${num}">
          <input type="hidden" name="invoice_id" value="${tbl.invoice_id}">
          <input type="hidden" name="amount" value="${tbl.total}">
          <input type="hidden" name="order_desc" value="Thanh toán hóa đơn bàn ${num}">
          <button type="submit" class="btn btn-payment">Proceed to Payment</button>
        </form>`;
      
    } else {
      html += `<p class="text-muted">Table is empty or no order yet.</p>`;
    }

    document.getElementById('table-details').innerHTML = html;
  }
</script>

<?php
// admin_revenue_topsold.php
// (được include từ admin.php — đã session_start() và $conn)

// Bật debug lỗi
ini_set('display_errors',1);
error_reporting(E_ALL);

// Xác định tuần này
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end   = date('Y-m-d', strtotime('sunday this week'));

// 1) Top 7 món bán chạy tuần này
$stmt = $conn->prepare("
  SELECT i.name, SUM(oi.quantity) AS sold_qty
  FROM order_items oi
  JOIN items i   ON i.id = oi.item_id
  JOIN orders o  ON o.id = oi.order_id
  WHERE DATE(o.order_date) BETWEEN ? AND ?
  GROUP BY i.id
  ORDER BY sold_qty DESC
  LIMIT 7
");
$stmt->bind_param("ss", $week_start, $week_end);
$stmt->execute();
$top7 = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$labels_top = [];
$data_top   = [];
foreach ($top7 as $row) {
    $labels_top[] = $row['name'];
    $data_top[]   = (int)$row['sold_qty'];
}

// 2) Doanh số theo ngày trong tuần này
$stmt2 = $conn->prepare("
  SELECT DATE(o.order_date) AS day, SUM(oi.quantity) AS total_qty
  FROM order_items oi
  JOIN orders o ON o.id = oi.order_id
  WHERE DATE(o.order_date) BETWEEN ? AND ?
  GROUP BY DATE(o.order_date)
  ORDER BY DATE(o.order_date)
");
$stmt2->bind_param("ss", $week_start, $week_end);
$stmt2->execute();
$res2 = $stmt2->get_result();
$labels_day = [];
$data_day   = [];
// Điền đủ 7 ngày, nếu ngày nào không có sẽ thành 0
for ($d = strtotime($week_start); $d <= strtotime($week_end); $d += 86400) {
    $labels_day[] = date('D d', $d);
    $data_day[ date('Y-m-d', $d) ] = 0;
}
while ($r = $res2->fetch_assoc()) {
    $data_day[ $r['day'] ] = (int)$r['total_qty'];
}
$data_day_vals = array_values($data_day);
?>

<h4>Top 7 Dishes This Week</h4>
<div class="row mb-4">
  <div class="col-md-6">
    <table class="table table-striped">
      <thead class="table-dark">
        <tr><th>#</th><th>Dish</th><th>Qty Sold</th></tr>
      </thead>
      <tbody>
      <?php foreach ($top7 as $i => $r): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= intval($r['sold_qty']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="col-md-6">
    <canvas id="barChart"></canvas>
  </div>
</div>

<h4>Daily Sales Quantity (This Week)</h4>
<canvas id="lineChart" class="mb-5"></canvas>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels_top) ?>,
    datasets: [{
      label: 'Qty Sold',
      data: <?= json_encode($data_top) ?>,
      backgroundColor: 'rgba(54,162,235,0.6)'
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: { beginAtZero: true }
    },
    plugins: { legend: { display: false } }
  }
});

const lineCtx = document.getElementById('lineChart').getContext('2d');
new Chart(lineCtx, {
  type: 'line',
  data: {
    labels: <?= json_encode($labels_day) ?>,
    datasets: [{
      label: 'Daily Qty',
      data: <?= json_encode($data_day_vals) ?>,
      fill: false,
      tension: 0.4,
      borderColor: 'rgba(255,99,132,1)',
      pointBackgroundColor: 'rgba(255,99,132,1)',
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: { beginAtZero: true }
    },
    plugins: { legend: { position: 'bottom' } }
  }
});
</script>

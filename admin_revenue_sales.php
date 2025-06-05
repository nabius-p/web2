<?php
// admin_revenue_sales.php

include 'db.php'; // $conn → hotpot_app1

// 1) AJAX handler: when ?detail=sales&date=YYYY-MM-DD
if (isset($_GET['detail']) && $_GET['detail'] === 'sales') {
    $date = $_GET['date'] ?? date('Y-m-d');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<div class="table-responsive"><table class="table table-striped">';
    echo '<thead><tr><th>Invoice #</th><th>Customer</th><th>DateTime</th><th class="text-end">Amount</th></tr></thead><tbody>';
    $stmt = $conn->prepare("
      SELECT id, customer_name, created_at, total_amount
      FROM invoices
      WHERE DATE(created_at)=?
      ORDER BY created_at DESC
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        echo '<tr>'
           . '<td>'.$row['id'].'</td>'
           . '<td>'.htmlspecialchars($row['customer_name']).'</td>'
           . '<td>'.date('Y-m-d H:i:s', strtotime($row['created_at'])).'</td>'
           . '<td class="text-end">'.number_format($row['total_amount'],0,',','.').'₫</td>'
           . '</tr>';
    }
    echo '</tbody></table></div>';
    exit;
}

// 2) Login check
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// 3) Get parameters
$date   = $_GET['date']   ?? date('Y-m-d');
$period = intval($_GET['period'] ?? 7);

// 4) Fetch daily KPIs
// Total Sales
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE DATE(created_at)=?");
$stmt->bind_param("s", $date); $stmt->execute(); $stmt->bind_result($totalSales);
$stmt->fetch(); $stmt->close();

// Total Orders
$stmt = $conn->prepare("SELECT COUNT(*) FROM invoices WHERE DATE(created_at)=?");
$stmt->bind_param("s", $date); $stmt->execute(); $stmt->bind_result($totalOrders);
$stmt->fetch(); $stmt->close();

// Dishes Sold
$stmt = $conn->prepare("
  SELECT COALESCE(SUM(ii.quantity),0)
  FROM invoice_items ii
  JOIN invoices i ON ii.invoice_id=i.id
  WHERE DATE(i.created_at)=?
");
$stmt->bind_param("s", $date); $stmt->execute(); $stmt->bind_result($dishesSold);
$stmt->fetch(); $stmt->close();

// New Customers
$stmt = $conn->prepare("SELECT COUNT(*) FROM customers WHERE DATE(created_at)=?");
$stmt->bind_param("s", $date); $stmt->execute(); $stmt->bind_result($newCustomers);
$stmt->fetch(); $stmt->close();

// 5) Build data for charts
$labels = $dataCur = $dataPrev = [];
for ($i=$period-1; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days", strtotime($date)));
    $labels[] = date('D', strtotime($d));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE DATE(created_at)=?");
    $stmt->bind_param("s",$d); $stmt->execute(); $stmt->bind_result($sum);
    $stmt->fetch(); $stmt->close();
    $dataCur[] = (float)$sum;
}
for ($i=2*$period-1; $i>= $period; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days", strtotime($date)));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE DATE(created_at)=?");
    $stmt->bind_param("s",$d); $stmt->execute(); $stmt->bind_result($sum);
    $stmt->fetch(); $stmt->close();
    $dataPrev[] = (float)$sum;
}
$weeklyData = $dataCur;

// Peak Hour
$peakLabels = ['9:00','11:00','13:00','15:00','17:00','19:00','21:00','22:00'];
$peakValues = [];
foreach ([9,11,13,15,17,19,21,22] as $hr) {
    $stmt = $conn->prepare("
      SELECT COUNT(*) FROM invoices
      WHERE DATE(created_at)=? AND HOUR(created_at)=?
    ");
    $stmt->bind_param("si",$date,$hr); $stmt->execute();
    $stmt->bind_result($cnt); $stmt->fetch(); $stmt->close();
    $peakValues[] = $cnt;
}

// Customer Insights
$ciLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$ciLoyal = $ciNew = $ciUnique = [];
for ($m=1; $m<=12; $m++) {
    // Loyal
    $stmt = $conn->prepare("
      SELECT COUNT(DISTINCT customer_email) FROM invoices
      WHERE MONTH(created_at)=? AND customer_email IN (
        SELECT customer_email FROM invoices
        WHERE MONTH(created_at)=?
        GROUP BY customer_email
        HAVING COUNT(*)>1
      )
    ");
    $stmt->bind_param("ii",$m,$m); $stmt->execute();
    $stmt->bind_result($v); $stmt->fetch(); $stmt->close();
    $ciLoyal[] = $v;
    // New
    $stmt = $conn->prepare("SELECT COUNT(*) FROM customers WHERE MONTH(created_at)=?");
    $stmt->bind_param("i",$m); $stmt->execute();
    $stmt->bind_result($v); $stmt->fetch(); $stmt->close();
    $ciNew[] = $v;
    // Unique
    $stmt = $conn->prepare("SELECT COUNT(*) FROM invoices WHERE MONTH(created_at)=?");
    $stmt->bind_param("i",$m); $stmt->execute();
    $stmt->bind_result($v); $stmt->fetch(); $stmt->close();
    $ciUnique[] = $v;
}

// 6) Recent Transactions (for main table)
$stmt = $conn->prepare("
  SELECT id, customer_name, created_at, total_amount
  FROM invoices
  ORDER BY created_at DESC
  LIMIT 5
");
$stmt->execute();
$recent = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin View Sales | ShinHot Pot</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {font-family:'Nunito',sans-serif; background:#f8f9fa;}
    .sidebar{min-height:100vh;background:#fff;border-right:1px solid #ddd;padding-top:1rem;}
    .sidebar .logo{font-family:'Pacifico',cursive;color:#fea116;padding:.5rem 1rem;}
    .sidebar .nav-link{color:#333;padding:.75rem 1rem;}
    .sidebar .nav-link.active{background:#ffd54f;color:#fff;border-radius:.25rem;font-weight:600;}
    .content{padding:2rem;}
    .summary-card{border-radius:.75rem;}
    .profit-card{border-radius:1rem;background:linear-gradient(135deg,#FFA726 0%,#FFCC80 100%);color:#fff;box-shadow:0 8px 20px rgba(0,0,0,0.15);}
    .profit-balance{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;}
    .profit-balance h3{margin:0;font-size:2.5rem;font-weight:700;}
    .net-profit{display:flex;align-items:center;}
    .net-profit i{font-size:1.75rem;margin-right:.75rem;}
    .net-profit small{opacity:.85;}
  </style>
</head>
<body>

<div class="container-fluid">
  <div class="row">
    

    <!-- Main -->
    <main class="col-10 content">
      <!-- Sales Summary Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Sales Summary</h4>
        <input type="date" class="form-control w-auto"
               value="<?= htmlspecialchars($date) ?>"
               onchange="location.href='admin_revenue_sales.php?date='+this.value+'&period='+<?= $period ?>;">
      </div>

      <!-- Summary Cards -->
      <div class="row row-cols-1 row-cols-md-4 g-4 mb-5">
        <div class="col">
          <div class="card summary-card h-100 p-3 bg-warning-subtle d-flex flex-column justify-content-center">
            <div class="d-flex align-items-center mb-2">
              <i class="fas fa-coins text-warning me-3"></i><h6 class="mb-0">Total Sales</h6>
            </div>
            <h4 class="flex-grow-1"><?= number_format($totalSales,0,',','.') ?>₫</h4>
            <small class="text-success">+8% from yesterday</small>
          </div>
        </div>
        <div class="col">
          <div class="card summary-card h-100 p-3 bg-warning text-white d-flex flex-column justify-content-center">
            <div class="d-flex align-items-center mb-2">
              <i class="fas fa-utensils me-3"></i><h6 class="mb-0">Dishes Sold</h6>
            </div>
            <h4 class="flex-grow-1"><?= $dishesSold ?></h4>
            <small>+12% from yesterday</small>
          </div>
        </div>
        <div class="col">
          <div class="card summary-card h-100 p-3 bg-white d-flex flex-column justify-content-center">
            <div class="d-flex align-items-center mb-2">
              <i class="fas fa-receipt text-primary me-3"></i><h6 class="mb-0">Total Orders</h6>
            </div>
            <h4 class="flex-grow-1"><?= $totalOrders ?></h4>
            <small class="text-success">+5% from yesterday</small>
          </div>
        </div>
        <div class="col">
          <div class="card summary-card h-100 p-3 bg-white d-flex flex-column justify-content-center">
            <div class="d-flex align-items-center mb-2">
              <i class="fas fa-user-plus text-secondary me-3"></i><h6 class="mb-0">New Customers</h6>
            </div>
            <h4 class="flex-grow-1"><?= $newCustomers ?></h4>
            <small>+0.5% from yesterday</small>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="row">
        <div class="col-md-8 mb-4">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between mb-2">
              <h6>Analysis (Last <?= $period ?> Days)</h6>
              <select class="form-select w-auto"
                      onchange="location.href='admin_revenue_sales.php?date=<?= $date ?>&period='+this.value;">
                <?php foreach ([7,30,90] as $p): ?>
                  <option value="<?= $p ?>" <?= $p===$period?'selected':'' ?>><?= $p ?> days</option>
                <?php endforeach; ?>
              </select>
            </div>
            <canvas id="analysisChart" height="150"></canvas>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="card p-3 text-center h-100">
            <h6>Weekly Sales</h6>
            <h3><?= number_format(array_sum($weeklyData),0,',','.') ?>₫</h3>
            <canvas id="weeklyChart" height="120"></canvas>
          </div>
        </div>
      </div>

      <!-- Profitability -->
      <h4>Profitability</h4>
      <small class="text-muted">Total Net Profit &amp; Income</small>
      <div class="card profit-card p-4 mb-5">
        <div class="profit-balance">
          <div>
            <small>Total Balance</small>
            <h3><?= number_format(array_sum($weeklyData),0,',','.') ?>₫</h3>
          </div>
          <canvas id="profitSparkline" width="60" height="30"></canvas>
        </div>
        <div class="net-profit">
          <i class="fas fa-chart-line text-success"></i>
          <div>
            <div class="value"><?= number_format(array_sum($weeklyData)*0.03,2,',','.') ?>₫</div>
            <small>Today, <?= date('H:i') ?></small>
          </div>
        </div>
      </div>

      <!-- Customer Insights & Peak Hour -->
      <div class="row mb-5">
        <div class="col-md-6 mb-4">
          <h6>Customer Insights</h6>
          <div class="card p-3 h-100"><canvas id="customerInsightsChart" height="200"></canvas></div>
        </div>
        <div class="col-md-6 mb-4">
          <h6>Peak Hour (<?= $date ?>)</h6>
          <div class="card p-3 h-100"><canvas id="peakHourChart" height="200"></canvas></div>
        </div>
      </div>

      <!-- Recently Orders + View All -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Recently Orders</h5>
    <a href="admin.php?page=orders&date=<?= htmlspecialchars($date) ?>" class="btn btn-link">
      View All
    </a>
  </div>
      <div class="card p-3 mb-5">
        <table class="table mb-0">
          <thead class="table-light">
            <tr>
              <th>Number</th><th>Customer</th><th>Date</th><th class="text-end">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php while($r = $recent->fetch_assoc()): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><?= htmlspecialchars($r['customer_name']) ?></td>
              <td><?= date('Y-m-d H:i:s',strtotime($r['created_at'])) ?></td>
              <td class="text-end"><?= number_format($r['total_amount'],0,',','.') ?>₫</td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Modal -->
      <div class="modal fade" id="ordersModal" tabindex="-1" aria-labelledby="ordersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="ordersModalLabel">Orders for <?= htmlspecialchars($date) ?></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="ordersModalBody">
              <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Chart.js & Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Analysis Chart
new Chart(document.getElementById('analysisChart'), {
  type:'line',
  data:{ labels:<?= json_encode($labels) ?>, datasets:[
    { label:'Prev', data:<?= json_encode($dataPrev) ?>, borderDash:[5,5], fill:false, tension:.4 },
    { label:'Cur',  data:<?= json_encode($dataCur) ?>,  fill:false, tension:.4 }
  ]},
  options:{ responsive:true, plugins:{ legend:{ position:'bottom' }} }
});
// Weekly Area
new Chart(document.getElementById('weeklyChart'), {
  type:'line',
  data:{ labels:<?= json_encode($labels) ?>, datasets:[{
      data:<?= json_encode($weeklyData) ?>, fill:true, tension:.4, borderWidth:0
  }]},
  options:{ responsive:true, scales:{ x:{display:false},y:{display:false} }, plugins:{ legend:{display:false} }}
});
// Profit Sparkline
new Chart(document.getElementById('profitSparkline'), {
  type:'line',
  data:{ datasets:[{ data:<?= json_encode($weeklyData) ?>, borderColor:'#2E7D32', pointRadius:0, tension:.4 }]},
  options:{ responsive:false, plugins:{ legend:{display:false} }, scales:{ x:{display:false},y:{display:false} }}
});
// Peak Hour
new Chart(document.getElementById('peakHourChart'), {
  type:'bar',
  data:{ labels:<?= json_encode($peakLabels) ?>, datasets:[{ data:<?= json_encode($peakValues) ?>, borderRadius:5 }]},
  options:{ responsive:true, plugins:{ legend:{display:false} }}
});
// Customer Insights
new Chart(document.getElementById('customerInsightsChart'), {
  type:'line',
  data:{ labels:<?= json_encode($ciLabels) ?>, datasets:[
    { label:'Loyal',  data:<?= json_encode($ciLoyal) ?>,  fill:false, tension:.4 },
    { label:'New',    data:<?= json_encode($ciNew) ?>,    fill:false, tension:.4 },
    { label:'Unique', data:<?= json_encode($ciUnique) ?>, fill:false, tension:.4 }
  ]},
  options:{ responsive:true }
});


</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

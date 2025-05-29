<?php
// admin_revenue_sales.php – include từ admin.php (đã session_start & include db.php)

// --- 0) Nếu có yêu cầu detail, trả về HTML ngay và exit ---
if (isset($_GET['detail'])) {
    include 'db.php';
    $today = $_GET['date'] ?? date('Y-m-d');
    $key   = $_GET['detail'];
    header('Content-Type: text/html; charset=UTF-8');
    echo '<div class="table-responsive"><table class="table table-sm mb-0">';
    switch ($key) {
        case 'sales':
            echo '<thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
            $stmt = $conn->prepare("
              SELECT id, customer_name, total_price, status
              FROM orders
              WHERE DATE(order_date)=?
              ORDER BY order_date DESC
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $rs = $stmt->get_result();
            while ($r = $rs->fetch_assoc()) {
                printf(
                    '<tr>
                       <td>%d</td>
                       <td>%s</td>
                       <td>%s ₫</td>
                       <td>%s</td>
                     </tr>',
                    $r['id'],
                    htmlspecialchars($r['customer_name']),
                    number_format($r['total_price'], 0, ',', '.'),
                    ucfirst($r['status'])
                );
            }
            echo '</tbody>';
            break;

        case 'dishes':
            echo '<thead><tr><th>Dish</th><th class="text-center">Qty</th></tr></thead><tbody>';
            $stmt = $conn->prepare("
              SELECT i.name, SUM(oi.quantity) AS qty
              FROM orders o
              JOIN order_items oi ON oi.order_id=o.id
              JOIN items i       ON i.id=oi.item_id
              WHERE DATE(o.order_date)=?
              GROUP BY oi.item_id
              ORDER BY qty DESC
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $rs = $stmt->get_result();
            while ($r = $rs->fetch_assoc()) {
                printf(
                    '<tr><td>%s</td><td class="text-center">%d</td></tr>',
                    htmlspecialchars($r['name']),
                    $r['qty']
                );
            }
            echo '</tbody>';
            break;

        case 'orders':
            echo '<thead><tr><th>Order ID</th><th>Table</th><th>Customer</th></tr></thead><tbody>';
            $stmt = $conn->prepare("
              SELECT id, table_number, customer_name
              FROM orders
              WHERE DATE(order_date)=?
              ORDER BY order_date DESC
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $rs = $stmt->get_result();
            while ($r = $rs->fetch_assoc()) {
                printf(
                    '<tr><td>%d</td><td>%d</td><td>%s</td></tr>',
                    $r['id'],
                    $r['table_number'],
                    htmlspecialchars($r['customer_name'])
                );
            }
            echo '</tbody>';
            break;

        case 'new':
            echo '<thead><tr><th>Customer</th><th>Email</th></tr></thead><tbody>';
            $stmt = $conn->prepare("
              SELECT DISTINCT customer_name, customer_email
              FROM orders
              WHERE DATE(order_date)=?
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $rs = $stmt->get_result();
            while ($r = $rs->fetch_assoc()) {
                printf(
                    '<tr><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars($r['customer_name']),
                    htmlspecialchars($r['customer_email'])
                );
            }
            echo '</tbody>';
            break;

        default:
            echo '<tr><td colspan="4" class="text-danger">Unknown detail key.</td></tr>';
    }
    echo '</table></div>';
    exit;
}

// 1) Lấy tham số ngày và khoảng
$range     = intval($_GET['range']  ?? 7);
$today     = $_GET['date'] ?? date('Y-m-d');
if (!in_array($range, [7,30,90])) $range = 7;
$yesterday = date('Y-m-d', strtotime("$today -1 day"));

// 2) Các hàm tiện ích
function get_total_sales($conn, $date) {
    $stmt = $conn->prepare("SELECT SUM(total_price) AS s FROM orders WHERE DATE(order_date)=?");
    $stmt->bind_param("s",$date);
    $stmt->execute();
    return (float)$stmt->get_result()->fetch_assoc()['s'];
}
function get_total_orders($conn, $date) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE DATE(order_date)=?");
    $stmt->bind_param("s",$date);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}
function get_dishes_sold($conn, $date) {
    $stmt = $conn->prepare("
      SELECT SUM(oi.quantity) AS q
      FROM orders o
      JOIN order_items oi ON oi.order_id=o.id
      WHERE DATE(o.order_date)=?
    ");
    $stmt->bind_param("s",$date);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['q'];
}
function get_new_customers($conn, $date) {
    $stmt = $conn->prepare("
      SELECT COUNT(DISTINCT customer_email) AS c
      FROM orders
      WHERE DATE(order_date)=?
    ");
    $stmt->bind_param("s",$date);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}
function pct_change($new, $old) {
    if ($old <= 0) return 0;
    return round((($new-$old)/$old)*100,1);
}

// 3) Lấy dữ liệu cho ngày được chọn và ngày trước
$total_sales    = get_total_sales($conn,$today);
$total_orders   = get_total_orders($conn,$today);
$dishes_sold    = get_dishes_sold($conn,$today);
$new_customers  = get_new_customers($conn,$today);

$chg_sales      = pct_change($total_sales,   get_total_sales($conn,$yesterday));
$chg_orders     = pct_change($total_orders,  get_total_orders($conn,$yesterday));
$chg_dishes     = pct_change($dishes_sold,   get_dishes_sold($conn,$yesterday));
$chg_customers  = pct_change($new_customers, get_new_customers($conn,$yesterday));

// 4) Dữ liệu cho charts
$labels = $data_cur = $data_prev = [];
for($i=$range-1;$i>=0;$i--){
  $d = date('Y-m-d',strtotime("-{$i} days"));
  $labels[] = date('d M',strtotime($d));
  $stmt = $conn->prepare("SELECT SUM(total_price) AS s FROM orders WHERE DATE(order_date)=?");
  $stmt->bind_param("s",$d); $stmt->execute();
  $data_cur[]  = (float)$stmt->get_result()->fetch_assoc()['s'];
  $d2 = date('Y-m-d',strtotime("-".($i+$range)." days"));
  $stmt->bind_param("s",$d2); $stmt->execute();
  $data_prev[] = (float)$stmt->get_result()->fetch_assoc()['s'];
}

// 5) Giao dịch gần nhất (Today)
$recent = $conn->query("
  SELECT customer_name,table_number,total_price,status
  FROM orders
  WHERE DATE(order_date)= '$today'
  ORDER BY order_date DESC
  LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!-- Form chọn ngày -->
<form method="get" class="mb-4">
  <input type="hidden" name="page" value="revenue_sales">
  <div class="d-flex gap-2 align-items-center">
    <label class="m-0">Choose date:</label>
    <input type="date" name="date" class="form-control form-control-sm w-auto" value="<?=htmlspecialchars($today)?>">
    <button class="btn btn-primary btn-sm">View</button>
  </div>
</form>

<!-- Các thẻ chính -->
<div class="row gy-4 mb-4">
  <?php
  $cards = [
    ['key'=>'sales','title'=>'Total Sales','value'=>$total_sales,'chg'=>$chg_sales,'suffix'=>' ₫'],
    ['key'=>'dishes','title'=>'Dishes Sold','value'=>$dishes_sold,'chg'=>$chg_dishes,'suffix'=>''],
    ['key'=>'orders','title'=>'Total Orders','value'=>$total_orders,'chg'=>$chg_orders,'suffix'=>''],
    ['key'=>'new','title'=>'New Customers','value'=>$new_customers,'chg'=>$chg_customers,'suffix'=>''],
  ];
  foreach($cards as $c): ?>
  <div class="col-md-3">
    <div class="card shadow-sm"
         role="button"
         data-bs-toggle="modal"
         data-bs-target="#detailModal"
         onclick="showDetail('<?= $c['key'] ?>')">
      <div class="card-body">
        <h6><?= $c['title'] ?></h6>
        <h3><?= number_format($c['value'],0,',','.') ?><?= $c['suffix'] ?></h3>
        <small class="text-<?= $c['chg']>=0?'success':'danger' ?>">
          <?= ($c['chg']>=0?'+':'').$c['chg'] ?>% from yesterday
        </small>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>


<!-- Profitability (ví dụ) -->
<div class="row mb-5">
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <small class="text-muted">Profitability</small>
        <h2><?=number_format($total_sales,0,',','.')?> ₫</h2>
        <small>Total Net Profit & Income</small><hr>
        <div class="d-flex justify-content-between">
          <div>
            <small>Net Profit Today, <?=date('H:i')?></small><br>
            <strong>+ <?=number_format(1540.5,0,',','.')?> ₫</strong>
          </div>
          <i class="fas fa-chart-line fa-2x text-secondary"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Phần phân tích & chart -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5>Analysis</h5>
  <select id="rangeSelect" class="form-select form-select-sm w-auto">
    <option <?= $range===7?'selected':''?>  value="7">7 days</option>
    <option <?= $range===30?'selected':''?> value="30">30 days</option>
    <option <?= $range===90?'selected':''?> value="90">90 days</option>
  </select>
</div>
<canvas id="salesChart" height="120"></canvas>

<!-- Recent Transactions -->
<div class="row gy-4 mt-5">
  <div class="col-lg-6">
    <h5>Recent Transactions</h5>
    <table class="table">
      <thead><tr><th>Customer</th><th>Table</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach($recent as $t): ?>
          <tr>
            <td><?=htmlspecialchars($t['customer_name'])?></td>
            <td><?=$t['table_number']?></td>
            <td><?=number_format($t['total_price'],0,',','.')?> ₫</td>
            <td>
              <span class="badge bg-<?= $t['status']=='paid'?'success':'warning'?>">
                <?=ucfirst($t['status'])?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="col-lg-6">
    <h5>Weekly Sales</h5>
    <canvas id="weeklyChart" height="220"></canvas>
  </div>
</div>
<!-- modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailTitle"></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailContent">
        <!-- sẽ load AJAX vào đây -->
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart chính
new Chart(document.getElementById('salesChart'), {
  type: 'line',
  data: {
    labels: <?=json_encode($labels)?>,
    datasets: [
      { label:'Last Week', data:<?=json_encode($data_prev)?>, borderColor:'rgba(201,203,207,1)', borderDash:[5,5], fill:false },
      { label:'This Week', data:<?=json_encode($data_cur)?>, borderColor:'rgba(255,99,132,1)', fill:false, tension:0.4 },
    ]
  },
  options:{ plugins:{ legend:{ position:'bottom' }}, scales:{ y:{ beginAtZero:true } } }
});
// Chart phụ
new Chart(document.getElementById('weeklyChart'), {
  type:'line',
  data:{
    labels: <?=json_encode($labels)?>,
    datasets:[{ data:<?=json_encode($data_cur)?>, fill:true, tension:0.4 }]
  },
  options:{ plugins:{ legend:{ display:false }}, scales:{ y:{ display:false } } }
});

// thay đổi range
document.getElementById('rangeSelect').addEventListener('change', e=>{
  const p = new URLSearchParams(location.search);
  p.set('range', e.target.value);
  p.set('page','revenue_sales');
  location.search = p.toString();
});

// show detail
function showDetail(key) {
  // Lấy ngày đang chọn (input type="date")
  const dateInput = document.querySelector("input[name='date']");
  const date = dateInput ? dateInput.value : '<?= date('Y-m-d') ?>';

  // Tiêu đề Modal
  const titles = {
    sales:  'All Orders Today',
    dishes: 'Dishes Sold Today',
    orders: 'Orders List Today',
    new:    'New Customers Today'
  };
  document.getElementById('detailTitle').innerText = titles[key] || 'Details';

  // Hiển thị loading
  document.getElementById('detailContent').innerHTML =
    '<div class="text-center py-5 text-muted">Loading…</div>';

  // Fetch HTML chi tiết
  fetch(`admin_revenue_sales.php?date=${encodeURIComponent(date)}&detail=${encodeURIComponent(key)}`, {
  credentials: 'same-origin'
  })
  .then(res => res.text())
  .then(html => {
    document.getElementById('detailContent').innerHTML = html;
  })
  .catch(err => {
    console.error(err);
    document.getElementById('detailContent').innerHTML =
      '<div class="text-danger p-3">Error loading details.</div>';
  });
}
</script>

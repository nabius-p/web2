<?php
// admin_revenue_topsold.php
// (được include từ admin.php — đã session_start() and $conn)
ini_set('display_errors',1);
error_reporting(E_ALL);

// 1) Tuần hiện tại
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end   = date('Y-m-d', strtotime('sunday this week'));

// 2) Top 7 món bán chạy
$stmt = $conn->prepare("
  SELECT i.name, SUM(ii.quantity) AS sold_qty
  FROM invoice_items ii
  JOIN items i      ON i.id   = ii.item_id
  JOIN invoices inv ON inv.id = ii.invoice_id
  WHERE DATE(inv.created_at) BETWEEN ? AND ?
  GROUP BY i.id
  ORDER BY sold_qty DESC
  LIMIT 7
");
$stmt->bind_param("ss",$week_start,$week_end);
$stmt->execute();
$top7 = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$labels_top = array_column($top7,'name');
$data_top   = array_map('intval', array_column($top7,'sold_qty'));

// 3) Daily sales
$stmt2 = $conn->prepare("
  SELECT DATE(inv.created_at) AS d, SUM(ii.quantity) AS qty
  FROM invoice_items ii
  JOIN invoices inv ON inv.id = ii.invoice_id
  WHERE DATE(inv.created_at) BETWEEN ? AND ?
  GROUP BY d
  ORDER BY d
");
$stmt2->bind_param("ss",$week_start,$week_end);
$stmt2->execute();
$res2 = $stmt2->get_result();
$labels_day = []; $data_day = [];
for($t=strtotime($week_start); $t<=strtotime($week_end); $t+=86400){
  $labels_day[] = date('D d',$t);
  $data_day[date('Y-m-d',$t)] = 0;
}
while($r=$res2->fetch_assoc()){
  $data_day[$r['d']] = (int)$r['qty'];
}
$data_day_vals = array_values($data_day);

// 4) Completed / Canceled
$stmt3 = $conn->prepare("
  SELECT ii.status, SUM(ii.quantity) AS qty
  FROM invoice_items ii
  JOIN invoices inv ON inv.id = ii.invoice_id
  WHERE DATE(inv.created_at) BETWEEN ? AND ?
  GROUP BY ii.status
");
$stmt3->bind_param("ss",$week_start,$week_end);
$stmt3->execute();
$res3 = $stmt3->get_result();
$completed = $canceled = 0;
while($r=$res3->fetch_assoc()){
  if($r['status']==='completed') $completed = (int)$r['qty'];
  if($r['status']==='canceled')  $canceled  = (int)$r['qty'];
}
$score = ($completed+$canceled)
       ? round($completed/($completed+$canceled)*10,1)
       : 0;

// palette
$palette = ['#FF4D4F','#52C41A','#722ED1','#FA8C16','#EB2F96','#1890FF','#13C2C2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin – Top Sold</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .badge-pct { font-size:.85em; padding:.4em .8em; color:#fff; border-radius:.25rem; }
    .bg-rect   { background:#FFA500; color:#fff; border-radius:.5rem; }
    .gauge-box { width:200px; height:200px; position:relative; }
    .gauge-box canvas { position:absolute; top:0; left:0; }
    .gauge-value {
      position:absolute; top:50%; left:50%;
      transform:translate(-50%,-50%);
      text-align:center;
    }
  </style>
</head>
<body class="d-flex">


  <!-- MAIN -->
  <main class="flex-fill p-4">

    <h4>Weekly’s Top Sold Dishes</h4>
    <div class="row mb-5">
      <!-- Top7 -->
      <div class="col-md-6">
        <ul class="list-group shadow-sm">
          <?php $total=array_sum($data_top); foreach($top7 as $i=>$it):
            $pct = $total?round($data_top[$i]/$total*100):0;
            $col = $palette[$i % count($palette)];
          ?>
          <li class="list-group-item d-flex align-items-center">
            <div class="me-3 fw-bold"><?= str_pad($i+1,2,'0',STR_PAD_LEFT) ?></div>
            <div class="me-3 flex-grow-1"><?= htmlspecialchars($it['name']) ?></div>
            <div class="progress flex-grow-2 me-3" style="height:6px">
              <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
            </div>
            <span class="badge-pct" style="background:<?= $col ?>;"><?= $pct ?>%</span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="col-md-6">
        <canvas id="barChart"></canvas>
      </div>
    </div>

    <!-- Daily + Completed -->
    <h5 class="mb-3">Daily Sales Quantity (This Week)</h5>
    <div class="d-flex mb-5">
      <div class="flex-grow-1 me-4">
        <canvas id="lineChart"></canvas>
      </div>
      <div style="width:260px">
        <h5>Completed / Cancel Rate</h5>
        <div class="bg-rect p-3 mb-3">
          <div class="fw-bold">Completed</div>
          <div><?= $completed ?> dishes</div>
        </div>
        <div class="bg-rect p-3 mb-4">
          <div class="fw-bold">Canceled</div>
          <div><?= $canceled ?> dishes</div>
        </div>
        <div class="gauge-box mx-auto">
          <canvas id="gaugeChart" width="200" height="200"></canvas>
          <div class="gauge-value">
            <div class="fs-2 fw-bold"><?= $score ?></div>
            <div>Total Score</div>
          </div>
        </div>
      </div>
    </div>

  </main>

  <!-- CHART.JS SCRIPTS -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Bar Top7
    new Chart(document.getElementById('barChart'), {
      type:'bar',
      data:{
        labels: <?= json_encode($labels_top) ?>,
        datasets:[{ data: <?= json_encode($data_top) ?>, backgroundColor: <?= json_encode($palette) ?> }]
      },
      options:{
        responsive:true,
        scales:{ y:{ beginAtZero:true } },
        plugins:{ legend:{ display:false } }
      }
    });

    // Line Daily với axis titles
    new Chart(document.getElementById('lineChart'), {
      type:'line',
      data:{
        labels: <?= json_encode($labels_day) ?>,
        datasets:[{
          label:'Sales per day',
          data: <?= json_encode($data_day_vals) ?>,
          fill:false,
          tension:0.4
        }]
      },
      options:{
        responsive:true,
        scales:{
          x:{ title:{ display:true, text:'Day of week' } },
          y:{ beginAtZero:true, title:{ display:true, text:'Quantity' } }
        },
        plugins:{ legend:{ display:true, position:'bottom' } }
      }
    });

    // Gauge
    new Chart(document.getElementById('gaugeChart'), {
      type:'doughnut',
      data:{ datasets:[{
        data:[<?= $score ?>,10-<?= $score ?>],
        backgroundColor:['#FFA500','#EEE'],
        cutout:'80%'
      }]},
      options:{
        maintainAspectRatio:false,
        rotation:-90*Math.PI/180,
        circumference:180*Math.PI/180,
        plugins:{ tooltip:{enabled:false}, legend:{display:false} }
      }
    });
  </script>
</body>
</html>

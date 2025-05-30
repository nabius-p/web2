<?php
// admin_inventory.php — include from admin.php (session_start & $conn already done)
// Đảm bảo file db.php tồn tại và kết nối $conn được khởi tạo
require_once 'db.php';

// 1) Handle new Purchase Order (PO) creation (Nếu bạn vẫn muốn giữ chức năng này trong trang này)
// Chức năng "Add New Items" đã được chuyển sang admin_add_item.php
// Phần này chỉ còn ý nghĩa nếu bạn muốn tạo PO nhanh từ đây, nếu không, có thể bỏ.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['po_ing'], $_POST['po_qty'])) {
    $item_id = intval($_POST['po_ing']);
    $qty     = intval($_POST['po_qty']);

    // create a dummy “receive” invoice to model stock replenishment
    $stmt = $conn->prepare("
      INSERT INTO invoices (customer_name, customer_email, total_amount)
      VALUES ('--PO--', '--PO--', 0)
    ");
    if ($stmt === false) {
        $_SESSION['flash_message'] = "Lỗi chuẩn bị SQL (PO): " . $conn->error;
        error_log("SQL Prepare Error (PO): " . $conn->error);
    } else {
        if ($stmt->execute()) {
            $inv_id = $stmt->insert_id;

            // record into invoice_items
            $stmt2 = $conn->prepare("
              INSERT INTO invoice_items (invoice_id, item_id, quantity, price)
              VALUES (?, ?, ?, 0)
            ");
            if ($stmt2 === false) {
                 $_SESSION['flash_message'] = "Lỗi chuẩn bị SQL (PO Item): " . $conn->error;
                 error_log("SQL Prepare Error (PO Item): " . $conn->error);
            } else {
                $stmt2->bind_param("iii", $inv_id, $item_id, $qty);
                if ($stmt2->execute()) {
                    $_SESSION['flash_message'] = "PO created for item #{$item_id} (qty={$qty})";
                } else {
                    $_SESSION['flash_message'] = "Lỗi khi thực thi SQL (PO Item): " . $stmt2->error;
                    error_log("SQL Execute Error (PO Item): " . $stmt2->error);
                }
                $stmt2->close();
            }
        } else {
            $_SESSION['flash_message'] = "Lỗi khi thực thi SQL (PO Inv): " . $stmt->error;
            error_log("SQL Execute Error (PO Inv): " . $stmt->error);
        }
        $stmt->close();
    }
    // Chuyển hướng đến trang Inventory Overview
    header('Location: admin.php?page=inventory_overview#inventory');
    exit();
}

// 2) Fetch categories of ingredients for filter tabs
$cats_query = $conn->query("SELECT DISTINCT category FROM items ORDER BY category");
$categories = [];
if ($cats_query) {
    while ($r = $cats_query->fetch_assoc()) {
        $categories[] = $r['category'];
    }
}
// Mặc định là 'Hotpot' theo ảnh thiết kế
$sel_cat = $_GET['cat'] ?? 'Hotpot'; 

// 3) Query current stock by item for "HotPot List" table
$base_query = "
  SELECT
    itm.id                  AS item_id,
    itm.name,
    itm.category,
    itm.image_url, -- Thêm image_url
    COALESCE(po_imports.total,0) - COALESCE(sales.total,0) AS stock_quantity
  FROM items itm
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name='--PO--'
    GROUP BY ii.item_id
  ) AS po_imports ON po_imports.item_id = itm.id
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name<>'--PO--'
    GROUP BY ii.item_id
  ) AS sales ON sales.item_id = itm.id
";

// Thực hiện truy vấn cho bảng "HotPot List" (Current Stock)
if ($sel_cat !== 'All') { // Lọc theo danh mục được chọn
    $stmt_stock_filtered = $conn->prepare($base_query . " WHERE itm.category=? ORDER BY itm.name");
    if ($stmt_stock_filtered === false) {
        error_log("SQL Prepare Error (Stock Filtered): " . $conn->error);
        $stock_res = null; // Gán null để tránh lỗi nếu prepare thất bại
    } else {
        $stmt_stock_filtered->bind_param("s", $sel_cat);
        $stmt_stock_filtered->execute();
        $stock_res = $stmt_stock_filtered->get_result();
    }
} else { // Hiển thị tất cả
    $stock_res = $conn->query($base_query . " ORDER BY itm.category, itm.name");
}

// 4) Fetch recent “replenishment” POs (Có thể không hiển thị trực tiếp trên thiết kế này, nhưng vẫn cần cho tính toán "Pending PO")
// Dòng này không cần thay đổi nếu không hiển thị chi tiết PO
$po_res = $conn->query("
  SELECT inv.id, ii.item_id, itm.name, ii.quantity, inv.created_at
  FROM invoices inv
  JOIN invoice_items ii ON ii.invoice_id=inv.id
  JOIN items itm ON itm.id=ii.item_id
  WHERE inv.customer_name='--PO--'
  ORDER BY inv.created_at DESC
  LIMIT 5
");

// 5) Summaries for "Inventory Checking" cards and pie chart data
// total on-hand
$total_qty_result = $conn->query("
  SELECT SUM(
    COALESCE(po_imports.total,0) - COALESCE(sales.total,0)
  ) AS t
  FROM items itm
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name='--PO--'
    GROUP BY ii.item_id
  ) po_imports ON po_imports.item_id = itm.id
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name<>'--PO--'
    GROUP BY ii.item_id
  ) sales ON sales.item_id = itm.id
");
$total_qty = $total_qty_result ? ($total_qty_result->fetch_assoc()['t'] ?? 0) : 0;

// pending PO = tổng số lượng PO gần đây
$pending_po_result = $conn->query("
  SELECT COALESCE(SUM(ii.quantity), 0)
  FROM invoices inv
  JOIN invoice_items ii ON ii.invoice_id=inv.id
  WHERE inv.customer_name='--PO--'
");
$pending_po = $pending_po_result ? ($pending_po_result->fetch_row()[0] ?? 0) : 0;

$suppliers_cnt = 31;  // placeholder, cần query DB nếu muốn dynamic
$cat_cnt       = count($categories);

// 6) Fetch low stock items for "ALERT - REFILL REQUIRED"
$low_stock_threshold = 50; // Ngưỡng tồn kho thấp
$low_stock_query = "
  SELECT
    itm.name,
    COALESCE(po_imports.total,0) - COALESCE(sales.total,0) AS stock_quantity
  FROM items itm
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name='--PO--'
    GROUP BY ii.item_id
  ) AS po_imports ON po_imports.item_id = itm.id
  LEFT JOIN (
    SELECT ii.item_id, SUM(ii.quantity) AS total
    FROM invoices inv
    JOIN invoice_items ii ON ii.invoice_id=inv.id
    WHERE inv.customer_name<>'--PO--'
    GROUP BY ii.item_id
  ) AS sales ON sales.item_id = itm.id
  HAVING stock_quantity <= ? AND stock_quantity > 0
  ORDER BY stock_quantity ASC
";
$stmt_low_stock = $conn->prepare($low_stock_query);
if ($stmt_low_stock === false) {
    error_log("SQL Prepare Error (Low Stock): " . $conn->error);
    $low_stock_items = [];
} else {
    $stmt_low_stock->bind_param("i", $low_stock_threshold);
    $stmt_low_stock->execute();
    $low_stock_items = $stmt_low_stock->get_result()->fetch_all(MYSQLI_ASSOC);
}


// 7) Fetch data for "Recent Usage" (Sales from tables)
// Query này cần dữ liệu từ invoices, invoice_items và restaurant_tables.
// Giả định invoices.table_id đã được thêm vào và invoices.customer_name <> '--PO--' là sales
$recent_usage_query = "
    SELECT
        rt.table_number,
        itm.name AS item_name,
        ii.quantity,
        inv.created_at
    FROM invoices inv
    JOIN invoice_items ii ON inv.id = ii.invoice_id
    JOIN items itm ON ii.item_id = itm.id
    LEFT JOIN restaurant_tables rt ON inv.table_id = rt.id -- Join với bảng bàn
    WHERE inv.customer_name <> '--PO--'
    ORDER BY inv.created_at DESC
    LIMIT 100 -- Lấy đủ dữ liệu để xử lý trong PHP
";
$recent_usage_res = $conn->query($recent_usage_query);

$recent_table_usage = [];
if ($recent_usage_res) {
    while ($row = $recent_usage_res->fetch_assoc()) {
        $table_number = $row['table_number'] ?? 'N/A'; // Lấy số bàn, hoặc 'N/A' nếu không có
        if (!isset($recent_table_usage[$table_number])) {
            $recent_table_usage[$table_number] = [];
        }
        $recent_table_usage[$table_number][] = $row;
    }
}
// Giới hạn chỉ lấy 2 bàn gần đây nhất theo thiết kế (nếu có đủ data)
// Sắp xếp các bàn theo số thứ tự (ví dụ: 03 trước 08)
ksort($recent_table_usage);
$limited_table_usage = array_slice($recent_table_usage, 0, 2, true);


// 8) Data for Pie Chart (Inventory Distribution by Category)
$category_quantities = [];
$all_stock_by_category_query = $conn->query("
    SELECT
        itm.category,
        SUM(COALESCE(po_imports.total,0) - COALESCE(sales.total,0)) AS category_stock
    FROM items itm
    LEFT JOIN (
        SELECT ii.item_id, SUM(ii.quantity) AS total
        FROM invoices inv
        JOIN invoice_items ii ON ii.invoice_id=inv.id
        WHERE inv.customer_name='--PO--'
        GROUP BY ii.item_id
    ) AS po_imports ON po_imports.item_id = itm.id
    LEFT JOIN (
        SELECT ii.item_id, SUM(ii.quantity) AS total
        FROM invoices inv
        JOIN invoice_items ii ON ii.invoice_id=inv.id
        WHERE inv.customer_name<>'--PO--'
        GROUP BY ii.item_id
    ) AS sales ON sales.item_id = itm.id
    GROUP BY itm.category
    ORDER BY category_stock DESC
");
if ($all_stock_by_category_query) {
    while ($row = $all_stock_by_category_query->fetch_assoc()) {
        if ($row['category_stock'] > 0) { // Chỉ thêm các danh mục có tồn kho > 0 vào biểu đồ
            $category_quantities[] = [
                'category' => $row['category'],
                'stock' => intval($row['category_stock'])
            ];
        }
    }
}
?>

<style>
    /* Tổng quan các card dashboard */
    .dashboard-card {
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08); /* Bóng đổ mềm mại hơn */
        transition: transform 0.2s ease-in-out;
        background-color: #fff;
        height: 100%; /* Đảm bảo chiều cao đồng đều */
    }
    .dashboard-card:hover {
        transform: translateY(-5px);
    }
    .card-body-custom {
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
    }
    .card-icon {
        font-size: 2.5rem;
        margin-bottom: 10px;
        color: #0d6efd; /* Màu mặc định */
    }
    /* Màu icon theo thiết kế */
    .card-icon.total-stock { color: #0d6efd; } /* Blue */
    .card-icon.pending-po { color: #ffc107; } /* Orange/Yellow */
    .card-icon.supplier { color: #28a745; } /* Green */
    .card-icon.category { color: #17a2b8; } /* Teal */

    .card-title-text {
        font-size: 0.95rem;
        color: #6c757d;
        margin-bottom: 5px;
        font-weight: 500;
    }
    .card-value {
        font-size: 2.2rem;
        font-weight: 700;
        color: #343a40;
        margin-bottom: 0;
    }
    .card-meta-text {
        font-size: 0.75rem;
        color: #999;
        margin-top: 5px;
    }
    /* ALERT */
    .alert-refill {
        background-color: #fff3cd; /* Màu vàng nhạt */
        color: #856404; /* Màu chữ vàng đậm */
        border-color: #ffc107;
        font-weight: 600;
        border-radius: 8px;
        padding: 12px 20px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    /* Category Filter Tabs */
    .nav-pills .nav-link {
        border-radius: 25px; /* Nút bo tròn */
        padding: 8px 20px;
        font-weight: 500;
        color: #6c757d;
        background-color: #e9ecef; /* Nền xám nhạt */
        margin-right: 10px;
        margin-bottom: 10px;
    }
    .nav-pills .nav-link.active {
        background-color: #ffc107; /* Màu vàng cam của thiết kế */
        color: #333;
        font-weight: 600;
        border: 1px solid #ffc107;
    }

    /* HotPot List Table */
    .table-container-custom {
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
        padding: 20px;
        height: 100%; /* Để card bên trái cao bằng card bên phải */
    }
    .table-header-custom th {
        background-color: #f8f9fa; /* Nền header */
        color: #343a40;
        font-weight: 600;
        vertical-align: middle;
        padding: 12px 15px;
        border-bottom: 1px solid #dee2e6;
    }
    .table-hover tbody tr:hover {
        background-color: #f2f2f2;
    }
    .table-item-img-small {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 6px;
    }
    .status-tag { /* Có thể không dùng trong trang này, nhưng giữ để khớp phong cách */
        display: inline-block;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.8em;
        font-weight: 700;
        text-transform: uppercase;
        color: #fff;
    }
    .status-good { background-color: #28a745; }
    .status-refill { background-color: #ffc107; }
    .status-empty { background-color: #dc3545; }

    /* Recent Usage & Pie Chart */
    .recent-usage-card {
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
        padding: 20px;
        height: 100%; /* Đảm bảo card chiếm hết chiều cao */
    }
    .recent-usage-card h5 {
        font-weight: 600;
        margin-bottom: 15px;
    }
    .list-group-table-usage .list-group-item {
        border: none;
        border-bottom: 1px dashed #eee; /* Đường kẻ mờ */
        padding: 10px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.95rem;
    }
    .list-group-table-usage .list-group-item:last-child {
        border-bottom: none;
    }
    .list-group-table-usage .badge {
        background-color: #0d6efd; /* Màu xanh của badge */
        color: white;
        min-width: 30px;
        text-align: center;
    }
    #inventoryPieChart {
        max-height: 250px; /* Giới hạn chiều cao biểu đồ */
        width: 100%;
        margin: auto;
    }
</style>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 id="inventory" class="fw-bold text-dark">Inventory Overview</h2>
        <form method="get" action="admin.php" class="d-flex align-items-center gap-2">
            <input type="hidden" name="page" value="inventory_overview">
            <input type="text" name="search" class="form-control" placeholder="Search for ingredients" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="border-radius: 20px; width: 250px;">
            <button class="btn btn-light" type="submit" style="border-radius: 20px; border: 1px solid #ddd;"><i class="fas fa-search text-muted"></i></button>
        </form>
    </div>

    <?php if (!empty($low_stock_items)): ?>
        <div class="alert alert-refill alert-dismissible fade show mb-4" role="alert">
            <strong>ALERT! REFILL REQUIRED:</strong>
            <?php foreach ($low_stock_items as $index => $item): ?>
                <?= htmlspecialchars($item['name']) ?> (Còn: <?= intval($item['stock_quantity']) ?>)
                <?php if ($index < count($low_stock_items) - 1): ?>, <?php endif; ?>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <h5 class="mb-3 fw-bold">Inventory Checking</h5>
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card dashboard-card">
                <div class="card-body card-body-custom">
                    <i class="fas fa-layer-group card-icon total-stock"></i>
                    <h6 class="card-title-text">Total Stock</h6>
                    <h4 class="card-value"><?= number_format($total_qty) ?></h4>
                    <p class="card-meta-text">458 total stock</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card">
                <div class="card-body card-body-custom">
                    <i class="fas fa-truck-loading card-icon pending-po"></i>
                    <h6 class="card-title-text">Pending PO</h6>
                    <h4 class="card-value"><?= number_format($pending_po) ?></h4>
                    <p class="card-meta-text">51 pending PO</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card">
                <div class="card-body card-body-custom">
                    <i class="fas fa-industry card-icon supplier"></i>
                    <h6 class="card-title-text">Supplier</h6>
                    <h4 class="card-value"><?= $suppliers_cnt ?></h4>
                    <p class="card-meta-text">3 new in this month</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card">
                <div class="card-body card-body-custom">
                    <i class="fas fa-tags card-icon category"></i>
                    <h6 class="card-title-text">Categories</h6>
                    <h4 class="card-value"><?= $cat_cnt ?></h4>
                    <p class="card-meta-text">All product categories</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="table-container-custom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">HotPot List</h5>
                    <a href="admin.php?page=inventory_checking" class="btn btn-link p-0 text-decoration-none fw-bold" style="color: #0d6efd;">View All</a>
                </div>

                <ul class="nav nav-pills mb-3 justify-content-start" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $sel_cat === 'Hotpot' ? 'active' : '' ?>" href="admin.php?page=inventory_overview&cat=Hotpot#inventory" role="tab">Hotpot</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $sel_cat === 'Meat' ? 'active' : '' ?>" href="admin.php?page=inventory_overview&cat=Meat#inventory" role="tab">Meat</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $sel_cat === 'Viscera' ? 'active' : '' ?>" href="admin.php?page=inventory_overview&cat=Viscera#inventory" role="tab">Viscera</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $sel_cat === 'Sea food' ? 'active' : '' ?>" href="admin.php?page=inventory_overview&cat=Sea%20food#inventory" role="tab">Seafood</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $sel_cat === 'Hot pot balls' ? 'active' : '' ?>" href="admin.php?page=inventory_overview&cat=Hot%20pot%20balls#inventory" role="tab">Hotpot Balls</a>
                    </li>
                     <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $sel_cat === 'All' ? 'active' : '' ?>" href="admin.php?page=inventory_overview&cat=All#inventory" role="tab">View All</a>
                    </li>
                    <?php
                    // Display other categories dynamically if needed, or filter out specific ones
                    // foreach ($categories as $c) {
                    //     if (!in_array($c, ['Hotpot', 'Meat', 'Viscera', 'Sea food', 'Hot pot balls'])) {
                    //         echo '<li class="nav-item" role="presentation"><a class="nav-link ' . ($sel_cat === $c ? 'active' : '') . '" href="admin.php?page=inventory_overview&cat=' . urlencode($c) . '#inventory" role="tab">' . htmlspecialchars($c) . '</a></li>';
                    //     }
                    // }
                    ?>
                </ul>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-header-custom">
                            <tr>
                                <th>Name</th>
                                <th>Image</th>
                                <th>Type</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stock_res && $stock_res->num_rows > 0): ?>
                                <?php while($r = $stock_res->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['name']) ?></td>
                                    <td><img src="<?= htmlspecialchars($r['image_url'] ?? 'https://placehold.co/40x40/e0e0e0/ffffff?text=No+Img') ?>" alt="<?= htmlspecialchars($r['name']) ?>" class="table-item-img-small"></td>
                                    <td><?= htmlspecialchars($r['category']) ?></td>
                                    <td><?= intval($r['stock_quantity']) ?> pack</td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No items found in this category.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="recent-usage-card">
                <h5 class="fw-bold">Recent Usage</h5>
                <?php if (!empty($limited_table_usage)): ?>
                    <?php foreach ($limited_table_usage as $table_number => $items_on_table): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold text-muted mb-2">Table #<?= htmlspecialchars($table_number) ?></h6>
                            <ul class="list-group list-group-table-usage">
                                <?php
                                $displayed_items_count = 0;
                                // Hiển thị tối đa 3-4 món mỗi bàn để không bị quá dài
                                foreach ($items_on_table as $item):
                                    if ($displayed_items_count >= 4) break; // Giới hạn số lượng món mỗi bàn
                                ?>
                                    <li class="list-group-item">
                                        <span><?= htmlspecialchars($item['item_name']) ?></span>
                                        <span class="badge rounded-pill"><?= intval($item['quantity']) ?></span>
                                    </li>
                                <?php
                                    $displayed_items_count++;
                                endforeach;
                                ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info text-center py-3">No recent usage data.</div>
                <?php endif; ?>

                <h5 class="fw-bold mt-4 mb-3">Inventory Distribution</h5>
                <div class="chart-container text-center">
                    <canvas id="inventoryPieChart"></canvas>
                    <p class="card-meta-text mt-2"><small>Distribution of items by quantity across categories.</small></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('inventoryPieChart');
    if (ctx) {
        var phpCategoryQuantities = <?php echo json_encode($category_quantities); ?>;
        var chartLabels = phpCategoryQuantities.map(item => item.category);
        var chartData = phpCategoryQuantities.map(item => item.stock);
        var predefinedColors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED',
            '#A7D9D8', '#F1B7B0', '#B0E0E6', '#F0F8FF', '#ADD8E6', '#87CEEB'
        ];

        // Chỉ vẽ biểu đồ nếu có dữ liệu
        if (chartData.length > 0) {
            new Chart(ctx, {
                type: 'doughnut', // Dùng doughnut chart thay vì pie chart để giống thiết kế
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartData,
                        backgroundColor: predefinedColors.slice(0, chartLabels.length),
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right', // Đặt legend sang phải
                            labels: {
                                boxWidth: 10,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        title: {
                            display: false, // Bỏ tiêu đề Chart.js nếu đã có tiêu đề HTML
                        }
                    },
                    cutout: '60%', // Kích thước lỗ ở giữa cho doughnut chart
                }
            });
        } else {
            // Hiển thị thông báo nếu không có dữ liệu
            const parent = ctx.parentElement;
            parent.innerHTML = '<p class="text-muted text-center">No inventory distribution data to display.</p>';
        }
    }
});
</script>
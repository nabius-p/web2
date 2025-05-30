<?php
// admin_inventory.php — include from admin.php (session_start & $conn already done)
// Đảm bảo file db.php tồn tại và kết nối $conn được khởi tạo
require_once 'db.php'; // KIỂM TRA LẠI FILE NÀY!

// --- Xử lý Thêm sản phẩm mới ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['item_category'] ?? '');
    $price = floatval($_POST['item_price'] ?? 0);
    $image_url = trim($_POST['item_image_url'] ?? '');

    // Xử lý trường hợp người dùng chọn "Other" cho danh mục
    if ($category === 'Other' && isset($_POST['new_category']) && !empty(trim($_POST['new_category']))) {
        $category = trim($_POST['new_category']);
    }

    if (!empty($name) && !empty($category) && $price >= 0) {
        $stmt = $conn->prepare("INSERT INTO items (name, category, price, image_url) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
            $_SESSION['flash_message'] = "Lỗi chuẩn bị SQL (Add Item): " . $conn->error;
            error_log("SQL Prepare Error (Add Item): " . $conn->error); // Ghi log lỗi
        } else {
            $stmt->bind_param("ssds", $name, $category, $price, $image_url);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Sản phẩm '{$name}' đã được thêm thành công!";
            } else {
                $_SESSION['flash_message'] = "Lỗi khi thực thi SQL (Add Item): " . $stmt->error;
                error_log("SQL Execute Error (Add Item): " . $stmt->error); // Ghi log lỗi
            }
            $stmt->close();
        }
    } else {
        $_SESSION['flash_message'] = "Vui lòng điền đầy đủ thông tin sản phẩm và đảm bảo giá hợp lệ.";
    }

    // Xây dựng URL chuyển hướng để giữ lại các tham số hiện có
    $current_query_params = array_filter($_GET); // Lọc bỏ các tham số rỗng
    $current_query_params['page'] = 'inventory_checking'; // Đảm bảo page luôn là inventory_checking
    $redirect_url = 'admin.php?' . http_build_query($current_query_params);
    header('Location: ' . $redirect_url);
    exit();
}

// --- Lấy danh sách danh mục cho bộ lọc và form thêm sản phẩm ---
$cats_query = $conn->query("SELECT DISTINCT category FROM items ORDER BY category");
$categories = [];
if ($cats_query) {
    while ($r = $cats_query->fetch_assoc()) {
        $categories[] = $r['category'];
    }
}

// --- Logic Tìm kiếm và Lọc ---
$keyword = trim($_GET['search'] ?? '');
$filter_category = $_GET['filter_category'] ?? 'All';
$filter_status = $_GET['filter_status'] ?? 'All'; // Trạng thái: Good, Refill, Out of Stock

// --- Phân trang ---
$items_per_page = 10;
$current_page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// --- Xây dựng Truy vấn Đếm và Dữ liệu ---
$sql_select_columns = "
    i.id,
    i.name,
    i.image_url,
    i.category,
    i.price,
    COALESCE(SUM(il.quantity), 0) AS amount,
    MAX(il.imported_at) AS latest_date
";

$sql_from_join = "
    FROM items i
    LEFT JOIN inventory_log il ON il.item_id = i.id
";

$conditions = [];
$bind_params = []; // Dùng chung cho cả đếm và dữ liệu, sau đó cắt bớt cho đếm
$bind_types = '';

// Điều kiện tìm kiếm
if ($keyword !== '') {
    $conditions[] = "(i.name LIKE ? OR i.category LIKE ?)";
    $bind_params[] = "%" . $keyword . "%";
    $bind_params[] = "%" . $keyword . "%";
    $bind_types .= "ss";
}

// Điều kiện lọc theo danh mục
if ($filter_category !== 'All') {
    $conditions[] = "i.category = ?";
    $bind_params[] = $filter_category;
    $bind_types .= "s";
}

// Xây dựng mệnh đề WHERE chung
$where_clause = '';
if (count($conditions) > 0) {
    $where_clause = " WHERE " . implode(" AND ", $conditions);
}

// --- Truy vấn đếm tổng số mục ---
$sql_count = "SELECT COUNT(DISTINCT i.id) AS total_items " . $sql_from_join . $where_clause;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    error_log("SQL Prepare Error (Count): " . $conn->error); // Ghi log lỗi
    die("Lỗi chuẩn bị câu lệnh đếm: " . $conn->error);
}

// Bind tham số cho truy vấn đếm (chỉ các điều kiện WHERE)
if (!empty($bind_types)) {
    $stmt_count->bind_param($bind_types, ...$bind_params);
}
$stmt_count->execute();
$total_items_result = $stmt_count->get_result()->fetch_assoc();
$total_items = $total_items_result['total_items'] ?? 0;
$total_pages = ceil($total_items / $items_per_page);
$stmt_count->close();

// Đảm bảo $current_page không vượt quá $total_pages sau khi tính toán tổng số mục
if ($total_pages == 0) {
    $current_page = 1;
    $offset = 0;
} elseif ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}

// --- Truy vấn lấy dữ liệu chính ---
$sql_data = "
    SELECT " . $sql_select_columns . "
    " . $sql_from_join . $where_clause . "
    GROUP BY i.id
    ORDER BY i.name
    LIMIT ? OFFSET ?";

$stmt_data = $conn->prepare($sql_data);
if ($stmt_data === false) {
    error_log("SQL Prepare Error (Data): " . $conn->error); // Ghi log lỗi
    die("Lỗi chuẩn bị câu lệnh dữ liệu: " . $conn->error);
}

// Thêm tham số LIMIT và OFFSET vào cuối mảng bind_params và bind_types
$final_bind_params = array_merge($bind_params, [$items_per_page, $offset]);
$final_bind_types = $bind_types . "ii";

if (!empty($final_bind_types)) {
    $stmt_data->bind_param($final_bind_types, ...$final_bind_params);
}
$stmt_data->execute();
$res = $stmt_data->get_result();

// --- Lọc trạng thái bằng PHP sau khi lấy dữ liệu ---
$filtered_items_by_status = [];
if ($res) {
    while ($item = $res->fetch_assoc()) {
        $amount = (int)$item['amount'];
        $current_status = 'Good';
        if ($amount <= 50 && $amount > 0) {
            $current_status = 'Refill';
        } elseif ($amount === 0) {
            $current_status = 'Out of Stock';
        }

        if ($filter_status === 'All' || $filter_status === $current_status) {
            $filtered_items_by_status[] = $item;
        }
    }
}
?>

<style>
    .category-header {
        font-size: 2rem;
        font-weight: 700;
        color: #333;
    }
    .search-filter-section {
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .search-input {
        border-radius: 20px;
        padding-left: 15px;
        border: 1px solid #ddd;
    }
    .btn-add-item, .btn-filter {
        border-radius: 20px;
        padding: 8px 20px;
        font-weight: 500;
        margin-left: 10px; /* Thêm khoảng cách */
    }
    .btn-add-item {
        background-color: #ffc107; /* Màu vàng */
        border-color: #ffc107;
        color: #333;
    }
    .btn-filter {
        background-color: #0d6efd; /* Màu xanh primary */
        border-color: #0d6efd;
        color: white;
    }
    .table-header-custom th {
        background-color: #f0f2f5; /* Nền xám nhạt */
        color: #555;
        font-weight: 600;
        vertical-align: middle;
        padding: 12px 15px;
    }
    .table-item-img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 8px; /* Bo góc ảnh */
    }
    .status-badge {
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.85em;
        font-weight: 600;
    }
    .status-good { background-color: #d4edda; color: #155724; } /* Xanh lá nhạt */
    .status-refill { background-color: #fff3cd; color: #856404; } /* Vàng nhạt */
    .status-out-of-stock { background-color: #f8d7da; color: #721c24; } /* Đỏ nhạt, đổi tên */

    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
    }
    .pagination .page-item .page-link {
        border-radius: 5px;
        margin: 0 3px;
    }
    .pagination .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: white;
    }
    .pagination-info {
        font-size: 0.9em;
        color: #6c757d;
    }
    /* Đảm bảo modal hiển thị trên cùng */
    .modal-backdrop.fade.show {
        z-index: 1050;
    }
    .modal.fade.show {
        z-index: 1060;
    }
</style>

<div class="container-fluid px-4 py-3">
    <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <h2 class="category-header mb-4">Category</h2>

    <div class="search-filter-section d-flex justify-content-between align-items-center mb-4">
        <form method="get" action="admin.php" class="d-flex align-items-center gap-3">
            <input type="hidden" name="page" value="inventory_checking">
            <input type="text" name="search" class="form-control search-input" placeholder="Search for ingredients..." value="<?= htmlspecialchars($keyword) ?>" style="width: 300px;">
            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
        </form>

        <div class="d-flex gap-3">
            <button class="btn btn-add-item" data-bs-toggle="modal" data-bs-target="#addItemModal">
                <i class="fas fa-plus me-2"></i>Add Item
            </button>
            <button class="btn btn-filter" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fas fa-filter me-2"></i>Filter
            </button>
        </div>
    </div>

    <div class="card shadow-sm rounded-lg">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-header-custom">
                    <tr>
                        <th class="ps-3">#</th> <th>Name</th>
                        <th>Image</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Price</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filtered_items_by_status)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No items found.</td></tr>
                    <?php else: ?>
                        <?php
                        // Tính toán số thứ tự bắt đầu
                        $row_number = $offset + 1;
                        foreach ($filtered_items_by_status as $item):
                        ?>
                            <?php
                                $amount = (int)$item['amount'];
                                $status_text = 'Good';
                                $status_class = 'status-good';

                                if ($amount <= 50 && $amount > 0) {
                                    $status_text = 'Refill';
                                    $status_class = 'status-refill';
                                } elseif ($amount === 0) {
                                    $status_text = 'Out of Stock';
                                    $status_class = 'status-out-of-stock';
                                }

                                // Sử dụng null coalescing operator cho image_url để tránh lỗi nếu null
                                $item_image_url = $item['image_url'] ?? 'https://placehold.co/50x50/e0e0e0/ffffff?text=No+Img';
                                // Xử lý đường dẫn ảnh nếu nó là img/abc.png
                                if (strpos($item_image_url, 'img/') === 0) {
                                    $item_image_url = htmlspecialchars($item_image_url);
                                } else {
                                    // Nếu là một URL đầy đủ hoặc không bắt đầu bằng img/, vẫn dùng trực tiếp
                                    $item_image_url = htmlspecialchars($item_image_url);
                                }

                                // Đảm bảo ngày nhập hiển thị đúng, hoặc '-' nếu NULL
                                $date = $item['latest_date'] ? date('d/m/Y', strtotime($item['latest_date'])) : '-';
                            ?>
                            <tr>
                                <td class="ps-3"><?= $row_number++ ?></td> <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><img src="<?= $item_image_url ?>" alt="Item Image" class="table-item-img"></td>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td><?= $amount ?> pack</td>
                                <td><?= number_format($item['price']) ?>₫</td>
                                <td><?= $date ?></td>
                                <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pagination-container">
        <div class="pagination-info">
            Showing <?= count($filtered_items_by_status) ?> of <?= $total_items ?> entries
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination mb-0">
                <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                    <?php
                    $prev_page_params = $_GET;
                    $prev_page_params['p'] = max(1, $current_page - 1);
                    // Lọc bỏ các tham số rỗng (ví dụ: search='') để URL gọn gàng hơn
                    $prev_page_url = 'admin.php?' . http_build_query(array_filter($prev_page_params, function($v) { return $v !== ''; }));
                    ?>
                    <a class="page-link" href="<?= htmlspecialchars($prev_page_url) ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo; Previous</span>
                    </a>
                </li>
                <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                    <?php
                    $next_page_params = $_GET;
                    $next_page_params['p'] = min($total_pages, $current_page + 1);
                    // Lọc bỏ các tham số rỗng
                    $next_page_url = 'admin.php?' . http_build_query(array_filter($next_page_params, function($v) { return $v !== ''; }));
                    ?>
                    <a class="page-link" href="<?= htmlspecialchars($next_page_url) ?>" aria-label="Next">
                        Next &raquo;
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="admin.php?page=inventory_checking">
                <input type="hidden" name="action" value="add_item">
                <?php // Giữ lại các tham số URL hiện có cho modal submit để quay lại trang đúng ?>
                <?php foreach ($_GET as $key => $value): ?>
                    <?php if ($key !== 'page'): // Tránh lặp lại 'page' vì nó đã có trong 'action' của form ?>
                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">Add New Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="item_name" class="form-label">Item Name</label>
                        <input type="text" class="form-control" id="item_name" name="item_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="item_category" class="form-label">Category</label>
                        <select class="form-select" id="item_category" name="item_category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                            <option value="Other">Other (Specify below)</option>
                        </select>
                        <input type="text" class="form-control mt-2" id="new_category" name="new_category" placeholder="Enter new category if 'Other' selected" style="display: none;">
                    </div>
                    <div class="mb-3">
                        <label for="item_price" class="form-label">Price (₫)</label>
                        <input type="number" step="1" class="form-control" id="item_price" name="item_price" required min="0">
                    </div>
                    <div class="mb-3">
                        <label for="item_image_url" class="form-label">Image URL</label>
                        <input type="url" class="form-control" id="item_image_url" name="item_image_url" placeholder="e.g., img/new_item.jpg">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="get" action="admin.php">
                <input type="hidden" name="page" value="inventory_checking">
                <?php // Giữ lại các tham số URL hiện có cho form filter ?>
                <?php
                $current_filter_params = $_GET;
                unset($current_filter_params['page']); // Tránh lặp lại page
                unset($current_filter_params['filter_category']); // Sẽ được đặt lại bởi select box
                unset($current_filter_params['filter_status']); // Sẽ được đặt lại bởi select box
                unset($current_filter_params['p']); // Sẽ được đặt lại về trang 1 khi lọc
                foreach ($current_filter_params as $key => $value): ?>
                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="p" value="1">

                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filter Items</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="filter_category" class="form-label">Category</label>
                        <select class="form-select" id="filter_category" name="filter_category">
                            <option value="All" <?= ($filter_category === 'All') ? 'selected' : '' ?>>All Categories</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= ($filter_category === $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="filter_status" class="form-label">Status</label>
                        <select class="form-select" id="filter_status" name="filter_status">
                            <option value="All" <?= ($filter_status === 'All') ? 'selected' : '' ?>>All Statuses</option>
                            <option value="Good" <?= ($filter_status === 'Good') ? 'selected' : '' ?>>Good</option>
                            <option value="Refill" <?= ($filter_status === 'Refill') ? 'selected' : '' ?>>Refill</option>
                            <option value="Out of Stock" <?= ($filter_status === 'Out of Stock') ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logic để hiển thị trường "Enter new category" khi chọn "Other"
    const itemCategorySelect = document.getElementById('item_category');
    const newCategoryInput = document.getElementById('new_category');

    if (itemCategorySelect && newCategoryInput) {
        itemCategorySelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                newCategoryInput.style.display = 'block';
                newCategoryInput.setAttribute('required', 'required');
            } else {
                newCategoryInput.style.display = 'none';
                newCategoryInput.removeAttribute('required');
                newCategoryInput.value = ''; // Xóa giá trị khi ẩn
            }
        });
    }

    // Đảm bảo modal hiển thị chính xác khi mở
    var addItemModal = document.getElementById('addItemModal');
    if (addItemModal) {
        addItemModal.addEventListener('show.bs.modal', function (event) {
            // Reset form khi modal mở
            const form = this.querySelector('form');
            if (form) {
                form.reset();
                // Đảm bảo trường new_category ẩn đi khi mở modal
                if (newCategoryInput) {
                    newCategoryInput.style.display = 'none';
                    newCategoryInput.removeAttribute('required');
                }
            }
        });
    }

    // Đảm bảo modal filter hiển thị các giá trị đã chọn
    var filterModal = document.getElementById('filterModal');
    if (filterModal) {
        filterModal.addEventListener('show.bs.modal', function (event) {
            const urlParams = new URLSearchParams(window.location.search);

            const filterCategory = urlParams.get('filter_category') || 'All';
            const filterStatus = urlParams.get('filter_status') || 'All';

            document.getElementById('filter_category').value = filterCategory;
            document.getElementById('filter_status').value = filterStatus;
        });
    }
});
</script>
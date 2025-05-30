<?php
// admin_add_item.php — include from admin.php (session_start & $conn already done)
require_once 'db.php'; // KIỂM TRA LẠI FILE NÀY!

// --- Lấy danh sách danh mục hiện có cho dropdown "Type" ---
$cats_query = $conn->query("SELECT DISTINCT category FROM items ORDER BY category");
$categories = [];
if ($cats_query) {
    while ($r = $cats_query->fetch_assoc()) {
        $categories[] = $r['category'];
    }
}

// --- Xử lý form Thêm sản phẩm mới khi được gửi ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_new_item') {
    $name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['item_category'] ?? '');
    $date_of_purchased = trim($_POST['date_of_purchased'] ?? ''); // Thêm trường ngày nhập
    $price = floatval($_POST['item_price'] ?? 0);
    $amount = intval($_POST['item_amount'] ?? 0); // Thêm trường số lượng
    $image_url = trim($_POST['item_image_url'] ?? '');

    // Xử lý trường hợp người dùng chọn "Other" cho danh mục
    if ($category === 'Other' && isset($_POST['new_category']) && !empty(trim($_POST['new_category']))) {
        $category = trim($_POST['new_category']);
    }

    // Kiểm tra dữ liệu đầu vào cơ bản
    if (empty($name) || empty($category) || empty($date_of_purchased) || $price < 0 || $amount < 0) {
        $_SESSION['flash_message'] = "Vui lòng điền đầy đủ thông tin bắt buộc: Tên, Loại, Ngày nhập, Giá, Số lượng.";
    } else {
        $conn->begin_transaction(); // Bắt đầu một transaction
        try {
            // 1. Thêm sản phẩm vào bảng `items`
            // Đã bỏ cột 'description'
            $stmt_item = $conn->prepare("INSERT INTO items (name, category, price, image_url) VALUES (?, ?, ?, ?)");
            if ($stmt_item === false) {
                throw new Exception("Lỗi chuẩn bị SQL (Add Item - items table): " . $conn->error);
            }
            // Bind tham số (ssds: string, string, double, string)
            $stmt_item->bind_param("ssds", $name, $category, $price, $image_url);
            if (!$stmt_item->execute()) {
                throw new Exception("Lỗi khi thêm sản phẩm vào bảng items: " . $stmt_item->error);
            }
            $new_item_id = $stmt_item->insert_id; // Lấy ID của sản phẩm vừa thêm
            $stmt_item->close();

            // 2. Thêm bản ghi vào `inventory_log` cho lần nhập kho ban đầu
            $stmt_log = $conn->prepare("INSERT INTO inventory_log (item_id, quantity, import_price, imported_at) VALUES (?, ?, ?, ?)");
            if ($stmt_log === false) {
                throw new Exception("Lỗi chuẩn bị SQL (Add Item - inventory_log): " . $conn->error);
            }
            // Giá nhập (import_price) lấy từ giá bán (price) nếu không có trường giá nhập riêng
            $stmt_log->bind_param("iids", $new_item_id, $amount, $price, $date_of_purchased);
            if (!$stmt_log->execute()) {
                throw new Exception("Lỗi khi thêm bản ghi vào inventory_log: " . $stmt_log->error);
            }
            $stmt_log->close();

            $conn->commit(); // Commit transaction nếu tất cả thành công
            $_SESSION['flash_message'] = "The product '{$name}' has been added successfully!";

        } catch (Exception $e) {
            $conn->rollback(); // Rollback transaction nếu có lỗi
            $_SESSION['flash_message'] = "Error when adding product: " . $e->getMessage();
            error_log("Add Item Transaction Error: " . $e->getMessage()); // Ghi log lỗi để debug
        }
    }

    // Chuyển hướng sau khi xử lý form
    header('Location: admin.php?page=add_item'); // Chuyển hướng về trang add_item
    exit();
}
?>

<style>
    .add-item-container {
        background-color: #ffffff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        max-width: 900px; /* Chiều rộng tối đa của form */
        margin: 30px auto; /* Căn giữa */
    }
    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }
    .form-header h2 {
        font-size: 1.8rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 0;
    }
    .form-group-custom {
        margin-bottom: 20px;
    }
    .form-control-custom {
        border-radius: 8px;
        padding: 10px 15px;
        border: 1px solid #ddd;
    }
    /* Đã bỏ style cho textarea.form-control-custom */
    .btn-save {
        background-color: #ffc107; /* Màu vàng */
        border-color: #ffc107;
        color: #333;
        font-weight: 600;
        padding: 10px 30px;
        border-radius: 25px;
        float: right; /* Đẩy nút save sang phải */
        margin-top: 20px;
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

    <div class="add-item-container">
        <div class="form-header">
            <h2>Add New Item</h2>
            <button type="button" class="btn-close" aria-label="Close" onclick="window.location.href='admin.php?page=inventory_checking';"></button>
        </div>

        <form method="post" action="admin.php?page=add_item" class="row g-3">
            <input type="hidden" name="action" value="add_new_item">

            <div class="col-md-6 form-group-custom">
                <label for="item_category" class="form-label">Type</label>
                <select class="form-select form-control-custom" id="item_category" name="item_category" required>
                    <option value="">Choose Type</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                    <option value="Other">Other (Specify below)</option>
                </select>
                <input type="text" class="form-control form-control-custom mt-2" id="new_category" name="new_category" placeholder="Enter new category if 'Other' selected" style="display: none;">
            </div>

            <div class="col-md-6 form-group-custom">
                <label for="item_name" class="form-label">Name</label>
                <input type="text" class="form-control form-control-custom" id="item_name" name="item_name" placeholder="Enter item name" required>
            </div>

            <div class="col-md-6 form-group-custom">
                <label for="item_image_url" class="form-label">Image URL</label>
                <input type="url" class="form-control form-control-custom" id="item_image_url" name="item_image_url" placeholder="e.g., img/new_item.jpg">
            </div>

            <div class="col-md-6 form-group-custom">
                <label for="date_of_purchased" class="form-label">Date of Purchased</label>
                <input type="date" class="form-control form-control-custom" id="date_of_purchased" name="date_of_purchased" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-6 form-group-custom">
                <label for="item_price" class="form-label">Price</label>
                <input type="number" step="0.01" class="form-control form-control-custom" id="item_price" name="item_price" placeholder="Enter price" min="0" required>
            </div>

            <div class="col-md-6 form-group-custom">
                <label for="item_amount" class="form-label">Amount</label>
                <input type="number" step="1" class="form-control form-control-custom" id="item_amount" name="item_amount" placeholder="Enter amount" min="0" required>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-save">Save</button>
            </div>
        </form>
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
});
</script>
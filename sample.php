<?php
session_start();

// Kết nối cơ sở dữ liệu
$servername = "localhost";
$username = "root";  // Thay bằng username của bạn
$password = "";      // Thay bằng password của bạn
$dbname = "hotpot_app";  // Tên cơ sở dữ liệu của bạn

$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Truy vấn danh sách món ăn từ cơ sở dữ liệu
$sql = "SELECT * FROM menu_items";
$result = $conn->query($sql);

// Xử lý khi form gửi lên để thêm món ăn vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = $_POST['item_name'];
    $price = $_POST['price'];
    $quantity = intval($_POST['quantity']);

    // Kiểm tra và thêm món ăn vào giỏ
    if (isset($_SESSION['cart'])) {
        $item_found = false;
        foreach ($_SESSION['cart'] as &$cart_item) {
            if ($cart_item['name'] == $item_name) {
                $cart_item['quantity'] += $quantity;  // Cập nhật số lượng món ăn
                $item_found = true;
                break;
            }
        }
        if (!$item_found) {
            $_SESSION['cart'][] = ['name' => $item_name, 'price' => $price, 'quantity' => $quantity];
        }
    } else {
        $_SESSION['cart'] = [['name' => $item_name, 'price' => $price, 'quantity' => $quantity]];
    }
}
?>

<!-- Menu Start -->
<div class="container-xxl py-5">
    <div class="container">
        <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
            <h5 class="section-title ff-secondary text-center text-primary fw-normal">Food Menu</h5>
            <h1 class="mb-5">Shinhot Pot Menu</h1>
        </div>
        <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
            <ul class="nav nav-pills d-inline-flex justify-content-center border-bottom mb-5">
                <?php
                // Hiển thị các thể loại món ăn từ cơ sở dữ liệu
                $categories = ["Hotpot", "Meat", "Viscera", "Sea food", "Hot pot balls", "Vegetables & Mushrooms", "Noodles"];
                foreach ($categories as $index => $category) {
                    echo "<li class='nav-item'>
                            <a class='d-flex align-items-center text-start mx-3 pb-3' data-bs-toggle='pill' href='#tab-".($index + 1)."'>
                                <div class='ps-3'>
                                    <h6 class='mt-n1 mb-0'>$category</h6>
                                </div>
                            </a>
                          </li>";
                }
                ?>
            </ul>

            <div class="tab-content">
                <?php
                // Lặp qua các thể loại món ăn và hiển thị các món ăn tương ứng
                foreach ($categories as $index => $category) {
                    echo "<div id='tab-".($index + 1)."' class='tab-pane fade show p-0 ".($index == 0 ? 'active' : '')."'>
                             <div class='row g-4'>";
                    
                    // Truy vấn món ăn theo thể loại
                    $sql = "SELECT * FROM menu_items WHERE category = '$category'";
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<div class='col-lg-6'>
                                    <div class='d-flex align-items-center'>
                                        <img class='flex-shrink-0 img-fluid rounded' src='".$row['image_url']."' alt='' style='width: 150px;'>
                                        <div class='w-100 d-flex flex-column text-start ps-4'>
                                            <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                <span>".$row['name']."</span>
                                                <span class='text-primary'>".number_format($row['price'], 0, ',', '.')." ₫</span>
                                            </h5>
                                            <form action='menu.php' method='post'>
                                                <input type='hidden' name='item_name' value='".$row['name']."'>
                                                <input type='hidden' name='price' value='".$row['price']."'>
                                                <input type='number' name='quantity' value='1' min='0' class='form-control mb-2' style='width: 70px;'>
                                                <button type='submit' class='btn btn-primary'>+</button>
                                            </form>
                                        </div>
                                    </div>
                                  </div>";
                        }
                    } else {
                        echo "<p>No items found in this category.</p>";
                    }
                    
                    echo "</div></div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>
<!-- Menu End -->

<?php
// Đóng kết nối
$conn->close();
?>

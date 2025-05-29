<?php
session_start();
include('db.php');

// Khởi tạo cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Bắt buộc đã chọn bàn
if (!isset($_SESSION['table_number'], $_SESSION['customer_name'], $_SESSION['customer_email'])) {
    header('Location: select_table.php');
    exit();
}

// Xác định category (nếu có)
$selected = $_GET['category'] ?? '';

// === 1) XỬ LÝ REMOVE ===
if (isset($_GET['remove_item'])) {
    // Nếu bạn dùng index làm key:
    $idx = intval($_GET['remove_item']);
    if (isset($_SESSION['cart'][$idx])) {
        unset($_SESSION['cart'][$idx]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    header("Location: menu.php?category=" . urlencode($selected) . "#menu-items");
    exit();
}

// === 2) XỬ LÝ UPDATE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'], $_POST['item_name'], $_POST['quantity'])) {
    $name = $_POST['item_name'];
    $qty  = max(1, intval($_POST['quantity']));
    foreach ($_SESSION['cart'] as &$it) {
        if ($it['name'] === $name) {
            $it['quantity'] = $qty;
            break;
        }
    }
    unset($it);
    header("Location: menu.php?category=" . urlencode($selected) . "#menu-items");
    exit();
}

// === 3) XỬ LÝ ADD (dấu +) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_name'], $_POST['price'], $_POST['quantity']) && !isset($_POST['update_quantity'])) {
    $name  = $_POST['item_name'];
    $price = floatval($_POST['price']);
    $qty   = max(1, intval($_POST['quantity']));
    $found = false;
    foreach ($_SESSION['cart'] as &$it) {
        if ($it['name'] === $name) {
            $it['quantity'] += $qty;
            $found = true;
            break;
        }
    }
    unset($it);
    if (!$found) {
        $_SESSION['cart'][] = ['name'=>$name, 'price'=>$price, 'quantity'=>$qty];
    }
    header("Location: menu.php?category=" . urlencode($selected) . "#menu-items");
    exit();
}

// Tính tổng để hiển thị trong giỏ
$total_price = 0;
foreach ($_SESSION['cart'] as $ci) {
    $total_price += $ci['price'] * $ci['quantity'];
}
?>
 

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Restoran - Bootstrap Restaurant Template</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

    <!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Nunito:wght@600;700;800&family=Pacifico&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <div class="container-xxl bg-white p-0">


        <!-- Navbar & Hero Start -->
        <div class="container-xxl position-relative p-0">
            <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4 px-lg-5 py-3 py-lg-0">
                <a href="" class="navbar-brand p-0">
                    <h1 class="text-primary m-0"><i class="fa fa-utensils me-3"></i>ShinHot Pot</h1>
                    <!-- <img src="img/logo.png" alt="Logo"> -->
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto py-0 pe-4">
                        <a href="index.html" class="nav-item nav-link">Home</a>
                        <a href="about.html" class="nav-item nav-link">About</a>
                        <a href="select_table.php" class="nav-item nav-link active">Order</a>
                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Login</a>
                            <div class="dropdown-menu m-0">
                                <a href="login.php" class="dropdown-item">Admin</a>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-xxl py-5 bg-dark hero-header mb-5">
                <div class="container text-center my-5 pt-5 pb-4">
                    <h1 class="display-3 text-white mb-3 animated slideInDown">Food Menu</h1>
                </div>
            </div>
        </div>
        <!-- Navbar & Hero End -->


 <!-- Menu Start -->
        <div class="container-xxl py-5">
            <div class="container">
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h5 class="section-title ff-secondary text-center text-primary fw-normal">Food Menu</h5>
                    <h1 class="mb-5">Shinhot Pot Menu</h1>
                </div>
                <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
                    <ul class="nav nav-pills d-inline-flex justify-content-center border-bottom mb-5">
                        <li class="nav-item">
                            <a class="d-flex align-items-center text-start mx-3 ms-0 pb-3 active" data-bs-toggle="pill" href="#tab-1">
                                <div class="ps-3">
                                    <h6 class="mt-n1 mb-0">Hotpot</h6>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex align-items-center text-start mx-3 pb-3" data-bs-toggle="pill" href="#tab-2">
                                <div class="ps-3">
                                    <h6 class="mt-n1 mb-0">Meat</h6>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex align-items-center text-start mx-3 pb-3" data-bs-toggle="pill" href="#tab-3">
                                <div class="ps-3">
                                    <h6 class="mt-n1 mb-0">Viscera</h6>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex align-items-center text-start mx-3 me-0 pb-3" data-bs-toggle="pill" href="#tab-4">
                                <div class="ps-3">
                                    <h6 class="mt-n1 mb-0">Seafood</h6>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex align-items-center text-start mx-3 me-0 pb-3" data-bs-toggle="pill" href="#tab-5">
                                <div class="ps-3">
                                    <h6 class="mt-n1 mb-0">Hot pot balls</h6>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex align-items-center text-start mx-3 me-0 pb-3" data-bs-toggle="pill" href="#tab-6">
                                <div class="ps-3">
                                    <h6 class="mt-n1 mb-0">Vegetables & Mushrooms</h6>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex align-items-center text-start mx-3 me-0 pb-3" data-bs-toggle="pill" href="#tab-7">
                                <div class="ps-3">
                                    <h6 class="mt-n1 mb-0">Noodles</h6>
                                </div>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div id="tab-1" class="tab-pane fade show p-0 active">
                            <div class="row g-4">
                                <!-- Content for Hotpot -->
                                <?php
                                // Fetch and display items for Hotpot
                                $sql = "SELECT * FROM items WHERE category = 'Hotpot'";
                                $result = $conn->query($sql);
                                while($item = $result->fetch_assoc()) {
                                    echo "
                                    <div class='col-lg-6'>
                                        <div class='d-flex align-items-center'>
                                            <img class='flex-shrink-0 img-fluid rounded' src='" . $item['image_url'] . "' alt='' style='width: 150px;'>
                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                    <span>" . $item['name'] . "</span>
                                                    <span class='text-primary'>" . number_format($item['price'], 0, ',', '.') . " ₫</span>
                                                </h5>
                                                <form action='menu.php' method='post'>
                                                    <input type='hidden' name='item_name' value='" . $item['name'] . "'>
                                                    <input type='hidden' name='price' value='" . $item['price'] . "'>
                                                    <input type='number' name='quantity' value='1' min='1' 
                                                           class='form-control mb-2' style='width:70px;'>

                                                    <button type='submit' class='btn btn-primary'>+</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>";
                                }
                                ?>
                            </div>
                        </div>
                        <div id="tab-2" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <!-- Content for Meat -->
                                <?php
                                // Fetch and display items for Meat
                                $sql = "SELECT * FROM items WHERE category = 'Meat'";
                                $result = $conn->query($sql);
                                while($item = $result->fetch_assoc()) {
                                    echo "
                                    <div class='col-lg-6'>
                                        <div class='d-flex align-items-center'>
                                            <img class='flex-shrink-0 img-fluid rounded' src='" . $item['image_url'] . "' alt='' style='width: 150px;'>
                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                    <span>" . $item['name'] . "</span>
                                                    <span class='text-primary'>" . number_format($item['price'], 0, ',', '.') . " ₫</span>
                                                </h5>
                                                <form action='menu.php' method='post'>
                                                    <input type='hidden' name='item_name' value='" . $item['name'] . "'>
                                                    <input type='hidden' name='price' value='" . $item['price'] . "'>
                                                     <input type='number' name='quantity' value='1' min='1' 
                                                           class='form-control mb-2' style='width:70px;'>
                                                    <button type='submit' class='btn btn-primary'>+</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>";
                                }
                                ?>
                            </div>
                        </div>
                        <div id="tab-3" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <!-- Content for Viscera -->
                                <?php
                                // Fetch and display items for Viscera
                                $sql = "SELECT * FROM items WHERE category = 'Viscera'";
                                $result = $conn->query($sql);
                                while($item = $result->fetch_assoc()) {
                                    echo "
                                    <div class='col-lg-6'>
                                        <div class='d-flex align-items-center'>
                                            <img class='flex-shrink-0 img-fluid rounded' src='" . $item['image_url'] . "' alt='' style='width: 150px;'>
                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                    <span>" . $item['name'] . "</span>
                                                    <span class='text-primary'>" . number_format($item['price'], 0, ',', '.') . " ₫</span>
                                                </h5>
                                                <form action='menu.php' method='post'>
                                                    <input type='hidden' name='item_name' value='" . $item['name'] . "'>
                                                    <input type='hidden' name='price' value='" . $item['price'] . "'>
                                                     <input type='number' name='quantity' value='1' min='1' 
                                                           class='form-control mb-2' style='width:70px;'>
                                                    <button type='submit' class='btn btn-primary'>+</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>";
                                }
                                ?>
                            </div>
                        </div>
                        <div id="tab-4" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <!-- Content for Sea Food -->
                                <?php
                                // Fetch and display items for Sea Food
                                $sql = "SELECT * FROM items WHERE category = 'Sea Food'";
                                $result = $conn->query($sql);
                                while($item = $result->fetch_assoc()) {
                                    echo "
                                    <div class='col-lg-6'>
                                        <div class='d-flex align-items-center'>
                                            <img class='flex-shrink-0 img-fluid rounded' src='" . $item['image_url'] . "' alt='' style='width: 150px;'>
                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                    <span>" . $item['name'] . "</span>
                                                    <span class='text-primary'>" . number_format($item['price'], 0, ',', '.') . " ₫</span>
                                                </h5>
                                                <form action='menu.php' method='post'>
                                                    <input type='hidden' name='item_name' value='" . $item['name'] . "'>
                                                    <input type='hidden' name='price' value='" . $item['price'] . "'>
                                                     <input type='number' name='quantity' value='1' min='1' 
                                                           class='form-control mb-2' style='width:70px;'>
                                                    <button type='submit' class='btn btn-primary'>+</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>";
                                }
                                ?>
                            </div>
                        </div>
                        <div id="tab-5" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <!-- Content for Hot pot balls -->
                                <?php
                                // Fetch and display items for Hot pot balls
                                $sql = "SELECT * FROM items WHERE category = 'Hot pot balls'";
                                $result = $conn->query($sql);
                                while($item = $result->fetch_assoc()) {
                                    echo "
                                    <div class='col-lg-6'>
                                        <div class='d-flex align-items-center'>
                                            <img class='flex-shrink-0 img-fluid rounded' src='" . $item['image_url'] . "' alt='' style='width: 150px;'>
                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                    <span>" . $item['name'] . "</span>
                                                    <span class='text-primary'>" . number_format($item['price'], 0, ',', '.') . " ₫</span>
                                                </h5>
                                                <form action='menu.php' method='post'>
                                                    <input type='hidden' name='item_name' value='" . $item['name'] . "'>
                                                    <input type='hidden' name='price' value='" . $item['price'] . "'>
                                                     <input type='number' name='quantity' value='1' min='1' 
                                                           class='form-control mb-2' style='width:70px;'>
                                                    <button type='submit' class='btn btn-primary'>+</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>";
                                }
                                ?>
                            </div>
                        </div>
                        <div id="tab-6" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <!-- Content for Vegetables & Mushrooms -->
                                <?php
                                // Fetch and display items for Vegetables & Mushrooms
                                $sql = "SELECT * FROM items WHERE category = 'Vegetables & Mushrooms'";
                                $result = $conn->query($sql);
                                while($item = $result->fetch_assoc()) {
                                    echo "
                                    <div class='col-lg-6'>
                                        <div class='d-flex align-items-center'>
                                            <img class='flex-shrink-0 img-fluid rounded' src='" . $item['image_url'] . "' alt='' style='width: 150px;'>
                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                    <span>" . $item['name'] . "</span>
                                                    <span class='text-primary'>" . number_format($item['price'], 0, ',', '.') . " ₫</span>
                                                </h5>
                                                <form action='menu.php' method='post'>
                                                    <input type='hidden' name='item_name' value='" . $item['name'] . "'>
                                                    <input type='hidden' name='price' value='" . $item['price'] . "'>
                                                     <input type='number' name='quantity' value='1' min='1' 
                                                           class='form-control mb-2' style='width:70px;'>
                                                    <button type='submit' class='btn btn-primary'>+</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>";
                                }
                                ?>
                            </div>
                        </div>
                        <div id="tab-7" class="tab-pane fade show p-0">
                            <div class="row g-4">
                                <!-- Content for Noodles -->
                                <?php
                                // Fetch and display items for  Noodles
                                $sql = "SELECT * FROM items WHERE category = 'Noodles'";
                                $result = $conn->query($sql);
                                while($item = $result->fetch_assoc()) {
                                    echo "
                                    <div class='col-lg-6'>
                                        <div class='d-flex align-items-center'>
                                            <img class='flex-shrink-0 img-fluid rounded' src='" . $item['image_url'] . "' alt='' style='width: 150px;'>
                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                    <span>" . $item['name'] . "</span>
                                                    <span class='text-primary'>" . number_format($item['price'], 0, ',', '.') . " ₫</span>
                                                </h5>
                                                <form action='menu.php' method='post'>
                                                    <input type='hidden' name='item_name' value='" . $item['name'] . "'>
                                                    <input type='hidden' name='price' value='" . $item['price'] . "'>
                                                     <input type='number' name='quantity' value='1' min='1' 
                                                           class='form-control mb-2' style='width:70px;'>
                                                    <button type='submit' class='btn btn-primary'>+</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Menu End -->
        
        <!-- Cart Section Start -->
<div class="container my-5">
  <h3 class="mb-4">Your Cart</h3>

  <?php if (!empty($_SESSION['cart'])): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Dish</th>
                <th class="text-center">Quantity</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Subtotal</th>
                <th class="text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($_SESSION['cart'] as $item): 
                $subtotal = $item['price'] * $item['quantity'];
              ?>
                <tr>
                  <td><?= htmlspecialchars($item['name']) ?></td>
                  <td class="text-center"><?= intval($item['quantity']) ?></td>
                  <td class="text-end"><?= number_format($item['price'],0,',','.') ?> ₫</td>
                  <td class="text-end"><?= number_format($subtotal,0,',','.') ?> ₫</td>
                  <td class="text-center">
                    <a href="?remove_item=<?= urlencode($item['name']) ?>"
                       class="btn btn-sm btn-outline-danger"
                       title="Remove">
                      <i class="fas fa-trash-alt"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4">
          <h5 class="mb-0">Total:</h5>
          <h4 class="mb-0 text-primary"><?= number_format($total_price,0,',','.') ?> ₫</h4>
        </div>

        <div class="text-end mt-3">
          <form action="order.php" method="post" class="d-inline">
            <button type="submit" name="place_order" class="btn btn-success btn-lg">
              <i class="fas fa-credit-card me-2"></i> Place Order
            </button>
          </form>
        </div>
      </div>
    </div>

  <?php else: ?>
    <div class="alert alert-info">
      Your cart is currently empty.
    </div>
  <?php endif; ?>
</div>
<!-- Cart Section End -->



        <!-- Footer Start -->
        <div class="container-fluid bg-dark text-light footer pt-5 mt-5 wow fadeIn" data-wow-delay="0.1s">
            <div class="container py-5">
                <div class="row g-5">
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-primary fw-normal mb-4">Company</h4>
                        <a class="btn btn-link" href="about.html">About Us</a>
                        <a class="btn btn-link" href="select_table.php">Order</a>
                        
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-primary fw-normal mb-4">Contact</h4>
                        <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i>100 Vinh Phuc, Hanoi, Viet Nam</p>
                        <p class="mb-2"><i class="fa fa-phone-alt me-3"></i>+012 345 67890</p>
                        <p class="mb-2"><i class="fa fa-envelope me-3"></i>info@example.com</p>
                        <div class="d-flex pt-2">
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-twitter"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-youtube"></i></a>
                            <a class="btn btn-outline-light btn-social" href=""><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-primary fw-normal mb-4">Opening</h4>
                        <h5 class="text-light fw-normal">Monday - Saturday</h5>
                        <p>09AM - 09PM</p>
                        <h5 class="text-light fw-normal">Sunday</h5>
                        <p>10AM - 08PM</p>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <h4 class="section-title ff-secondary text-start text-primary fw-normal mb-4">Newsletter</h4>
                        <p>Dolor amet sit justo amet elitr clita ipsum elitr est.</p>
                        <div class="position-relative mx-auto" style="max-width: 400px;">
                            <input class="form-control border-primary w-100 py-3 ps-4 pe-5" type="text" placeholder="Your email">
                            <button type="button" class="btn btn-primary py-2 position-absolute top-0 end-0 mt-2 me-2">SignUp</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="copyright">
                    <div class="row">
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer End -->


        <!-- Back to Top -->
        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/tempusdominus/js/moment.min.js"></script>
    <script src="lib/tempusdominus/js/moment-timezone.min.js"></script>
    <script src="lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>

    <!-- Template Javascript -->
    <script src="js/main.js"></script>
</body>

</html>
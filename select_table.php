<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "hotpot_app";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$error = '';
$step = 1;

// Bước 2: Xử lý form thông tin khách hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_table_step2'])) {
    $table_id      = intval($_POST['table_id']);
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email= trim($_POST['customer_email'] ?? '');
    if ($customer_name === '' || $customer_email === '') {
        $error = "Vui lòng điền đầy đủ thông tin.";
        $step = 2;
        $selected_table_id = $table_id;
    } else {
        $_SESSION['table_id']       = $table_id;
        $_SESSION['customer_name']  = $customer_name;
        $_SESSION['customer_email'] = $customer_email;
        // Tạo hóa đơn mới
        $stmt = $conn->prepare("
            INSERT INTO invoices (customer_name, customer_email, total_amount, table_id, created_at)
            VALUES (?, ?, 0, ?, NOW())
        ");
        $stmt->bind_param("ssi", $customer_name, $customer_email, $table_id);
        if ($stmt->execute()) {
            $_SESSION['invoice_id'] = $conn->insert_id;
            // Cập nhật bàn sang trạng thái 'occupied' (đang đợi phục vụ)
            $conn->query("UPDATE restaurant_tables SET status='occupied' WHERE id=$table_id");
            header("Location: menu.php");
            exit();
        } else {
            $error = "Lỗi khi tạo hóa đơn: " . $stmt->error;
            $step = 2;
            $selected_table_id = $table_id;
        }
        $stmt->close();
    }
}

// Bước 1: Chọn bàn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_table_step1'])) {
    $selected_table_id = intval($_POST['selected_table_id']);
    $step = 2;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>ShinHot Pot - Chọn Bàn</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body { background: #fff; }
        .table-btn {
            width: 110px; height: 110px; font-size: 2rem; margin: 10px;
            border-radius: 18px; border: 2px dashed #f0ad4e; transition: 0.15s;
        }
        .table-btn.open {
            background: #fff; color: #222;
            font-weight: 600;
        }
        .table-btn.occupied {
            background: #FFA726; color: #fff; border-style: solid; cursor: not-allowed;
            font-weight: 600;
        }
        .table-btn:active { transform: scale(0.98); }
        /* Footer màu xanh đen, chữ vàng cho section title, logo vàng */
        .footer, .footer a, .footer p, .footer h4, .footer h5, .footer h6, .footer .section-title, .footer .btn-link {
            background: #081A3A !important;
            color: #fff !important;
        }
        .footer .section-title, .footer h4.section-title.ff-secondary {
            color: #FFD600 !important;
        }
        .logo-yellow { color: #FFD600 !important; }
        .toggle-label {margin-right: 20px; font-weight: 600;}
        .toggle-dot {
            width: 18px; height: 18px; border-radius: 4px; display: inline-block; margin-right: 7px;
            border: 2px solid #FFA726; background: #fff;
            vertical-align: middle;
        }
        .toggle-dot.serving { background: #FFA726; }
        .toggle-dot.available { background: #fff; }
    </style>
</head>
<body>
    <!-- Navbar & Hero Start -->
    <div class="container-xxl position-relative p-0">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4 px-lg-5 py-3 py-lg-0">
            <a href="" class="navbar-brand p-0">
                <h1 class="m-0" style="color:#FFA726;font-weight:bold;letter-spacing:1px;">
                    <i class="fa fa-utensils me-3"></i>ShinHot Pot
                </h1>
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
                <h1 class="display-3 text-white mb-3 fw-bold" style="font-weight:900 !important;letter-spacing:1px;">
                    Food Menu
                </h1>
            </div>
        </div>
    </div>
    <!-- Navbar & Hero End -->

    <!-- MAIN CONTENT -->
    <div class="container my-5">
        <div class="mx-auto" style="max-width:800px;">
            <?php if ($step == 1): ?>
                <div class="bg-white rounded shadow p-4">
                    <div style="font-size:1.25rem; font-weight:600;" class="mb-2">TABLE LIST</div>
                    <div class="mb-3" style="font-size:1rem;">
                        <span class="toggle-label"><span class="toggle-dot serving"></span>Serving</span>
                        <span class="toggle-label"><span class="toggle-dot available"></span>Available</span>
                    </div>
                    <form id="selectTableForm" method="POST">
                        <div class="d-flex flex-wrap justify-content-center">
                            <?php
                            $result = $conn->query("SELECT * FROM restaurant_tables ORDER BY table_number ASC");
                            while ($row = $result->fetch_assoc()):
                                // Lấy trạng thái từ DB
                                $status    = $row['status'];                // 'open' hoặc 'occupied'
                                // Nếu status = 'open', gán class open, cho type="submit"
                                // Nếu status = 'occupied', gán class occupied, type="button" + disabled
                                $btn_class = ($status == 'open') ? 'open' : 'occupied';
                            ?>
                                <?php if ($status == 'open'): ?>
                                    <button
                                        type="submit"
                                        class="table-btn <?php echo $btn_class; ?>"
                                        name="select_table_step1"
                                        value="1"
                                        onclick="document.getElementById('selected_table_id').value='<?php echo $row['id']; ?>';"
                                    >
                                        <?php echo sprintf('%02d', $row['table_number']); ?>
                                    </button>
                                <?php else: /* occupied */ ?>
                                    <button
                                        type="button"
                                        class="table-btn <?php echo $btn_class; ?>"
                                        disabled
                                    >
                                        <?php echo sprintf('%02d', $row['table_number']); ?>
                                    </button>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        </div>
                        <input type="hidden" name="selected_table_id" id="selected_table_id" />
                    </form>
                </div>
            <?php elseif ($step == 2): ?>
                <div class="d-flex justify-content-center align-items-center" style="min-height:400px;">
                    <div class="reservation-card d-flex w-100" style="max-width:600px;">
                        <div class="card-left d-flex align-items-center justify-content-center flex-grow-1 flex-shrink-1 flex-basis-0">
                            <div class="text-white text-center" style="font-size:2.4rem;font-weight:700;line-height:1.1;">
                                Table<br>No: <span id="show-table-num"><?php echo sprintf('%02d', $selected_table_id); ?></span>
                            </div>
                        </div>
                        <div class="vr mx-2" style="background:rgba(255,255,255,0.3); width:1px;"></div>
                        <div class="card-right flex-grow-1 flex-shrink-1 flex-basis-0 py-4 px-3">
                            <form method="POST" action="select_table.php">
                                <input type="hidden" name="table_id" value="<?php echo $selected_table_id; ?>">
                                <div class="mb-3">
                                    <label class="form-label text-white">Customer Name</label>
                                    <input type="text" class="form-control custom-input" name="customer_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-white">Email</label>
                                    <input type="email" class="form-control custom-input" name="customer_email" required>
                                </div>
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                <div class="d-flex gap-2 mt-4">
                                    <button type="submit" name="select_table_step2" class="btn btn-white-green">Select Table</button>
                                    <a href="select_table.php" class="btn btn-white-normal">Back</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <style>
                .reservation-card {
                  background: #FFA726;
                  border-radius: 36px;
                  overflow: hidden;
                  min-height: 320px;
                  box-shadow: 0 6px 32px 0 rgba(0,0,0,0.10);
                }
                .card-left {
                  min-width: 180px;
                  background: transparent;
                  display: flex;
                  justify-content: center;
                  align-items: center;
                }
                .card-right {
                  background: transparent;
                  min-width: 220px;
                }
                .vr {
                  width: 1.5px;
                  background: rgba(255,255,255,0.25);
                  height: 100%;
                  align-self: stretch;
                }
                .custom-input {
                  border-radius: 8px !important;
                  border: none !important;
                  padding: 0.7rem 1rem;
                }
                .btn-white-green {
                  background: #fff;
                  color: #222;
                  border-radius: 24px;
                  border: 2px solid #2ecc40;
                  font-weight: 500;
                  padding: 7px 24px;
                  transition: box-shadow 0.1s;
                }
                .btn-white-green:hover {
                  background: #f5f5f5;
                  box-shadow: 0 2px 8px rgba(46,204,64,0.10);
                }
                .btn-white-normal {
                  background: #fff;
                  color: #222;
                  border-radius: 24px;
                  border: none;
                  font-weight: 500;
                  padding: 7px 28px;
                  transition: background 0.1s;
                  text-align: center;
                  text-decoration: none;
                  display: inline-block;
                }
                .btn-white-normal:hover {
                  background: #eee;
                }
                @media (max-width: 600px) {
                  .reservation-card { flex-direction: column; min-width:0; }
                  .vr { display:none;}
                }
                </style>
            <?php endif; ?>
        </div>
    </div>
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
                    <p class="mb-2"><i class="fa fa-map-marker-alt me-3"></i>1 Trinh Van Bo, Hanoi</p>
                    <p class="mb-2"><i class="fa fa-phone-alt me-3"></i>+84123456789</p>
                    <p class="mb-2"><i class="fa fa-envelope me-3"></i>info@example.com</p>
                    <div class="d-flex pt-2">
                        <a class="btn btn-outline-light btn-social" href="#"><i class="fab fa-twitter"></i></a>
                        <a class="btn btn-outline-light btn-social" href="#"><i class="fab fa-facebook-f"></i></a>
                        <a class="btn btn-outline-light btn-social" href="#"><i class="fab fa-youtube"></i></a>
                        <a class="btn btn-outline-light btn-social" href="#"><i class="fab fa-linkedin-in"></i></a>
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
                <div class="row"></div>
            </div>
        </div>
    </div>
    <!-- Footer End -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>

<?php
// admin_rvn_cus_detail.php

include 'db.php';
// hiển thị flash voucher
if (!empty($_SESSION['flash_voucher_success'])) {
    echo '<div class="alert alert-success text-center">'
       . htmlspecialchars($_SESSION['flash_voucher_success'])
       . '</div>';
    unset($_SESSION['flash_voucher_success']);
}
if (!empty($_SESSION['flash_voucher_error'])) {
    echo '<div class="alert alert-danger text-center">'
       . htmlspecialchars($_SESSION['flash_voucher_error'])
       . '</div>';
    unset($_SESSION['flash_voucher_error']);
}
// 1) Bảo vệ trang
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// 2) Lấy email từ query string
$email = $_GET['email'] ?? '';
if (!$email) {
    header('Location: admin.php?page=revenue_customers');
    exit;
}

// 3) Lấy tên khách và tổng chi tiêu
// 3.1) Tên
$stmt = $conn->prepare("SELECT customer_name FROM invoices WHERE customer_email=? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($name);
if (!$stmt->fetch()) {
    header('Location: admin.php?page=revenue_customers');
    exit;
}
$stmt->close();

// 3.2) Tổng chi tiêu -> derive EVA
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE customer_email=?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($total_spent);
$stmt->fetch();
$stmt->close();

if      ($total_spent >= 5000000) $eva = 'VIP';
elseif  ($total_spent >= 1000000) $eva = 'POTENTIAL';
else                              $eva = 'NEW';

// 4) Lấy 10 hóa đơn gần nhất
$stmt = $conn->prepare("
  SELECT id, created_at, total_amount
  FROM invoices
  WHERE customer_email=?
  ORDER BY created_at DESC
  LIMIT 10
");
$stmt->bind_param('s', $email);
$stmt->execute();
$recent = $stmt->get_result();
$stmt->close();
?>

<?php
  // trước khi xuất HTML
  $parts     = explode(' ', $name);
  $firstName = $parts[0] ?? '';
  $lastName  = $parts[count($parts)-1] ?? '';
?> 

<!-- phần này chỉ render nội dung chính, giả định được include trong admin.php -->
<div class="container my-5">
  <div class="row gx-5 gy-4">
    <!-- Left: Banner + Form -->
    <div class="col-lg-8">
      <div class="card shadow-sm rounded-3 overflow-hidden">
        <!-- Banner -->
        <div class="position-relative" style="height:180px; background:#e9ecef">
          <img src="https://via.placeholder.com/900x180" class="w-100 h-100" style="object-fit:cover" alt="banner">
          <!-- Avatar upload -->
          <label class="position-absolute" style="left:2rem; bottom:-30px;
                   width:80px; height:80px; border:3px solid #fff;
                   border-radius:50%; overflow:hidden; cursor:pointer;">
            <img id="avatarPreview" src="https://via.placeholder.com/80"
                 class="w-100 h-100" style="object-fit:cover" alt="avatar">
            <input type="file" accept="image/*" class="d-none" onchange="previewAvatar(this)">
          </label>
          <!-- Delete icon -->
          <button class="btn btn-light position-absolute"
                  style="top:1rem; right:1rem; border-radius:50%">
            <i class="fa fa-trash text-danger"></i>
          </button>
        </div>
        <!-- Form -->
        <div class="card-body pt-5">
          <form action="admin_customer_save.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            <div class="row gx-4 gy-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control bg-light" readonly
       value="<?=htmlspecialchars($firstName)?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-control bg-light" readonly
       value="<?=htmlspecialchars($lastName)?>">
              </div>
              <div class="col-12">
                <label class="form-label">Email</label>
                <input type="email" class="form-control bg-light" readonly
                       value="<?= htmlspecialchars($email) ?>">
              </div>
              <div class="col-12">
                <label class="form-label">EVA</label>
                <select name="eva" class="form-select bg-light">
                  <option value="NEW"       <?= $eva==='NEW'       ? 'selected' : '' ?>>NEW</option>
                  <option value="POTENTIAL" <?= $eva==='POTENTIAL'? 'selected' : '' ?>>POTENTIAL</option>
                  <option value="VIP"       <?= $eva==='VIP'       ? 'selected' : '' ?>>VIP</option>
                </select>
              </div>
            </div>
            <div class="d-flex gap-3">
              <button type="submit" class="btn btn-outline-success rounded-pill px-4">Save</button>
              <a href="admin.php?page=revenue_customers"
                 class="btn btn-outline-secondary rounded-pill px-4">Back</a>
              <a href="admin.php?page=revenue_customer_voucher&email=<?= urlencode($email) ?>"
   class="btn btn-warning rounded-pill px-4">
  Voucher
</a>

            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Right: Recent Bills -->
    <div class="col-lg-4">
      <h5 class="mb-3">Recent Bills</h5>
      <ul class="list-unstyled recent-bills">
        <?php while ($r = $recent->fetch_assoc()): ?>
          <li class="d-flex align-items-center">
            <img src="https://via.placeholder.com/40"
                 class="rounded-circle me-3" alt="">
            <div class="me-auto">
              <strong>No: <?= $r['id'] ?></strong><br>
              <small class="text-muted">
                <?= date('Y-m-d H:i', strtotime($r['created_at'])) ?>
              </small>
            </div>
            <span class="text-warning fw-semibold">
              <?= number_format($r['total_amount'],0,',','.') ?> ₫
            </span>
          </li>
        <?php endwhile; ?>
      </ul>
      <?php if ($recent->num_rows === 10): ?>
        <div class="text-center mt-3">
          <button class="btn btn-link text-danger">Load More</button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  // Preview avatar khi chọn file
  function previewAvatar(input) {
    const img = document.getElementById('avatarPreview');
    img.src = URL.createObjectURL(input.files[0]);
  }
</script>

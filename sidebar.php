<?php
// sidebar.php
// Gọi session_start() trước khi include nếu chưa gọi
// Xác định biến $page:
$page = $_GET['page'] ?? 'revenue_sales';
?>
<nav class="col-2 sidebar d-flex flex-column vh-100 bg-white border-end">
  
  <a href="admin.php" class="logo text-decoration-none px-3 py-2 fs-4 fw-bold">
    ShinHot Pot
  </a>
  <hr class="my-2">

  <h6 class="px-3 text-uppercase text-muted small">Operations</h6>
  <ul class="nav flex-column mb-4">
    <li class="nav-item">
      <a class="nav-link <?= ($page==='revenue_sales')?'active':''?>"
         href="admin.php?page=revenue_sales">
        <i class="fas fa-chart-line me-2"></i>Sales
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($page==='revenue_customers')?'active':''?>"
         href="admin.php?page=revenue_customers">
        <i class="fas fa-user-friends me-2"></i>Customers
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($page==='revenue_topsold')?'active':''?>"
         href="admin.php?page=revenue_topsold">
        <i class="fas fa-fire me-2"></i>Top Sold
      </a>
    </li>
  </ul>

  <h6 class="px-3 text-uppercase text-muted small">Inventory</h6>
  <ul class="nav flex-column mb-4">
    <li class="nav-item">
      <a class="nav-link <?= ($page==='inventory')?'active':''?>"
         href="admin.php?page=inventory">
        <i class="fas fa-boxes me-2"></i>Inventory Checking
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($page==='category')?'active':''?>"
         href="admin.php?page=category">
        <i class="fas fa-list-alt me-2"></i>Category
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($page==='add_item')?'active':''?>"
         href="admin.php?page=add_item">
        <i class="fas fa-plus-circle me-2"></i>Add New Items
      </a>
    </li>
  </ul>

  <h6 class="px-3 text-uppercase text-muted small">Table</h6>
  <ul class="nav flex-column mb-4">
    <li class="nav-item">
      <a class="nav-link <?= ($page==='table_list')?'active':''?>"
         href="admin.php?page=table_list">
        <i class="fas fa-table me-2"></i>Table List
      </a>
    </li>
  </ul>

  <div class="mt-auto px-3 pb-3">
    <a class="nav-link text-danger" href="logout.php">
      <i class="fas fa-sign-out-alt me-2"></i>Logout
    </a>
  </div>
</nav>

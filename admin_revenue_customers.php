<?php
// admin_revenue_customers.php

// 1) Include database connection
include 'db.php';

// 2) Handle the evaluation form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'evaluate_customer') {
    $email    = $_POST['email'];
    $category = $_POST['category'];  // new | potential | vip
    $note     = $_POST['note'];

    // Save into customer_evaluation table (must exist in your DB)
    $stmt = $conn->prepare("
      INSERT INTO customer_evaluation
        (customer_email, category, note, evaluated_at)
      VALUES (?, ?, ?, NOW())
      ON DUPLICATE KEY UPDATE
        category     = VALUES(category),
        note         = VALUES(note),
        evaluated_at = NOW()
    ");
    $stmt->bind_param("sss", $email, $category, $note);
    $stmt->execute();
    $msg = "Customer evaluation saved successfully!";
}

// 3) Pagination & search
$page_size = 10;
$page_num  = max(1, intval($_GET['p'] ?? 1));
$offset    = ($page_num - 1) * $page_size;
$search    = trim($_GET['q'] ?? '');

// Count total unique customers
if ($search !== '') {
    $like = '%' . $conn->real_escape_string($search) . '%';
    $qr = $conn->prepare("
      SELECT COUNT(DISTINCT customer_email) AS cnt
      FROM orders
      WHERE customer_name LIKE ?
    ");
    $qr->bind_param("s", $like);
    $qr->execute();
    $total_customers = (int)$qr->get_result()->fetch_assoc()['cnt'];
} else {
    $res = $conn->query("
      SELECT COUNT(DISTINCT customer_email) AS cnt 
      FROM orders
    ");
    $total_customers = (int)$res->fetch_assoc()['cnt'];
}

// 4) Fetch customers + aggregates + any existing evaluation
if ($search !== '') {
    $stmt = $conn->prepare("
      SELECT 
        o.customer_name,
        o.customer_email,
        MAX(o.order_date)      AS last_order,
        SUM(oi.quantity)       AS total_items,
        SUM(o.total_price)     AS total_spent,
        ce.category,
        ce.note
      FROM orders o
      JOIN order_items oi 
        ON oi.order_id = o.id
      LEFT JOIN customer_evaluation ce 
        ON ce.customer_email COLLATE utf8mb4_unicode_ci = o.customer_email
      WHERE o.customer_name LIKE ?
      GROUP BY o.customer_email, ce.category, ce.note
      ORDER BY last_order DESC
      LIMIT ?, ?
    ");
    $stmt->bind_param("sii", $like, $offset, $page_size);
} else {
    $stmt = $conn->prepare("
      SELECT 
        o.customer_name,
        o.customer_email,
        MAX(o.order_date)      AS last_order,
        SUM(oi.quantity)       AS total_items,
        SUM(o.total_price)     AS total_spent,
        ce.category,
        ce.note
      FROM orders o
      JOIN order_items oi 
        ON oi.order_id = o.id
      LEFT JOIN customer_evaluation ce 
        ON ce.customer_email COLLATE utf8mb4_unicode_ci = o.customer_email
      GROUP BY o.customer_email, ce.category, ce.note
      ORDER BY last_order DESC
      LIMIT ?, ?
    ");
    $stmt->bind_param("ii", $offset, $page_size);
}
$stmt->execute();
$customers = $stmt->get_result();
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Customer Overview</h5>
      <small class="text-muted">Total: <?= $total_customers ?></small>
      <form class="d-flex" method="get" action="">
        <input type="hidden" name="page" value="revenue_customers">
        <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Search by name"
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-sm btn-primary">Search</button>
      </form>
    </div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Last Order</th>
            <th>Items</th>
            <th>Spent</th>
            <th>Type</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="customer-accordion">
        <?php if ($customers->num_rows === 0): ?>
          <tr><td colspan="7" class="text-center py-4">No customers found.</td></tr>
        <?php else: ?>
          <?php while ($c = $customers->fetch_assoc()): ?>
            <?php 
              // Fetch per-customer history for collapse
              $hist = $conn->prepare("
                SELECT i.name, SUM(oi.quantity) AS qty, 
                       SUM(oi.total_price) AS spent
                FROM orders o
                JOIN order_items oi ON oi.order_id=o.id
                JOIN items i ON i.id=oi.item_id
                WHERE o.customer_email = ?
                GROUP BY oi.item_id
              ");
              $hist->bind_param("s", $c['customer_email']);
              $hist->execute();
              $res_hist = $hist->get_result();
            ?>
            <tr>
              <td><?= htmlspecialchars($c['customer_name']) ?></td>
              <td><?= htmlspecialchars($c['customer_email']) ?></td>
              <td><?= date('Y-m-d H:i', strtotime($c['last_order'])) ?></td>
              <td><?= number_format($c['total_items']) ?></td>
              <td><?= number_format($c['total_spent'],0,',','.') ?> ₫</td>
              <td>
                <?php 
                  $map = [
                    'new'       => ['badge bg-success','New'],
                    'potential' => ['badge bg-warning text-dark','Potential'],
                    'vip'       => ['badge bg-primary','VIP']
                  ];
                  if (!empty($c['category']) && isset($map[$c['category']])) {
                    list($cls,$lbl) = $map[$c['category']];
                    echo "<span class=\"$cls\">$lbl</span>";
                  } else {
                    echo '<span class="text-muted">—</span>';
                  }
                ?>
              </td>
              <td class="text-nowrap">
                <button class="btn btn-sm btn-info"
                        data-bs-toggle="collapse"
                        data-bs-target="#hist-<?= md5($c['customer_email']) ?>"
                        aria-expanded="false">
                  View
                </button>
                <button class="btn btn-sm btn-outline-primary"
                        onclick="showEvalModal('<?= addslashes($c['customer_email']) ?>')">
                  Eval
                </button>
              </td>
            </tr>
            <tr class="collapse" id="hist-<?= md5($c['customer_email']) ?>" data-bs-parent="#customer-accordion">
              <td colspan="7" class="p-0">
                <table class="table mb-0">
                  <thead class="table-light">
                    <tr><th>Dish</th><th>Qty</th><th class="text-end">Total Spent</th></tr>
                  </thead>
                  <tbody>
                  <?php while ($row = $res_hist->fetch_assoc()): ?>
                    <tr>
                      <td><?= htmlspecialchars($row['name']) ?></td>
                      <td><?= intval($row['qty']) ?></td>
                      <td class="text-end"><?= number_format($row['spent'],0,',','.') ?> ₫</td>
                    </tr>
                  <?php endwhile; ?>
                  </tbody>
                </table>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($offset + $page_size < $total_customers): ?>
      <div class="card-footer text-center">
        <a href="?page=revenue_customers&p=<?= $page_num+1 ?>&q=<?= urlencode($search) ?>"
           class="btn btn-outline-primary btn-sm">Load More</a>
      </div>
    <?php endif; ?>
  </div>

<!-- Evaluation Modal -->
<div class="modal fade" id="evalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content"
          method="post"
          action="admin.php?page=revenue_customers">
      <input type="hidden" name="action" value="evaluate_customer">
      <input type="hidden" name="email" id="evalEmail">
      <div class="modal-header">
        <h5 class="modal-title">Customer Evaluation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="evalCategory" class="form-label">Customer Type</label>
          <select name="category" id="evalCategory" class="form-select" required>
            <option value="new">New Customer</option>
            <option value="potential">Potential Customer</option>
            <option value="vip">VIP Customer</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="evalNote" class="form-label">Notes</label>
          <textarea name="note" id="evalNote" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save Evaluation</button>
      </div>
    </form>
  </div>
</div>

<script>
// this runs in the context of admin.php, which already loaded Bootstrap’s JS
function showEvalModal(email) {
  document.getElementById('evalEmail').value = email;
  new bootstrap.Modal(document.getElementById('evalModal')).show();
}
</script>
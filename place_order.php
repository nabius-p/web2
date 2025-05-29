<?php
session_start();
include('db.php'); // connect to DB, get $conn

// 1. If cart empty, send back to menu
if (empty($_SESSION['cart'])) {
    header('Location: menu.php');
    exit();
}

// 2. Grab session info
$ordered_items  = $_SESSION['cart'];
$table_number   = $_SESSION['table_number'];
$customer_name  = $_SESSION['customer_name'];
$customer_email = $_SESSION['customer_email'];

// 3. Compute total
$total_price = 0;
foreach ($ordered_items as $ci) {
    $total_price += $ci['price'] * $ci['quantity'];
}

// 4. Insert into orders
$sql_order = "INSERT INTO orders
    (table_number, customer_name, customer_email, total_price, order_date, status)
  VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, 'pending')";
$stmt = $conn->prepare($sql_order);
$stmt->bind_param("issd",
    $table_number,
    $customer_name,
    $customer_email,
    $total_price
);
$stmt->execute();
$order_id = $stmt->insert_id;

// 5. Insert each line item
$sql_item   = "SELECT id FROM items WHERE name = ?";
$sql_insert = "INSERT INTO order_items (order_id, item_id, quantity, total_price)
               VALUES (?, ?, ?, ?)";
foreach ($ordered_items as $ci) {
    $stmt_item = $conn->prepare($sql_item);
    $stmt_item->bind_param("s", $ci['name']);
    $stmt_item->execute();
    $res = $stmt_item->get_result()->fetch_assoc();
    if ($res) {
        $item_id    = $res['id'];
        $line_total = $ci['price'] * $ci['quantity'];
        $stmt_ins   = $conn->prepare($sql_insert);
        $stmt_ins->bind_param("iiid",
            $order_id,
            $item_id,
            $ci['quantity'],
            $line_total
        );
        $stmt_ins->execute();
    }
}

// 6. Clear the cart
unset($_SESSION['cart']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Confirmation | ShinHot Pot</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">
  <style>
    :root {
  --accent-color: #FFD54F;
  --bg-color:     #0d1c2e;
  --text-color:   #FFFFFF;
}
body {
  margin: 0;
  background: var(--bg-color);
  color: var(--text-color);
  font-family: 'Segoe UI', sans-serif;
}
.success-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 1rem;
}
.card-success {
  background: var(--bg-color);
  border: 2px solid var(--accent-color);
  border-radius: 1rem;
  box-shadow: 0 8px 20px rgba(0,0,0,0.5);
  max-width: 600px;
  width: 100%;
}
.card-success .card-body {
  text-align: center;
  padding: 2rem;
  color: var(--text-color);
  text-shadow: 0 1px 2px rgba(0,0,0,0.5);
}
.card-success h2 {
  margin-bottom: .5rem;
  font-size: 2rem;
  font-weight: 600;
}
.card-success p {
  margin-bottom: 1.5rem;
  font-size: 1.1rem;
}
.btn-home {
  display: inline-block;
  padding: .75rem 1.5rem;
  border: 2px solid var(--accent-color);
  border-radius: .5rem;
  background: transparent;
  color: var(--text-color);
  text-decoration: none;
  transition: background .2s, color .2s;
}
.btn-home:hover {
  background: var(--accent-color);
  color: var(--bg-color);
}

  </style>
</head>
<body>
  <div class="success-container">
    <div class="card card-success">
      <div class="card-body">
        <h2>Thank you, <?= htmlspecialchars($customer_name) ?>!</h2>
        <p>Your order <strong>#<?= $order_id ?></strong> has been received.</p>
        <a href="menu.php" class="btn-home">Back to Menu</a>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
  </script>
</body>
</html>

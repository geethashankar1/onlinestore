<?php
// admin/view_orders.php
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    $_SESSION['admin_redirect_message'] = "You are not authorized to access this page.";
    header("Location: ../login.php");
    exit;
}

$message = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id_update = intval($_POST['order_id']);
    $new_status = $conn->real_escape_string($_POST['status']);

    $stmt_update = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    if ($stmt_update) {
        $stmt_update->bind_param("si", $new_status, $order_id_update);
        if ($stmt_update->execute()) {
            $message = "<div class='alert alert-success'>Order status updated successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to update order status: " . $stmt_update->error . "</div>";
        }
        $stmt_update->close();
    } else {
        $message = "<div class='alert alert-danger'>Database error (prepare update): " . $conn->error . "</div>";
    }
}


// Fetch orders with user details
$orders_sql = "
    SELECT o.id, o.total_amount, o.created_at, o.status, o.shipping_address, u.username AS customer_name, u.email AS customer_email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
";
$orders_result = $conn->query($orders_sql);

// Possible statuses for dropdown
$possible_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
$page_title = "View Orders";
$nav_mode = 'admin';
include '../header.php';
?>
  <section class="page">
    <div class="container">
      <div class="page-head"><div class="eyebrow">Operations</div><h2 class="mb-0">View Orders</h2></div>
      <?php echo $message; ?>

      <div class="table-shell">
        <table class="table theme-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Customer</th>
              <th>Total</th>
              <th>Date</th>
              <th>Status</th>
              <th>Shipping</th>
              <th>Update</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($orders_result && $orders_result->num_rows > 0) {
                while ($order = $orders_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>#" . $order['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($order['customer_name']) . "<br><small style='color:var(--muted);'>" . htmlspecialchars($order['customer_email']) . "</small></td>";
                    echo "<td>$" . number_format($order['total_amount'], 2) . "</td>";
                    echo "<td>" . date("M d, Y H:i", strtotime($order['created_at'])) . "</td>";
                    echo "<td><span class='status-badge'>" . htmlspecialchars($order['status']) . "</span></td>";
                    echo "<td style='max-width:220px;font-size:.88rem;color:#4A453C;'>" . nl2br(htmlspecialchars($order['shipping_address'])) . "</td>";
                    echo "<td>
                            <form method='POST' action='view_orders.php' class='d-flex gap-2'>
                                <input type='hidden' name='order_id' value='" . $order['id'] . "'>
                                <select name='status' class='form-select form-select-sm' style='width:auto;'>";
                            foreach ($possible_statuses as $status_option) {
                                echo "<option value='" . htmlspecialchars($status_option) . "'" . ($order['status'] == $status_option ? " selected" : "") . ">" . htmlspecialchars($status_option) . "</option>";
                            }
                    echo       "</select>
                                <button type='submit' name='update_status' class='btn btn-sm'>Update</button>
                            </form>
                          </td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7' class='text-center' style='color:var(--muted);'>No orders found.</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
<?php include '../footer.php'; $conn->close(); ?>

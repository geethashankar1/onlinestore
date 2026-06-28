<?php
// login.php
require_once 'config/db.php';
$message = '';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Redirect if already logged in
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email_or_username = $conn->real_escape_string(trim($_POST['email_or_username']));
    $password = $_POST['password'];

    if (empty($email_or_username) || empty($password)) {
        $message = "<div class='alert alert-danger'>Email/Username and Password are required.</div>";
    } else {
        $stmt = $conn->prepare("SELECT id, username, email, password FROM users WHERE email = ? OR username = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $email_or_username, $email_or_username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Password is correct, set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    // Example: Check if admin (you might have a 'role' column in users table)
                    // For simplicity, let's assume user with ID 1 is admin, or specific username
                    if ($user['username'] === 'admin' || $user['id'] === 1) { // Adjust admin check as needed
                        $_SESSION['is_admin'] = true;
                    }

                    header("Location: index.php"); // Redirect to home page or dashboard
                    exit;
                } else {
                    $message = "<div class='alert alert-danger'>Invalid email/username or password.</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>Invalid email/username or password.</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='alert alert-danger'>Database error: " . $conn->error . "</div>";
        }
    }
}
$page_title = "Login";
include 'header.php';
?>
  <section class="page">
    <div class="container">
      <div class="surface form-shell reveal d2">
        <div class="text-center mb-4">
          <div class="eyebrow">Welcome back</div>
          <h2 class="mt-2 mb-0">Login</h2>
        </div>
        <?php echo $message; ?>
        <form action="login.php" method="post">
          <div class="mb-3">
            <label for="email_or_username" class="form-label">Email or Username</label>
            <input type="text" id="email_or_username" name="email_or_username" class="form-control" required>
          </div>
          <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn w-100">Login</button>
        </form>
        <p class="text-center mt-4 mb-0" style="color:var(--muted);">Don’t have an account? <a href="register.php">Register here</a>.</p>
      </div>
    </div>
  </section>
<?php include 'footer.php'; $conn->close(); ?>

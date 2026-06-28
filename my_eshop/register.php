<?php
// register.php
require_once 'config/db.php';

$message      = '';
$field_errors = [];
$old          = [];

function password_rules(string $pw): array {
    $fails = [];
    if (strlen($pw) < 8)                    $fails[] = 'length';
    if (!preg_match('/[A-Z]/', $pw))        $fails[] = 'upper';
    if (!preg_match('/[a-z]/', $pw))        $fails[] = 'lower';
    if (!preg_match('/[0-9]/', $pw))        $fails[] = 'number';
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) $fails[] = 'special';
    return $fails;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']          ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    $old = ['username' => $username, 'email' => $email];

    if ($username === '') $field_errors['username'] = 'Username is required.';

    if ($email === '')
        $field_errors['email'] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $field_errors['email'] = 'Please enter a valid email address.';

    $pw_fails = password_rules($password);
    if ($password === '') {
        $field_errors['password'] = ['Password is required.'];
    } elseif (!empty($pw_fails)) {
        $field_errors['password'] = $pw_fails; // list of failed rule keys
    }

    if ($password !== $confirm)
        $field_errors['confirm'] = 'Passwords do not match.';

    if (empty($field_errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $message = "<div class='alert alert-danger'>Username or email already taken.</div>";
        } else {
            $hash  = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt2->bind_param('sss', $username, $email, $hash);
            if ($stmt2->execute()) {
                $message = "<div class='alert alert-success'>Registration successful! You can now <a href='login.php'>login</a>.</div>";
                $old = []; // clear fields on success
            } else {
                $message = "<div class='alert alert-danger'>Registration failed. Please try again.</div>";
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}

// Encode failed rules for JS to pre-highlight after a server-side rejection
$failed_rules_json = isset($field_errors['password']) && is_array($field_errors['password'])
    ? json_encode($field_errors['password'])
    : '[]';

$page_title = "Register";
include 'header.php';
?>
<style>
/* password requirements list */
.pw-rules{list-style:none;padding:0;margin:.5rem 0 0;display:flex;flex-direction:column;gap:.25rem;}
.pw-rules li{font-size:.82rem;display:flex;align-items:center;gap:.45rem;color:var(--muted);transition:color .2s;}
.pw-rules li::before{content:"○";font-size:.7rem;flex-shrink:0;}
.pw-rules li.pw-ok{color:#2C6B34;}
.pw-rules li.pw-ok::before{content:"✓";}
.pw-rules li.pw-fail{color:var(--danger);}
.pw-rules li.pw-fail::before{content:"✕";}
/* field-level error text */
.field-error{font-size:.82rem;color:var(--danger);margin-top:.3rem;}
/* invalid input border */
.input-invalid{border-color:var(--danger)!important;}
.input-invalid:focus{box-shadow:0 0 0 .15rem rgba(155,59,54,.18)!important;}
</style>

  <section class="page">
    <div class="container">
      <div class="surface form-shell reveal d2">
        <div class="text-center mb-4">
          <div class="eyebrow">Join us</div>
          <h2 class="mt-2 mb-0">Create an Account</h2>
        </div>

        <?php echo $message; ?>

        <form action="register.php" method="post" id="registerForm" novalidate>

          <!-- Username -->
          <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" id="username" name="username"
                   class="form-control <?php echo isset($field_errors['username']) ? 'input-invalid' : ''; ?>"
                   value="<?php echo htmlspecialchars($old['username'] ?? ''); ?>" required>
            <?php if (isset($field_errors['username'])): ?>
              <div class="field-error"><?php echo htmlspecialchars($field_errors['username']); ?></div>
            <?php endif; ?>
          </div>

          <!-- Email -->
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email"
                   class="form-control <?php echo isset($field_errors['email']) ? 'input-invalid' : ''; ?>"
                   value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" required>
            <?php if (isset($field_errors['email'])): ?>
              <div class="field-error"><?php echo htmlspecialchars($field_errors['email']); ?></div>
            <?php endif; ?>
          </div>

          <!-- Password -->
          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password"
                   class="form-control <?php echo isset($field_errors['password']) ? 'input-invalid' : ''; ?>"
                   autocomplete="new-password" required>

            <!-- Live requirements checklist -->
            <ul class="pw-rules" id="pwRules">
              <li data-rule="length">At least 8 characters</li>
              <li data-rule="upper">At least one uppercase letter (A–Z)</li>
              <li data-rule="lower">At least one lowercase letter (a–z)</li>
              <li data-rule="number">At least one number (0–9)</li>
              <li data-rule="special">At least one special character (!&nbsp;@&nbsp;#&nbsp;$&nbsp;%&nbsp;…)</li>
            </ul>
          </div>

          <!-- Confirm password -->
          <div class="mb-4">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   class="form-control <?php echo isset($field_errors['confirm']) ? 'input-invalid' : ''; ?>"
                   autocomplete="new-password" required>
            <?php if (isset($field_errors['confirm'])): ?>
              <div class="field-error" id="confirmError"><?php echo htmlspecialchars($field_errors['confirm']); ?></div>
            <?php else: ?>
              <div class="field-error" id="confirmError" style="display:none;"></div>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn w-100">Register</button>
        </form>

        <p class="text-center mt-4 mb-0" style="color:var(--muted);">
          Already have an account? <a href="login.php">Login here</a>.
        </p>
      </div>
    </div>
  </section>

<script>
(function () {
  const pwInput      = document.getElementById('password');
  const cfInput      = document.getElementById('confirm_password');
  const confirmError = document.getElementById('confirmError');
  const rules = {
    length:  v => v.length >= 8,
    upper:   v => /[A-Z]/.test(v),
    lower:   v => /[a-z]/.test(v),
    number:  v => /[0-9]/.test(v),
    special: v => /[^A-Za-z0-9]/.test(v),
  };

  // Pre-mark failed rules from server-side validation
  const serverFails = <?php echo $failed_rules_json; ?>;
  if (serverFails.length > 0) {
    document.querySelectorAll('#pwRules li').forEach(li => {
      if (serverFails.includes(li.dataset.rule)) li.classList.add('pw-fail');
    });
  }

  function updateRules() {
    const v = pwInput.value;
    const allPass = Object.keys(rules).every(key => {
      const ok = rules[key](v);
      const li = document.querySelector('#pwRules li[data-rule="' + key + '"]');
      li.classList.toggle('pw-ok',   ok);
      li.classList.toggle('pw-fail', v.length > 0 && !ok);
      return ok;
    });
    pwInput.classList.toggle('input-invalid', v.length > 0 && !allPass);
  }

  function updateConfirm() {
    const mismatch = cfInput.value.length > 0 && cfInput.value !== pwInput.value;
    cfInput.classList.toggle('input-invalid', mismatch);
    confirmError.textContent = mismatch ? 'Passwords do not match.' : '';
    confirmError.style.display = mismatch ? '' : 'none';
  }

  pwInput.addEventListener('input', () => { updateRules(); updateConfirm(); });
  cfInput.addEventListener('input', updateConfirm);

  // Client-side guard before submit
  document.getElementById('registerForm').addEventListener('submit', function (e) {
    const v = pwInput.value;
    const allPass = Object.keys(rules).every(key => rules[key](v));
    if (!allPass) {
      e.preventDefault();
      updateRules();
      pwInput.focus();
    }
    if (cfInput.value !== v) {
      e.preventDefault();
      updateConfirm();
    }
  });
})();
</script>

<?php include 'footer.php'; $conn->close(); ?>

<?php
/*
Flexible PHP File-based Website (No Database)
Structure (place in public webroot):
- index.php          (router/front controller)
- header.php         (shared header)
- footer.php         (shared footer)
- functions.php      (helper functions)
- admin.php          (simple page editor, protected by password stored in config)
- styles.css         (responsive styles)
- content/           (directory that contains page files: home.html, about.html, etc.)
- uploads/           (optional directory for uploaded files)
- README.txt         (instructions)

Notes:
- This system uses flat files in the content/ directory for pages — no database required.
- To protect admin.php, set a password in functions.php (or better: use server-level protection).
- Make sure content/ and uploads/ are writable by the webserver if you want editing/uploading.
*/

// ===================== file: functions.php =====================
?>
<?php
// functions.php
function get_content_path($slug) {
    $safe = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    $file = __DIR__ . '/content/' . ($safe ?: 'home') . '.html';
    return $file;
}

function load_page($slug) {
    $file = get_content_path($slug);
    if (is_readable($file)) {
        return file_get_contents($file);
    }
    return '<h2>Page not found</h2><p>The requested page does not exist.</p>';
}

function save_page($slug, $html) {
    $file = get_content_path($slug);
    // Basic sanitation: do not allow PHP code in content
    $html = str_replace('<?', '&lt;?', $html);
    return file_put_contents($file, $html) !== false;
}

// Basic admin password (change this!)
function admin_password() {
    // CHANGE this to a strong password before using in production
    return 'ChangeMe123!';
}

function is_admin_logged_in() {
    session_start();
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function admin_login($password) {
    session_start();
    if ($password === admin_password()) {
        $_SESSION['is_admin'] = true;
        return true;
    }
    return false;
}

function admin_logout() {
    session_start();
    unset($_SESSION['is_admin']);
}

?>



// ===================== file: header.php =====================
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(\$title ?? 'My Site'); ?></title>
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
<header class="site-header">
  <div class="container">
    <a class="brand" href="/">My Flexible Site</a>
    <nav class="nav">
      <a href="/?p=home">Home</a>
      <a href="/?p=about">About</a>
      <a href="/?p=contact">Contact</a>
      <?php if (is_admin_logged_in()): ?>
        <a href="/admin.php">Admin</a>
        <a href="/?logout=1">Logout</a>
      <?php else: ?>
        <a href="/admin.php">Login</a>
      <?php endif; ?>
    </nav>
    <button class="hamburger" aria-label="Toggle menu">☰</button>
  </div>
</header>
<main class="container main">

<?php
// ===================== file: footer.php =====================
?>
</main>
<footer class="site-footer">
  <div class="container">
    <p>© <?php echo date('Y'); ?> My Flexible Site — Built with PHP (no DB)</p>
  </div>
</footer>
<script>
// Simple hamburger for mobile
document.addEventListener('DOMContentLoaded', function(){
  var btn = document.querySelector('.hamburger');
  var nav = document.querySelector('.nav');
  btn && btn.addEventListener('click', function(){
    nav.classList.toggle('open');
  });
});
</script>
</body>
</html>



// ===================== file: index.php =====================
?>
<?php
require __DIR__ . '/functions.php';
// simple logout handler
if (isset($_GET['logout'])) {
    admin_logout();
    header('Location: /');
    exit;
}

\$page = isset($_GET['p']) ? $_GET['p'] : 'home';
\$content = load_page(\$page);
\$title = ucfirst(\$page);
include __DIR__ . '/header.php';

// render content
echo \$content;

include __DIR__ . '/footer.php';
?>



// ===================== file: admin.php =====================
?>
<?php
require __DIR__ . '/functions.php';
// plain and simple admin editor
session_start();

// login attempt
if (isset($_POST['password']) && !is_admin_logged_in()) {
    if (admin_login(
        isset($_POST['password']) ? $_POST['password'] : ''
    )) {
        header('Location: /admin.php'); exit;
    } else {
        \$error = 'Invalid password';
    }
}

// protected actions
if (!is_admin_logged_in()) {
    // show login form
    ?>
    <!doctype html>
    <html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login</title>
    <link rel="stylesheet" href="/styles.css"></head><body>
    <div class="container admin">
      <h2>Admin Login</h2>
      <?php if (isset(\$error)) echo '<p class="error">'.htmlspecialchars(\$error).'</p>'; ?>
      <form method="post">
        <label>Password<br><input type="password" name="password" required></label><br>
        <button>Login</button>
      </form>
      <p><a href="/">Back to site</a></p>
    </div>
    </body></html>
    <?php
    exit;
}

// logged in: editor UI
\$slug = isset($_GET['p']) ? $_GET['p'] : 'home';
if (isset(\$_POST['save'])) {
    \$saveSlug = isset(\$_POST['slug']) ? preg_replace('/[^a-z0-9\-]/','',strtolower(\$_POST['slug'])) : 'home';
    \$html = isset(\$_POST['content']) ? \$_POST['content'] : '';
    if (save_page(\$saveSlug, \$html)) {
        \$message = 'Saved successfully.';
    } else {
        \$message = 'Failed to save. Check permissions.';
    }
}

\$existing = '';
\$file = get_content_path(\$slug);
if (is_readable(\$file)) \$existing = file_get_contents(\$file);
?>
<!doctype html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Editor</title>
  <link rel="stylesheet" href="/styles.css">
</head>
<body>
<div class="container admin">
  <h2>Admin Page Editor</h2>
  <?php if (isset(\$message)) echo '<p class="message">'.htmlspecialchars(\$message).'</p>'; ?>
  <form method="post">
    <label>Page slug (e.g. home, about, contact)<br>
      <input name="slug" value="<?php echo htmlspecialchars(\$slug); ?>" required>
    </label>
    <label>Content (HTML allowed, PHP is stripped)<br>
      <textarea name="content" rows="16"><?php echo htmlspecialchars(\$existing); ?></textarea>
    </label>
    <button name="save" value="1">Save Page</button>
  </form>
  <p><a href="/">View site</a> | <a href="/?logout=1">Logout</a></p>
</div>
</body>
</html>



// ===================== file: styles.css =====================
?>
/* Basic responsive styles. Put this in styles.css in the site root. */
*{box-sizing:border-box}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial;line-height:1.5;margin:0;color:#111}
.container{max-width:1000px;margin:0 auto;padding:1rem}
.site-header{background:#f8f9fa;border-bottom:1px solid #e6e6e6}
.site-header .container{display:flex;align-items:center;gap:1rem}
.brand{font-weight:700;text-decoration:none;color:inherit}
.nav{margin-left:auto;display:flex;gap:1rem}
.nav a{text-decoration:none;padding:.5rem}
.hamburger{display:none;background:none;border:0;font-size:1.4rem}
.main{padding:1.5rem 0}
.site-footer{background:#fafafa;border-top:1px solid #eee;padding:1rem 0;text-align:center}

/* Admin and utility */
.admin{padding:2rem}
.admin input, .admin textarea{width:100%;padding:.5rem;margin:.5rem 0}
.error{color:#a00}
.message{color:#080}

/* Mobile responsiveness */
@media (max-width:700px){
  .nav{position:absolute;right:0;top:60px;background:#fff;flex-direction:column;display:none;padding:1rem;border:1px solid #eee}
  .nav.open{display:flex}
  .hamburger{display:block;margin-left:auto}
}



// ===================== file: content/home.html =====================
?>
<h1>Welcome</h1>
<p>This is the home page. Edit this content from <a href="/admin.php?p=home">Admin → home</a>.</p>


// ===================== file: content/about.html =====================
?>
<h1>About</h1>
<p>Write something about your site here.</p>


// ===================== file: content/contact.html =====================
?>
<h1>Contact</h1>
<p>You can add contact details or a contact form here. For a simple form that emails you, use a server mailer script or integrate with an external service.</p>


// ===================== file: README.txt =====================
?>
1) Upload all files to your PHP webserver (shared hosting, XAMPP, etc.).
2) Create folders: content/ and uploads/ and make them writable (chmod 755 or 775) if you want to allow editing from the admin UI.
3) Edit functions.php and change admin_password() to a secure password.
4) Visit / to view the site. Visit /admin.php to login and edit pages.
5) This system intentionally does not allow raw PHP in content to reduce risks. Use HTML in content files.

Security notes:
- For production, prefer server-level protection for the admin area (HTTP auth) and use HTTPS.
- Consider moving admin credentials out of source code; this example keeps it simple for learning.

Customization tips:
- Replace header/footer with your own design.
- Add templates or micro-templates for repeated patterns.
- Implement Markdown parsing (e.g., Parsedown) to author content in markdown instead of raw HTML.


/* End of package */

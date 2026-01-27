<?php
session_start();
include 'db.php';
if(!isset($_SESSION['user_id'])){
    header("Location: login.php"); // ma3netha mazal marahouch logged in
}else{
    if($_SESSION['user_role'] == "instructor" || $_SESSION['user_role'] == "admin"){

    }
    else{
        header("Location: dashboard.php"); // marahouch instructor, cant create course
    }
}

$errors = [];
$success = '';

// levels allowed
$levels = ['beginner','intermediate','advanced'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '0');
    $level = trim($_POST['level'] ?? 'beginner');

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($description === '') {
        $errors[] = 'Description is required.';
    }
    if (!is_numeric($price) || $price < 0) {
        $errors[] = 'Price must be a positive number.';
    }
    if (!in_array($level, $levels, true)) { // insure the variable is one of the allowed levels
        $level = 'beginner';
    }

    // handle image upload (optional)
    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed.';
        } else {
            $allowed = ['image/jpeg','image/png','image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed, true)) {
                $errors[] = 'Only JPG, PNG or WebP images are allowed.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Image must be smaller than 2MB.';
            } else {
                $ext = '';
                if ($mime === 'image/jpeg') $ext = '.jpg';
                if ($mime === 'image/png') $ext = '.png';
                if ($mime === 'image/webp') $ext = '.webp';
                $dir = dirname(__DIR__) . '/images/courses';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = time() . '_' . bin2hex(random_bytes(6)) . $ext;
                $dest = $dir . '/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $errors[] = 'Failed to move uploaded image.';
                } else {
                    $image_path = 'images/courses/' . $filename;
                }
            }
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO courses (title, description, price, level, created_by, created_at, image) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $user_id = (int)$_SESSION['user_id'];
        $stmt->bind_param('ssdiss', $title, $description, $price, $level, $user_id, $image_path);
        if ($stmt->execute()) {
            $success = 'Course created successfully.';
        } else {
            $errors[] = 'Database error: ' . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../styles/auth.css">
  <link rel="stylesheet" href="../styles/create_course.css">
  <title>Create Course</title>
</head>
<body>
  <a href="hpage.php" class="back-btn">← Back to homepage</a>

  <div class="auth-wrapper">
    <div class="auth-box">
      <h2>Create Course</h2>

      <?php if ($success): ?>
        <div class="message success"><?php echo $success; ?></div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="message error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
      <?php endif; ?>

      <form action="create_course.php" method="POST" enctype="multipart/form-data">
        <label>Title</label>
        <input type="text" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">

        <label>Description</label>
        <textarea name="description" rows="6" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

        <div class="form-row">
          <div>
            <label>Price (USD)</label>
            <input type="number" step="0.01" name="price" min="0" required value="<?php echo htmlspecialchars($_POST['price'] ?? '0'); ?>">
          </div>
          <div>
            <label>Level</label>
            <select name="level">
              <?php foreach ($levels as $lv): ?>
                <option value="<?php echo $lv; ?>" <?php echo (($_POST['level'] ?? '') === $lv) ? 'selected' : ''; ?>><?php echo ucfirst($lv); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <label>Image (optional, JPG/PNG/WebP ≤ 2MB)</label>
        <input type="file" name="image" accept="image/*">

        <button class="btn primary" type="submit">Create Course</button>
      </form>
    </div>
  </div>
</body>
</html>

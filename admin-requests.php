<?php
require_once __DIR__ . '/config.php';

session_start();

$dataDir = __DIR__ . '/data';
$requestsFile = $dataDir . '/approval-requests.json';
$postsFile = $dataDir . '/posts.json';
$message = '';
$isAuthenticated = !empty($_SESSION['brightpath_admin_authenticated']);

if (!is_dir($dataDir)) {
  mkdir($dataDir, 0775, true);
}
if (!file_exists($requestsFile)) {
  file_put_contents($requestsFile, '[]');
}
if (!file_exists($postsFile)) {
  file_put_contents($postsFile, '[]');
}

function read_json_file($file, $fallback) {
  return json_decode(file_get_contents($file), true) ?: $fallback;
}

function save_json_file($file, $data) {
  file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function slugify_title($title) {
  $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
  return $slug ?: 'post';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = $_POST['passcode'] ?? '';
  $requestId = $_POST['request_id'] ?? '';
  $action = $_POST['action'] ?? '';
  $hasAccess = brightpath_passcode_is_valid($code);

  if (!$hasAccess) {
    $message = 'Wrong passcode. Request was not changed.';
  } elseif (!$action) {
    $_SESSION['brightpath_admin_authenticated'] = true;
    $isAuthenticated = true;
    $message = 'Approval requests unlocked.';
  } else {
    $_SESSION['brightpath_admin_authenticated'] = true;
    $isAuthenticated = true;
    $requests = read_json_file($requestsFile, []);
    $target = null;
    $remaining = [];

    foreach ($requests as $request) {
      if (($request['id'] ?? '') === $requestId) {
        $target = $request;
      } else {
        $remaining[] = $request;
      }
    }

    if (!$target) {
      $message = 'Request not found.';
    } elseif ($action === 'approve') {
      $posts = read_json_file($postsFile, []);
      $body = trim($target['body'] ?? '');
      $wordCount = str_word_count(strip_tags($body));
      $post = [
        'id' => slugify_title($target['title'] ?? 'post') . '-' . time(),
        'title' => $target['title'] ?? 'Untitled post',
        'category' => $target['category'] ?? 'writing',
        'categoryLabel' => $target['categoryLabel'] ?? 'Writing',
        'date' => date('F j, Y'),
        'readTime' => max(1, ceil($wordCount / 180)) . ' min read',
        'excerpt' => $target['excerpt'] ?? '',
        'image' => !empty($target['image']) ? $target['image'] : 'images/post-2.svg',
        'imageAlt' => ($target['title'] ?? 'Blog') . ' blog photo',
        'content' => [
          [
            'heading' => 'Article',
            'body' => $body
          ]
        ]
      ];

      array_unshift($posts, $post);
      save_json_file($postsFile, $posts);
      save_json_file($requestsFile, $remaining);
      $message = 'Request approved and published.';
    } elseif ($action === 'reject') {
      save_json_file($requestsFile, $remaining);
      $message = 'Request rejected and removed.';
    }
  }
}

$requests = $isAuthenticated ? read_json_file($requestsFile, []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Approval Requests | BrightPath Blog</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="index.html">BrightPath</a>
      <nav>
        <a href="index.html">Home</a>
        <a href="blog.html">Blog</a>
        <a href="publish.html">Publish</a>
        <a class="active" href="admin-requests.php">Requests</a>
      </nav>
    </div>
  </header>

  <main class="container page-content">
    <section class="page-hero">
      <p class="eyebrow">Admin</p>
      <h1>Review blog approval requests.</h1>
      <p>Enter your owner passcode, then approve or reject pending guest submissions.</p>
    </section>

    <?php if ($message): ?>
      <section class="content-block"><p><?php echo htmlspecialchars($message); ?></p></section>
    <?php endif; ?>

    <?php if (!$isAuthenticated): ?>
      <section class="content-block">
        <form method="post" class="request-actions">
          <label>
            Owner passcode
            <input type="password" name="passcode" placeholder="Enter passcode" required />
          </label>
          <button class="btn" type="submit">Unlock requests</button>
        </form>
      </section>
    <?php else: ?>
    <section class="content-block manage-list">
      <?php if (!$requests): ?>
        <p class="empty-state">No pending requests.</p>
      <?php endif; ?>

      <?php foreach ($requests as $request): ?>
        <article class="request-card">
          <form method="post" class="request-actions">
            <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id'] ?? ''); ?>" />
            <label>
              Owner passcode
              <input type="password" name="passcode" placeholder="Enter passcode" required />
            </label>
            <button class="btn" type="submit" name="action" value="approve">Approve</button>
            <button class="btn btn-danger" type="submit" name="action" value="reject">Reject</button>
          </form>
          <div class="request-content">
            <p class="section-label"><?php echo htmlspecialchars($request['categoryLabel'] ?? 'Blog'); ?></p>
            <h2><?php echo htmlspecialchars($request['title'] ?? 'Untitled request'); ?></h2>
            <p class="meta"><?php echo htmlspecialchars($request['submittedAt'] ?? ''); ?></p>
            <p><strong>Preview:</strong> <?php echo htmlspecialchars($request['excerpt'] ?? ''); ?></p>
            <p><strong>Photo:</strong> <?php echo htmlspecialchars($request['imageName'] ?? 'No photo selected'); ?></p>
            <?php if (!empty($request['image'])): ?>
              <img class="request-preview-image" src="<?php echo htmlspecialchars($request['image']); ?>" alt="Request preview image" />
            <?php endif; ?>
            <pre class="request-body"><?php echo htmlspecialchars($request['body'] ?? ''); ?></pre>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
  </main>
</body>
</html>



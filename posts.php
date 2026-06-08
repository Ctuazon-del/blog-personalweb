<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config.php';

$dataDir = __DIR__ . '/../data';
$postsFile = $dataDir . '/posts.json';

if (!is_dir($dataDir)) {
  mkdir($dataDir, 0775, true);
}

if (!file_exists($postsFile)) {
  file_put_contents($postsFile, '[]');
}

function read_posts($file) {
  return json_decode(file_get_contents($file), true) ?: [];
}

function submitted_passcode($data = []) {
  return $_SERVER['HTTP_X_OWNER_PASSCODE'] ?? ($data['passcode'] ?? ($_POST['passcode'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  echo json_encode(read_posts($postsFile));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!brightpath_passcode_is_valid(submitted_passcode($data ?: []))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid owner passcode']);
    exit;
  }

  if (!$data || empty($data['id']) || empty($data['title']) || empty($data['excerpt'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing required post fields']);
    exit;
  }

  $posts = read_posts($postsFile);
  $posts = array_values(array_filter($posts, fn($post) => ($post['id'] ?? '') !== $data['id']));
  array_unshift($posts, $data);
  file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT));
  echo json_encode(['ok' => true]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
  if (!brightpath_passcode_is_valid(submitted_passcode())) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid owner passcode']);
    exit;
  }

  $id = $_GET['id'] ?? '';
  $posts = read_posts($postsFile);
  $posts = array_values(array_filter($posts, fn($post) => ($post['id'] ?? '') !== $id));
  file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT));
  echo json_encode(['ok' => true]);
  exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'message' => 'Method not allowed']);


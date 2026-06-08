<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$dataDir = __DIR__ . '/../data';
$commentsFile = $dataDir . '/comments.json';

if (!is_dir($dataDir)) {
  mkdir($dataDir, 0775, true);
}

if (!file_exists($commentsFile)) {
  file_put_contents($commentsFile, '{}');
}

function read_comments($file) {
  return json_decode(file_get_contents($file), true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $postId = $_GET['postId'] ?? '';
  $comments = read_comments($commentsFile);
  echo json_encode($comments[$postId] ?? []);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!$data || empty($data['postId']) || empty($data['name']) || empty($data['message'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing required comment fields']);
    exit;
  }

  $comments = read_comments($commentsFile);
  $postId = $data['postId'];
  if (!isset($comments[$postId])) {
    $comments[$postId] = [];
  }

  $comments[$postId][] = [
    'name' => trim($data['name']),
    'message' => trim($data['message']),
    'date' => date('c')
  ];

  file_put_contents($commentsFile, json_encode($comments, JSON_PRETTY_PRINT));
  echo json_encode(['ok' => true]);
  exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'message' => 'Method not allowed']);


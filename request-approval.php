<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
  exit;
}

function field_value($name, $fallback = '') {
  global $data;
  if (isset($_POST[$name])) {
    return trim($_POST[$name]);
  }
  return trim($data[$name] ?? $fallback);
}

function save_uploaded_image() {
  if (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return ['name' => 'No photo selected', 'path' => ''];
  }

  if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    return ['name' => $_FILES['photo']['name'] ?? 'Upload failed', 'path' => ''];
  }

  $allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
  ];
  $mimeType = mime_content_type($_FILES['photo']['tmp_name']);
  if (!isset($allowedTypes[$mimeType])) {
    return ['name' => $_FILES['photo']['name'] ?? 'Unsupported image', 'path' => ''];
  }

  $uploadDir = __DIR__ . '/../uploads';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
  }

  $originalName = basename($_FILES['photo']['name'] ?? 'blog-photo');
  $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $originalName);
  $extension = $allowedTypes[$mimeType];
  $fileName = uniqid('blog_', true) . '-' . pathinfo($safeName, PATHINFO_FILENAME) . '.' . $extension;
  $targetPath = $uploadDir . '/' . $fileName;

  if (!move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
    return ['name' => $originalName, 'path' => ''];
  }

  return ['name' => $originalName, 'path' => 'uploads/' . $fileName];
}

$data = [];
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
  $rawInput = file_get_contents('php://input');
  $data = json_decode($rawInput, true) ?: [];
}

$title = field_value('title');
$categoryLabel = field_value('categoryLabel');
$excerpt = field_value('excerpt');
$body = field_value('body');

if (!$title || !$categoryLabel || !$excerpt || !$body) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Missing required post fields']);
  exit;
}

$uploadedImage = save_uploaded_image();
$imageName = field_value('imageName', $uploadedImage['name']);
$imagePath = $uploadedImage['path'] ?: field_value('image');

$request = [
  'id' => uniqid('request_', true),
  'status' => 'pending',
  'submittedAt' => date('c'),
  'title' => $title,
  'category' => field_value('category'),
  'categoryLabel' => $categoryLabel,
  'excerpt' => $excerpt,
  'body' => $body,
  'imageName' => $imageName ?: $uploadedImage['name'],
  'image' => $imagePath
];

$dataDir = __DIR__ . '/../data';
$requestsFile = $dataDir . '/approval-requests.json';

if (!is_dir($dataDir)) {
  mkdir($dataDir, 0775, true);
}

$requests = [];
if (file_exists($requestsFile)) {
  $requests = json_decode(file_get_contents($requestsFile), true) ?: [];
}

array_unshift($requests, $request);
file_put_contents($requestsFile, json_encode($requests, JSON_PRETTY_PRINT));

$to = 'tuazonc410@gmail.com';
$subject = 'Blog approval request: ' . $request['title'];
$message = "Hello Christian,\n\nA guest requested approval to publish a blog post.\n\n"
  . "Title: {$request['title']}\n"
  . "Category: {$request['categoryLabel']}\n"
  . "Photo: {$request['imageName']}\n\n"
  . "Preview:\n{$request['excerpt']}\n\n"
  . "Full article:\n{$request['body']}\n\n"
  . "Saved request ID: {$request['id']}\n"
  . "Approval page: " . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/admin-requests.php') . "\n";
$headers = "From: BrightPath Blog <no-reply@brightpath.local>\r\n";

// This sends only if your XAMPP/PHP mail settings are configured.
$mailSent = @mail($to, $subject, $message, $headers);

echo json_encode(['ok' => true, 'mailSent' => $mailSent, 'message' => 'Approval request saved']);
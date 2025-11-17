<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filename'], $data['boxes']) || !is_array($data['boxes'])) {
    http_response_code(400);
    echo "Bad Request";
    exit;
}

$imageName = basename($data['filename']);
$labelPath = __DIR__ . "/dataset/labels/train/" . pathinfo($imageName, PATHINFO_FILENAME) . ".txt";

$lines = [];
foreach ($data['boxes'] as $box) {
    if (!isset($box['class'], $box['x'], $box['y'], $box['w'], $box['h'])) continue;
    $lines[] = implode(' ', [
        (int)$box['class'],
        round((float)$box['x'], 6),
        round((float)$box['y'], 6),
        round((float)$box['w'], 6),
        round((float)$box['h'], 6)
    ]);
}

file_put_contents($labelPath, implode("\n", $lines));

echo json_encode(['success' => true]);

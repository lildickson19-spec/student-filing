<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$d = get_doc($id);
if (!$d || !can_access_doc($d)) { http_response_code(404); exit('Not found.'); }
$stored = $d['stored_name']; $name = $d['original_name'];
if (!empty($_GET['v'])) {
    $v = one('SELECT * FROM document_versions WHERE document_id=? AND version=?', [$id, (int)$_GET['v']]);
    if (!$v) { http_response_code(404); exit('Not found.'); }
    $stored = $v['stored_name']; $name = $v['original_name'];
}
$path = UPLOAD_DIR . '/' . basename($stored);
if (!is_file($path)) { http_response_code(404); exit('The file is missing on the server.'); }
$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
$mime = MIME_BY_EXT[$ext] ?? 'application/octet-stream';
$inline = !empty($_GET['inline']) && in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
if (!$inline) audit('download', "#$id $name");
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $name) . '"');
header('Cache-Control: private, no-store');
readfile($path);
exit;

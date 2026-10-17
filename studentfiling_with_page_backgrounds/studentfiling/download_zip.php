<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('student');
$u = me();
if (!class_exists('ZipArchive')) exit('The PHP zip extension is not enabled in XAMPP.');
$where = 'student_id=?'; $p = [$u['id']];
if (!empty($_GET['folder_id'])) { $where .= ' AND folder_id=?'; $p[] = (int)$_GET['folder_id']; }
$docs = all("SELECT id, original_name, stored_name FROM documents WHERE $where", $p);
if (!$docs) { flash('You have no documents to download.', 'err'); redirect('documents.php'); }
$tmp = tempnam(sys_get_temp_dir(), 'zip');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
foreach ($docs as $d) {
    $file = UPLOAD_DIR . '/' . basename($d['stored_name']);
    if (is_file($file)) $zip->addFile($file, $d['id'] . '_' . $d['original_name']);
}
$zip->close();
audit('download_zip', count($docs) . ' documents');
header('Content-Type: application/zip');
header('Content-Length: ' . filesize($tmp));
header('Content-Disposition: attachment; filename="my_records.zip"');
readfile($tmp);
unlink($tmp);
exit;

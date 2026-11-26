<?php
// Sends one exam attachment to the browser.
// The files are in uploads/, which .htaccess blocks from direct access,
// so this page is the only way to open them, and it checks the role first.
// It only reads, so a GET request is fine here.
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/ExamController.php';

// Only Optometrists (and the Admin) may open clinical attachments
require_staff(['Optometrist']);

$controller = new ExamController();
$file = $controller->getAttachmentFile((int) ($_GET['id'] ?? 0));

if (!$file) {
    set_flash('error', 'That file was not found.');
    redirect('views/admin/exams.php');
}

// The real file type, read from the file itself
$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['full_path']);

// A safe name for the download box (letters, numbers, dots, dashes only)
$downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file['file_name']);

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($file['full_path']));
// "inline" opens PDFs and pictures in the browser; staff can still save them
header('Content-Disposition: inline; filename="' . $downloadName . '"');
// Stop the browser guessing a different type (e.g. treating it as a web page)
header('X-Content-Type-Options: nosniff');
// Patient files must not be kept in shared caches
header('Cache-Control: private, no-store');

readfile($file['full_path']);
exit;

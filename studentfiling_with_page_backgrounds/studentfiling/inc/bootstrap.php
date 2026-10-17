<?php
// Shared core: session, database, security helpers, access rules.
require_once __DIR__ . '/../config.php';
date_default_timezone_set(TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
foreach ([UPLOAD_DIR, BACKUP_DIR] as $dir) {
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
}

// ---------- database ----------
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function one(string $sql, array $p = []) { $r = q($sql, $p)->fetch(); return $r ?: null; }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []) { return q($sql, $p)->fetchColumn(); }

// ---------- small helpers ----------
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function u(string $path): string { return BASE_URL . '/' . $path; }
function redirect(string $path): void { header('Location: ' . u($path)); exit; }
function nn($v) { return ($v === null || $v === '') ? null : (int)$v; }
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }
function fmt_size($b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB'; }
function badge(string $status): string { return '<span class="badge st-' . strtolower(str_replace(' ', '-', $status)) . '">' . e($status) . '</span>'; }
function paginate(int $total, int $per = 20): array {
    $pages = max(1, (int)ceil($total / $per));
    $page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    return [$page, $pages, ($page - 1) * $per, $per];
}
function pager(int $page, int $pages): string {
    if ($pages < 2) return '';
    $qs = $_GET; $h = '<div class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        $qs['page'] = $i;
        $h .= $i === $page ? '<b>' . $i . '</b>' : '<a href="?' . e(http_build_query($qs)) . '">' . $i . '</a>';
    }
    return $h . '</div>';
}

// <option> lists. The SQL must return columns: id, label
const OPT_CATEGORIES  = 'SELECT id, name AS label FROM categories ORDER BY name';
const OPT_YEARS       = 'SELECT id, label FROM academic_years ORDER BY label DESC';
const OPT_SEMESTERS   = 'SELECT id, label FROM semesters ORDER BY id';
const OPT_DEPARTMENTS = 'SELECT id, name AS label FROM departments ORDER BY name';
const OPT_COURSES     = "SELECT id, CONCAT(code, ' - ', name) AS label FROM courses ORDER BY code";
function opt_list(string $sql, $sel = null, ?string $blank = 'All'): string {
    $h = $blank === null ? '' : '<option value="">' . e($blank) . '</option>';
    foreach (all($sql) as $r) {
        $h .= '<option value="' . $r['id'] . '"' . ((string)$sel === (string)$r['id'] ? ' selected' : '') . '>' . e($r['label']) . '</option>';
    }
    return $h;
}

// ---------- CSRF protection (checked automatically on every POST) ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    exit('Invalid form token. Go back, refresh the page and try again.');
}

// ---------- login state and roles ----------
function me(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $user = one('SELECT * FROM users WHERE id=?', [$_SESSION['uid']]);
        if (!$user || $user['status'] !== 'active') { $user = null; unset($_SESSION['uid']); }
    }
    return $user;
}
function require_login(): void { if (!me()) redirect('login.php'); }
function require_role(string ...$roles): void {
    require_login();
    if (!in_array(me()['role'], $roles, true)) { http_response_code(403); exit('You do not have permission to open this page.'); }
}

// ---------- audit log and notifications ----------
function audit(string $action, string $details = '', ?int $uid = null): void {
    $uid = $uid ?? (me()['id'] ?? null);
    q('INSERT INTO audit_logs(user_id,action,details,ip) VALUES(?,?,?,?)', [$uid, $action, mb_substr($details, 0, 500), $_SERVER['REMOTE_ADDR'] ?? '']);
}
function notify(int $uid, string $msg, string $link = ''): void {
    q('INSERT INTO notifications(user_id,message,link) VALUES(?,?,?)', [$uid, mb_substr($msg, 0, 300), $link]);
}
// tell every lecturer who is assigned to this student / course
function notify_staff_of_doc(array $student, ?int $courseId, int $docId, string $msg): void {
    $rows = all('SELECT lecturer_id AS id FROM lecturer_courses WHERE course_id IN (?,?)
                 UNION SELECT lecturer_id FROM lecturer_students WHERE student_id=?',
                [(int)$student['course_id'], (int)$courseId, (int)$student['id']]);
    foreach ($rows as $r) notify((int)$r['id'], $msg, 'document.php?id=' . $docId);
}
function ann_audience_sql(): string {
    $r = me()['role'];
    return $r === 'admin' ? '1=1' : "(a.audience='all' OR a.audience='" . ($r === 'student' ? 'students' : 'lecturers') . "')";
}

// ---------- who may see which records ----------
// Lecturers see: students in their courses, students assigned to them directly, and documents tagged with their courses.
function lec_student_scope(int $lid, string $a = 's'): string {
    return "($a.course_id IN (SELECT course_id FROM lecturer_courses WHERE lecturer_id=$lid)
             OR $a.id IN (SELECT student_id FROM lecturer_students WHERE lecturer_id=$lid))";
}
function lec_scope(int $lid): string { // expects aliases d (documents) and s (student user)
    return '(' . lec_student_scope($lid, 's') . " OR d.course_id IN (SELECT course_id FROM lecturer_courses WHERE lecturer_id=$lid))";
}
function get_doc(int $id): ?array {
    return one('SELECT d.*, s.full_name AS student_name, s.admission_no, s.course_id AS student_course
                FROM documents d JOIN users s ON s.id=d.student_id WHERE d.id=?', [$id]);
}
function can_access_doc(array $d): bool {
    $user = me();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    if ($user['role'] === 'student') return (int)$d['student_id'] === (int)$user['id'];
    return (bool)val('SELECT 1 FROM documents d JOIN users s ON s.id=d.student_id WHERE d.id=? AND ' . lec_scope((int)$user['id']), [$d['id']]);
}

// ---------- document list filters (shared by documents.php and reports.php) ----------
const DOC_FROM = 'FROM documents d JOIN users s ON s.id=d.student_id JOIN categories c ON c.id=d.category_id
    LEFT JOIN courses co ON co.id=COALESCE(d.course_id, s.course_id)
    LEFT JOIN academic_years ay ON ay.id=d.academic_year_id
    LEFT JOIN semesters se ON se.id=d.semester_id';
function doc_filter_sql(array $g): array {
    $user = me(); $w = ['1=1']; $p = [];
    if ($user['role'] === 'student') { $w[] = 'd.student_id=?'; $p[] = $user['id']; }
    elseif ($user['role'] === 'lecturer') { $w[] = lec_scope((int)$user['id']); }
    $t = trim($g['q'] ?? '');
    if ($t !== '') {
        $w[] = '(d.title LIKE ? OR d.description LIKE ? OR s.full_name LIKE ? OR s.admission_no LIKE ?)';
        $l = '%' . $t . '%'; array_push($p, $l, $l, $l, $l);
    }
    $map = ['category_id' => 'd.category_id', 'status' => 'd.status', 'academic_year_id' => 'd.academic_year_id',
            'semester_id' => 'd.semester_id', 'folder_id' => 'd.folder_id', 'student_id' => 'd.student_id',
            'department_id' => 's.department_id'];
    foreach ($map as $k => $col) {
        if (isset($g[$k]) && $g[$k] !== '') { $w[] = "$col=?"; $p[] = $g[$k]; }
    }
    if (!empty($g['course_id'])) { $w[] = 'COALESCE(d.course_id, s.course_id)=?'; $p[] = $g['course_id']; }
    if (!empty($g['from'])) { $w[] = 'DATE(d.created_at)>=?'; $p[] = $g['from']; }
    if (!empty($g['to']))   { $w[] = 'DATE(d.created_at)<=?'; $p[] = $g['to']; }
    return [implode(' AND ', $w), $p];
}

// ---------- safe file upload ----------
function save_upload(array $f, ?string &$err): ?array {
    $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code !== UPLOAD_ERR_OK) {
        $err = ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) ? 'The file is too large.' : 'Please choose a file to upload.';
        return null;
    }
    if ($f['size'] > MAX_UPLOAD_MB * 1048576) { $err = 'The file is too large (maximum ' . MAX_UPLOAD_MB . ' MB).'; return null; }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!isset(ALLOWED_TYPES[$ext])) { $err = 'Only PDF, DOC, DOCX, JPG, JPEG and PNG files are allowed.'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES[$ext], true)) { $err = 'The file contents do not match its file type.'; return null; }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $stored)) { $err = 'The file could not be saved.'; return null; }
    $orig = substr(preg_replace('/[^\w.\- ]+/u', '_', basename($f['name'])), 0, 200);
    return ['stored' => $stored, 'original' => $orig, 'mime' => MIME_BY_EXT[$ext], 'size' => (int)$f['size']];
}

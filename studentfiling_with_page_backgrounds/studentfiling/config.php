<?php
// ================= EDIT THESE TO MATCH YOUR XAMPP SETUP =================
const DB_HOST  = '127.0.0.1';
const DB_NAME  = 'student_filing';
const DB_USER  = 'root';
const DB_PASS  = '';                 // XAMPP's default root password is empty. Set one and put it here.
const BASE_URL = '/studentfiling';   // the folder name inside htdocs ('' if installed at the web root)
const TIMEZONE = 'Africa/Nairobi';

// ================= SYSTEM RULES =================
const APP_NAME          = 'Student Filing System';
const REQUIRE_APPROVAL  = true;      // new student registrations must be approved by an administrator
const LECTURER_CAN_APPROVE = true;   // false = lecturers may only mark Under Review / Reviewed / Revisions Required
const MAX_UPLOAD_MB     = 10;

// ================= PATHS (outside public access via storage/.htaccess) =================
const STORAGE_DIR = __DIR__ . '/storage';
const UPLOAD_DIR  = STORAGE_DIR . '/uploads';
const BACKUP_DIR  = STORAGE_DIR . '/backups';

const STATUSES = ['Pending', 'Under Review', 'Reviewed', 'Approved', 'Rejected', 'Revisions Required'];

// allowed extension => accepted real file types (checked against the file content)
const ALLOWED_TYPES = [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'doc'  => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
];
const MIME_BY_EXT = [
    'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

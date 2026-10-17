STUDENT FILING AND RECORDS MANAGEMENT SYSTEM
PHP + MySQL + HTML/CSS/JavaScript for XAMPP.  Works offline on a local network.

--------------------------------------------------------------
1. INSTALL (Windows + XAMPP)
--------------------------------------------------------------
 1. Install XAMPP (apachefriends.org). Open the XAMPP Control Panel and Start
    both "Apache" and "MySQL".
 2. Copy the whole "studentfiling" folder into  C:\xampp\htdocs\
 3. Open  http://localhost/phpmyadmin  ->  Import  ->  choose database.sql  ->  Go.
    (This creates the database "student_filing" and its starter data.)
 4. Open config.php in VS Code and check the settings. If you installed the
    project in a different folder name, change BASE_URL. If you set a MySQL
    password, put it in DB_PASS.
 5. Open  http://localhost/studentfiling/setup.php  and create the first
    administrator. Then DELETE setup.php from the folder.
 6. Log in at  http://localhost/studentfiling/
 7. As administrator: create lecturer accounts (Users), add your real
    departments and courses (Manage data), and assign each lecturer their
    courses (Assignments). Students register themselves; you approve them
    under Users.

 Other computers/phones on the same Wi-Fi or LAN open
    http://<this-PC-IP>/studentfiling
 (find the IP with "ipconfig"; allow Apache through Windows Firewall).

 If "Backup" says the zip extension is missing: open php.ini from the XAMPP
 Control Panel (Apache -> Config), remove the ";" before  extension=zip
 and restart Apache.

--------------------------------------------------------------
2. WHAT EACH ROLE CAN DO
--------------------------------------------------------------
 STUDENT   Register / log in (admission number or email) - edit profile and
           password - upload PDF, DOC, DOCX, JPG, JPEG, PNG with title,
           description, category, course, academic year, semester (upload date
           is automatic) - folders - search / filter / preview / download -
           edit details - replace a file (old versions kept) - delete own
           documents (not once Approved) - see status (Pending, Under Review,
           Reviewed, Approved, Rejected, Revisions Required) - read lecturer and
           admin comments and reply - notifications - history of uploads and
           activity - download one file, a folder or everything as a ZIP.
           Students can only ever open their own documents.

 LECTURER  Sees only students/courses assigned to them - list of their
           students - records list with filters (name, admission no.,
           department, course, semester, category, status, dates) - preview and
           download - comment and set status (Under Review, Reviewed,
           Approved, Rejected, Revisions Required) - request corrections -
           notifications when their students upload - review history -
           reports (CSV, Excel, print/PDF) for their students only.
           Cannot touch accounts or system settings.

 ADMIN     Dashboard statistics - create / approve / suspend / deactivate /
           reactivate accounts and reset passwords - assign lecturers to
           courses or individual students - see and review all documents -
           manage categories, departments, courses, academic years, semesters -
           announcements (with notifications) - reports and exports - audit
           log (logins, uploads, downloads, edits, deletions, approvals,
           account changes) - backup and restore of database + documents.

--------------------------------------------------------------
3. SECURITY BUILT IN
--------------------------------------------------------------
 - Passwords stored with password_hash (bcrypt); 5 wrong logins lock the
   account for 15 minutes.
 - Every page checks the user's role; every document request checks that the
   user may access that exact document.
 - Uploaded files are stored in storage/uploads with random names, blocked from
   direct web access (storage/.htaccess) and served only through download.php
   after a permission check. File type is checked by extension AND content.
 - All database queries use prepared statements; all output is HTML-escaped;
   every form has a CSRF token.
 - Exports neutralise spreadsheet formulas typed into titles.

 SETTINGS in config.php:  REQUIRE_APPROVAL (admin approves new students),
 LECTURER_CAN_APPROVE, MAX_UPLOAD_MB.

--------------------------------------------------------------
4. GOOD TO KNOW
--------------------------------------------------------------
 - PDF reports: use the "Print / Save as PDF" button and choose "Save as PDF"
   in the print window. CSV opens in Excel; the "Excel" export is a .xls file
   Excel opens (it may show a format warning, click Yes).
 - Word files (DOC/DOCX) cannot be previewed in the browser; they download.
 - Restoring a backup REPLACES all current data.
 - Back up regularly and copy the zip files off the computer.
 - Going online later: use HTTPS, a non-root MySQL account with a password,
   a hosting plan that supports PHP 8 + MySQL, and turn off display_errors.
 - This code was written without a PHP interpreter available, so it has not
   been run. Report any error message you see and it can be fixed quickly.

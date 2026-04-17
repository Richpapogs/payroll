# Payroll Management System

A full-featured Payroll Management System built with PHP, MySQL, and Bootstrap 5.

## Features
- **Admin Dashboard**: Summary cards and payroll trends chart.
- **Employee Management**: CRUD operations for employees.
- **Attendance System**: Mark daily attendance (Present, Absent, Leave, Half-day).
- **Payroll Calculation**: Automated Philippine-based deductions (SSS, PhilHealth, Pag-IBIG, Tax).
- **Payslip Generation**: Generate professional PDF payslips using FPDF.
- **Employee Portal**: Separate login for employees to view profile, attendance, and download payslips.
- **Reports**: Monthly attendance and payroll summaries.

## Setup Instructions

1. **Database Setup**:
   - Create a database named `payroll_db` in your MySQL server (e.g., via phpMyAdmin).
   - Import the `database.sql` file into the `payroll_db` database.

2. **Configuration**:
   - Open `config.php` and update the database credentials if necessary:
     ```php
     define('DB_SERVER', 'localhost');
     define('DB_USERNAME', 'root');
     define('DB_PASSWORD', '');
     define('DB_NAME', 'payroll_db');
     ```

3. **FPDF Library**:
   - This project includes a minimal stub for `assets/fpdf/fpdf.php`. For full PDF generation functionality, download the official FPDF library from [fpdf.org](http://www.fpdf.org/en/download.php) and replace the contents of `assets/fpdf/fpdf.php` with the official one.

4. **Login Credentials**:
   - **Admin**: 
     - Username: `admin`
     - Password: `admin123`
   - **Employee**: 
     - Created automatically when adding an employee.
     - Default Username: Employee ID
     - Default Password: `employee123`

## Project Structure
- `config.php`: Database connection and global functions.
- `login.php`: Authentication page.
- `dashboard.php`: Admin overview.
- `employees.php`: Employee CRUD.
- `attendance.php`: Attendance marking.
- `payroll.php`: Payroll calculation and history.
- `reports.php`: Admin reports.
- `payslip_gen.php`: PDF generator.
- `profile.php`: Employee profile & password change.
- `my_attendance.php`: Employee's own attendance view.
- `my_payslips.php`: Employee's own payslip history.

## Security
- Uses PHP PDO with prepared statements to prevent SQL Injection.
- Password hashing using `password_hash()` and `password_verify()`.
- Session-based authentication and role-based access control.

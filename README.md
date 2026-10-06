# Smart SMS - XAMPP

1. Extract this folder to `D:\Xamp\htdocs\smart-sms`.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/smart-sms/`.
4. The app creates/upgrades the `smart_sms` database automatically when MySQL is available.

## Admin
- Email: `admin@school.com`
- Password: `admin123`
- **Classes**: view students by Class 5-10.
- **Fees**: set one fee for a whole class and see class fee totals.
- **Fee Status**: separate class-wise report showing each student's total, paid amount, and Paid / Not Paid / Due status.

## Roles
- Student: class selection is shown only on Student login/registration.
- Faculty: no class selection on login; class is selected inside Attendance, Marks, and Notes.
- Admin: no class selection on login.

## Login tabs
Each browser tab has its own login token. Logout invalidates only that tab's token.

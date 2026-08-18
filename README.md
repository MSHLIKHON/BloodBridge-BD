# BloodBridge BD

BloodBridge BD is a web-based blood donation and hospital blood-bank management system for Bangladesh. It helps blood seekers create requests, donors maintain their availability and health information, and hospital staff monitor donors and blood stock.

> Project type: Software Engineering Lab Project  
> Current status: Working academic prototype

## Main features

- Registration and secure login
- Four user roles: Blood Seeker, Donor, Hospital Staff and Admin
- Blood-request create, read, update and delete operations
- Donor search by blood group and Bangladesh location
- Hospital blood-bank stock search
- Bangladesh location autocomplete
- Donor availability and last-donation record
- Private donor medical information
- Automatic next eligible donation date after 120 days
- Hospital/Admin donor screening verification
- Responsive interface for desktop and mobile

## Technology

- HTML5
- CSS3
- JavaScript
- PHP 8+
- MariaDB / MySQL
- XAMPP

## Team NullLogic

| Student ID | Name |
|---|---|
| 0112330688 | Md Syed Hasan Likhon |
| 0112330518 | Jahedul Islam Rahat |
| 0112330117 | Md. Tanvir |
| 011212081 | Shak Tayef Uddin |
| 0112310347 | Md. Sadnan Sakir Hemel |

## Run on XAMPP

1. Install and open XAMPP.
2. Start Apache and MySQL.
3. Copy the `BloodBridge_BD` folder to:
   - macOS: `/Applications/XAMPP/xamppfiles/htdocs/`
   - Windows: `C:\xampp\htdocs\`
4. Open `http://localhost/BloodBridge_BD/setup.php`.
5. After setup completes, open the login page.

The default XAMPP database configuration is:

- Host: `localhost`
- Port: `3306`
- Database: `bloodbridge_bd`
- Username: `root`
- Password: empty

If MySQL has a password, copy `config/database.local.example.php` as `config/database.local.php` and enter the password there. The local file is ignored by Git and must not be uploaded.

## Demo accounts

These accounts contain sample data only.

| Role | Email | Password |
|---|---|---|
| Admin | admin@bloodbridge.test | admin123 |
| Blood Seeker | seeker@bloodbridge.test | seeker123 |
| Donor | donor@bloodbridge.test | donor123 |
| Hospital Staff | hospital@bloodbridge.test | hospital123 |

New users can also create an account from the registration page.

## Project structure

```text
BloodBridge_BD/
├── assets/                 CSS and JavaScript
├── config/                 Application and database settings
├── docs/                   Project and GitHub guides
├── includes/               Shared authentication and layout files
├── database.sql            Database schema
├── setup.php               Local database setup
├── login.php               User authentication
├── dashboard.php           Role-based dashboard
├── requests.php            Blood request list
├── search.php              Donor and blood-bank search
├── donor_profile.php       Donor health profile
└── donor_details.php       Privacy-aware donor information
```

## Screenshots

Add real screenshots of the running project inside `docs/screenshots/`. Recommended screenshots are login, dashboard, blood search, request form and donor profile. Do not include real patient or donor data.

## GitHub workflow

Each team member should use their own GitHub account and make genuine contributions. Use a separate branch for a task, make a clear commit, and open a pull request. See [`docs/GITHUB_SETUP_BN.md`](docs/GITHUB_SETUP_BN.md) for the complete beginner-friendly steps.

## Security note

This repository is an academic prototype. Do not upload real passwords, patient records, donor medical data or production database backups. Rename or remove `setup.php` before a real deployment.

## Future improvements

- Hospital inventory management
- Blood reservation workflow
- Email or SMS notifications
- Verified hospital onboarding
- Emergency request prioritization
- Production deployment and security review

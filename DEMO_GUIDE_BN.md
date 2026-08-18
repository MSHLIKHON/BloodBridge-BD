# ১৬ আগস্ট Update Forum — তোমাদের করণীয়

## ১. ক্লাসের আগের দিন যা করবে

- পাঁচজন সদস্যের laptop-এ না হলেও অন্তত demo laptop-এ XAMPP install করবে।
- Apache এবং MySQL start করে project setup করবে।
- চারটি demo account দিয়ে একবার করে login করে দেখবে।
- Create, Read, Update, Delete—চারটি operation আগে থেকে practice করবে।
- Internet ছাড়াও যেন demo চলে, সেটি নিশ্চিত করবে।
- Project folder এবং database-এর backup pen drive/Google Drive-এ রাখবে।
- পাঁচজন সদস্যকেই উপস্থিত থাকতে হবে।

## ২. Teacher-এর সামনে exact demo order

### Step 1 — System setup

XAMPP Control Panel দেখিয়ে বলবে:

> We have set up Apache for the PHP backend and MySQL for the database. Our frontend uses HTML, CSS and JavaScript.

### Step 2 — Connection

Login page open করে login করবে। Dashboard data দেখিয়ে বলবে:

> The dashboard data is coming from our MySQL database through the PHP backend. So the frontend, backend and database are connected.

### Step 3 — Create

Blood Seeker account দিয়ে নতুন request তৈরি করবে।

> This is our Create operation. A new blood request is inserted into the database.

### Step 4 — Read

Requests page ও Search Blood page দেখাবে।

> This is our Read operation. The system reads requests, matching donors and hospital stock from the database.

### Step 5 — Update

Request-এর location/units edit করবে অথবা Donor account দিয়ে status Accepted করবে।

> This is our Update operation. The selected database record is updated successfully.

### Step 6 — Delete

একটি pending request delete করবে।

> This is our Delete operation. The request is removed from the database.

### Step 7 — Dashboard

Admin account দিয়ে login করে summary cards এবং সব request দেখাবে।

> We have role-based login and dashboards for seeker, donor, hospital staff and admin.

## ৩. পাঁচজনের কাজ ভাগ

| Member | দায়িত্ব | কী বলবে/দেখাবে |
|---|---|---|
| Member 1 | Introduction + Frontend | Project purpose এবং login UI |
| Member 2 | Backend + Connection | PHP backend এবং PDO–MySQL connection |
| Member 3 | Database + Create/Read | Tables, create request এবং requests list |
| Member 4 | Update/Delete | Edit/status update এবং delete operation |
| Member 5 | Dashboard + Workflow | Role dashboard, search flow এবং next plan |

## ৪. Screenshot/প্রমাণ রাখবে

- XAMPP-এ Apache ও MySQL running
- Login page
- Seeker dashboard
- Create request form
- Request list
- Update page
- Delete success message
- phpMyAdmin-এর `users` এবং `blood_requests` table

## ৫. এখনো যা তোমাদের নিজে করতে হবে

- নিজের laptop-এ XAMPP install করতে হবে।
- Project folder `htdocs`-এ copy করে `setup.php` run করতে হবে।
- Team member অনুযায়ী speaking part ভাগ করে practice করতে হবে।
- Teacher-এর সামনে actual browser এবং phpMyAdmin দিয়ে live demo দিতে হবে।
- চাইলে demo data-তে নিজেদের নাম, phone এবং location বসাতে পারবে।

## ৬. ২০% পর্যন্ত completed বলে যা দেখাবে

- Requirements and system workflow
- Frontend/backend/database setup
- Database connection
- Secure login
- Four user roles
- Role-based dashboard
- Blood Request CRUD
- Donor and hospital stock search

পরবর্তী update-এ donor notification, hospital approval, inventory increase/decrease, donation history এবং report generation করা হবে।

# Donor Health Profile — সহজ ব্যাখ্যা

## Featureটি কী করে

Donor নিজের last donation date, total donation, availability, medical condition এবং current medicine update করতে পারে। Profile update করার পর screening status `Pending` হয়। Hospital Staff অথবা Admin তথ্য যাচাই করে status update করতে পারে।

## কে কী দেখতে পাবে

- Blood Seeker: blood group, location, last donation, next eligible date, availability এবং verification status।
- Donor: নিজের public ও private health information।
- Hospital Staff: donor-এর health declaration এবং screening controls।
- Admin: donor list, private information এবং verification controls।

Blood Seeker নির্দিষ্ট রোগ বা medicine-এর তথ্য দেখতে পাবে না।

## 120 দিনের হিসাব

`config/app.php`-এর `next_eligible_date()` function last donation date-এর সঙ্গে 120 দিন যোগ করে। এটি প্রাথমিক system calculation। Donation-এর আগে hospital screening প্রয়োজন।

## গুরুত্বপূর্ণ files

- `donor_profile.php`: Donor নিজের তথ্য update করে।
- `donor_details.php`: Role অনুযায়ী public অথবা private information দেখায়।
- `donors.php`: Hospital Staff ও Admin সব donor review করে।
- `search.php`: Blood Seeker safe donor information দেখে।
- `setup.php`: পুরোনো database-এ নতুন donor columns যোগ করে।

## Class-এ দেখানোর sequence

1. Donor account দিয়ে login করো।
2. `My Profile` খুলে last donation এবং health information save করো।
3. দেখাও যে status `Pending` হয়েছে।
4. Hospital account দিয়ে login করে `Donors` page খোলো।
5. Donor review করে `Eligible` ও `Verified` করো।
6. Seeker account দিয়ে search করে public information দেখাও।
7. বোঝাও যে seeker medical-condition details দেখতে পাচ্ছে না।

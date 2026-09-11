# Donor Health ও Donation Workflow

## Donor কোন তথ্য দেয়

Donor নিজের last donation date, current availability, কোনো illness/medical condition আছে কি না এবং current medication লিখতে পারে। Verified total donation donor নিজে পরিবর্তন করতে পারে না; completed request থেকে এটি automatic update হয়।

Profile-এর health information বদলালে screening status আবার `Pending` হয়। Hospital Staff অথবা Admin নতুন তথ্য দেখে screening status ঠিক করে এবং verification দেয়।

## কে কী দেখতে পাবে

- সাধারণ Blood Seeker: blood group, location, eligibility, last donation ও hospital-verification status।
- Request accept হওয়ার পর সংশ্লিষ্ট Blood Seeker: accepted donor-এর contact এবং medical declaration।
- Donor: নিজের সম্পূর্ণ profile ও donation history।
- Hospital Staff: donor-এর medical declaration, medication ও screening controls।
- Admin: সম্পূর্ণ donor information ও screening controls।

এই permission server-side PHP দিয়ে পরীক্ষা করা হয়; শুধু CSS দিয়ে তথ্য লুকানো হয়নি।

## Eligibility হিসাব

`next_eligible_date()` last donation date-এর সঙ্গে 120 দিন যোগ করে। Donor-এর account active, availability on, screening `Eligible` এবং 120 দিনের সময় শেষ হলে search/matching-এ eligible দেখায়। Donation-এর আগে hospital-এর final medical screening প্রয়োজন।

## Request workflow

1. Seeker donor-source request তৈরি করে।
2. একই blood group-এর active donors notification পায়।
3. Eligible donor Accept অথবা Not Available দেয়।
4. Row lock-এর কারণে প্রথম valid acceptance-ই request পায়।
5. Acceptance-এর পরে দুই পক্ষের প্রয়োজনীয় contact দেখা যায়।
6. Seeker/Admin completion confirm করলে donation history তৈরি হয়।
7. Donor-এর last donation date ও verified donation count automatic update হয়।

## গুরুত্বপূর্ণ files

- `donor_profile.php`: health declaration ও availability
- `donor_details.php`: privacy-aware details ও hospital screening
- `donors.php`: Hospital/Admin donor review list
- `request_edit.php`: donor response এবং completion
- `donation_history.php`: verified completion history
- `config/app.php`: eligibility ও privacy helper

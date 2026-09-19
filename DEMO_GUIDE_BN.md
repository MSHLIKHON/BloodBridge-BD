# BloodBridge BD 1.1 — demo শুরু

প্রথমে START_HERE_BN.html অনুসরণ করে setup.php চালান। এই নতুন flow আগের donor সরাসরি Accept flow-কে replace করেছে।

1. Admin: Services → Testing Centre চালান। Hospital, club, prescription review ও reports দেখান। Admin-এর New Request নেই।
2. Donor: Services → My location থেকে Division → District / Zila → Upazila / Area দিন। Nearby consent দিলে map point save হবে। Screening status Eligible থাকতে হবে।
3. Seeker: prescription-সহ এক unit Donor request তৈরি করুন। একই এলাকার map point দিন।
4. Assigned Hospital বা Admin: Prescription review করুন। এটি শুধু document review।
5. Request owner: request খুলে Check Nearby Donors। ১ কিমির মধ্যে matching donor না থাকলে zero দেখানোই সঠিক।
6. দুটি eligible personal donor account: request-এ Interested চাপুন। Fresh seed-এর দ্বিতীয় donor অন্য blood group; দুই matching donor scenario-র জন্য দ্বিতীয় fictional account তৈরি/screen করুন।
7. Seeker: একজনকে Select। অন্য donor Not selected দেখবে।
8. Selected donor: Confirm I Can Donate; donation বাস্তবে হওয়ার পরে I Have Donated।
9. Seeker: Confirm Blood Received। Status Completed; history একবার update।
10. Alternate demo: donation-এর আগে Not Received দিয়ে close; donation report-এর পরে Not Received দিলে Disputed। Donor ভুল report দিয়ে থাকলে correction দিতে পারবে।
11. Hospital: নিজের inventory, reservation approval/rejection/collection এবং direct donation screening দেখান।
12. Services: university club, membership/campaign; private health report ও consent; reminder settings।
13. Admin: Reports/Audit ও Testing Centre আবার দেখান।

Map street tiles-এ internet লাগে। Saved donor coordinates live tracking নয়। 105 দিনের reminder in-app; PC বন্ধ থাকলে scheduler চলবে না। Actual XAMPP/browser ফল docs/MANUAL_ACCEPTANCE.md-তে লিখুন।

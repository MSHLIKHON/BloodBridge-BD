# Existing Git repository update

<!-- File purpose: GIT UPDATE BN documents the BloodBridge BD release, workflow, or verification process. -->

এই ZIP-এ existing repository history নেই। এই কাজের সময় GitHub-এ commit/push করা হয়নি। শুধু source file, tests এবং documentation update হয়েছে।

1. তোমার existing cloned repository-এর backup রাখো। Uncommitted কাজ থাকলে আগে review করে preserve করো।
2. নতুন branch বানাও, যেমন `feature/lab-completion`।
3. ZIP-এর **ভিতরের** `BloodBridge_BD` source files repository-এর একই জায়গায় copy করো। পুরো project folder আরেক স্তর ভিতরে ঢোকাবে না।
4. Existing `.git` history এবং নিজের `config/database.local.php` overwrite/delete করবে না। Real SQL export বা private reports commit করবে না।
5. Changed files review, local tests ও demo চালাও।
6. যাচাই করা changes নিয়ে meaningful commits তৈরি করো; না-করা কাজ বা পুরোনো তারিখের commit বানানোর দরকার নেই।

Logical commit groups (কিছু file-এ mixed change থাকলে hunk staging লাগবে):

- `feat: add versioned schema upgrade and protected installer`
- `feat: add club membership and campaign coordination`
- `feat: add private prescriptions and consented health reports`
- `feat: complete direct hospital donation workflow`
- `feat: add expiry reminders stock thresholds and admin reports`
- `fix: enforce workflow permissions and preserve stock consistency`
- `test: add unit workflow and native database suites`
- `docs: add setup demo and acceptance guides`

প্রতিটি commit-এর diff যেন তার message-এর সঙ্গে মেলে। শুধু বেশি সংখ্যা করার জন্য empty/duplicate commit নয়। একটি feature commit-এ তার প্রয়োজনীয় schema/service code একসঙ্গে রাখা ভালো।

GitHub Actions workflow source root-এর `.github/workflows/php-tests.yml`-এ আছে। Repository-তে push করার পরে Actions-এর বাস্তব ফল দেখবে। Workflow থাকা মানে CI ইতিমধ্যে pass করা নয়।

Native integration suite শুধু random `bb_qa_...` test database তৈরি/মুছে। কোনো existing lab database test runner-এর target নয়।

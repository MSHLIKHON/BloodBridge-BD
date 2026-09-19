# GitHub-এ Project Upload করার নিয়ম

## ১. GitHub repository তৈরি

GitHub-এ `BloodBridge-BD` নামে একটি নতুন repository তৈরি করো। Assessment চলাকালে `Private` রাখলে teacher এবং team members-কে collaborator হিসেবে যোগ করতে হবে। Repository তৈরির সময় README, `.gitignore` বা License automatically add করবে না—এই project-এ প্রয়োজনীয় ফাইল আগে থেকেই আছে।

## ২. Mac Terminal থেকে প্রথম upload

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/BloodBridge_BD
git init -b main
git status
git add .
git commit -m "Initial working prototype of BloodBridge BD"
git remote add origin https://github.com/YOUR_USERNAME/BloodBridge-BD.git
git remote -v
git push -u origin main
```

`YOUR_USERNAME`-এর জায়গায় নিজের GitHub username বসাবে।

## ৩. Team members যোগ করা

Repository থেকে `Settings > Collaborators > Add people` নির্বাচন করো। প্রত্যেক member নিজের GitHub account দিয়ে invitation accept করবে।

## ৪. পরবর্তী কাজের নিয়ম

কাজ শুরু করার আগে latest code নাও:

```bash
git switch main
git pull origin main
```

নিজের কাজের জন্য branch তৈরি করো:

```bash
git switch -c feature/task-name
```

কাজ শেষ হলে:

```bash
git status
git add .
git commit -m "Describe the real change clearly"
git push -u origin feature/task-name
```

তারপর GitHub থেকে Pull Request তৈরি করবে। অন্য member code দেখে main branch-এ merge করবে।

## ভালো commit message-এর উদাহরণ

- `Add donor profile validation`
- `Fix request edit navigation`
- `Improve location search suggestions`
- `Update XAMPP setup instructions`
- `Add dashboard screenshots`

এক commit-এ একটি নির্দিষ্ট কাজ রাখার চেষ্টা করো। পুরোনো তারিখ দিয়ে commit বা কাজ না করে activity তৈরি করবে না।

## Presentation-এর আগে checklist

- README-তে project overview এবং setup instructions আছে
- Team member-দের নিজস্ব GitHub contribution আছে
- Commit message পরিষ্কার
- অন্তত কয়েকটি real issue এবং pull request আছে
- Running project-এর real screenshot আছে
- Repository-তে password বা real medical data নেই
- অন্য computer-এ README অনুসরণ করে project চালানো যায়

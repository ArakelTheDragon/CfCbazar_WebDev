# CfCbazar_WebDev
Web site and services for CfCbazar. Platform address is https://cfcbazar.ct.ws and https://cfcbazar.42web.io.

The repo is under development. All files are not uploaded. Ignored are config files.

Thanks Arak — I’ve reviewed the [CfCbazar_WebDev repository](https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/cfcbazar.ct.ws), and here’s a refined GitHub project description you can use for your repo’s README or landing page:

---

## 💼 CfCbazar Web Platform

A modular PHP-based platform for CfCbazar that integrates token mining, games, utilities, and DIY tools. Designed for ESP8266 compatibility, decentralized rewards, and user engagement through creative features.

### 🌐 Live Endpoints

- **Mining API**: [`http://cfc-api.atwebpages.com/api.php`](http://cfc-api.atwebpages.com/api.php)  
- **Dashboard**: [`http://cfcbazar.ct.ws/d.php`](http://cfcbazar.ct.ws/d.php)

---

### 🧱 Project Structure

```
cfcbazar.ct.ws/
├── includes/           # Reusable PHP functions
│   ├── reusable.php
│   └── reusable2.php
├── css/                # Global styles
├── js/                 # Global scripts
├── index.php           # Landing page
├── d.php               # Dashboard: balances, withdraw, deposit, login/register
├── testapi.php         # Sync tokens from mining API to local DB
├── w.php               # Withdraw WorkTokens or WorkTHR
├── buy.php             # Deposit WorkTokens or BNB for platform credit
├── price.php           # Admin: set WorkToken/BNB price
├── mail.php            # Email handling via Brevo SMTP
├── verify.php          # Email verification
├── forgot_password.php # Password recovery
├── register.php        # User registration
├── login.php           # User login
├── t.php               # Terms, privacy, contact
├── errors.php          # Reusable error handling
├── games/              # Game modules (basket, slot, maze, dino, etc.)
├── features/           # Utility tools (power calc, speed test, survival budget, etc.)
├── diy.php             # DIY tutorials and guides
├── projects.php        # Project listings
├── r.php               # URL shortener
├── tv.php              # YouTube playlist viewer
├── about.php           # About CfCbazar
├── admin.php           # Admin panel
├── server.php          # Deprecated legacy compatibility, not included on repo
```

---

### 🔧 Platform Highlights

- ⛏️ ESP8266-compatible token mining via `api.php`
- 🏦 Platform-controlled token supply with reserve enforcement
- 🔄 Token syncing from remote API to dashboard via `testapi.php`
- 💰 Deposit and withdrawal system for WorkToken and WorkTHR
- 🎮 Mini-games with token rewards
- 🛠️ DIY guides, calculators, and utilities
- 📺 Media features like YouTube playlists and speed tests
- 🔐 Full user system with registration, login, email verification, and password recovery

---

Let me know if you’d like this turned into a full `README.md` with badges, setup instructions, or contributor guidelines. I can also help you generate a landing page or GitHub Pages site to showcase the platform.

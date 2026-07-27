# 📚 CfCbazar `/includes`

> **Shared PHP Core Library for the CfCbazar Ecosystem**

The `/includes` directory contains the reusable backend components that power every page of the CfCbazar platform. The heart of this directory is **`reusable.php`**, which centralizes application logic, session handling, authentication, UI rendering, mining, analytics, and RPG progression into a single shared library.

By pairing `reusable.php` with the global stylesheet (`/css/styles.css`) and global JavaScript (`/js/scripts.js`), the platform follows a strict **DRY (Don't Repeat Yourself)** architecture while maintaining a consistent user experience across every page.

---

# 🏗 Architecture

```
                    ┌─────────────────────────┐
                    │    CfCbazar Platform    │
                    └────────────┬────────────┘
                                 │
        ┌────────────────────────┼────────────────────────┐
        ▼                        ▼                        ▼
  PHP Core Library        Global Stylesheet        Global Scripts
 /includes/reusable.php    /css/styles.css         /js/scripts.js
 Logic & Rendering          UI & Theme             Client Logic
```

---

# 📂 Directory Overview

```
includes/
├── reusable.php          # Core reusable library
├── common.php            # Shared helper functions
├── track_visits.php      # Analytics & visit tracking
├── ...
```

---

# 🚀 Quick Start

Every page should include only the reusable library.

`reusable.php` automatically loads `config.php` internally.

```php
<?php

require_once __DIR__ . '/includes/reusable.php';

// Ensure database connection
require_database_connection();

// Validate user session
session_check();

// Record page visit
trackVisit('dashboard');

// Render page
include_header('Dashboard');
?>

<main class="container">

    <h2>Welcome to CfCbazar</h2>

</main>

<?php
include_footer();
?>
```

---

# 🔄 Typical Request Flow

```
Browser Request
        │
        ▼
reusable.php
        │
        ├── Loads config.php
        ├── Database connection
        ├── Session validation
        ├── Security checks
        ├── Feature flags
        ├── Analytics
        ├── HTML rendering
        │
        ▼
   Page Content
        │
        ▼
Footer + scripts.js
```

---

# 🖼 UI Rendering

These functions generate the shared layout used throughout the platform.

| Function | Description |
|----------|-------------|
| `include_header($title)` | Outputs the HTML document, metadata, CSS, and page wrapper. |
| `include_footer()` | Closes page layout and loads the global JavaScript. |
| `include_menu()` | Displays the site's navigation menu. |
| `render_top_userbar()` | Displays the logged-in user summary. |
| `render_worktoken_dashboard()` | Shows the WorkToken dashboard. |
| `show_disabled_message()` | Displays a styled disabled-feature message. |
| `displayServerStatus()` | Shows server health and uptime information. |

---

# 🔐 Authentication & Security

Shared authentication and security helpers.

| Function | Description |
|----------|-------------|
| `session_check()` | Validates user login session. |
| `getUserStatus($email)` | Returns the user's permission level. |
| `logoutUser()` | Ends the active session. |
| `enforce_https()` | Redirects HTTP traffic to HTTPS when enabled. |
| `setReturnUrlCookie()` | Stores the destination page before login. |
| `redirectToReturnUrl()` | Redirects the user after authentication. |

---

# ⛏ Mining Engine

Functions shared by the browser miner and mining ecosystem.

| Function | Description |
|----------|-------------|
| `getWorkerStats($email)` | Retrieves mining statistics. |
| `handleMinerReward()` | Processes accepted mining shares. |
| `reward_miner_workthr()` | Awards WorkTHR tokens based on shares. |
| `grant_mining_bonus()` | Applies mining streak bonuses. |
| `renderMinerScript()` | Injects the browser miner configuration. |
| `renderMinerClient()` | Displays the browser miner interface. |

---

# 🎮 RPG Progression

Functions responsible for leveling, achievements, quests, and equipment.

| Function | Description |
|----------|-------------|
| `addExp()` | Adds experience points. |
| `setLevel()` | Sets a player's level. |
| `checkLevelUp()` | Evaluates and performs level progression. |
| `upgradeGearSlot()` | Upgrades a selected equipment slot. |
| `upgradeRandomGear()` | Upgrades a random equipment slot. |
| `upgradeAllGear()` | Upgrades every equipment slot. |
| `syncQuestsAchievementsAndRewards()` | Synchronizes quests, achievements, and rewards. |

---

# 📊 Analytics & System Utilities

Platform-wide monitoring and maintenance functions.

| Function | Description |
|----------|-------------|
| `trackVisit($slug)` | Records page traffic analytics. |
| `require_database_connection()` | Verifies the database connection. |
| `close_database()` | Safely closes the database connection. |
| `checkSystemFlags()` | Reads maintenance mode and feature flags. |
| `redirectToCfCbazar42web()` | Redirects users to the production domain when required. |

---

# 🧩 Design Philosophy

The reusable library serves as the central application framework for CfCbazar.

It provides:

- Centralized business logic
- Shared UI rendering
- Authentication management
- Session handling
- Database utilities
- Mining functionality
- RPG progression
- Analytics
- Feature flag management
- Cross-page consistency

Every new page should leverage these shared functions instead of duplicating code.

---

# ⚠ Developer Guidelines

## Always use the reusable library

Do **not** include `config.php` directly.

```php
require_once __DIR__ . '/includes/reusable.php';
```

---

## Track pages correctly

Pass a page identifier string.

```php
trackVisit('admin');
```

Not:

```php
trackVisit($conn);
```

---

## Keep styling centralized

HTML generated by reusable functions should rely on the shared design system provided by:

```
/css/styles.css
```

Prefer existing CSS variables such as:

- `--primary`
- `--secondary`
- `--card-bg`
- `--text-color`
- `--radius`

---

## Use the shared JavaScript

Interactive functionality should be placed in:

```
/js/scripts.js
```

instead of embedding duplicate JavaScript into individual pages whenever practical.

---

## Handle database failures gracefully

Reusable functions should validate database availability and avoid fatal application crashes whenever possible.

---

# 📁 Related Files

```
/includes/
    reusable.php
    common.php
    track_visits.php

/css/
    styles.css

/js/
    scripts.js
```

---

# 📜 License

This directory is part of the **CfCbazar Web Development** project.

See the repository license for usage, modification, and distribution terms.
````0

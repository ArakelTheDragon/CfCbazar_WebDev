# 📚 Reusable PHP Library (`/includes/reusable.php`)
> **CfCbazar Ecosystem Core Architecture**

Inspired by modular single-library frameworks (such as *Micronova Exam*), the CfCbazar ecosystem centralizes core backend logic, session state management, user authorization, UI rendering, mining telemetries, and level progression into a single global include (`reusable.php`).

Pairing this PHP library with our global stylesheet (`/css/styles.css`) and script handler (`/js/scripts.js`) ensures strict DRY (Don't Repeat Yourself) compliance, uniform layout components, and clean cross-page execution.

---

## 🏗 Ecosystem Architecture


┌─────────────────────────┐
│    CfCbazar Platform    │
└────────────┬────────────┘
│
┌────────────────────────────┼────────────────────────────┐
▼                            ▼                            ▼
📂 PHP Core Library        🎨 Global Stylesheet         ⚡ Global Scripts
/includes/reusable.php    /css/styles.css            /js/scripts.js
(Logic & HTML Engine)       (Design Tokens & UI)         (AJAX & Interactivity)

---

## 🚀 Quick Start & Integration

Include `reusable.php` near the top of any endpoint or web page file inside the CfCbazar platform:

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/reusable.php';

// 1. Enforce active MySQL database connection
require_database_connection();

// 2. Validate user authentication & session state
session_check();

// 3. Log page traffic (Pass page slug identifier as string)
trackVisit('page_slug_name');

// 4. Render standard ecosystem header
include_header("Page Title Here");
?>

<main class="container">
    <h2>Welcome to CfCbazar</h2>
</main>

<?php
// 5. Render standard ecosystem footer
include_footer();
?>

🛠 Function Reference
1. 🖼 UI Layout & Rendering
Renders uniform site components driven by CSS variables in /css/styles.css.
| Function | Signature | Description |
|---|---|---|
| include_header | include_header(string $title) | Outputs standard <head>, meta tags, links /css/styles.css, and initializes main page structure. |
| include_footer | include_footer() | Closes page containers, renders the footer, and injects global /js/scripts.js. |
| include_menu | include_menu() | Generates top navigation links dynamically based on active session state. |
| render_top_userbar | render_top_userbar() | Outputs the logged-in user summary bar showing account details and balances. |
| render_worktoken_dashboard | render_worktoken_dashboard() | Renders the visual WorkToken dashboard card with live stats. |
| show_disabled_message | show_disabled_message(string $msg) | Displays a styled alert notification when features are toggled off. |
| displayServerStatus | displayServerStatus() | Renders server status indicators and system uptime telemetry. |
2. 🔐 Session & Security Utilities
Handles authentication enforcement, access permissions, and connection safety checks.
| Function | Signature | Description |
|---|---|---|
| session_check | session_check() | Verifies valid login session; redirects unauthenticated requests to login.php. |
| getUserStatus | getUserStatus(string $email): int | Queries numerical access status (1 = Admin, 2 = Moderator, etc.). |
| logoutUser | logoutUser() | Terminates session variables, flushes persistent cookies, and safely redirects. |
| enforce_https | enforce_https() | Automatically redirects HTTP connections to secure HTTPS endpoints. |
| setReturnUrlCookie | setReturnUrlCookie(string $path, int $expireSeconds = 300) | Stores target page URL prior to redirecting for authentication. |
| redirectToReturnUrl | redirectToReturnUrl(string $fallback = '/index.php') | Redirects user back to intended destination after successful login. |
3. ⛏ Mining Engine & WorkTHR Rewards
Processes worker telemetries, share submissions, and mints WorkTHR tokens.
| Function | Signature | Description |
|---|---|---|
| getWorkerStats | getWorkerStats(string $email) | Fetches aggregate shares, total worker count, and mining metrics for a user. |
| handleMinerReward | handleMinerReward(...) | Validates worker MAC address, active status, and processes incoming share rewards. |
| reward_miner_workthr | reward_miner_workthr(string $email, int $shares) | Calculates WorkTHR token yields per share and commits directly to database. |
| grant_mining_bonus | grant_mining_bonus(string $email) | Evaluates consecutive streak triggers and applies bonus token multipliers. |
| renderMinerScript | renderMinerScript() | Inject JS mining client configuration for browser-based hashing. |
| renderMinerClient | renderMinerClient() | Outputs the web mining interface container and worker controls. |
4. 📈 Progression, XP & Gear Upgrades
Manages RPG-style account leveling, quest sync, and gear slot upgrades.
| Function | Signature | Description |
|---|---|---|
| addExp | addExp(string $email, int $xp) | Awards experience points to an account and triggers level-up evaluations. |
| setLevel | setLevel(string $email, int $level) | Manually updates and commits the target level for a user. |
| checkLevelUp | checkLevelUp(string $email) | Computes level thresholds, increments user level, and issues level rewards. |
| upgradeGearSlot | upgradeGearSlot(string $email, string $slot, float $amount) | Spends token balance to upgrade individual equipment slots. |
| upgradeRandomGear | upgradeRandomGear(string $email, float $amount) | Upgrades a randomly chosen gear slot. |
| upgradeAllGear | upgradeAllGear(string $email, float $amount) | Distributes upgrades evenly across all available gear slots. |
| syncQuestsAchievementsAndRewards | syncQuestsAchievementsAndRewards(string $email) | Validates task milestones and disburses pending achievement payouts. |
5. 📊 Analytics, Health & System Utilities
Maintains platform telemetry, database connection safety, and maintenance flags.
| Function | Signature | Description |
|---|---|---|
| trackVisit | trackVisit(string $slug) | Records IP address, User-Agent, referrer, and page slug into visit_logs. |
| require_database_connection | require_database_connection() | Validates MySQL active status; halts execution with a 500 alert if unavailable. |
| close_database | close_database() | Safely closes active MySQL database connections. |
| checkSystemFlags | checkSystemFlags(mysqli $conn) | Pulls system maintenance states and global feature flags. |
| redirectToCfCbazar42web | redirectToCfCbazar42web() | Redirects external traffic to the primary production domain. |
⚠️ Key Developer Guidelines
 * String Slugs for Tracking: trackVisit() expects a string parameter representing the page identifier, not the $conn database object.
   * ✅ trackVisit('admin2');
   * ❌ trackVisit($conn);
 * Global Styling Parity: Ensure HTML elements output by functions use the CSS variables defined in /css/styles.css (e.g., var(--primary), var(--card-bg), var(--radius)).
 * Graceful Database Handling: Wrap MySQL queries within internal helper logic in try...catch blocks or suppress fatal unhandled query errors to keep the application operational.


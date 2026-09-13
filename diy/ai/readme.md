### **`README.md`**

```markdown
# CfCbazar AI Hub 🚀
A lightweight, high-performance hybrid AI router and local memory caching engine built entirely in **PHP**. Designed for standard LAMP/LEMP shared hosting environments (like cPanel or InfinityFree) without requiring Python, Node.js, or complex Docker containers.

---

## 📂 Required Files & Directory Structure

To run the AI Hub successfully, ensure your server matches the following folder layout:

```text
project_root/
├── ai/
│   ├── index.php                 <-- Main UI & Execution Router (Required)
│   ├── local_memory.json         <-- Auto-generated local cache log
│   └── memory_topics/            <-- Auto-generated folder for topic JSONs (Must be writable: 0755)
├── css/
│   └── styles.css                <-- CfCbazar UI theme stylesheet
├── includes/
│   ├── reusable.php              <-- Global configuration / API key holder
│   └── skills/
│       ├── AISkillInterface.php  <-- Skill architecture interface
│       ├── PromptUnderstandingSkill.php <-- Intent router
│       ├── DiagnosticSkill.php   <-- Local diagnostic engine (Bypasses OpenRouter for 'diagnostic 0f')
│       ├── KnowledgeSkill.php    <-- Fallback knowledge handler
│       └── agent_openrouter.php  <-- OpenRouter API connector
└── config.php                    <-- Alternative config file

```

---

## 🔑 How to Get a Free OpenRouter API Key

OpenRouter provides access to multiple top-tier AI models through a single unified API, and signing up is completely free.

1. **Visit OpenRouter:** Go to [openrouter.ai](https://openrouter.ai).
2. **Register/Sign In:** Click **Sign In** or **Sign Up**. You can quickly create an account using your Google account, GitHub, or email address.
3. **Navigate to API Keys:** Once logged in, go to your account dashboard and click on **Keys** (or visit `openrouter.ai/keys`).
4. **Create a Key:** Click **Create Key**, give it a descriptive name (e.g., *CfCbazar AI Hub*), and generate it.
5. **Copy Your Key:** Copy the generated API key (it typically starts with `sk-or-v1-...`). *Keep this key private and secure.*

---

## ⚙️ Installation & Configuration

### Step 1: Upload Files

Upload the project folder structure to your web server via FTP or your hosting file manager into your document root (e.g., `/htdocs/ai/` or `/public_html/ai/`).

### Step 2: Configure Your API Key

Open your `reusable.php` (or `config.php`) file and define your OpenRouter API key variable, or let it fall back in `ai/index.php`:

```php
<?php
// Inside includes/reusable.php or config.php
$API_openrouter = 'YOUR_OPENROUTER_API_KEY_HERE';
?>

```

### Step 3: Set File Permissions

Ensure the `/ai/memory_topics/` folder has write permissions enabled (**chmod 755** or **777** if required by your host) so the script can dynamically save cached topic files.

### Step 4: Launch

Navigate in your browser to your deployment URL:

```text
[https://yourdomain.com/ai/index.php](https://yourdomain.com/ai/index.php)

```

---

## 💡 Special Features

* **Smart API Bypass:** Typing prompts containing **"diagnostic 0f"** (case-insensitive) automatically bypasses remote cloud calls and instantly triggers local diagnostic skills.
* **Local Memory Caching:** Automatically mirrors and stores successful AI responses into local JSON topic files to minimize API consumption and accelerate recurring queries.
* **Instant Copy Utilities:** Built-in clipboard integration lets you copy user prompts and AI responses with a single click.

```

```

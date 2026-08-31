# 🤖 CfCbazar AI Agent  
Smart, lightweight PHP-based AI chat interface powered by the **OpenRouter API** and integrated into the CfCbazar ecosystem.

This module provides a fully responsive AI chat page with:
- CfCbazar global layout (header, menu, footer)
- Session-based message history
- Auto-scroll to latest message
- Loading spinner animation
- Secure API key handling via `/config.php`
- Full reusable bootstrap integration

---

## 🚀 Features

- **OpenRouter-powered AI chat**
- **Session-based conversation memory**
- **Auto-scroll to newest message**
- **Loading spinner while generating responses**
- **Fully integrated with CfCbazar reusable system**
- **Secure API key loading from `/config.php`**
- **Responsive UI using `/styles.css`**
- **No external dependencies**

---

## 📂 File Structure

```
/diy/ai/
│── index.php          # Main AI agent page
│── readme.md          # This file
│
/includes/
│── reusable.php       # CfCbazar master bootstrap
│── include_header.php
│── include_menu.php
│── include_footer.php
│── cfc_footer.php
│── ... (many more)
│
/config.php            # Contains $API_openrouter
/styles.css            # Global CfCbazar stylesheet
```

---

## 🔧 Requirements

- PHP 8.2+
- CfCbazar reusable system
- OpenRouter API key stored in:

```
$config['API_openrouter']
```

or

```
$API_openrouter
```

(depending on your config structure)

---

## ⚙️ Installation

1. Place `index.php` inside:

```
/diy/ai/
```

2. Ensure your `/config.php` contains:

```php
$API_openrouter = "your_api_key_here";
```

3. Ensure `/includes/reusable.php` is present.

4. Visit:

```
https://yourdomain/diy/ai/
```

---

## 🧠 How It Works

### 1. Bootstrap  
The page begins with the CfCbazar system loader:

- HTTPS enforcement  
- Database connection  
- System flags  
- Visit tracking  
- User session  
- Header + menu + top userbar  

### 2. AI Logic  
The page sends user messages to OpenRouter:

```php
function call_openrouter($messages) {
    global $API_openrouter;
    ...
}
```

### 3. Chat History  
Stored in:

```php
$_SESSION["messages"]
```

### 4. Auto-scroll  
After page load:

```js
window.onload = function() {
    chatBox.scrollTop = chatBox.scrollHeight;
};
```

### 5. Loading Spinner  
Displayed during POST submission.

---

## 🖼️ Screenshot (Example UI)
NA

MIT License

Copyright (c) 2026 CfCbazar

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the “Software”), to deal
in the Software without restriction, including without limitation the rights  
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell  
copies of the Software, and to permit persons to whom the Software is  
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in  
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR  
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,  
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE  
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER  
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,  
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN  
THE SOFTWARE.

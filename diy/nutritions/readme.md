# 🥗 CfCbazar - Nutrition & Calorie Counter

A lightweight, PHP and JSON-backed tool for calculating and viewing macro-nutrients (calories, carbs, protein, fat) for various ingredients and dishes. Built as part of the **CfCbazar DIY Tools** collection.

---

## 🚀 Features

* **Instant Search**: Client-side, zero-latency filtering powered by vanilla JavaScript (`liveSearch()`).
* **Role-Based Access Control (RBAC)**: Addition of new items is restricted to authorized user roles (`getUserStatus()` levels 1, 2, and 3).
* **Flat-File JSON Storage**: Simple, database-free persistence using `nutrition.json` with auto-initialization if missing.
* **Modular Integration**: Uses global layout and processing functions provided by `/includes/reusable.php` and `/includes/add-item.php`.

---

## 📁 Directory Structure

```text
diy/nutritions/
├── index.php         # Main application controller, table view, and routing
├── nutrition.json    # JSON data file storing items and macros
└── README.md         # Project documentation

📊 Data Schema (nutrition.json)
Items in nutrition.json are structured as an array of objects:
[
  {
    "name": "Grilled Chicken Breast",
    "grams": 100,
    "calories": 165,
    "carbs": 0,
    "protein": 31,
    "fat": 3.6
  }
]

⚙️ Setup & Dependencies
 * PHP: Version 7.4+ recommended.
 * Global Includes: Expects /includes/reusable.php two directories up (../../includes/reusable.php).
 * File Permissions: Ensure the server write permissions allow editing nutrition.json when adding new entries.


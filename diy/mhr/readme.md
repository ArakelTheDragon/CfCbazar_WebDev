
# 📋 MHR Customer & Account Management Portal

A lightweight, PHP-based Customer Relationship and Account Management system designed for fast lookup, security verification, account registration, and contact logging without the overhead of a SQL database.

---

## 🚀 Features

* **🔍 Account Directory & Search**
  * Search accounts instantly by **Account Number**, **Customer Name**, or **Postal Code**.
  * Visual status indicators for active (`🟢`) and inactive (`🔴`) services.

* **📝 Dedicated Account Registration (`register.php`)**
  * Clean form for creating new customer accounts.
  * Generates a unique 12-digit account number.
  * Mutually exclusive payment method setup (**Direct Debit** or **16-Digit Card Account**).

* **🔐 Security Check Verification Gate (`index.php`)**
  * Multi-channel verification support (**Phone Call** and **Backoffice Access**).
  * On-screen **Verification Answers Reference Box** for quick identity confirmation during calls.
  * Flexible identity confirmation via Calling Password or security fallbacks:
    * Mother's Maiden Name
    * Last 2 Digits of Direct Debit
    * Last 4 Digits of 16-Digit Card
  * Automated audit trail generation upon successful access verification.

* **👤 Interactive Account Dashboard (`account.php`)**
  * View and update customer details (Name, Address, Postal Code, Password, Maiden Name, DOB, Payment Details).
  * Instant service toggle (Activate / Deactivate services).

* **📞 Contact History Log & Audit System (`contacts.php`)**
  * Log incoming/outgoing contacts and notes against specific account profiles.
  * Flexible history filter options: **All Time**, **Last 7 Days**, **Last 30 Days**, or **Custom Date Range**.

* **📁 Zero Database Configuration (JSON Flat-File)**
  * Stores data cleanly in flat JSON files inside the `/data` folder.

---

## 📂 Project Directory Structure

```text
diy/mhr/
├── index.php          # Search Directory & Security Check Gate
├── register.php       # Account Registration Page
├── account.php        # Customer Dashboard & Profile Editor
├── contacts.php       # Contact Logging & Audit Log Viewer
└── data/              # Flat-file storage (Auto-created on first run)
     ├── accounts.json
     └── contacts/
          └── {ACCOUNT_NUMBER}.json

---

### `LICENSE`

```text
MIT License

Copyright (c) 2026 CfCbazar / ArakelTheDragon

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

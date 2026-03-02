# cfcbazar.atwebpages.com
This is the API backend that our other sites connect to. In some cases users can also connect to an API and read data or write date for some of the site's features.

- /track - this API provides third party services with info about tracking numbers generated from cfcbazar.42web.io/track. The API is located on [http://cfcbazar.atwebpages.com/track/json.php?go=TrackingNumber](http://cfcbazar.atwebpages.com/track/json.php?go=CFC-262945), **do not use HTTPS!**
- JSON response format:
```
{

"id": 5,

"tracking\_number": "CFC-262945",

"product\_name": "Test3",

"description": "NA",

"download\_link": "https://cfcbazar.42web.io",

"status": "delivered",

"created\_by": "181",

"created\_at": "2026-02-01 13:35:34",

"email\_downloader": "cfcbazar.payments@gmail.com"

}
```

# 📁 GitHub Repository Structure

```
cfcbazar-tracking-system/
│
├── backend/                     # Backend API (atwebpages.com)
│   ├── track/
│   │   ├── json.php             # Main API endpoint
│   │   └── README.md            # Backend-specific docs
│   └── includes/
│       └── reusable.php         # DB config + helpers
│
├── frontend/                    # Front-end (42web.io)
│   ├── track/
│   │   ├── index.php            # Main tracking page
│   │   └── README.md            # Front-end docs
│   └── includes/
│       └── reusable.php         # Front-end DB + login system
│
├── sql/
│   ├── backend_schema.sql       # Full backend DB schema
│   └── frontend_schema.sql      # Mirror DB schema
│
└── README.md                    # Main project documentation
```

---

# 📘 **README.md (Main Project Documentation)**  
*(Copy this into your GitHub repo root)*

---

# CfCbazar Digital Product Tracking System  
A lightweight, API‑driven digital product delivery and tracking system designed for:

- Digital product sellers  
- Automated delivery  
- Admin approval workflows  
- ESP8266/IoT integrations  
- Multi‑domain setups (front‑end + backend separation)

This project powers:

- **Front‑end:** `https://cfcbazar.42web.io/track/`  
- **Backend API:** `https://cfcbazar.atwebpages.com/track/json.php`

---

## 🚀 Features

### 🔹 1. Tracking Number Generation  
Logged‑in users can generate unique tracking numbers for digital products.

### 🔹 2. Admin Approval Workflow  
Admins (status = 1) can approve pending tracking numbers.

### 🔹 3. Secure Digital Delivery  
Users enter their email → backend logs the download → redirects to the file.

### 🔹 4. Two‑Database Architecture  
- **Backend database** = master  
- **Front‑end database** = mirror copy  
- Front‑end always syncs with backend API

### 🔹 5. ESP8266 Compatible  
The backend API returns clean JSON with no JavaScript challenges.

---

## 🧩 Architecture Overview

```
User → Front-end (42web.io) → Backend API (atwebpages.com) → Master SQL
                         ↓
                     Local SQL Mirror
```

### Why two databases?

- Backend = secure, authoritative  
- Front‑end = fast UI + local caching  
- ESP8266 can call either one  
- No CORS issues  
- No InfinityFree JavaScript challenge  

---

## 📡 Backend API Endpoints

### 🔍 Lookup tracking  
```
GET /track/json.php?go=CFC-262945
```

### 📥 Download + email capture  
```
GET /track/json.php?download=CFC-262945&email=user@example.com
```

### 🆕 Create tracking  
```
POST /track/json.php
product_name=
description=
download_link=
creator_email=
```

### ✔ Approve tracking  
```
GET /track/json.php?approve=ID
```

### 📄 List pending  
```
GET /track/json.php?list=pending
```

### 📄 List all  
```
GET /track/json.php?list=all
```

---

## 🛠 Example cURL Usage

### Lookup
```bash
curl "https://cfcbazar.atwebpages.com/track/json.php?go=CFC-262945"
```

### Download
```bash
curl "https://cfcbazar.atwebpages.com/track/json.php?download=CFC-262945&email=test@example.com"
```

### Create
```bash
curl -X POST -d "product_name=Book&description=PDF&download_link=https://example.com&creator_email=me@example.com" \
"https://cfcbazar.atwebpages.com/track/json.php"
```

### Approve
```bash
curl "https://cfcbazar.atwebpages.com/track/json.php?approve=5"
```

---

## 🗄 SQL Schemas

### Backend (master)
Located in: `sql/backend_schema.sql`  
Matches the schema you posted.

### Front‑end (mirror)
Located in: `sql/frontend_schema.sql`  
Same fields, but **no AUTO_INCREMENT**.

---

## 🖥 Front-End Behavior

The front‑end:

- Calls backend API for all operations  
- Mirrors backend data into local SQL  
- Displays tracking status  
- Provides admin approval UI  
- Handles download email capture  

---

## 🔧 ESP8266 Example

```cpp
HTTPClient http;
WiFiClientSecure client;
client.setInsecure();

http.begin(client, "https://cfcbazar.atwebpages.com/track/json.php?go=CFC-262945");
int code = http.GET();
String json = http.getString();
```

---

## 📦 Installation

### Backend
1. Upload `backend/track/json.php`  
2. Upload `backend/includes/reusable.php`  
3. Import `sql/backend_schema.sql`

### Front-end
1. Upload `frontend/track/index.php`  
2. Upload `frontend/includes/reusable.php`  
3. Import `sql/frontend_schema.sql`

---

## 📜 License
MIT License (optional)

---

## 🤝 Contributing
Pull requests welcome.

---

If you want, I can also generate:

- A **GitHub Actions CI workflow**  
- A **logo + banner** for the repo  
- A **demo GIF** showing the tracking flow  

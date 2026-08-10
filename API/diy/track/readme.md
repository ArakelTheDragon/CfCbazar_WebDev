
# CfCbazar DIY Package Tracking API

A lightweight, high-performance PHP-based JSON API for creating and querying package tracking records. Designed to handle individual tracking records as lightweight, isolated `.json` files for instant $O(1)$ lookups without database overhead.

---

## 🚀 Features

* **Instant File-Based Lookups:** Fetches package status directly from lightweight JSON files without looping or querying a database.
* **Isolated Storage:** Automatically creates and organizes records into a dedicated `/numbers/` directory (`/diy/track/numbers/{TRACKING_NUMBER}.json`).
* **Secure Bulk/Single Ingestion:** Endpoint secured with custom HTTP Header API Key validation (`X-API-KEY`).
* **Cross-Origin Ready:** Pre-configured CORS headers (`Access-Control-Allow-Origin: *`) for easy frontend integration.

---

## 📁 Directory Structure

```text
diy/
└── track/
    ├── index.php         # Public GET endpoint to retrieve tracking details
    ├── update.php        # Secured POST endpoint to create/update tracking files
    └── numbers/          # Auto-created folder containing individual tracking JSON files
        ├── 1413383877.json
        └── 1234768032.json

```

---

## ⚙️ Configuration & Setup

1. Upload `index.php` and `update.php` to your web server under `/diy/track/`.
2. Open `update.php` and set a strong secret key:
```php
define('API_SECRET_KEY', 'YOUR_STRONG_SECRET_KEY_HERE');

```


3. Ensure write permissions (`0755` or `0777`) are granted to the `/diy/track/` directory so PHP can automatically create the `numbers/` directory on the first update.

---

## 📡 API Endpoints

### 1. Retrieve Tracking Details

Fetch status and details for a specific package.

* **Endpoint:** `GET /diy/track/index.php`
* **Query Parameters:**
| Parameter | Type | Required | Description | Example |
| --- | --- | --- | --- | --- |
| `track` | String | **Yes** | The tracking number to look up | `1413383877` |



#### Example Request

```http
GET /diy/track/index.php?track=1413383877 HTTP/1.1
Host: cfcbazar.atwebpages.com

```

#### Success Response (`200 OK`)

```json
{
    "status": "success",
    "data": {
        "id": 30,
        "tracking_number": "1413383877",
        "product_name": "Sample Product",
        "description": "Digital Download",
        "download_link": "[https://example.com/download](https://example.com/download)",
        "status": "delivered",
        "created_at": "2026-08-09 05:41:55",
        "delivered_at": "2026-08-09 05:42:31"
    }
}

```

#### Error Response (`404 Not Found`)

```json
{
    "status": "error",
    "message": "Tracking number '1413383877' not found."
}

```

---

### 2. Update / Push Tracking Records

Create or update individual package JSON files. Supports single records or bulk updates under a `tracking` array.

* **Endpoint:** `POST /diy/track/update.php`
* **Required Headers:**
* `Content-Type: application/json`
* `X-API-KEY: YOUR_STRONG_SECRET_KEY_HERE`



#### Example Request Payload (Bulk or Single)

```json
{
  "tracking": [
    {
      "id": 30,
      "tracking_number": "1413383877",
      "product_name": "Sample Product",
      "description": "Digital Download",
      "download_link": "[https://example.com/download](https://example.com/download)",
      "status": "delivered",
      "created_at": "2026-08-09 05:41:55",
      "delivered_at": "2026-08-09 05:42:31"
    }
  ]
}

```

#### Success Response (`200 OK`)

```json
{
    "status": 200,
    "message": "1 tracking file(s) updated in /numbers/",
    "saved": [
        "numbers/1413383877.json"
    ],
    "failed": [],
    "timestamp": "2026-08-10 11:58:33"
}

```

#### Error Response (`403 Unauthorized`)

```json
{
    "status": 403,
    "error": "Unauthorized: Invalid API Key"
}

```

---

## 🔒 Security Best Practices

* Always keep your `API_SECRET_KEY` safe and avoid committing sensitive keys directly to public git repositories.
* Use HTTPS for all production requests to prevent key exposure over plain text.

```

```

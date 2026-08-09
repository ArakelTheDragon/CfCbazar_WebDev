Here is a complete, well-structured `README.md` file tailored for your GitHub repository. It includes project details, architecture, database setup, PHP function reference, and full API documentation for the remote JSON synchronization.

---

```markdown
# CfCbazar – Digital Product Tracking & Delivery System

A PHP and MySQL-based digital product tracking system designed for **CfCbazar**. It enables customers to check the delivery status of their purchased digital goods using a numeric tracking number, records download events with delivery timestamps, and synchronizes product status in real-time to an external JSON API endpoint.

---

## 🌟 Key Features

* **Order Lookup & Tracking:** Customers can enter their unique numeric tracking number to check order status, view product details, and download their digital goods.
* **Delivery Logging:** Automatically records `delivered_at` timestamps and the downloader's email address (`email_downloader`) upon product access.
* **Admin Approval Workflow:** New tracking submissions are marked as `pending` until approved by an administrator.
* **Remote Sync API Engine:** Automatically pushes database updates via cURL HTTP POST requests to maintain an up-to-date `index.json` file hosted on an external server (`atwebpages.com`).

---

## 📁 Repository Structure

```text
├── diy/
│   └── track/
│       ├── index.php                 # Main frontend UI for tracking lookup, generation, & admin approval
│       └── index.json                # Remote mirrored tracking data JSON file
├── includes/
│   ├── getTrackingRecord.php        # Helper function to query database for tracking records
│   ├── markDownloadDelivered.php    # Updates record status to 'delivered' & triggers JSON sync
│   ├── updateTrackingJson.php       # Syncs MySQL database rows to external update.php API endpoint
│   └── reusable.php                 # Global codebase helper library & includes
└── README.md                         # Project documentation

```

---

## 🗄️ Database Schema (`tracking` table)

Run the following SQL query to set up or update the `tracking` table structure:

```sql
CREATE TABLE IF NOT EXISTS `tracking` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tracking_number` VARCHAR(50) NOT NULL UNIQUE,
  `product_name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `download_link` TEXT NOT NULL,
  `status` ENUM('pending', 'in_transit', 'delivered') NOT NULL DEFAULT 'pending',
  `created_by` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `email_downloader` VARCHAR(255) NULL DEFAULT NULL,
  `delivered_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

```

If you are updating an existing database, ensure the `delivered_at` column is present:

```sql
ALTER TABLE `tracking` 
ADD COLUMN `delivered_at` DATETIME NULL DEFAULT NULL AFTER `email_downloader`;

```

---

## 📡 Remote Synchronization API

Whenever a tracking status changes or a product is delivered, the system triggers `updateTrackingJson()` to push current database records to the remote server.

### 1. External Sync Endpoint (POST)

* **URL:** `http://cfcbazar.atwebpages.com/diy/track/update.php`
* **Method:** `POST`
* **Content-Type:** `application/json`
* **Authentication Header:** `X-API-KEY: CfCbazar_Secure_Track_Key_2026_X9z`

#### Request Payload Envelope:

```json
{
  "status": "success",
  "last_updated": "2026-08-09 15:40:00",
  "total_tracking": 2,
  "tracking": [
    {
      "id": 10,
      "tracking_number": "1234768032",
      "product_name": "Digital Ebook",
      "description": "Math Learning Guide",
      "download_link": "[https://cfcbazar.42web.io/download](https://cfcbazar.42web.io/download)",
      "status": "delivered",
      "created_by": "cfcbazar.payments@gmail.com",
      "created_at": "2026-08-09 12:22:15",
      "email_downloader": "customer@example.com",
      "delivered_at": "2026-08-09 15:39:45"
    },
    {
      "id": 11,
      "tracking_number": "9345948451",
      "product_name": "Product Title",
      "description": "Item description",
      "download_link": "[https://cfcbazar.42web.io](https://cfcbazar.42web.io)",
      "status": "in_transit",
      "created_by": "cfcbazar.payments@gmail.com",
      "created_at": "2026-08-09 12:25:00",
      "email_downloader": null,
      "delivered_at": null
    }
  ]
}

```

* **Expected Response:** HTTP Status Code `200 OK` on successful write.

---

### 2. Public Read Mirror (GET)

* **URL:** `http://cfcbazar.atwebpages.com/diy/track/index.json`
* **Method:** `GET`
* **Description:** Publicly accessible, real-time mirrored JSON endpoint displaying all tracking items and delivery statuses.

---

## 🔧 Core PHP Functions

### `updateTrackingJson(): bool`

Queries the local MySQL database for all tracking rows, formats the JSON envelope, and pushes it via cURL to `http://cfcbazar.atwebpages.com/diy/track/update.php`.

### `markDownloadDelivered(int $id, string $emailDownloader): bool`

Updates the record status to `'delivered'`, records `$emailDownloader`, sets `delivered_at = NOW()`, and automatically triggers `updateTrackingJson()`.

```php
// Example usage:
markDownloadDelivered(31, 'buyer@example.com');

```

### `getTrackingRecord(string $trackingNumber): ?array`

Retrieves a single tracking record array by its numeric tracking string.

```php
// Example usage:
$record = getTrackingRecord('9951633946');

```

---

## 🔒 Security & Best Practices

* **Prepared Statements:** Database queries utilize MySQLi prepared statements (`prepare()` / `bind_param()`) to protect against SQL injection.
* **XSS Protection:** Output variables are sanitized using HTML escaping helper functions prior to DOM rendering.
* **API Access Control:** Remote synchronization endpoints enforce verification via custom HTTP header API keys (`X-API-KEY`).

---

## 📄 License

This repository is maintained for **CfCbazar Digital Products**. All rights reserved.

```

```

# 📦 Delivery Status API

A lightweight, read-only JSON API for checking real-time delivery statuses from the **CfCbazar DIY Tracking System**.

---

## 🌐 Base Endpoint

```http
GET [http://cfcbazar.atwebpages.com/diy/track/index.json](http://cfcbazar.atwebpages.com/diy/track/index.json)

```

* **Method:** `GET`
* **Content-Type:** `application/json`
* **Authentication:** None (Public Read-Only)

---

## 📥 Response Format

A successful request returns a JSON object containing delivery details and current tracking statuses.

### Example JSON Response

```json
{
  "status": "success",
  "data": [
    {
      "tracking_id": "CFC123456789",
      "carrier": "Post / Courier",
      "status": "In Transit",
      "origin": "Bulgaria",
      "destination": "United Kingdom",
      "last_update": "2026-08-10 10:30:00",
      "estimated_delivery": "2026-08-15"
    }
  ]
}

```

---

## 📊 Field Descriptions

| Field | Type | Description |
| --- | --- | --- |
| `status` | `string` | API response status (`success` or `error`) |
| `data` | `array` | List of active tracking records |
| `tracking_id` | `string` | Unique package tracking code |
| `carrier` | `string` | Postal or courier service provider |
| `status` | `string` | Current delivery stage (e.g., *Pending*, *In Transit*, *Delivered*) |
| `last_update` | `string` | Timestamp of the latest status update (`YYYY-MM-DD HH:MM:SS`) |

---

## 💻 Code Examples

### 1. cURL (Terminal)

```bash
curl -X GET [http://cfcbazar.atwebpages.com/diy/track/index.json](http://cfcbazar.atwebpages.com/diy/track/index.json)

```

### 2. JavaScript (`fetch`)

```javascript
fetch('[http://cfcbazar.atwebpages.com/diy/track/index.json](http://cfcbazar.atwebpages.com/diy/track/index.json)')
  .then(response => response.json())
  .then(data => {
    console.log('Delivery Statuses:', data);
  })
  .catch(error => console.error('Error fetching status:', error));

```

### 3. PHP

```php
<?php
$apiUrl = '[http://cfcbazar.atwebpages.com/diy/track/index.json](http://cfcbazar.atwebpages.com/diy/track/index.json)';
$response = file_get_contents($apiUrl);

if ($response !== false) {
    $trackingData = json_decode($response, true);
    print_r($trackingData);
}
?>

```

---

## 🔒 Rate Limits & Usage Notes

* This endpoint is strictly **read-only**.
* Cache responses locally where possible to minimize bandwidth.

```

# 📦 Delivery Status API

A lightweight, read-only JSON API for checking real-time delivery statuses from the **CfCbazar DIY Tracking System**.
https://cfcbazar.42web.io/diy/track/

---

## 🌐 Base Endpoint

```http
GET [http://cfcbazar.atwebpages.com/diy/track/index.json](http://cfcbazar.atwebpages.com/diy/track/index.json)

Simple read the endpoint, expected output format:
```
{
    "status": "success",
    "last_updated": "2026-08-09 15:00:00",
    "total_tracking": 23,
    "tracking": [
        {
            "id": 10,
            "tracking_number": "1234768032",
            "product_name": "TEST7",
            "description": "Test7",
            "download_link": "https://cfcbazar.42web.io",
            "status": "delivered",
            "created_by": "cfcbazar.payments@gmail.com",
            "created_at": "2026-02-01 13:52:11",
            "email_downloader": "cfcbazar.payments@gmail.com",
            "delivered_at": null
        }
    ]
}
```

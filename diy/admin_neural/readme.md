**Base:** `https://cfcbazar.8bit.ca/diy/admin_neural/api.php`

**Endpoints:**

- **GET** `?action=ping` — health
- **GET** `?action=stats` — training stats
- **POST** `?action=predict`  
  `{"input":[16 ints -128..127]}`
- **POST** `?action=learn`  
  `{"input":[...],"output":[...]}` or `{"pairs":[{input,output},...],"epochs":10}`
- **POST** `?action=retrain`  
  `{"epochs":20}`
- **POST** `?action=reset`  
  `{"wipe_data":true}` (optional)

**Example predict:**
```bash
curl -X POST "https://cfcbazar.8bit.ca/diy/admin_neural/api.php?action=predict" \
  -H "Content-Type: application/json" \
  -d '{"input":[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16]}'
```
Output:
{
    "ok": true,
    "input": [
        1,
        2,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        10,
        11,
        12,
        13,
        14,
        15,
        16
    ],
    "predicted": [
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0
    ]

Retrain must be post not get:
```bash
curl -X POST "https://cfcbazar.8bit.ca/diy/admin_neural/api.php?action=retrain" \
  -H "Content-Type: application/json" \
  -d '{"epochs":20}'
```

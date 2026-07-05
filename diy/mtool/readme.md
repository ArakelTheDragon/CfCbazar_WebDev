# CfCbazar Message Tool (mtool)

A lightweight, single‑file PHP tool for storing and retrieving short messages.  
Built for CfCbazar and deployed at:  
**https://cfcbazar.42web.io/diy/mtool/index.php**

This tool allows users to save a message (up to 32 characters) and receive a unique 6‑digit reference number.  
Messages automatically expire after **1 hour**.

---

## ✨ Features

- Store short messages (1–32 characters)
- Auto‑generated 6‑digit reference code
- Messages expire after 60 minutes
- Clean CfCbazar UI using global `/css/styles.css`
- Mobile‑friendly layout
- Copy buttons for message and reference
- Live character counter
- Safe JSON storage (`messages.json`)
- Automatic cleanup of expired entries
- Legal disclaimer included

---

## 📁 File Structure

/diy/mtool/
├── index.php        # Main tool (frontend + backend)
└── messages.json    # Auto‑generated storage file

---

## ⚙️ How It Works

### 1. Store a Message
- Enter a message (max 32 chars)
- Tool generates a **6‑digit reference**
- Message is saved in `messages.json`
- Expires in **1 hour**

### 2. Retrieve a Message
- Enter the 6‑digit reference
- Tool returns the stored message + remaining time

---

## 🔒 Data & Security

- All content is **user‑generated**
- No guarantee of privacy or long‑term storage
- Messages may be deleted at any time
- Provided “as is” without warranties

---

## 🌐 Live Demo

Try it here:  
**https://cfcbazar.42web.io/diy/mtool/index.php**

---

## 📜 License

This project is part of the CfCbazar ecosystem.  
Free to use, modify, and extend.

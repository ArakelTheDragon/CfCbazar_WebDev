# Ping & Latency Monitor

A lightweight browser-based network monitoring tool that tracks latency over time with an interactive chart and historical statistics.

**Live Demo:** https://cfcbazar.42web.io/diy/pinglatency/

## Features

* 📈 Live latency chart powered by Chart.js
* ⏱ Multiple time ranges (5 minutes to 1 month)
* 📊 Average, minimum, and maximum latency statistics
* 📦 Packet loss tracking
* 💾 Local history stored in the browser
* 🌐 Tests multiple public DNS servers
* 🖥 Responsive interface with no backend required

## Technologies

* PHP
* JavaScript (ES6)
* HTML5 & CSS3
* Chart.js

## Notes

This tool estimates latency using browser HTTP requests. Due to modern browser security restrictions, it cannot perform true ICMP ping tests and results may differ from command-line ping utilities.

## License

This project is part of the CfCbazar DIY Tools collection.

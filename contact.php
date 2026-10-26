<?php
// /contact.php — CfCbazar Contact Page

require_once __DIR__ . '/includes/reusable.php';

$title = "Contact CfCbazar";
include_header();
include_menu();
showAdvertPopup();
render_top_userbar();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $title ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="/css/styles.css">
<script src="/js/scripts.js"></script>

<style>
.contact-card {
    max-width: 650px;
    margin: auto;
}
#contact-status {
    margin-top: 15px;
    padding: 12px;
    border-radius: 8px;
    display: none;
}
#contact-status.success {
    background: #d4f8d4;
    color: #0a7a0a;
}
#contact-status.error {
    background: #ffd6d6;
    color: #a30000;
}
</style>

</head>
<body>

<div class="container">

    <div class="card contact-card">
        <h1>Contact CfCbazar</h1>
        <p>If you have questions, feedback, or need support — send us a message below.</p>

        <form id="contactForm">
            <div class="input-group">
                <label>Your Email</label>
                <input type="email" name="email" id="email" required placeholder="you@example.com">
            </div>

            <div class="input-group">
                <label>Subject</label>
                <input type="text" name="subject" id="subject" required placeholder="What is this about?" maxlength="150">
            </div>

            <div class="input-group">
                <label>Your Message</label>
                <textarea name="message" id="message" required placeholder="Write your message here..." rows="5"></textarea>
            </div>

            <div class="input-group">
                <button type="submit" class="btn">Send Message</button>
            </div>
        </form>

        <div id="contact-status"></div>
    </div>

</div>

<script>
// AJAX contact form
document.getElementById("contactForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const userEmail = document.getElementById("email").value.trim();
    const subject   = document.getElementById("subject").value.trim();
    const message   = document.getElementById("message").value.trim();
    const statusBox = document.getElementById("contact-status");

    statusBox.style.display = "none";

    // Always send to admin
    const email = "cfcbazar@gmail.com";

    // Generate support ticket number
    const ticket = Math.floor(100000 + Math.random() * 900000);

    // Current date/time
    const now = new Date();
    const timestamp = now.toLocaleString();

    // Build formatted message for mail.php
    const formattedMessage =
        "Support Request #" + ticket + "\n" +
        "Date: " + timestamp + "\n\n" +
        "From: " + userEmail + "\n" +
        "Subject: " + subject + "\n\n" +
        "Message:\n" + message + "\n";

    // Prefix the ticket so the admin can find the request later.
    const mailSubject = "[#" + ticket + "] " + subject;

    fetch("/mail.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            email:   email,
            subject: mailSubject,
            heading: "New Support Request",
            intro:   "A new support request has been submitted:",
            message: formattedMessage,
            footer_note: "Sent from the CfCbazar contact form."
        })
    })
    .then(res => res.json())
    .then(data => {
        statusBox.style.display = "block";

        if (data.success) {
            statusBox.className = "success";
            statusBox.textContent = "Your message has been sent. Support Ticket #" + ticket;
            document.getElementById("contactForm").reset();
        } else {
            statusBox.className = "error";
            statusBox.textContent = data.error || "Something went wrong.";
        }
    })
    .catch(() => {
        statusBox.style.display = "block";
        statusBox.className = "error";
        statusBox.textContent = "Network error. Please try again.";
    });
});
</script>

<?php include_footer(); ?>
</body>
</html>

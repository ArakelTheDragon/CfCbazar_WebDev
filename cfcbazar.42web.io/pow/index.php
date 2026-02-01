<?php
// No login required for this page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CfCbazar Proof Of Work Mining & Earning WorkTokens</title>
    <meta name="description" content="Learn how to mine and earn WorkTokens with CfCbazar using Proof of Work methods and online tasks. Step-by-step guides, tutorials, and tips to maximize your token earnings.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Open Graph -->
    <meta property="og:title" content="CfCbazar Proof Of Work Mining & Earning WorkTokens">
    <meta property="og:description" content="Guides on mining and earning WorkTokens via Proof of Work and completing online tasks.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://cfcbazar.ct.ws/proof-of-work/">
    <meta property="og:image" content="https://cfcbazar.ct.ws/images/worktoken-mining.jpg">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="CfCbazar Proof Of Work Mining & Earning WorkTokens">
    <meta name="twitter:description" content="Learn how to mine and earn WorkTokens through Proof of Work methods and online tasks.">
    <meta name="twitter:image" content="https://cfcbazar.ct.ws/images/worktoken-mining.jpg">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f7f9fc;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }

        p.intro {
            max-width: 800px;
            margin: 0 auto 20px auto;
            font-size: 1.1em;
            color: #444;
            text-align: center;
        }

        #search-box {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin: 20px auto;
            display: block;
            border: 1px solid #ccc;
            border-radius: 8px;
            max-width: 600px;
        }

        #articles {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        article.card {
            background: white;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        article.card:hover {
            transform: scale(1.02);
        }

        article.card h3 {
            margin-top: 0;
            color: #0077cc;
            font-size: 1.2em;
        }

        article.card p {
            color: #555;
            font-size: 0.95em;
        }

        article.card a {
            display: inline-block;
            margin-top: 8px;
            color: #0077cc;
            text-decoration: none;
            font-weight: bold;
        }

        article.card a:hover {
            text-decoration: underline;
        }

        #status {
            text-align: center;
            font-weight: bold;
            margin-top: 10px;
            color: #555;
        }
    </style>
</head>
<body>

    <h1>📘 CfCbazar Proof Of Work Center</h1>
    <p class="intro">
        Welcome to the <strong>CfCbazar Proof Of Work Center</strong> — your hub for learning how to 
        <em>mine WorkTokens</em> using proof-of-work algorithms or by completing tasks online. 
        Browse our guides to start earning digital rewards securely and efficiently.
    </p>

    <input type="text" id="search-box" placeholder="Search mining guides, tips, and tutorials..." />
    <div id="status"></div>
    <div id="articles"></div>

    <script>
        let articles = [];

        function displayArticles(list) {
            const container = document.getElementById('articles');
            container.innerHTML = '';
            if (list.length === 0) {
                document.getElementById('status').textContent = 'No articles found.';
                return;
            }

            document.getElementById('status').textContent = `${list.length} article(s) found.`;

            list.forEach(article => {
                const card = document.createElement('article');
                card.className = 'card';
                card.innerHTML = `
                    <h3>${article.title}</h3>
                    <p>${article.content}</p>
                    <a href="${article.link}" title="Read full guide on ${article.title}">Read the full guide →</a>
                `;
                container.appendChild(card);
            });
        }

        function searchArticles(term) {
            const query = term.toLowerCase();
            return articles.filter(article =>
                article.title.toLowerCase().includes(query) ||
                article.content.toLowerCase().includes(query)
            );
        }

        // Load articles from JSON
        fetch('articles.json')
            .then(response => {
                if (!response.ok) throw new Error("Failed to load articles.");
                return response.json();
            })
            .then(data => {
                articles = data;
                displayArticles(articles);
            })
            .catch(error => {
                document.getElementById('status').textContent = error.message;
            });

        // Search input debounce
        const searchBox = document.getElementById('search-box');
        let debounceTimeout;
        searchBox.addEventListener('input', () => {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const term = searchBox.value.trim();
                const results = searchArticles(term);
                displayArticles(results);
            }, 300);
        });
    </script>
</body>
</html>
<?php
// ============================================================================
// CfCbazar — Complete AI Libraries Central Autoloader
// File: /includes/libs_loader.php
// ============================================================================

// 1. Load Text Stemmer library files from the stemmer directory
$stemmerDir = __DIR__ . '/libs/stemmer/';
if (is_dir($stemmerDir)) {
    foreach (glob($stemmerDir . '*.php') as $file) {
        require_once $file;
    }
}

// 2. Load Markov Chain Generator[cite: 1]
$markovPath = __DIR__ . '/libs/markov/Markov.php';
if (file_exists($markovPath)) {
    require_once $markovPath;
}

// 3. Load PHP-ML Machine Learning Autoloader[cite: 1]
$phpmlPath = __DIR__ . '/libs/phpml/autoload.php';
if (file_exists($phpmlPath)) {
    require_once $phpmlPath;
}

// 4. Load SimpleBayes Classification[cite: 1]
$bayesPath = __DIR__ . '/libs/simplebayes/SimpleBayes.php';
if (file_exists($bayesPath)) {
    require_once $bayesPath;
}

// 5. Load TextRank Summarization Engine[cite: 1]
$textrankPath = __DIR__ . '/libs/textrank/TextRank.php';
if (file_exists($textrankPath)) {
    require_once $textrankPath;
}

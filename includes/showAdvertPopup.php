<?php
/**
 * CfCbazar UI & Advertisement Helper Library
 * File: /includes/showAdvertPopup.php
 *
 * Renders a delayed UI popup container with HTML and JavaScript encouraging 
 * users to report errors or request features via the contact page.
 */

declare(strict_types=1);

if (!function_exists('showAdvertPopup')) {
    /**
     * Renders an advertisement/feedback popup widget with a timed delay.
     *
     * @return void
     */
    function showAdvertPopup(): void
    {
        $linkUrl = '/contact.php';
        $linkText = 'Click here to report en error/feature!';
        $delay = 3000;

        echo <<<HTML
<div id="advertPopup" style="display:none; position:fixed; bottom:20px; right:20px; width:300px; background:#fff; border:1px solid #ccc; box-shadow:0 0 10px rgba(0,0,0,0.3); padding:15px; z-index:9999;">
    <span style="float:right; cursor:pointer;" onclick="document.getElementById('advertPopup').style.display='none';">✖</span>
    <strong>🔥 Contact:</strong><br>
    <a href="{$linkUrl}" target="_blank" style="color:#0077cc; text-decoration:underline;">
        {$linkText}
    </a>
</div>
<script>
    setTimeout(function() {
        if (document.getElementById('advertPopup')) document.getElementById('advertPopup').style.display = 'block';
    }, {$delay});
</script>
HTML;
    }
}

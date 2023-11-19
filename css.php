<?php
// Set the content type to CSS
header("Content-type: text/css");

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Path to your CSS file
$cssFilePath = 'webcam.css';

// Check if the file exists
if (file_exists($cssFilePath)) {
    // Read the contents of the CSS file
    $cssContent = file_get_contents($cssFilePath);

    // Echo the CSS content
    echo $cssContent;
} else {
    // If the CSS file doesn't exist, you can provide a default or handle the error accordingly
    echo '/* Error: CSS file not found */';
}
?>

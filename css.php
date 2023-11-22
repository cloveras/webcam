<?php
// Set the content type to CSS
header("Content-type: text/css");

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// The CSS file
$css = 'webcam.css';

if (file_exists($css)) {
    $cssContent = file_get_contents($css);
    echo $cssContent;
} else {
    echo "/* Error: CSS file \"$css\" not found */";
}
?>

<?php
// Set the content type to CSS
header("Content-type: text/css");

// Enable caching for CSS (1 day)
header("Cache-Control: public, max-age=86400");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 86400) . " GMT");

// The CSS file
$css = 'webcam.css';

if (file_exists($css)) {
    $cssContent = file_get_contents($css);
    echo $cssContent;
} else {
    echo "/* Error: CSS file \"$css\" not found */";
}
?>

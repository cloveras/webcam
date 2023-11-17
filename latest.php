<?php

// A script to send the latest image to the client with no caching.

// The image (full path is ok too).
$image = 'latest.jpg';

// Set content type header
header('Content-Type: image/jpeg');

// Set cache control headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Set content length header
header('Content-Length: ' . filesize($image));

// Set Last-Modified header
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($image)) . ' GMT');

// Set ETag header
header('ETag: ' . md5_file($image));

// Output the image data
readfile($image);

?>
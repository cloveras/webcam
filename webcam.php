<?php
/**
 * webcam.php - Webcam Image Gallery Generator
 * 
 * Generates HTML pages for webcam images stored in YYYY/MM/DD/YYYYMMDDHHMMSS.jpg structure.
 * Handles sunrise/sunset calculations, midnight sun, polar night, and navigation.
 * 
 * Code: https://github.com/cloveras/webcam
 * Example: https://lilleviklofoten.no/webcam/
 * 
 * @author https://github.com/cloveras/webcam contributors
 * @license Apache-2.0
 */

// Include required classes
require_once 'WebcamConfig.php';
require_once 'SunCalculator.php';
require_once 'ImageFileManager.php';
require_once 'NavigationHelper.php';

// ============================================================
// Functions
// ============================================================

/**
 * Generate HTML page header with title, meta tags, and JavaScript navigation
 * 
 * @param string $title Page title
 * @param string|false $previous URL for previous navigation (false if none)
 * @param string|false $next URL for next navigation (false if none)
 * @param string|false $up URL for up navigation (false if none)
 * @param string|false $down URL for down navigation (false if none)
 */
function page_header($title, $previous, $next, $up, $down)
{

    print <<<END1
<!DOCTYPE html>
<html lang="en-US">
<head>
  <meta charset="utf-8">

  <meta name="description" content="Lofoten webcam with view towards west from Vik, Gimsøy, Lofoten, Norway.">
  <meta name="keywords" content="lofoten,webcam,webcamera,webkamera,web cam, webcam,vik,gimsøy,lofoten islands,nordland,norway">
  <meta name="robot" content="index">
  <meta name="generator" content="webcam.php: https://github.com/cloveras/webcam">

  <meta property="og:title" content="Lillevik Lofoten Webcam">

  <link rel="icon" href="/wp-content/uploads/2020/08/cropped-lillevik-drone-001-20200613-0921-21-2-scaled-2-32x32.jpg" sizes="32x32">
  <link rel="icon" href="/wp-content/uploads/2020/08/cropped-lillevik-drone-001-20200613-0921-21-2-scaled-2-192x192.jpg" sizes="192x192">
  <link rel="apple-touch-icon" href="/wp-content/uploads/2020/08/cropped-lillevik-drone-001-20200613-0921-21-2-scaled-2-180x180.jpg">

  <link rel="canonical" href="https://lilleviklofoten.no/webcam/">

  <link rel="stylesheet" type="text/css" href="css.php">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "@id": "https://lilleviklofoten.no/webcam/#webpage",
    "url": "https://lilleviklofoten.no/webcam/",
    "name": "Live Webcam – Lillevik Lofoten, Gimsøysand, Norway",
    "description": "Webcam from Lillevik Lofoten on Gimsøy in Lofoten, Norway. See the midnight sun in summer and northern lights in winter. Image updates every 10 minutes.",
    "inLanguage": "en",
    "publisher": {
      "@type": "Organization",
      "@id": "https://lilleviklofoten.no/#organization",
      "name": "Lillevik Lofoten",
      "url": "https://lilleviklofoten.no/",
      "email": "post@lofotenvacation.no",
      "telephone": "+4741130944",
      "logo": "https://lilleviklofoten.no/logo/lillevik-logo-1000.jpg",
      "image": "https://lilleviklofoten.no/logo/lillevik-logo-1000.jpg",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Årstrandveien 663",
        "postalCode": "8314",
        "addressLocality": "Gimsøysand",
        "addressRegion": "Nordland",
        "addressCountry": "NO"
      },
        "sameAs": [
            "https://facebook.com/lilleviklofoten",
            "https://instagram.com/lilleviklofoten",
            "https://www.tiktok.com/@lilleviklofoten",
            "https://www.reddit.com/user/Lillevik_Lofoten/",
            "https://bsky.app/profile/lilleviklofoten.bsky.social",
            "https://www.booking.com/hotel/no/lillevik-lofoten.html",
            "https://www.airbnb.com/rooms/44385543",
            "https://lofotenvacation.com/en/lillevik-lofoten",
            "https://maps.app.goo.gl/nKPJn2wFm5uWBZTg7",
            "https://maps.apple.com/?ll=68.330081,14.091728&q=Lillevik%20Lofoten"
        ]
    },
    "primaryImageOfPage": {
      "@type": "ImageObject",
      "@id": "https://lilleviklofoten.no/webcam/latest.jpg",
      "contentUrl": "https://lilleviklofoten.no/webcam/latest.jpg",
      "url": "https://lilleviklofoten.no/webcam/latest.jpg",
      "caption": "Lillevik Lofoten live webcam - Gimsøy, Lofoten, Norway",
      "width": 2560,
      "height": 1920,
      "license": "https://lilleviklofoten.no/"
    },
    "image": "https://lilleviklofoten.no/webcam/latest.jpg"
  }
  </script>

END1;

    // Set some web variables for command-line use.
    if (!$_SERVER['SERVER_NAME']) {
        $_SERVER['SERVER_NAME'] = "lilleviklofoten.no";
        $_SERVER['SCRIPT_NAME'] = "webcam.php";
    }
    if ($previous) {
        echo "  <link rel=\"prefetch\" title=\"Previous\" href=\"http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . "$previous\">\n";
    }
    if ($next) {
        echo "  <link rel=\"prefetch\" title=\"Next\" href=\"http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . "$next\">\n";
    }

    print <<<END2
  <title>$title</title>
END2;

    // Javascript for navigation using arrow keys. Only print the ones that do something.
    if ($previous || $next || $up || $down) {
        echo "\n\n  <!-- Javascript for navigation using arrow keys. -->\n";
        echo "  <script>\n";

        function printArrowScript($keyCode, $url)
        {
            echo "    function {$keyCode}ArrowPressed() { window.location.href=\"$url\"; }\n";
        }

        if ($previous)
            printArrowScript("left", $previous);
        if ($next)
            printArrowScript("right", $next);
        if ($up)
            printArrowScript("up", $up);
        if ($down)
            printArrowScript("down", $down);

        echo "    document.onkeydown = function(evt) {\n";
        echo "      evt = evt || window.event;\n";
        echo "      switch (evt.keyCode) {\n";
        if ($previous)
            echo "        case 37: leftArrowPressed(); break;\n";
        if ($up)
            echo "        case 38: upArrowPressed(); break;\n";
        if ($next)
            echo "        case 39: rightArrowPressed(); break;\n";
        if ($down)
            echo "        case 40: downArrowPressed(); break;\n";
        echo "      }\n";
        echo "    };\n";
        echo "  </script>\n\n";
    }

    // Google Analytics and Microsoft Clarity
    print <<<END3

  <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo WebcamConfig::GOOGLE_ANALYTICS_ID; ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?php echo WebcamConfig::GOOGLE_ANALYTICS_ID; ?>');
    </script>

    <!-- Microsoft Clarity -->
    <script>
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "<?php echo WebcamConfig::MICROSOFT_CLARITY_ID; ?>");
    </script>

</head>
<body>

<h1>$title</h1>

<p>
<a href=".">Webcam</a>
at
<a href="https://lilleviklofoten.no">Lillevik Lofoten</a>,
Vik, Gimsøy, Lofoten, Norway.
See also: <a href="https://lilleviklofoten.no/webcams/">many other webcams in Lofoten</a>.
</p>
END3;
}

/**
 * Output debug message if debugging is enabled
 * 
 * @param string $txt Debug message to output
 */
function debug($txt)
{
    global $debug;
    if ($debug) {
        echo "$txt<br/>\n";
    }
}


/**
 * Generate HTML page footer with navigation and site links
 * 
 * @param int $images_printed Number of images displayed on the page
 * @param string|false $previous URL for previous navigation
 * @param string|false $next URL for next navigation
 * @param string|false $up URL for up navigation
 * @param string|false $down URL for down navigation
 */
function footer($images_printed, $previous, $next, $up, $down)
{

    $touch = true;

    if ($touch) {
        echo <<<TOUCH

<!-- Touch gestures -->
<!-- script src="https://hammerjs.github.io/dist/hammer.min.js"></script -->
<script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var body = document.body;
        var hammer = new Hammer(body);
        hammer.on('swiperight', function() {
            window.location.href = '{$previous}';
        });
        hammer.on('swipeleft', function() {
            window.location.href = '{$next}';
        });
    });
</script>
TOUCH;
    }

    // Navigation links
    echo "\n\n<p>";
    if ($touch) {
        echo "Swipe left/right or use ";
    } else {
        echo "Use ";
    }
    echo 'the arrow keys to navigate: ';
    if ($next) {
        echo "<a href=\"$next\">forward</a> (&rarr;), ";
    }
    if ($previous) {
        echo "<a href=\"$previous\">back</a> (&larr;), ";
    }
    if ($up) {
        echo "<a href=\"$up\">up</a> (&uarr;) ";
    }
    if ($down) {
        echo "and <a href=\"$down\">down</a> (&darr;)\n";
    }
    echo ".</p>\n\n";


    print_lillevik_images_and_links();

    echo "<p style=\"color: rgb(200, 200, 200);\" >Made with <a style=\"color: rgb(200, 200, 200);\" href=\"https://github.com/cloveras/webcam\">webcam.php</a></p>\n\n";

    echo "</body>\n</html>\n";
}

/**
 * Get date/time components from image filename
 * 
 * @deprecated Use ImageFileManager::splitImageFilename() instead
 * @param string $image_filename Filename in YYYYMMDDHHMMSS format
 * @return array [year, month, day, hour, minute, seconds]
 */
function split_image_filename($image_filename)
{
    global $imageManager;
    if ($imageManager) {
        return $imageManager->splitImageFilename($image_filename);
    }
    // Fallback for backwards compatibility
    $year = substr($image_filename, 0, 4);
    $month = substr($image_filename, 4, 2);
    $day = substr($image_filename, 6, 2);
    $hour = substr($image_filename, 8, 2);
    $minute = substr($image_filename, 10, 2);
    $seconds = substr($image_filename, 12, 2);
    return array($year, $month, $day, $hour, $minute, $seconds);
}

/**
 * Check if it's midnight sun period
 * 
 * @deprecated Use SunCalculator::isMidnightSun() instead
 * @param int $timestamp Unix timestamp
 * @param float $latitude Latitude (unused, kept for compatibility)
 * @param float $longitude Longitude (unused, kept for compatibility)
 * @return bool True if midnight sun period
 */
function midnight_sun($timestamp, $latitude, $longitude)
{
    global $sunCalculator;
    return $sunCalculator ? $sunCalculator->isMidnightSun($timestamp) : false;
}

/**
 * Check if it's polar night period
 * 
 * @deprecated Use SunCalculator::isPolarNight() instead
 * @param int $timestamp Unix timestamp
 * @param float $latitude Latitude (unused, kept for compatibility)
 * @param float $longitude Longitude (unused, kept for compatibility)
 * @return bool True if polar night period
 */
function polar_night($timestamp, $latitude, $longitude)
{
    global $sunCalculator;
    return $sunCalculator ? $sunCalculator->isPolarNight($timestamp) : false;
}

/**
 * Find sunrise, sunset, dawn and dusk times for a given timestamp
 * Handles midnight sun and polar night periods
 * 
 * @deprecated Use SunCalculator::findSunTimes() instead
 * @param int $timestamp Unix timestamp
 * @return array [sunrise, sunset, dawn, dusk, midnight_sun, polar_night]
 */
function find_sun_times($timestamp)
{
    global $sunCalculator;
    return $sunCalculator ? $sunCalculator->findSunTimes($timestamp) : [0, 0, 0, 0, false, false];
}

/**
 * Print all images for one full month
 * 
 * @param string|int $year
 * @param string|int $month
 */
function print_full_month($year, $month)
{
    debug("<br/>print_full_month($year, $month)");
    global $size;
    global $monthly_day;
    global $monthly_hour;
    global $large_image_width;
    global $large_image_height;
    global $mini_image_width;
    global $mini_image_height;

    // Find previous and next month, and create the links to them.
    list($year_previous, $month_previous, $year_next, $month_next) = find_previous_and_next_month($year, $month);
    $previous = "?type=month&year=$year_previous&month=$month_previous&size=$size"; // Previous month.
    $next = "?type=month&year=$year_next&month=$month_next&size=$size"; // Next month.
    $up = "?type=year&year=$year"; // Up: SHow the full year.

    // "Down" goes to the first day n this month that has images.
    $first_day_with_images = find_first_day_with_images($year, $month);
    if ($first_day_with_images) {
        $down = "?type=day&date=" . find_first_day_with_images($year, $month);
    } else {
        $down = false;
    }

    // Make timestamp for this month.
    $minute = 0;
    $second = 0;
    $timestamp = mktime($monthly_hour, 0, 0, $month, $monthly_day, $year); // Using the $monthly_day as average.
    $title = "Lillevik Lofoten webcam: " . date("F Y", $timestamp) . " (ca. $monthly_hour:00 each day)";
    page_header($title, $previous, $next, $up, $down);

    list($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night) = find_sun_times($timestamp);
    print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, "average");
    print_yesterday_tomorrow_links($timestamp, true);

    // CSS overlay
    echo "<div class=\"grid-container\">";

    $images_printed = 0;
    for ($i = 1; $i <= 31; $i += 1) { // Works for February and 30-day months too.
        $now = mktime($hour, $minute, $second, $month, $i, $year);
        $i = sprintf("%02d", $i); // Need to pad the days with 0 first. Still works fine in for() above.

        // Get all *jpg images that start with the right year, month, day and hour.
        $directory = $year . "/" . $month . "/" . $i;
        if (file_exists($directory)) {
            debug("Directory exists: $directory");
            // Getting the latest image in that directory for that hour (monthly hour = the fixed hour we look for).
            $image = get_latest_image_in_directory_by_date_hour($directory, $monthly_hour);
            if ($image) {
                debug("Image found: $image");
                // There was at least one image: 2023/11/14/20231114154051.jpg
                $yyyymmddhhmmss = get_yyyymmddhhmmss($image);
                debug("Image datepart: $yyyymmddhhmmss<br/>");
                list($year, $month, $day, $hour, $minute, $seconds) = split_image_filename($yyyymmddhhmmss);
                // Print mini images, link to all images for that day.

                // CSS overlay   
                echo "<div class=\"grid-item\">";

                echo "<a href=\"?type=day&date=$year$month$day\">";
                echo "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" ";
                //echo "title=\"$year-$month-$day $hour:$minute\" ";
                echo "src=\"$year/$month/$day/";
                if ($size == "mini" || empty($size)) {
                    // Mini. If the mini version has been created: Use that. If not: Scale down the large version.
                    if (file_exists("$year/$month/$day/mini/$yyyymmddhhmmss.jpg")) {
                        echo "mini/";
                    }
                    echo "$yyyymmddhhmmss.jpg\" width=\"$mini_image_width\" height=\"$mini_image_height\" ";
                } else {
                    // Large.
                    echo "$yyyymmddhhmmss.jpg\" width=\"$large_image_width\" height=\"$large_image_height\" ";
                }
                echo "></a>\n";

                // CSS overlay 
                if ($size == "mini" || empty($size)) {
                    echo "<span class=\"time\">$day</span>";
                }
                echo "</div>";

                $images_printed += 1; // Count the image just printed.
            }
        } else {
            debug("Directory does not exist: $directory");
        }
    }


    // CSS overlay
    echo "</div>\n";
    if ($images_printed == 0) {
        // No pictures found for this month.
        echo "<p>(No photos to display for " . date("Y-m-d", $timestamp) . ")</p>\n";
    }
    footer($images_printed, $previous, $next, $up, $down);
}

/**
 * Print images for a whole year
 * 
 * @param string|int $year
 */
function print_full_year($year)
{
    debug("<br/>print_full_year($year)");
    global $monthly_hour;
    global $mini_image_width;
    global $mini_image_height;

    // Find previous and next year, and create the links to them.
    $previous = $next = $up = $down = false;
    $previous = "?type=year&year=" . ($year - 1);
    if ($year < date('Y')) {
        $next = "?type=year&year=" . ($year + 1); // Next year only if it exists.
    }

    // Find the first day in the month, and use that for the down link.
    $first_day_with_images = "";
    for ($month = 1; $month <= 12; $month++) {
        $month = sprintf("%02d", $month);
        $first_day_with_images = find_first_day_with_images($year, $month);
        if ($first_day_with_images) {
            // We found a month (and also a day, which we don't need now).
            $down = "?type=month&year=$year&month=$month";
            break;
        }
    }

    page_header("Lillevik Lofoten webcam: $year (ca. $monthly_hour:00 each day)", $previous, $next, $up, $down);
    print_previous_next_year_links($year);

    // Links to all months 1-12: Commas and "and" for the last one.
    echo "\n<p>Months: \n";
    $monthLinks = [];
    for ($i = 1; $i <= 12; $i++) {
        $monthLinks[] = "<a href=\"?type=month&year=$year&month=" . sprintf("%02d", $i) . "\">" . sprintf("%02d", $i) . "</a>";
    }
    $formattedMonthLinks = implode(', ', array_slice($monthLinks, 0, -1)) . ' and ' . end($monthLinks);
    print $formattedMonthLinks . ".\n";

    // Link to today.
    echo "<a href=\"?type=day&date=" . date('Ymd') . "\">Today: " . date("M d") . "</a>.\n";
    echo "<a href=\"?type=last\">Latest image</a>.\n";
    echo "</p>\n\n";

    // Loop through all months 1-12 (again) and print images for the $days if they exist.

    // CSS overlay
    echo "<div class=\"grid-container\">";

    $days = range(1, 31);
    $images_printed = 0;
    $yyyymmddhhmmss = "";
    $image_filename = "";
    for ($month = 1; $month <= 12; $month++) {
        $month = sprintf("%02d", $month);
        // Check for each of the days in the $days array
        foreach ($days as $day) {
            $day = sprintf("%02d", $day);
            // Find first image for that day taken after $monthly_hour
            debug("monthly_hour: $monthly_hour");
            debug("find_first_image_after_time($year, $month, $day, $monthly_hour, 0, 0);");
            $yyyymmddhhmmss = find_first_image_after_time($year, $month, $day, $monthly_hour, 0, 0);
            if ($yyyymmddhhmmss) {
                // There was an image.
                $hour = substr($yyyymmddhhmmss, 8, 2);
                $minute = substr($yyyymmddhhmmss, 10, 2);
                $image_filename = $year . "/" . $month . "/" . $day . "/" . $yyyymmddhhmmss . ".jpg";
                debug($year . "/" . $month . "/" . $day . "/" . $yyyymmddhhmmss . ".jpg");
                // Print mini images (never large images for full years), link to all images for that day.

                // CSS overlay   
                echo "<div class=\"grid-item\">";

                echo "<a href=\"?type=one&image=$yyyymmddhhmmss\">";
                echo "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" ";
                //echo "title=\"$year-$month-$day $hour:$minute\" ";
                echo "width=\"$mini_image_width\" height=\"$mini_image_height\" ";
                echo "src=\"$year/$month/$day/";
                // If the mini version has been created: Use that. If not: Scale down the full version.
                if (file_exists("$year/$month/$day/mini/$yyyymmddhhmmss.jpg")) {
                    echo "mini/";
                }
                echo "$yyyymmddhhmmss.jpg\"></a>\n";

                // CSS overlay    
                if ($size == "mini" || empty($size)) {
                    echo "<span class=\"time\">$year-$month-$day</span>";
                }
                //echo "<span class=\"time\">$year-$month-$day</span>";
                echo "</div>";

                $images_printed += 1;
            }
        }
    }
    // CSS overlay
    echo "</div>\n";

    if ($images_printed > 0) {
        echo "</p>\n";
    } else {
        // No pictures found for this year.
        echo "<p>(No photos to display for " . date("Y", mktime(12, 0, 0, 1, 1, $year)) . ")</p>\n";
    }
    footer($images_printed, $previous, $next, $up, $down);
}

/**
 * Print images for all years
 */
function print_all_years()
{
    debug("<br/>print_all_years()");
    global $monthly_day;
    global $monthly_hour;
    global $mini_image_width;
    global $mini_image_height;

    $start_year = WebcamConfig::START_YEAR;
    $this_year = date('Y');
    $monthly_days = [1, 7, 14, 21, 28]; // Only show these days in each month.

    $previous = $next = $up = $down = false;
    page_header("Lillevik Lofoten webcam: $start_year" . "-" . "$this_year", $previous, $next, $up, $down);
    echo "<p>Displaying images for $monthly_hour:00 on the $monthly_day" . "th for each month for all year.</p>\n";
    echo "<p>\n<a href=\"?type=day&date=" . date('Ymd') . "\">Today: " . date("M d") . "</a> \n";
    echo "<a href=\"?type=last\">Latest image</a>.\n";
    echo "</p>\n\n";

    for ($year = $start_year; $year <= $this_year; $year++) {

        print "\n<h2>$year</h2>\n\n";

        // Loop through all months 1-12 (again) and print images for the $days if they exist.
        $days = range(1, 31);
        $images_printed = 0;
        $yyyymmddhhmmss = "";
        $image_filename = "";

        // Loop through all months for this year.

        // CSS overlay
        echo "<div class=\"grid-container\">";

        for ($month = 1; $month <= 12; $month++) {
            $month = sprintf("%02d", $month);

            // Loop through all $monthly_days for this month.
            foreach ($monthly_days as $monthly_day) {

                // Find first image for the $monthly_day taken after $monthly_hour
                debug("find_first_image_after_time($year, $month, $monthly_day, $monthly_hour, 0, 0);");
                $yyyymmddhhmmss = find_first_image_after_time($year, $month, $monthly_day, $monthly_hour, 0, 0);
                if ($yyyymmddhhmmss) {
                    // There was an image.
                    $hour = substr($yyyymmddhhmmss, 8, 2);
                    $minute = substr($yyyymmddhhmmss, 10, 2);
                    // Print mini images (never large images for full years), link to all images for that day.

                    // CSS overlay   
                    echo "<div class=\"grid-item\">";

                    echo "<a href=\"?type=one&image=$yyyymmddhhmmss\">";
                    echo "<img alt=\"Lillevik Lofoten webcam: $year-$month--$monthly_day $hour:$minute\" ";
                    //echo "title=\"$year-$month-$monthly_day $hour:$minute\" ";
                    echo "width=\"$mini_image_width\" height=\"$mini_image_height\" ";
                    echo "src=\"$year/$month/$monthly_day/";
                    // If the mini version has been created: Use that. If not: Scale down the full version.
                    if (file_exists("$year/$month/$monthly_day/mini/$yyyymmddhhmmss.jpg")) {
                        echo "mini/";
                    }
                    echo "$yyyymmddhhmmss.jpg\"></a>\n";

                    // CSS overlay    
                    if ($size == "mini" || empty($size)) {
                        echo "<span class=\"time\">$year-$month-$day</span>";
                    }
                    echo "</div>";

                    $images_printed += 1;
                }
            }
        }
        // CSS overlay
        echo "</div>\n";

        if ($images_printed == 0) {
            echo "(No photos to display for $year)\n";
        }
        echo "</p>\n\n";

    }
    footer($images_printed, $previous, $next, $up, $down);
}

/**
 * Print links to mini and large image versions
 * 
 * @param int $timestamp Unix timestamp
 * @param string $size Current size setting
 */
function print_mini_large_links($timestamp, $size)
{
    $date = date('Ymd', $timestamp);
    echo "<p>\n";
    if ($size == "large") { // Link to mini if we showed large, or don't know.
        echo "<a href=\"?type=day&date=$date&size=mini\">Mini photos</a>. ";
    }
    if ($size == "mini" || empty($size)) { // Links to large if we showed mini, or don't know.
        echo "<a href=\"?type=day&date=$date&size=large\">Large photos</a>. ";
    }
    echo "</p>\n\n";
}

/**
 * Get date part (YYYYMMDDHHMMSS) from image path
 * 
 * @deprecated Use ImageFileManager::getYYYYMMDDHHMMSS() instead
 * @param string $fullPath Full path to image file
 * @return string Date part in YYYYMMDDHHMMSS format
 */
function get_yyyymmddhhmmss($fullPath)
{
    global $imageManager;
    return $imageManager ? $imageManager->getYYYYMMDDHHMMSS($fullPath) :
        preg_replace("/[^0-9]/", "", pathinfo(basename($fullPath), PATHINFO_FILENAME));
}

/**
 * Find the latest image in today's directory
 * 
 * @deprecated Use ImageFileManager::findLatestImage() instead
 * @return string Date part of filename (YYYYMMDDHHMMSS)
 */
function find_latest_image()
{
    global $imageManager;
    if ($imageManager) {
        return $imageManager->findLatestImage();
    }
    // Fallback for backwards compatibility
    list($year, $month, $day) = explode('-', date('Y-m-d'));
    if (is_dir("$year/$month/$day")) {
        debug("NORMAL: max(glob(\"$year/$month/$day/*.jpg\", GLOB_BRACE))");
        $latest_image = max(glob("$year/$month/$day/*.jpg", GLOB_BRACE));
    } else if (is_dir("$year/$month")) {
        debug("MONTH: max(glob(\"$year/$month/*.jpg\", GLOB_BRACE))");
        $latest_image = max(glob("$year/$month/**/*.jpg", GLOB_BRACE));
    } else if (is_dir("$year")) {
        debug("YEAR: max(glob(\"$year/**/*.jpg\", GLOB_BRACE))");
        $latest_image = max(glob("$year/**/*.jpg", GLOB_BRACE));
    }
    $image = get_yyyymmddhhmmss($latest_image);
    debug("FOUND: image (datepart): $image");
    return $image;
}

/**
 * Find the first day with images for a specific year and month
 * 
 * @deprecated Use ImageFileManager::findFirstDayWithImages() instead
 * @param string $year
 * @param string $month
 * @return string Date in YYYYMMDD format
 */
function find_first_day_with_images($year, $month)
{
    global $imageManager;
    if ($imageManager) {
        return $imageManager->findFirstDayWithImages($year, $month);
    }
    // Fallback
    debug("<br/>find_first_day_with_images($year, $month)");
    $directories = glob("$year/$month/*");
    $directory = !empty($directories) ? basename($directories[0]) : '';
    debug("First day with images: $directory");
    return $directory;
}

/**
 * Find the first year with images
 * 
 * @return int First year
 */
function find_first_year_with_images()
{
    debug("<br/>find_first_year_with_images()");
    return WebcamConfig::START_YEAR;
}

/**
 * Get all images in a directory for a specific day
 * 
 * @deprecated Use ImageFileManager::getAllImagesInDirectory() instead
 * @param string $directory Directory path
 * @return array Array of image file paths
 */
function get_all_images_in_directory($directory)
{
    global $imageManager;
    return $imageManager ? $imageManager->getAllImagesInDirectory($directory) : glob("$directory/*.jpg");
}

/**
 * Get the latest image in a directory for a specific hour
 * 
 * @deprecated Use ImageFileManager::getLatestImageInDirectoryByDateHour() instead
 * @param string $directory Directory path
 * @param int $hour Hour (0-23)
 * @return string Full path to image or empty string
 */
function get_latest_image_in_directory_by_date_hour($directory, $hour)
{
    global $imageManager;
    return $imageManager ? $imageManager->getLatestImageInDirectoryByDateHour($directory, $hour) : '';
}

/**
 * Find the first image after a given time
 * 
 * @deprecated Use ImageFileManager::findFirstImageAfterTime() instead
 * @param string $year
 * @param string $month
 * @param string $day
 * @param int $hour
 * @param int $minute
 * @param int $seconds
 * @return string Date part of filename (YYYYMMDDHHMMSS) or empty string
 */
function find_first_image_after_time($year, $month, $day, $hour, $minute, $seconds)
{
    global $imageManager;
    return $imageManager ?
        $imageManager->findFirstImageAfterTime($year, $month, $day, $hour, $minute, $seconds) : '';
}

/**
 * Print a single webcam image
 * 
 * @param string $image_filename Date part of filename (YYYYMMDDHHMMSS)
 * @param bool $last_image Whether this is the latest image
 */
function print_single_image($image_filename, $last_image)
{
    // $image_filename example: "20231114144049"
    global $large_image_width;
    global $large_image_height;

    // Validate input
    if (empty($image_filename)) {
        page_header("Error", false, false, false, false);
        echo "<p>No image found.</p>\n";
        echo "<p><a href=\".\">Back to webcam</a></p>\n";
        footer(0, false, false, false, false);
        return;
    }

    // Find the date and time for the image.
    debug("split_image_filename($image_filename)");
    list($year, $month, $day, $hour, $minute, $seconds) = split_image_filename($image_filename);

    // Make a timestamp for the image's date and time.
    debug(" mktime($hour, $minute, 0, $month, $day, $year)");
    $timestamp = mktime((int) $hour, (int) $minute, 0, (int) $month, (int) $day, (int) $year);

    // Calculate the sun times for the image's timestamp.
    list($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night) = find_sun_times($timestamp);

    // Get previous and next image: First get all images for the same day as the images passed as parameter.
    $directory = "$year/$month/$day";

    // Loop through all images to find previous and next based on the $image_filename's day's directory.
    $images = get_all_images_in_directory($directory);
    $previous_image = "";
    $next_image = "";
    $number_of_images = count($images); // Avoid count() in every iteration below.
    $i = 0;
    foreach ($images as $image) {
        if (strpos($images[$i], $image_filename) !== false) { // Faster than preg_match().
            // We found a match for $image_filename, now get the previous and next images.
            $image_filename = get_yyyymmddhhmmss($images[$i]) . ".jpg";
            if ($i != 0) {
                // This was not the first image in the array, so we can get the previous one.
                $previous_image = $images[$i - 1];
            }
            if ($i != $number_of_images) {
                // This was not the Latest image in the array, so we can get the next one.
                $next_image = $images[$i + 1];
            }
            break;
        }
        $i += 1;
    }

    // Links to previous, next, up, down.
    debug("previous_image: $previous_image<br/>next_image: $next_image<br/>");
    if ($previous_image) {
        $previous_image_datepart = get_yyyymmddhhmmss($previous_image);
        $previous = "?type=one&image=$previous_image_datepart"; // Only date for the link.
    }
    if ($next_image) {
        $next_image_datepart = get_yyyymmddhhmmss($next_image);
        $next = "?type=one&image=$next_image_datepart"; // Only date for the link.
    }
    $up_image_datepart = get_yyyymmddhhmmss($image_filename);
    $up = "?type=day&date=$up_image_datepart"; // The full day.
    $down = false; // Already showing a single image, not possible to go lower.

    debug("PREV: $previous<br/>NEXT: $next<br/>UP: $up<br/>DOWN: $down<br/>");

    // Print!
    $title = "Lillevik Lofoten webcam";
    if (!$last_image) {
        $title .= ": " . date("Y-m-d H:i", $timestamp);
    }
    page_header($title, $previous, $next, $up, $down);
    print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, false);
    print_full_day_link($timestamp);

    if ($previous_datepart || $next_datepart) {
        echo "<p>";
        if ($previous_datepart) {
            echo "<a href=\"$previous\">Previous: " . substr($previous_datepart, 8, 2) . ":" . substr($previous_datepart, 10, 2) . "</a>.\n";
        }
        if ($next_datepart) {
            echo "<a href=\"$next\">Next: " . substr($next_datepart, 8, 2) . ":" . substr($next_datepart, 10, 2) . "</a>.\n";
        }
        echo "</p>\n\n";
    }

    echo "<p>\n";
    echo "<a href=\"?type=day&date=$year$month$day\">";
    echo "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" ";
    //echo "title=\"$year-$month-$day $hour:$minute\" ";
    echo "width=\"$large_image_width\" height=\"$large_image_height\" ";
    echo "src=\"$year/$month/$day/$image_filename\">";
    echo "</a>\n";
    echo "</p>\n\n";

    list($width, $height) = getimagesize("$year/$month/$day/$image_filename");
    echo "<p>\n<a href=\"$year/$month/$day/$image_filename\">Full size ($width x $height)</a>\n</p>\n\n";

    footer($images_printed, $previous, $next, $up, $down);
}

/**
 * Print details about the sun (sunrise/sunset times) and what images are shown
 * 
 * @param int $sunrise Unix timestamp
 * @param int $sunset Unix timestamp
 * @param int $dawn Unix timestamp
 * @param int $dusk Unix timestamp
 * @param bool $midnight_sun Whether it's midnight sun period
 * @param bool $polar_night Whether it's polar night period
 * @param string|bool $include_interval Whether to include time interval ("day", "average", or false)
 */
function print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, $include_interval)
{
    debug("<br/>print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, $include_interval)");
    global $monthly_day;
    echo "\n\n<p>";
    if ($midnight_sun) {
        echo "Midnight sun &#9728;";
    } else if ($polar_night) {
        echo "Polar night";
    } else {
        echo "Sunrise: " . date('H:i', $sunrise) . ". Sunset: " . date('H:i', $sunset);
    }
    if ($include_interval == "day") {
        echo ". Displaying photos taken between " . date('H:i', $dawn) . " and " . date('H:i', $dusk);
    } else if ($include_interval == "average") {
        echo " (calculated for " . date('F', $dawn) . " $monthly_day)";
        //echo " with the newest images first";
    }
    echo ".</p>\n\n";
}

/**
 * Find previous and next month, handling year boundaries
 * 
 * @deprecated Use NavigationHelper::findPreviousAndNextMonth() instead
 * @param string|int $year
 * @param string|int $month
 * @return array [year_previous, month_previous, year_next, month_next]
 */
function find_previous_and_next_month($year, $month)
{
    global $navHelper;
    return $navHelper ? $navHelper->findPreviousAndNextMonth($year, $month) :
        [sprintf("%04d", $year - 1), '12', sprintf("%04d", $year + 1), '01'];
}

/**
 * Print links to previous and next year
 * 
 * @param string|int $year
 */
function print_previous_next_year_links($year)
{
    echo "<p><a href=\"?type=year&year=" . ($year - 1) . "\">Previous (" . ($year - 1) . ")</a>.\n";
    if ($year < date('Y')) {
        echo "<a href=\"?type=year&year=" . ($year + 1) . "\">Next (" . ($year + 1) . ")</a>.\n";
    }
    echo "<p>\n";
}

/**
 * Print links to yesterday and tomorrow
 * 
 * @param int $timestamp Unix timestamp
 * @param bool $is_full_month Whether showing full month view
 */
function print_yesterday_tomorrow_links($timestamp, $is_full_month)
{
    global $size;

    if ($is_full_month) {
        // No links to yesterday and tomorrow, but the the previous and next months. Easy.
        list($year_previous, $month_previous, $year_next, $month_next) = find_previous_and_next_month(date('Y', $timestamp), date('m', $timestamp));
        echo "<p><a href=\"?type=month&year=$year_previous&month=$month_previous\">Previous: " . date("F", mktime(0, 0, 0, $month_previous, 1, $year_previous)) . "</a>. \n";
        echo "<a href=\"?type=month&year=$year_next&month=$month_next\">Next: " . date("F", mktime(0, 0, 0, $month_next, 1, $year_previous)) . "</a>. \n";

        $requested_month = date('Y-m', $timestamp);
        $this_month = date('Y-m'); // 2023-11
        $previous_month = date('Y-m', time() - 60 * 60 * 24 * 30); // 2023-10
        if ($requested_month != $this_month) {
            echo "<a href=\"?type=month&year=" . date('Y') . "&month=" . date('m') . "\">Now: " . date("F") . "</a>. \n";
        }
        echo "<a href=\"?type=day&date=" . date('Ymd') . "\">Today: " . date("F d") . "</a>, \n";
        echo "<a href=\"?type=year&year=" . date('Y', $timestamp) . "\">Entire " . date('Y', $timestamp) . "</a>.\n";
        //echo "<a href=\"?type=last\">Latest image</a>.\n";
    } else {
        // Not showing a full month: Work hard to find the days.

        // Previous: Yesterday always exists.
        $yesterday_timestamp = $timestamp - 60 * 60 * 24;
        echo "<p>\n<a href=\"?type=day&date=" . date('Ymd', $yesterday_timestamp) . "&size=$size\">Previous: " . date("F d", $yesterday_timestamp) . "</a>.\n";

        // Next: Is there a tomorrow, based on the selected day?
        if (date('Y-m-d', $timestamp) == date('Y-m-d')) {
            // The $timestamp is today, so there is no tomorrow.
        } else {
            $tomorrow_timestamp = $timestamp + 60 * 60 * 24; // Add 24 hours for the "Next" link.
            echo "<a href=\"?type=day&date=" . date('Ymd', $tomorrow_timestamp) . "\">Next: " . date("F d", $tomorrow_timestamp) . "</a>.\n";
        }

        // Today: Only if this is the day before yesterday, or earlier.
        if (date('Y-m-d', $timestamp) <= date('Y-m-d', strtotime('-2 day'))) {
            echo "<a href=\"?type=day&date=" . date('Ymd') . "\">Today: " . date("F d") . "</a>, \n";
        }
        //echo "<a href=\"?type=last\">Latest image</a>.\n";

        // Link to the full month and year - and everything.
        //------------------------------------------------------------
        echo "<a href=\"?type=month&year=" . date('Y', $timestamp) . "&month=" . date('m', $timestamp) . "\">Entire " . date("F", $timestamp) . "</a>.\n";
        echo "<a href=\"?type=year&year=" . date('Y', $timestamp) . "\">Entire " . date('Y', $timestamp) . "</a>.\n";
    }
    echo "<a href=\"?type=last\">Latest image</a>.\n";
    echo "</p>\n\n";
}


/**
 * Print link to the full day view
 * 
 * @param int $timestamp Unix timestamp
 */
function print_full_day_link($timestamp)
{
    echo "<p>";
    echo "<a href=\"?type=day&date=" . date('Ymd', $timestamp) . "\">The whole day</a>.\n";
    echo "<a href=\"?type=month&year=" . date('Y', $timestamp) . "&month=" . date('m', $timestamp) . "\">Entire " . date("F", $timestamp) . "</a>.\n";
    echo "<a href=\"?type=year&year=" . date('Y', $timestamp) . "\">Entire " . date('Y', $timestamp) . "</a>.\n";
    echo "</p>\n\n";
}

/**
 * Rename files that haven't been processed by cron yet
 * This is a workaround for when cron is too slow
 * 
 * @deprecated Use ImageFileManager::checkAndRenameFilesHack() instead
 * @param string $filename_prefix Prefix to search for and remove
 */
function check_and_rename_files_hack($filename_prefix)
{
    global $imageManager;
    if ($imageManager) {
        $imageManager->checkAndRenameFilesHack($filename_prefix);
    }
}

/**
 * Round dawn and dusk times (currently unused but kept for potential future use)
 * 
 * @param int $dawn Unix timestamp
 * @param int $dusk Unix timestamp
 * @return array [rounded_dawn, rounded_dusk]
 */
function roundDawnAndDusk($dawn, $dusk)
{
    $dawn_adjust = 1; // Add hour(s) of dawn.
    $dusk_adjust = 1; // Add hour(s) of dusk.

    $dawn -= $dawn_adjust * 60 * 60;
    $roundedDawn = floor($dawn / 3600) * 3600; // Round down to nearest hour.

    $dusk += $dusk_adjust * 60 * 60;
    $roundedDusk = ceil($dusk / 3600) * 3600; // Round up to nearest hour.

    return [$roundedDawn, $roundedDusk];
}

/**
 * Print all images for a full day (between dawn and dusk)
 * 
 * @param int $timestamp Unix timestamp for the day
 * @param string $image_size Image size ("mini" or "large")
 * @param int $number_of_images Maximum number of images to show
 */
function print_full_day($timestamp, $image_size, $number_of_images)
{
    global $size;
    global $large_image_width;
    global $large_image_height;
    global $mini_image_width;
    global $mini_image_height;
    debug("print_full_day($timestamp, $image_size, $number_of_images)");

    list($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night) = find_sun_times($timestamp);
    // TODO
    //list($dawn, $dusk) = roundDawnAndDusk($dawn, $dusk);

    debug("IN: print_full_day(" . $timestamp . "," . $image_size . "," . $number_of_images . ")");
    debug("Sunrise: " . date('H:i', $sunrise) . " Sunset: " . date('H:i', $sunset) . " Dawn: " . date('H:i', $dawn) . " Dusk: " . date('H:i', $dusk));

    // Set the navigation (we need $dusk from above).
    // Previous: The previous day.
    $previous = "?type=day&date=" . date('Ymd', $timestamp - 60 * 60 * 24) . "&size=$size";
    // Next: The next day, but not if it's tomorrow.
    $next_date = date('Ymd', $timestamp + 60 * 60 * 24); // Add 24 hours.
    if (date('Ymd') != date('Ymd', $timestamp)) {
        // We are showing image for today, so no need for a link to tomorrow (no images there yet).
        $next = "?type=day&date=$next_date&size=$size";
        //$next     = "?type=day&date="   . date('Ymd', $timestamp + 60 * 60 * 24) . "&size=$size"; 
    } else {
        $next = false;
    }
    // Up: The full month.
    $up = "?type=month&year=" . date('Y', $timestamp) . "&month=" . date('m', $timestamp);
    // Down. The first image after dawn for this day.
    $down = "?type=one&image=" . find_first_image_after_time(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp), date('H', $dawn), 0, 0);

    // Print header now that we have the details for it.
    $title = "Lillevik Lofoten webcam: " . date('Y-m-d', $timestamp);
    if ($number_of_images == 1) {
        // We are printintg just the latest image, so include hour and minute too.
        $title .= date('H', $timestamp) . ":" . date('i', $timestamp);
    }

    page_header($title, $previous, $next, $up, $down);
    print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, $number_of_images != 1);
    print_mini_large_links($timestamp, $size);
    print_yesterday_tomorrow_links($timestamp, false);

    // Get all *jpg images in "today's" image directory.
    $directory = date('Y/m/d', $timestamp);
    $images_printed = 0;
    debug("Getting images from directory: <a href=\"$directory\">$directory</a>");

    //echo "<p>\n";

    // CSS overlay
    if ($size == "mini" || empty($size)) {
        echo "<div class=\"grid-container\">";
    } else {
        echo "<div class=\"grid-container-large\">";
    }

    if (file_exists($directory)) {
        debug("Directory exists: " . $directory);
        $images = glob("$directory/*.jpg");
        // Loop through all images. Reverse sort to start with the latest image at the top.
        foreach (array_reverse($images) as $image) {
            // Each filename is of this type: "2023/11/14/20231114134047.jpg".
            $yyyymmddhhmmss = get_yyyymmddhhmmss($image); // Get the "20231114134047" part.
            list($year, $month, $day, $hour, $minute, $seconds) = split_image_filename($yyyymmddhhmmss);

            // Create timestamp to check if this image is from between dawn and dusk.
            $image_timestamp = mktime((int) $hour, (int) $minute, (int) $seconds, (int) $month, (int) $day, (int) $year);

            // Check if the image is between dawn and dusk.
            if ($image_timestamp >= $dawn && $image_timestamp <= $dusk) {

                // ------------------------------------------------------------
                // CSS overlay   
                echo "<div class=\"grid-item\">";

                echo "<a href=\"?type=one&image=$year$month$day$hour$minute$seconds\">";
                echo "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" ";
                //echo "title=\"$year-$month-$day $hour:$minute\" ";
                echo "src=\"$year/$month/$day/";
                if ($size == "mini" || empty($size)) {
                    // Mini. If the mini version has been created: Use that. If not: Scale down the large version.
                    if (file_exists("$year/$month/$day/mini/$yyyymmddhhmmss.jpg")) {
                        echo "mini/";
                    }
                    echo "$yyyymmddhhmmss.jpg\" width=\"$mini_image_width\" height=\"$mini_image_height\" ";
                } else {
                    // Large.
                    echo "$yyyymmddhhmmss.jpg\" width=\"$large_image_width\" height=\"$large_image_height\" ";
                }
                echo "></a>\n";

                // CSS overlay 
                if ($size == "mini" || empty($size)) {
                    echo "<span class=\"time\">$hour:$minute</span>";
                }
                echo "</div>";

                $images_printed += 1; // Count the image just printed.
            } else {
                debug("Outside dusk and dawn:" . date('H:i:s', $dawn) . "  / " . date('H:i:s', $image_timestamp) . " / " . date('H:i:s', $dusk));
            }

        }
    } else {
        // No images for this day.
    }

    // CSS overlay
    echo "</div>\n";

    if ($images_printed > 0) {
        echo "</p>\n";
    } else {
        echo "<p>(No photos to display for " . date("Y-m-d", $timestamp) . ")</p>\n";
    }
    footer($images_printed, $previous, $next, $up, $down);
}

/**
 * Print Lillevik Lofoten images and promotional links
 */
function print_lillevik_images_and_links()
{
    $dir = __DIR__ . '/lillevik-photos';
    $url_base = 'lillevik-photos';

    // Get all JPEG files in the directory
    $images = glob($dir . '/*.jpeg');

    if (!$images || count($images) < 10) {
        echo "<!-- Not enough images to display -->\n";
        return;
    }

    // Randomly select 10 images
    shuffle($images);
    $selected = array_slice($images, 0, 10);

    // Output HTML
    echo "<!-- Lillevik images and links -->\n";
    echo "<h3>Lillevik Lofoten: More photos</h3>\n";
    echo "<p>";
    echo "The photos below are taken at <a href=\"https://lilleviklofoten.no?utm_source=webcam\">Lillevik Lofoten</a>, or nearby on Gimsøy.\n";
    echo "Information and booking: <a href=\"https://lilleviklofoten.no?utm_source=webcam\">lilleviklofoten.no</a>.\n";
    echo "For new photos: <a href=\"#\" onclick=\"location.reload(); return false;\">Reload</a>.\n";
    echo "</p>\n\n";

    // Responsive flex container
    echo "<div style=\"max-width: 980px; display: flex; flex-wrap: wrap; gap: 10px;\">\n";

    $width = "166px";
    $width = "166px";
    foreach ($selected as $path) {
        $filename = basename($path);
        $img_url = $url_base . '/' . urlencode($filename);
        $utm_url = "https://lilleviklofoten.no/?utm_source=webcam&utm_medium=thumbnail&utm_campaign=lillevik_photos&utm_content=" . urlencode($filename);
        echo "  <a href=\"$utm_url\" style=\"flex: 1 0 30%; max-width: $width;\">\n";
        echo "    <img src=\"$img_url\" alt=\"Lillevik Lofoten: lilleviklofoten.no\" style=\"width: $width; height: $height; object-fit: cover; display: block;\" />\n";
        echo "  </a>\n";
    }

    /*
    foreach ($selected as $path) {
        $filename = basename($path);
        $img_url = $url_base . '/' . urlencode($filename);
        echo "  <a href=\"https://lilleviklofoten.no\">\n";
        echo "    <img src=\"$img_url\" alt=\"Lillevik Lofoten: lilleviklofoten.no\" style=\"width: 164px; height: 164px; object-fit: cover;\" />\n";
        echo "  </a>\n";
    }
    */

    echo "</div>\n";
}

// ============================================================
// Main Script Execution
// ============================================================

// Configure error reporting
error_reporting(E_ERROR | E_PARSE);

// Set locale and timezone
setlocale(LC_ALL, WebcamConfig::LOCALE);
date_default_timezone_set(WebcamConfig::TIMEZONE);

// Initialize global variables
$timestamp = time();
$debug = 0;
$size = "mini";
$type = "one";
$monthly_day = WebcamConfig::MONTHLY_DAY;
$monthly_hour = WebcamConfig::MONTHLY_HOUR;
$max_images = WebcamConfig::MAX_IMAGES;
$large_image_width = WebcamConfig::LARGE_IMAGE_WIDTH;
$large_image_height = WebcamConfig::LARGE_IMAGE_HEIGHT;
$mini_image_width = WebcamConfig::MINI_IMAGE_WIDTH;
$mini_image_height = WebcamConfig::MINI_IMAGE_HEIGHT;

// Initialize helper objects
$sunCalculator = new SunCalculator(WebcamConfig::LATITUDE, WebcamConfig::LONGITUDE, $debug);
$imageManager = new ImageFileManager($debug);
$navHelper = new NavigationHelper();

// Parse query string to determine what to show
// ------------------------------------------------------------
if ($_SERVER['QUERY_STRING'] == 1) {
    $type = "last";
    debug("LAST");
} else if (empty($_SERVER['QUERY_STRING'])) {
    $type = "last";
    debug("DAY");
} else {
    $parse_output['type'] = "";
    $parse_output['date'] = "";
    $parse_output['year'] = "";
    $parse_output['month'] = "";
    $parse_output['size'] = "";
    $parse_output['image'] = "";
    $parse_output['last_image'] = "";
    parse_str($_SERVER['QUERY_STRING'], $parse_output);
    debug("WORK IN PROGRESS!");
    debug("Parse:");
    $type = $parse_output['type'];
    $date = $parse_output['date'];
    $year = $parse_output['year'];
    $month = $parse_output['month'];
    $size = $parse_output['size'];
    $image = $parse_output['image'];
    $last_image = $parse_output['last_image'];
}
//debug("QUERY_STRING: " . $_SERVER['QUERY_STRING']);
//debug("type: $type<br/>date: $date<br/>year: $year</br>month: $month</br>size: $size<br/>image: $image<br/>last_image: $last_image");

// Handle files not yet processed by cron
// Old webcam:
check_and_rename_files_hack("Lillevik Lofoten_01_");
// 2025 webcam:
check_and_rename_files_hack("Lillevik Lofoten_00_");

// Determine which page type to display and render it
// ------------------------------------------------------------
debug("type: $type");
if ($type == "" || $type == false) {
    $type = "last";
}

if ($type == "last") {
    // Only the Latest image, even if it is after both sunset and dusk.
    $latest_image = find_latest_image();
    $latest_image_filename = get_yyyymmddhhmmss($latest_image);
    print_single_image($latest_image_filename, true); // true = Latest image.
} else if ($type == "one") {
    // One specific image, the datepart is in the $image parameter (no path or .jpg): 2015112613051901
    print_single_image($image, false); // false = not Latest image.
} else if ($type == "day") {
    // All images for the specified date either in $date parameter or created below: 20151130.
    if ($date) {
        $timestamp = mktime(0, 0, 0, (int) substr($date, 4, 2), (int) substr($date, 6, 2), (int) substr($date, 0, 4));
    } // If $date is undefined, we use existing $timestamp.
    print_full_day($timestamp, $size, $max_images);
} else if ($type == "month") {
    // All images for this month, specified with $year and $month parameters.
    if ($month && $year) {
        print_full_month($year, sprintf("%02d", $month), $monthly_hour, $size);
    }
} else if ($type == "year") {
    // The full year, actually. Not all images, though.
    print_full_year($year);
} else if ($type == "all") {
    // All images for all years. Heavy!
    print_all_years();
} else {
    // Unknown type.
    page_header("Error", false, false, false, false);
    echo "<p>Unknown type: \"$type\".</p>";
    echo "<p><a href=\"javascript:history.back()\">Back</a>.</p>\n";
    footer();
}

?>
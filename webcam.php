<?php
/* ============================================================
//
// webcam.php
//
// Code: https://github.com/cloveras/webcam
//
// Example: http://lilleviklofoten.no/webcam/
//
============================================================ */

// Functions
// ============================================================

// Page header with title and Javascript navigation
// ------------------------------------------------------------
function page_header($title, $previous, $next, $up, $down) {

    print <<<END1
<!DOCTYPE html>
<html lang="en-US">
<head>
  <meta charset="utf-8">
  <meta name="description" content="Lofoten webcam with view towards west from Vik, Gimsøy, Lofoten, Norway.">
  <meta name="keywords" content="lofoten,webcam,webcamera,webkamera,web cam, webcam,vik,gimsøy,lofoten islands,nordland,norway">
  <meta name="robot" content="index">
  <meta name="generator" content="webcam.php: https://github.com/cloveras/webcam">
  <link rel="stylesheet" type="text/css" href="webcam.css">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

END1;

    // Set some web variables for command-line use.
    if (! $_SERVER['SERVER_NAME']) {
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
    
        function printArrowScript($keyCode, $url) {
            //echo "    function ${keyCode}ArrowPressed() { window.location.href=\"$url\"; }\n";
            echo "    function {$keyCode}ArrowPressed() { window.location.href=\"$url\"; }\n";
        }
    
        if ($previous) printArrowScript("left", $previous);
        if ($next) printArrowScript("right", $next);
        if ($up) printArrowScript("up", $up);
        if ($down) printArrowScript("down", $down);
    
        echo "    document.onkeydown = function(evt) {\n";
        echo "      evt = evt || window.event;\n";
        echo "      switch (evt.keyCode) {\n";
        if ($previous) echo "        case 37: leftArrowPressed(); break;\n";
        if ($up)       echo "        case 38: upArrowPressed(); break;\n";
        if ($next)     echo "        case 39: rightArrowPressed(); break;\n";
        if ($down)     echo "        case 40: downArrowPressed(); break;\n";
        echo "      }\n";
        echo "    };\n";
        echo "  </script>\n\n";
    }

    // Google Analytics and Microsoft Clarity
    print<<<END3

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-P8Z20DT0NR"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-P8Z20DT0NR');
    </script>

    <!-- Microsoft Clarity -->
    <script>
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "brp4ocus57");
    </script>

</head>
<body>

<h1>$title</h1>

<p>
<a href=".">Webcam</a>
at
<a href="https://lilleviklofoten.no">Lillevik Lofoten</a>,
<a href="https://maps.app.goo.gl/nZDV8TKvMUvEcLeQ7">Vik, Gimsøy, Lofoten, Norway</a>.
See also: <a href="https://lilleviklofoten.no/webcams/">other webcams in Lofoten</a>.
</p>
END3;
}

// Debug
// ------------------------------------------------------------
function debug($txt) {
    global $debug;
    if ($debug) {
        echo "$txt<br/>\n";
    }
}


// Footer
// ------------------------------------------------------------
function footer($images_printed, $previous, $next, $up, $down) {
 
    $touch = true;

    if ($touch) {
    echo <<<TOUCH

<!-- Touch gestures -->
<script src="https://hammerjs.github.io/dist/hammer.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var body = document.body;
        var hammer = new Hammer(body);
        hammer.on('swiperight', function() {
            window.location.href = '{$next}';
        });
        hammer.on('swipeleft', function() {
            window.location.href = '{$previous}';
        });
    });
</script>

TOUCH;
    }

    // Navigation links
    echo "\n\n<p>Use ";
    if ($touch) {
        echo "swipe gestures or ";
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
    echo "<p style=\"color: rgb(200, 200, 200);\" >Made with <a style=\"color: rgb(200, 200, 200);\" href=\"https://github.com/cloveras/webcam\">webcam.php</a></p>\n\n";
    
    echo "</body>\n</html>\n";
}

// Get variables from the date part of the image filename.
// ------------------------------------------------------------
function split_image_filename($image_filename) {
    if ($debug) {
        debug("<br/>IN split_image_filename($image_filename): $year-$month-$day $hour:$minute:$seconds");  
    }
    // Example filename: 20231114134047.jpg
    $yyyymmddhhmmss = get_yyyymmddhhmmss($image);  
    // We now have: 20231114134047
    // Which is   : YYYYMMDDHHMMSS 

    $year = substr($image_filename, 0, 4);
    $month = substr($image_filename, 4, 2);
    $day = substr($image_filename, 6, 2);
    $hour = substr($image_filename, 8, 2);
    $minute = substr($image_filename, 10, 2);
    $seconds = substr($image_filename, 12, 2);
    if ($debug) {
        debug("<br/>split_image_filename($image_filename): $year-$month-$day $hour:$minute:$seconds");  
    }
    return array($year, $month, $day, $hour, $minute, $seconds);
}

// Midnight sun? (tested with date_sunrise() and GPS coordinates used in this script on yr.no)
// ------------------------------------------------------------
function midnight_sun($timestamp, $latitude, $longitude) {
    $month = date('m', $timestamp);
    $day = date('d', $timestamp);

    return ($month == 5 && $day >= 24) || ($month == 6) || ($month == 7 && $day <= 18);
}

// Is it polar night? (tested with date_sunrise() and GPS coordinates used in this script on yr.no)
// ------------------------------------------------------------
function polar_night($timestamp, $latitude, $longitude) {
    $month = date('m', $timestamp);
    $day = date('d', $timestamp);
    return ($month == 12 && $day >= 6) || ($month == 1 && $day <= 6);
}

// Find sunrise and sunset, return all kinds of stuff we need later.
// Fakes sunrise and sunset for midnight sun and polar night.
// ------------------------------------------------------------
function find_sun_times($timestamp) {
    // Return timestamps for everything.

    // Default values
    $sunrise = $sunset = $dawn = $dusk = 0;
    $midnight_sun = $polar_night = false;

    // When to start showing images during the polar night.
    $polar_night_fake_sunrise_hour = 8; 
    $polar_night_fake_sunset_hour = 15; 
    $adjust_dawn_dusk_hours = 1; // How much before/after sunrise/sunset is dawn/dusk.
    $adjust_dawn_dusk_seconds = $adjust_dawn_dusk_hours * 60 * 60; // How much before/after sunrise/sunset is dawn/dusk.
   
    $polar_night_fake_dawn_hour = $polar_night_fake_sunrise_hour - $adjust_dawn_dusk_hours;
    $polar_night_fake_dusk_hour = $polar_night_fake_sunset_hour + $adjust_dawn_dusk_hours;
    $polar_night_hours = 30; // Adding this to the hours below.


    // Where: Årstrandveien 663, 8314 Gimsøysand: 68.33007, 14.09165
    $latitude = 68.33007; // North
    $longditude = 14.09165 ; // East

    // We will need these below.
    $year = date('Y', $timestamp);
    $month = date('m', $timestamp);
    $day = date('d', $timestamp);

    // Find the timestamps for sunrise, sunset, dawn, and dusk (as unix timestamps)
    if (midnight_sun($timestamp, $latitude, $longitude)) {
        debug("MIIDNIGHT SUN!");
        $midnight_sun = true;
        // We still need to show a few images, so: faking sunrise and sunset.

        $sunrise = mktime(0, 1, 0, $month, $day, $year);    // 00:01:00, One minute after midnight
        $sunset  = mktime(23, 59, 59, $month, $day, $year); // 23:59:59
        $dawn    = $sunrise;
        $dusk    = $sunset;
    } elseif (polar_night($timestamp, $latitude, $longitude)) {
        debug("POLAR NIGHT!");
        $polar_night = true;
        // We still need to show a few images, so: faking sunrise and sunset.
        $sunrise = mktime($polar_night_fake_sunrise_hour, 0, 0, $month, $day, $year);
        $sunset  = mktime($polar_night_fake_sunset_hour,  0, 0, $month, $day, $year);
        $dawn    = mktime($polar_night_fake_dawn_hour,    0, 0, $month, $day, $year);
        $dusk    = mktime($polar_night_fake_dusk_hour,    0, 0, $month, $day, $year);
    } else {
        // Do the math! Use the $timestamp passed as a parameter.
        debug("NORMAL SUN! timestamp: $timestamp, human-readable: " . date('Y-m-d H:i:s', $timestamp));

        // Get all sun info with PHP 8's built-in functionality.
        $sun_info = date_sun_info($timestamp, $latitude, $longitude);
        $sunrise = $sun_info['sunrise'] ?: mktime(0, 0, 0, $month, $day, $year); // Midnight
        $sunset = $sun_info['sunset'] ?: mktime(23, 59, 59, $month, $day, $year); // Almost midnight again
        $dawn = $sun_info['nautical_twilight_begin'] ?: $sunset - 1; // One second later
        $dusk = $sun_info['nautical_twilight_end'] ?: $sunset + 1; // One second later
    }

    // At the beginning and end of the midnight sun and polar night periods,
    // the dawn and dusk may be set to the wrong day because of the adjustments above.
    // Check if dawn/dusk are have been set too early or late above, and reset to start or end of the day.
    $day_start = mktime(0, 0, 0, date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp)); // 00:00:00
    $day_end = mktime(23, 59, 59, date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp)); // 23:59:59
    if ($sunrise - $adjust_dawn_dusk_seconds < $day_start) {
        // The time from 00:00:00 to sunrise is less than the adjustment time. Set dawn to start of day.
        debug("Dawn was before the start of this day: Adjusting dawn to be midnight (00:00:00)");
        $dawn = $day_start;
    }
    if ($sunset + $adjust_dawn_dusk_seconds > $day_end) {
        // The time from sunset to 23:59:59 is less than the adjustment time. Set dusk to end of day.
        debug("Sunset was after the end of this day: Adjusting it to be 23:59:50");
        $dusk = $day_end;
    }

    debug("<br/>find_sun_times($timestamp) (" . date('Y-m-d H:i', $timestamp) . ")");
    debug("midnight_sun: $midnight_sun");
    debug("polar_night: $polar_night");
    debug("dawn: $dawn (" . date('Y-m-d H:i', $dawn) . ")");
    debug("sunrise: $sunrise (" . date('Y-m-d H:i', $sunrise) . ")");
    debug("sunset: $sunset (" . date('Y-m-d H:i', $sunset) . ")");
    debug("dusk: $dusk (" . date('Y-m-d H:i', $dusk) . ")");

    return array($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night);
} 

// Print one image for every day in the month.
// ------------------------------------------------------------
function print_full_month($year, $month) {
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

    $images_printed = 0;
    for ($i = 1; $i <= 31; $i+=1) { // Works for February and 30-day months too.
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
                // Print it!
                if ($images_printed == 0) {
                    echo "<p>\n";
                }
                // Print mini images, link to all images for that day.
                echo "<a href=\"?type=day&date=$year$month$day\">";
                echo "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" ";
                echo "title=\"$year-$month-$day $hour:$minute\" ";
                echo "src=\"$year/$month/$day/";
                // If the mini version has been created: Use that. If not: Scale down the large version.
                if ($size == "mini" || empty($size)) {
                    if (file_exists("$year/$month/$day/mini/$yyyymmddhhmmss.jpg")) {
                        echo "mini/";
                    } 
                    echo "$yyyymmddhhmmss.jpg\" width=\"$mini_image_width\" height=\"$mini_image_height\" ";
                } else {
                    echo "$yyyymmddhhmmss.jpg\" width=\"$large_image_width\" height=\"$large_image_height\" ";
                }
                echo "></a>\n";
                $images_printed += 1; // Count the image just printed.
            }
        } else {
            debug("Directory does not exist: $directory");
        }
    }
    if ($images_printed > 0) {
        echo "</p>\n";
    } else {
        // No pictures found for this month.
        echo "<p>(No photos to display for " . date("Y-m-d", $timestamp) . ")</p>\n"; 
    }
    footer($images_printed, $previous, $next, $up, $down);
}

// Print images for a whole year.
// ------------------------------------------------------------
function print_full_year($year) {
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
    echo "<a href=\"?type=day&date=" .  date('Ymd') . "\">Today: " . date("M d") . "</a>, \n";
    echo "<a href=\"?type=last\">Latest image</a>.\n";
    echo "</p>\n\n";

    // Loop through all months 1-12 (again) and print images for the $days if they exist.
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
                // Print it!
                if ($images_printed == 0) {
                    echo "<p>\n"; 
                }
                // Print mini images (never large images for full years), link to all images for that day.
                echo "<a href=\"?type=one&image=$yyyymmddhhmmss\">";
                echo "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" ";
                echo "title=\"$year-$month-$day $hour:$minute\" width=\"$mini_image_width\" height=\"$mini_image_height\" ";
                echo "src=\"$year/$month/$day/";
                // If the mini version has been created: Use that. If not: Scale down the full version.
                if (file_exists("$year/$month/$day/mini/$yyyymmddhhmmss.jpg")) {
                    echo "mini/";
                } 
                echo "$yyyymmddhhmmss.jpg\"></a>\n";

                $images_printed += 1;
            }
        }
    }
    if ($images_printed > 0) {
        echo "</p>\n";
    } else {
        // No pictures found for this year.
        echo "<p>(No photos to display for " .  date("Y", mktime(12, 0, 0, 1, 1, $year)) . ")</p>\n"; 
    }
    footer($images_printed, $previous, $next, $up, $down);
}

// Print images for all years
// ------------------------------------------------------------
function print_all_years() {
    debug("<br/>print_all_years()");
    global $monthly_day;
    global $monthly_hour;
    global $mini_image_width;
    global $mini_image_height;
   
    $start_year = 2015;
    $monthly_days = [1, 7, 14, 21, 28];
    $this_year = date('Y');

    $previous = $next = $up = $down = false;
    page_header("Lillevik Lofoten webcam: $start_year" . "-" . "$this_year", $previous, $next, $up, $down);
    echo "<p>Displaying images for $monthly_hour:00 on the $monthly_day" . "th for each month for all year.</p>\n";
    echo "<a href=\"?type=day&date=" .  date('Ymd') . "\">Today: " . date("M d") . "</a> \n";
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
        for ($month = 1; $month <= 12; $month++) {
            $month = sprintf("%02d", $month);

            // Loop through all $monthly_days for this month.
            foreach($monthly_days as $monthly_day) {

                // Find first image for the $monthly_day taken after $monthly_hour
                debug("find_first_image_after_time($year, $month, $monthly_day, $monthly_hour, 0, 0);");
                print "<p>find_first_image_after_time($year, $month, $monthly_day, $monthly_hour, 0, 0);</p>\n";
                $yyyymmddhhmmss = find_first_image_after_time($year, $month, $monthly_day, $monthly_hour, 0, 0);
                if ($yyyymmddhhmmss) {
                    // There was an image.
                    $hour = substr($yyyymmddhhmmss, 8, 2);
                    $minute = substr($yyyymmddhhmmss, 10, 2);
                    // Print it!
                    if ($images_printed == 0) {
                        echo "<p>\n"; 
                    }
                    // Print mini images (never large images for full years), link to all images for that day.
                    echo "<a href=\"?type=one&image=$yyyymmddhhmmss\">";
                    echo "<img alt=\"Lillevik Lofoten webcam: $year-$month--$monthly_day $hour:$minute\" ";
                    echo "title=\"$year-$month-$monthly_day $hour:$minute\" width=\"$mini_image_width\" height=\"$mini_image_height\" ";
                    echo "src=\"$year/$month/$monthly_day/";
                    // If the mini version has been created: Use that. If not: Scale down the full version.
                    if (file_exists("$year/$month/$monthly_dayy/mini/$yyyymmddhhmmss.jpg")) {
                        echo "mini/";
                    } 
                    echo "$yyyymmddhhmmss.jpg\"></a>\n";

                    $images_printed += 1;
                }    
            } 
        }
        if ($images_printed > 0) {
            echo "</p>\n";
        }

    }
    footer($images_printed, $previous, $next, $up, $down);
}

// Print links to mini and large images
// ------------------------------------------------------------
function print_mini_large_links($timestamp, $size) {
    $date = date('Ymd', $timestamp);
    echo "<p>\n";
    if ($size == "large" || empty($size)) { // Link to mini if we showed large, or don't know.
        echo "<a href=\"?type=day&date=$date&size=mini\">Mini photos</a>. ";
    }
    if ($size == "mini" || empty($size)) { // Links to large if we showed mini, or don't know.
        echo "<a href=\"?type=day&date=$date&size=large\">Large photos</a>. ";
    }
    echo "</p>\n\n";
}

// Returns only the date part of an image filename (removes directory and ".jpg").
// ------------------------------------------------------------
function get_yyyymmddhhmmss($fullPath) {
    // Input: 2023/11/14/20231114144049.jpg
    // Output: 20231114144049
    return preg_replace("/[^0-9]/", "", pathinfo(basename($fullPath), PATHINFO_FILENAME));
}

// Finds the latest "*jpg" file in today's directory. Returns only date part of filename.
// ------------------------------------------------------------
function find_latest_image() {
    // Find newest directory with the right name format
    list($year, $month, $day) = explode('-', date('Y-m-d'));
    $latest_image = max(glob("$year/$month/$day/*.jpg"));
    $image = get_yyyymmddhhmmss($latest_image);
    debug("image (datepart): $image");
    // Now: 2015120209401200
    return $image;
}

// Finds the first day with images for a specific year and month. Returns only date part of filename.
// ------------------------------------------------------------
function find_first_day_with_images($year, $month) {
    // Find newest directory with the right name format
    debug("<br/>find_first_day_with_images($year, $month)");
    $directories = glob("$year$month*"); // Get the first first. 2* works until the year 3000.
    $directory = $directories[0]; // This is the first one in that month.
    debug("First day with images: $directory");
    return $directory;
}

// Finds the first year with images.
// ------------------------------------------------------------
function find_first_year_with_images() {
    debug("<br/>find_first_year_with_images()");
    return 2015; // Hah!
}

// Gets all images in the directory for a specific day (YYYYMMDD: 20231114).
// ------------------------------------------------------------
function get_all_images_in_directory($directory) {
    $images = glob("$directory/*.jpg");
    debug("<br/>get_all_images_in_directory($directory/*.jpg): " . count($images) . " images found.");
    return $images;
}

// Gets all images in the directory for a specific day and hour.
// ------------------------------------------------------------
function get_latest_image_in_directory_by_date_hour($directory, $hour) {
    // $date = 2023/11/14
    $date = preg_replace("/[^0-9]/", "", $directory);
    $images = glob("$directory/$date$hour*.jpg");
    debug("<br/>get_latest_image_in_directory_by_date_hour($directory, $hour)<br/>Found " . count($images) . "images, returning " . $images[0]);
    return $images[0];
}

// Find the first image after a given time. Used when going to the first image in a day.
// ------------------------------------------------------------
// function find_first_image_after_time_old($year, $month, $day, $hour, $minute, $seconds) {
//     if ($minute < 10) {
//         $minute = sprintf("%02d", $minute);
//     }
//     if ($seconds < 10) {
//         $seconds = sprintf("%02d", $seconds);
//     }
//     debug("<br/>find_first_image_after_time($year, $month, $day, $hour, $minute, $seconds)");
//     // Find all images for the specified date and hour.
//     $image = "";
//     $images = glob("$year/$month/$day/$year$month$day$hour*");
//     debug("Looking in directory: $year/$month/$day/$year$month$day$hour*"); 
//     if (!empty($images)) {
//         $image = $images[0];
//         $image = get_yyyymmddhhmmss($image);
//     } else {
//         debug("No images found in directory: $year/$month/$day/$year$month$day$hour*");
//     }
//     return $image;
// }
function find_first_image_after_time($year, $month, $day, $hour, $minute, $seconds) {
    $minute = sprintf("%02d", $minute);
    $seconds = sprintf("%02d", $seconds);

    debug("<br/>find_first_image_after_time($year, $month, $day, $hour, $minute, $seconds)");

    // Find all images for the specified date and hour.
    $image = "";
    $imagePattern = sprintf("%s/%s/%s/%s%s%s%s*", $year, $month, $day, $year, $month, $day, $hour);
    
    $images = glob($imagePattern);
    
    debug("Looking in directory: $imagePattern"); 

    if (!empty($images)) {
        $image = get_yyyymmddhhmmss($images[0]);
    } else {
        debug("No images found in directory: $imagePattern");
    }

    return $image;
}

// Print a single image, specified by the date part of the filename (no .jpg suffix, no path)
// ------------------------------------------------------------
function print_single_image($image_filename, $last_image) {
    // $image_filename example: "20231114144049"
    global $large_image_width;
    global $large_image_height;
  
    // Find the date and time for the image.
    debug("split_image_filename($image_filename)");
    list($year, $month, $day, $hour, $minute, $seconds) = split_image_filename($image_filename);

    // Make a timestamp for the image's date and time.
    debug(" mktime($hour, $minute, 0, $month, $day, $year)");
    $timestamp = mktime($hour, $minute, 0, $month, $day, $year); // Using 0 for minutes to get the one(s) before too.  
    
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
    foreach($images as $image) {
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
    if (! $last_image) {
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
    echo "title=\"$year-$month-$day $hour:$minute\" ";
    echo "width=\"$large_image_width\" height=\"$large_image_height\" ";
    echo "src=\"$year/$month/$day/$image_filename\">";
    echo "</a>\n";
    echo "</p>\n\n";

    list($width, $height) = getimagesize("$year/$month/$day/$image_filename");
    echo "<p>\n<a href=\"$year/$month/$day/$image_filename\">Full size ($width x $height)</a>\n</p>\n\n";

    footer($images_printed, $previous, $next, $up, $down);
}

// Print details about the sun, and what images are shown.
// ------------------------------------------------------------
function print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, $include_interval) {
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
        echo " (calculated for " . date('M', $dawn) . " $monthly_day)";
        //echo " with the newest images first";
    }
    echo ".</p>\n\n";
}

// Find the previous and next month, even for January and December.
// ------------------------------------------------------------
function find_previous_and_next_month($year, $month) {
    $month_previous = ($month == 1) ? 12 : sprintf("%02d", $month - 1);
    $year_previous = ($month == 1) ? sprintf("%4d", $year - 1) : $year;

    $month_next = ($month == 12) ? "01" : sprintf("%02d", $month + 1);
    $year_next = ($month == 12) ? sprintf("%4d", $year + 1) : $year;

    debug("<br/>find_previous_and_next_month($year, $month)<br/>year_previous: $year_previous<br/>month_previous: $month_previous<br/>year_next: $year_next<br/>month_next: $month_next");

    return array($year_previous, $month_previous, $year_next, $month_next);
}

// Links to previsou and next year.
// ------------------------------------------------------------
function print_previous_next_year_links($year) {
    echo "<p><a href=\"?type=year&year=" . ($year - 1) . "\">Previous (" . ($year - 1) . ")</a>.\n";
    if ($year < date('Y')) {
        echo "<a href=\"?type=year&year=" . ($year + 1) . "\">Next (" . ($year + 1) . ")</a>.\n";
    }
    echo "<p>\n";
}

// Links to yesterday and (possibly) tomorrow.
// ------------------------------------------------------------
function print_yesterday_tomorrow_links($timestamp, $is_full_month) {
    global $size;

    if ($is_full_month) {
        // No links to yesterday and tomorrow, but the the previous and next months. Easy.
        list($year_previous, $month_previous, $year_next, $month_next) = find_previous_and_next_month(date('Y', $timestamp), date('m', $timestamp));
        echo "<p><a href=\"?type=month&year=$year_previous&month=$month_previous\">Previous: " . date("M", mktime(0, 0, 0, $month_previous, 1, $year_previous)) . "</a>. \n";
        echo "<a href=\"?type=month&year=$year_next&month=$month_next\">Next: " .  date("M", mktime(0, 0, 0, $month_next, 1, $year_previous)) . "</a>. \n";

        $requested_month = date('Y-m', $timestamp);
        $this_month = date('Y-m'); // 2023-11
        $previous_month = date('Y-m', time() - 60 * 60 * 24 * 30); // 2023-10
        if ($requested_month != $this_month) {
            echo "<a href=\"?type=month&year=" . date('Y') . "&month=" . date('m') . "\">Now: " . date("M") .  "</a>. \n";
        }
        echo "<a href=\"?type=day&date=" .  date('Ymd') . "\">Today: " . date("M d") . "</a>, \n";
        echo "<a href=\"?type=year&year=" . date('Y', $timestamp) . "\">Entire " . date('Y', $timestamp) . "</a>.\n";
        //echo "<a href=\"?type=last\">Latest image</a>.\n";
    } else {
        // Not showing a full month: Work hard to find the days.

        // Previous: Yesterday always exists.
        $yesterday_timestamp = $timestamp - 60 * 60 * 24;
        echo "<p>\n<a href=\"?type=day&date=" . date('Ymd', $yesterday_timestamp) . "&size=$size\">Previous: " . date("M d", $yesterday_timestamp). "</a>.\n";

        // Next: Is there a tomorrow, based on the selected day?
        if (date('Y-m-d', $timestamp) == date('Y-m-d')) {
            // The $timestamp is today, so there is no tomorrow.
        } else {
            $tomorrow_timestamp = $timestamp + 60 * 60 * 24; // Add 24 hours for the "Next" link.
            echo "<a href=\"?type=day&date=" . date('Ymd', $tomorrow_timestamp) . "\">Next: " . date("M d", $tomorrow_timestamp) . "</a>.\n";
        }   

        // Today: Only if this is the day before yesterday, or earlier.
        if (date('Y-m-d', $timestamp) <= date('Y-m-d', strtotime('-2 day'))) {
            echo "<a href=\"?type=day&date=" .  date('Ymd') . "\">Today: " . date("M d") . "</a>, \n";
        } 
        //echo "<a href=\"?type=last\">Latest image</a>.\n";

        // Link to the full month and year - and everything.
        //------------------------------------------------------------
        echo "<a href=\"?type=month&year=" . date('Y', $timestamp) . "&month=" . date('m', $timestamp) . "\">Entire " . date("M", $timestamp) . "</a>.\n";
        echo "<a href=\"?type=year&year=" . date('Y', $timestamp) . "\">Entire " . date('Y', $timestamp) . "</a>.\n";
    }
    echo "<a href=\"?type=last\">Latest image</a>.\n";
    echo "</p>\n\n";
}


// Print link to all images for the day specified with a timestamp.
// ------------------------------------------------------------
function print_full_day_link($timestamp) {
    $date = date('Ymd', $timestamp);
    $link = "?type=day&date=$date";
    echo "<p><a href=\"$link\">The whole day</a>.</p>\n\n";
}

// Rename files in case there are new ones that have not been handles by cron yet.
// ------------------------------------------------------------
function check_and_rename_files_hack($filename_prefix) {
    list($year, $month, $day) = explode('-', date('Y-m-d'));
    debug("glob(\"$year/$month/$day/$filename_prefix*\")");
    $images = glob("$year/$month/$day/$filename_prefix*");
    debug("Found " . count($images) . " images to rename.");
    foreach ($images as $image_to_rename) {
        $new_name = str_replace($filename_prefix, '', $image_to_rename);
        debug("rename($image_to_rename, $new_name)");
        rename($image_to_rename, $new_name);
    }
}

// Dawn and dusk rounding to get more of the sub-horizon sunlight.
// ------------------------------------------------------------
function roundDawnAndDusk($dawn, $dusk) {
    $dawn_adjust = 1; // Add hour(s) of dawn.
    $dusk_adjust = 1; // Add hour(s) of dusk.

    $dawn -= $dawn_adjust * 60 * 60; 
    $roundedDawn = floor($dawn / 3600) * 3600; // Round down to nearest hour.

    $dusk += $dusk_adjust * 60 * 60; 
    $roundedDusk = ceil($dusk / 3600) * 3600; // Round up to nearest hour.

    return [$roundedDawn, $roundedDusk];
}

// Print one day: All images in a directory, between dawn and dusk, with mini/large size, optionally limited by a number.
// ------------------------------------------------------------
function print_full_day($timestamp, $image_size, $number_of_images) {
    global $size;
    global $large_image_width;
    global $large_image_height;
    global $mini_image_width;
    global $mini_image_height;
    debug("print_full_day($timestamp, $image_size, $number_of_images)");

    list($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night) = find_sun_times($timestamp);
    list($dawn, $dusk) = roundDawnAndDusk($dawn, $dusk);

    // Set the navigation (we need $dusk from above).
    // Previous: The previous day.
    $previous = "?type=day&date="   . date('Ymd', $timestamp - 60 * 60 * 24) . "&size=$size"; 
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
    $up       = "?type=month&year=" . date('Y', $timestamp) . "&month=" . date('m', $timestamp);
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

    // Streaming container div for all the mini images, so the CSS time overlay can be positioned relative to it.
    //echo "<div class=\"streaming-container\">\n";

    // Get all *jpg images in "today's" image directory.
    $directory = date('Y/m/d', $timestamp);
    $images_printed = 0;
    debug("Getting images from directory: <a href=\"$directory\">$directory</a>");
    if (file_exists($directory)) {
        debug("Directory exists: ". $directory);  
        $images = glob("$directory/*.jpg");
        // Loop through all images. Reverse sort to start with the latest image at the top.
        foreach(array_reverse($images) as $image) {
            // Each filename is of this type: "2023/11/14/20231114134047.jpg".
            $yyyymmddhhmmss = get_yyyymmddhhmmss($image); // Get the "20231114134047" part.
            list($year, $month, $day, $hour, $minute, $seconds) = split_image_filename($yyyymmddhhmmss); // Split into variables.
            
            // Create timestamp to check if this image is from between dawn and dusk.
            $image_timestamp = mktime($hour, $minute, $seconds, $month, $day, $year);

            // Check if the image is between dawn and dusk.
            if ($image_timestamp >= $dawn && $image_timestamp <= $dusk) {
                if ($images_printed === 0) {
                    echo "<p>\n";
                }
            
                $imagePath = "$year/$month/$day/";
                $imagePath .= ($image_size != "large" && file_exists("$imagePath/mini/$yyyymmddhhmmss.jpg")) ? "mini/" : "";
                
                echo "<a ";
                //echo "style=\"position: relative; display: inline-block;\" ";
                echo "href=\"?type=one&image=$year$month$day$hour$minute$seconds\">";

                //echo "<div class=\"mini\">";

                echo "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" ";
                echo "title=\"$year-$month-$day $hour:$minute\" ";
                if ($image_size == "large") {
                    echo "width=\"$large_image_width\" ";
                    echo "height=\"$large_image_height\" ";
                } else {
                    echo "width=\"$mini_image_width\" ";
                    echo "height=\"$mini_image_height\" ";
                }
                echo "src=\"$imagePath$yyyymmddhhmmss.jpg\">";

                //echo "<b>$hour:$minute</b>";
                //echo "</div>";

                echo "</a>\n";

                //echo "<span class=\"time-overlay\">$hour:$minute</span>";
    
                if ($image_size == "large") {
                    // Large images: Print full size with linebreaks.
                    echo "</p>\n\n";
                }
            
                $images_printed++;
                if ($images_printed >= $number_of_images) {
                    echo "</p>\n\n";
                    break;
                }
            } else {
                debug("Outside dusk and dawn:" . date('H:i:s', $dawn) . "  / " . date('H:i:s', $image_timestamp) . " / " . date('H:i:s', $dusk));
            }
            
        }
    } else {
        // No images for this day.
    }

    // Close the streaming-container div.
    //echo "</div>\n";

    if ($images_printed > 0) {
        echo "</p>\n";
    } else {
        echo "<p>(No photos to display for " . date("Y-m-d", $timestamp) . ")</p>\n";
    }
    footer($images_printed, $previous, $next, $up, $down);
}

// Action below
// ============================================================
error_reporting(E_ERROR | E_PARSE);

// Important variables and defaults.
// ------------------------------------------------------------
setlocale(LC_ALL,'en_US');
date_default_timezone_set("Europe/Oslo");
$timestamp = time();
$debug = 0;
$size = "mini";
$type = "one";
$monthly_day = 15; // The day to use for full month view.
$monthly_hour = 12; // Time of day to use when showing full months.
$max_images = 1000; // Unless we are showing less.
$large_image_width = 900;
$large_image_height = 750;
$mini_image_width = 160;
$mini_image_height = 120;

// Debug: Set the date to something else than today.
// ------------------------------------------------------------
if (0) {
    $debug_year = "2023";
    $debug_month = "11";
    $debug_day = "14";
    $timestamp = mktime(0, 0, 0, $debug_month, $debug_day, $debug_year);
    echo "Today (set in debug): " . date('Y-m-d H:i', $timestamp) . "<br/>\n";
}

// Sort out the QUERY_STRING
// ------------------------------------------------------------
if ($_SERVER['QUERY_STRING'] == 1) {
    $type = "last";
    debug("LAST");
} else if (empty($_SERVER['QUERY_STRING'])) {
    $type = "last";
    debug("DAY");
} else {
    $parse_output['type']  = "";
    $parse_output['date']  = "";
    $parse_output['year']  = "";
    $parse_output['month'] = "";
    $parse_output['size']  = "";
    $parse_output['image'] = "";
    $parse_output['last_image'] = "";
    parse_str($_SERVER['QUERY_STRING'], $parse_output);
    debug("PARSE");
    $type  = $parse_output['type'];
    $date  = $parse_output['date'];
    $year  = $parse_output['year'];
    $month = $parse_output['month'];
    $size  = $parse_output['size'];
    $image = $parse_output['image'];
    $last_image = $parse_output['last_image'];
}
//debug("QUERY_STRING: " . $_SERVER['QUERY_STRING']);
//debug("type: $type<br/>date: $date<br/>year: $year</br>month: $month</br>size: $size<br/>image: $image<br/>last_image: $last_image");

// Need to fix new files not handled by cron yet.
check_and_rename_files_hack("Lillevik Lofoten_01_"); 

// Check the $type of page to show, then do the right thing.
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
        $timestamp = mktime(0, 0, 0, substr($date, 4, 2), substr($date, 6, 2), $year = substr($date, 0, 4));
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

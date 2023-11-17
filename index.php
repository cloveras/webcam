<?php
/* ============================================================
//
// kamera.php
//
// Generates HTML for webcam images.
//
// Looks for directories and image files like this:
// ./20151202/image-2015120209401201.jpg
//
// Finds sunrise, sunset, dawn and dusk based on GPS coordinates.
// Only shows images taken between dawn and dusk, handles midnight sun and polar night.
//
// The script started as a simple hack, then grew into this much larger and almost
// maintainable hack. It is a good candidate for a complete rewrite, if you have the time.
//
// Code: https://github.com/cloveras/kamera
//
// Have a look: http://superelectric.net/viktun/kamera/
//
// Things that should be changed if you want to use this:
// * Names of directories and image filenames (maybe easiest to change on filesystem).
// * Latitude and longditude (use Google Maps to find coordinates)
// * Adjust zenith, apparently a black art: https://en.wikipedia.org/wiki/Solar_zenith_angle
// * Verify the calculated sunrise and sunset with the same at yr.no.
// * Check the dates in functions midnight_sun() and polar_night().
// * Set the locale, whith is used for printing month names.
// * Change the (hardcoded) text shown on the pages (not a lot, but some).
// * Change the Google Analytics id.
// * For surprisingly verbose feedback for debugging: $debug = 1
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
  <meta name="description" content="Web camera with view towards west from Vik, Gimsø, Lofoten, Norway.">
  <meta name="keywords" content="webcam,webcamera,web cam, web camera,vik,gimsøy,lofoten,lofoten islands,nordland,norway">
  <meta name="robot" content="index, nofollow" />
  <meta name="generator" content="kamera.php: https://github.com/cloveras/kamera">
  <meta name="author" content="Christian Løverås">
  <link rel="stylesheet" type="text/css" href="/style-viktun.css" />

  <link rel="apple-touch-icon" sizes="57x57" href="/favicon/apple-icon-57x57.png">
  <link rel="apple-touch-icon" sizes="60x60" href="/favicon/apple-icon-60x60.png">
  <link rel="apple-touch-icon" sizes="72x72" href="/favicon/apple-icon-72x72.png">
  <link rel="apple-touch-icon" sizes="76x76" href="/favicon/apple-icon-76x76.png">
  <link rel="apple-touch-icon" sizes="114x114" href="/favicon/apple-icon-114x114.png">
  <link rel="apple-touch-icon" sizes="120x120" href="/favicon/apple-icon-120x120.png">
  <link rel="apple-touch-icon" sizes="144x144" href="/favicon/apple-icon-144x144.png">
  <link rel="apple-touch-icon" sizes="152x152" href="/favicon/apple-icon-152x152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-icon-180x180.png">
  <link rel="icon" type="image/png" sizes="192x192"  href="/favicon//android-icon-192x192.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96" href="/favicon/favicon-96x96.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
  <link rel="manifest" href="/favicon/manifest.json">
  <meta name="msapplication-TileColor" content="#ffffff">
  <meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
  <meta name="theme-color" content="#ffffff">

END1;

    if (! $_SERVER['SERVER_NAME']) {
        $_SERVER['SERVER_NAME'] = "superelectric.net";
        $_SERVER['SCRIPT_NAME'] = "kamera.php";
    }
    if ($previous) {
        print "  <link rel=\"prev\" title=\"Previous\" href=\"http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . "$previous\" />\n";
    }
    if ($next) {
        print "  <link rel=\"next\" title=\"Next\" href=\"http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . "$next\" />\n";
    }

    print <<<END2
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>$title</title>
END2;

    // Javascript for navigation using arrow keys. Only print the ones that do something.
    if ($previous || $next || $up || $down) {
        print "\n\n<!-- Javascript for navigation using arrow keys. -->\n";
        print "<script>\n";
        if ($previous) {
            print "  function leftArrowPressed() {\n";
            print "    window.location.href=\"$previous\";\n";
            print "  }\n\n";
        }
        if ($next) {
            print "  function rightArrowPressed() {\n";
            print "    window.location.href=\"$next\";\n";
            print "  }\n\n";
        }
        if ($up) {
            print "  function upArrowPressed() {\n";
            print "    window.location.href=\"$up\";\n";
            print "  }\n\n";
        }
        if  ($down) {
            print "  function downArrowPressed() {\n";
            print "    window.location.href=\"$down\";\n";
            print "  }\n\n";
        }
        print "  document.onkeydown = function(evt) {\n";
        print "    evt = evt || window.event;\n";
        print "      switch (evt.keyCode) {\n";
        if ($previous) {
            print "        case 37:\n";
            print "          leftArrowPressed();\n";
            print "          break;\n";
        }
        if ($up) {
            print "        case 38:\n";
            print "          upArrowPressed();\n";
            print "          break;\n";
        }
        if ($next) {
            print "        case 39:\n";
            print "          rightArrowPressed();\n";
            print "          break;\n";
        }
        if ($down) {
            print "        case 40:\n";
            print "          downArrowPressed();\n";
            print "          break;\n";
        }
        print "      }\n";
        print "    };\n";
        print "</script>\n\n";
    }

    // Print the rest of the top of the page, including page title.
    // Remember to change the Google Analytics id.
    print<<<END3
</head>
<body>

<!-- You will now get ads for web cameras everywhere. -->
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-Y0HSL2DBBC"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-Y0HSL2DBBC');
</script>

<h1>$title</h1>

<p>
Webcamera at
<a href="https://lilleviklofoten.no">Lillevik Lofoten</a>, Vik, Gimsøy, Lofoten, Norway.
</p>

END3;
}

// Debug
// ------------------------------------------------------------
function debug($txt) {
    global $debug;
    if ($debug) {
        print "$txt<br/>\n";
    }
}


// Footer
// ------------------------------------------------------------
function footer($count, $previous, $next, $up, $down) {

    print "\n<p>Use the arrow keys to navigate ";

    if ($next) {
        print "<a href=\"$next\">forward</a> (&#9654;), ";
    } else {
        print "forward (&#9654;), ";
    }

    if ($previous) {
        print "<a href=\"$previous\">back</a> (&#9664;), ";
    } else {
        print "back (&#9664;), ";
    }

    if ($up) {
        print "<a href=\"$up\">up</a> (&#9650;) ";
    } else {
        print "up (&#9650;) ";
    }

    if ($down) {
        print "og <a href=\"$down\">down</a> (&#9660;).</p>\n\n<p>\n";
    } else {
        print "and down (&#9660;).</p>\n\n<p>\n";
    }

    if ($count > 0) {
        print "<a href=\"#\">To the top</a>.\n"; // Include link to top of page only if this is a "long" page.
    }
    print "<a href=\"https://lilleviklofoten.no\">Lillevik Lofoten</a>. \n";
    print "</body>\n</html>\n";
}


// Get variables from the date part of the image filename.
// ------------------------------------------------------------
function split_image_filename($image_filename) {
    $image_filename = preg_replace('/^.*image-/', '', $image_filename); // Remove everything up to and including the '/'.
    $image_filename = preg_replace('/.jpg/', '', $image_filename); // Remove the .jpg suffix.
    // 2015120209401201
    // YYYYMMDDHHMMSSFF
    $year = substr($image_filename, 0, 4);
    $month = substr($image_filename, 4, 2);
    $day = substr($image_filename, 6, 2);
    $hour = substr($image_filename, 8, 2);
    $minute = substr($image_filename, 10, 2);
    $seconds = substr($image_filename, 12, 4);
    debug("<br/>split_image_filename($image_filename): $year-$month-$day $hour:$minute:$seconds");
    return array($year, $month, $day, $hour, $minute, $seconds);
}

// Midnight sun? (tested with date_sunrise() and GPS coorinates used in this script on yr.no)
// ------------------------------------------------------------
function midnight_sun($timestamp, $latitude, $longditude) {
    // Details: https://en.wikipedia.org/wiki/Midnight_sun
    $return = false;
    // TODO: Base this on latitude and longditude.
    /*
      print "($timestamp, $latitude, $longditude)<br/>";
      print date('Y-m-d H:i', $timeStamp) . "<br/>";
      $sun_info = date_sun_info($timestamp, $latitude, $longditude);
      print_r($sun_info);
      print "<p>";
      foreach($sun_info as $s => $i){
      echo "$s: $i<br/>";
      }
      $sun_info = date_sun_info($timestamp, $latitude, $longditude);
      if ($sun_info['sunset'] == 1) {
      // The sun does not set: Midnight sun.
      print "<br/>Midnight sun!<br/>";
      $return = true;
      }
    */
    $month = date('m', $timestamp);
    $day = date('d', $timestamp);
    $return = (($month == 5 && $day >= 24) || ($month == 6) || ($month == 7 && $day <= 18));
    return $return;
}

// Is it polar night (no sun)? (tested with date_sunrise() and GPS coordinates used in this script on yr.no)
// ------------------------------------------------------------
function polar_night($timestamp, $latitude, $longditude) {
    // Details: https://en.wikipedia.org/wiki/Polar_night
    $return = false;
    // TODO: Base this on latitude and longditude.
    /*
      print "($timestamp, $latitude, $longditude)<br/>";
      print date('Y-m-d H:i', $timeStamp) . "<br/>";
      $sun_info = date_sun_info($timestamp, $latitude, $longditude);
      print_r($sun_info);
      if (! $sun_info['sunrise']) {
      // The sun does not rise: Polar night.
      print "<br/>Polar night!<br/>";
      $return = true;
      }
    */
    $month = date('m', $timestamp);
    $day = date('d', $timestamp);
    $return = (($month == 12 && $day >= 6) || ($month == 1 && $day <= 6));
    return $return;
}

// Find sunrise and sunset, return all kinds of stuff we need later.
// ------------------------------------------------------------
function find_sun_times($timestamp) {
    // Return timestamps for everything.
    $sunrise = 0;
    $sunset = 0;
    $dawn = 0;
    $dusk = 0;
    $midnight_sun = false;
    $polar_night = false;
    $polar_night_sunrise_hour = 8; // When to start showing images during the polar night.
    $polar_night_sunset_hour = 15; // When to stop showing images during the polar night.
    $polar_night_hours = 30; // Adding this to the hours below.
    $adjust_dawn_dusk = 3 * 60 * 60; // How much before/after sunrise/sunset is dawn/dusk.

    // Where: Årstrandveien 6630, 8314 Gimsøysand: 68.33007, 14.09165
    $latitude = 68.33007; // North
    $longditude = 14.09165 ; // East

    // We will need these below.
    $year = date('Y', $timestamp);
    $month = date('m', $timestamp);
    $day = date('d', $timestamp);

    // find the timestamps for sunrise, sunset, dawn and dusk (as unix timestamps)
    if (midnight_sun($timestamp, $latitude, $longditude)) {
        // Sun all the time.
        $midnight_sun = true;
        $sunrise = mktime(0, 0, 0, $month, $day, $year); // Midnight
        $sunset = mktime(23, 59, 59, $month, $day, $year); // Almost midnight again
        $dawn = $sunrise;
        $dusk = $sunset;
    } else if (polar_night($timestamp, $latitude, $longditude)) {
        // No sun at all.
        $polar_night = true;
        // We still need to show a few images, so: faking sunrise and sunset.
        $sunrise = mktime($polar_night_sunrise_hour, $polar_night_hours, 0, $month, $day, $year);
        $sunset = mktime($polar_night_sunset_hour, $polar_night_hours, 0, $month, $day, $year);
        $dawn = $sunrise;
        $dusk = $sunset;
    } else {
        // Do the math! Use the $timestamp passed as parameter.
        debug("Normal sun: No midnight sun, no polar night.");
        debug("timestamp: $timestamp, human-readable: " . date('Y-m-d H:i:s', $timestamp));
        //debug("\$sunrise = date_sunrise(" . $timestamp . ", SUNFUNCS_RET_TIMESTAMP, " . $latitude . ", " . $longditude . " , " . $zenith . ", 1);");
        //$sunrise = date_sunrise($timestamp, SUNFUNCS_RET_TIMESTAMP, $latitude, $longditude, $zenith, 1);
        //$sunset = date_sunset($timestamp, SUNFUNCS_RET_TIMESTAMP, $latitude, $longditude, $zenith, 1);

        
        // Get all sun info with PHP 8's built-in functionality.
        debug("date_sun_info(" . $timestamp . ", " . $latitude . ", " . $longditude . "))");
        $sun_info = date_sun_info($timestamp, $latitude, $longditude);
        foreach ($sun_info as $key => $val) {
            debug("sun_info[$key]: $val (human-readable: " . date("H:i:s", $val));
        }
        
        $sunrise = $sun_info['sunrise'];
        if ($sunrise == 1) {
            // In case date_sun_info reports midnight sun "incorrectly",
            $sunrise = mktime(mktime(0, 0, 0, $month, $day, $year)); // Midnight
        }
                     
        $sunset = $sun_info['sunset'];
        if ($sunset == 1) {
            // In case date_sun_info reports midnight sun "incorrectly",
            $sunset =  mktime(23, 59, 59, $month, $day, $year); // Almost midnight again   
        }
            
        debug("sunrise: $sunrise, sunset: $sunset");

        //$dawn = $sunrise - $adjust_dawn_dusk;
        //$dusk = $sunset + $adjust_dawn_dusk;
        $dawn = $sun_info['civil_twilight_begin'];
        $dusk = $sun_info['civil_twilight_end'];
        debug("dawn: $dawn, dusk: $dusk");
    }

    // At the beginning and end of the midnight sun and polar night periods, the sun may rise/set the day before/after.
    $day_start = mktime(0, 0, 0, date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp)); // 00:00:00
    $day_end = mktime(23, 59, 59, date('m', $timestamp), date('d', $timestamp), date('Y', $timestamp)); // 23:59:59
    // Check if dawn/dusk are have been set too early/late above, and reset to start/end of day.
    if ($sunrise - $adjust_dawn_dusk < $day_start) {
        // The time from 00:00:00 to sunrise is less than the adjustment time. Set dawn to start of day.
        $dawn = $day_start;
    }
    if ($sunset + $adjust_dawn_dusk > $day_end) {
        // The time from sunset to 23:59:59 is less than the adjustment time. Set dusk to end of day.
        $dusk = $day_end;
    }

    debug("<br/>find_sun_times($timestamp) (" . date('Y-m-d H:i', $timestamp) . ")");
    debug("dawn: $dawn (" . date('Y-m-d H:i', $dawn) . ")");
    debug("sunrise: $sunrise (" . date('Y-m-d H:i', $sunrise) . ")");
    debug("sunset: $sunset (" . date('Y-m-d H:i', $sunset) . ")");
    debug("dusk: $dusk (" . date('Y-m-d H:i', $dusk) . ")");
    debug("midnight_sun: $midnight_sun");
    debug("polar_night: $polar_night");
    debug("adjust_dawn_dusk: $adjust_dawn_dusk");
    return array($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night);
}

// Print one image for every day in the month.
// ------------------------------------------------------------
function print_full_month($year, $month) {
    debug("<br/>print_full_month($year, $month)");
    global $size;
    global $monthly_day;
    global $monthly_hour;

    // Find previous and next month, and create the links to them.
    list($year_previous, $month_previous, $year_next, $month_next) = find_previous_and_next_month($year, $month);
    $previous = "?type=month&year=$year_previous&month=$month_previous&size=$size"; // Previous month.
    $next = "?type=month&year=$year_next&month=$month_next&size=$size"; // Next month.
    $up = "?type=year&year=$year"; // Up: SHow the full year.
    // Down goes to the first day n this month that has images.
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
    $title = "Lillevik Lofoten webcam: " . date("Y-m", $timestamp) . " ca. $monthly_hour:00 each day";
    page_header($title, $previous, $next, $up, $down);

    list($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night) = find_sun_times($timestamp);
    print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, "average");
    print_yesterday_tomorrow_links($timestamp, true);

    $count = 0;
    for ($i = 1; $i <= 31; $i+=1) { // Works for February and 30-day months too.
        $now = mktime($hour, $minute, $second, $month, $i, $year);
        $i = sprintf("%02d", $i); // Need to pad the days with 0 first. Still works fine in for() above.
        $directory = date('Ymd', $now);
        // Get all *jpg images that start with the right year, month, day and hour.
        if (file_exists($directory)) {
            debug("Directory exists: $directory");
            // Getting the latest image in that directory for that hour.
            $image = get_latest_image_in_directory_by_date_hour($directory, $monthly_hour);
            if ($image) {
                debug("Image found: $image");
                // There was at least one image: 20151127/image-2015112700003401.jpg
                $image_datepart = get_date_part_of_image_filename($image);
                list($year, $month, $day, $hour, $minute, $seconds) = split_image_filename($image_datepart);
                // Print it!
                if ($count == 0) {
                    print "<p>\n";
                }
                print "<a href=\"?type=day&date=$year$month$day\">";
                if ($size == "small" || empty($size)) {
                    // Print small images.
                    if (file_exists("$year$month$day/small/image-$image_datepart.jpg")) {
                        // If the small version has been created: Use that.
                        print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"160\" height=\"120\" src=\"$year$month$day/small/image-$image_datepart.jpg\"/></a>\n";
                    } else {
                        // If not: scale down the large version.
                        print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"160\" height=\"120\" src=\"$image\"/></a>\n";
                    }
                } else if ($size == "large") {
                    // Print large images.
                    print "<a href=\"?type=one&image=$year$month$day$hour$minute$seconds\">";
                    print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"640\" height=\"480\" src=\"$image\"/></a><br/>\n";
                }
                $count += 1; // Count the image just printed.
            }
        } else {
            debug("Directory does not exist: $directory");
        }
    }
    if ($count > 0) {
        print "</p>\n";
    } else {
        print "<p>(No photos to display for " . date("Y-m-d", $timestamp) . ")</p>\n"; // No pictures found for this month.
    }
    footer($count, $previous, $next, $up, $down);
}

// Print images for a whole year.
// ------------------------------------------------------------
function print_full_year($year) {
    debug("<br/>print_full_year($year)");
    global $size;
    if (!$size) {
        $size = "small";
    }
    //$days = array(1, 8, 15, 23); // Four days per month.
    $days = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31);
    $hour = 11;

    // Find previous and next year, and create the links to them.
    $previous = "?type=year&year=" . ($year - 1);
    if ($year < date('Y')) {
        $next = "?type=year&year=" . ($year + 1); // Next only if it exists.
    } else {
        $next = false;
    }
    $up = false;
    // Down goes to the first month that has images.
    $down = false;
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

    page_header("Lillevik Lofoten: $year", $previous, $next, $up, $down);
    print_previous_next_year_links($year);

    // Links to all months.
    print "\n<p>Months: \n";
    for ($i = 1; $i <= 12; $i++) {
        $month_timestamp = mktime(12, 0, 0, $i, 1, $year);
        print "<a href=\"?type=month&year=$year&month=" . sprintf("%02d", $i) . "\">";
        if ($i == 1) {
            print date("m", $month_timestamp) . "</a>, \n";
        } else if ($i == 11) {
            print date("m", $month_timestamp) . "</a> and \n"; // "And" after November.
        } else if ($i == 12) {
            print date("m", $month_timestamp) . "</a>.\n"; // Period after December.
        } else {
            print date("m", $month_timestamp) . "</a>, \n"; // Comma after the other months.
        }
    }
    print "<a href=\"?\">Today: " . date("M d") . "</a>, \n";
    print "</p>\n\n";

    // Loop through all months 1-12 (again, sorry) and print images for the $days if they exist.
    $count = 0;
    $image_datepart = "";
    $image_filename = "";
    for ($month = 1; $month <= 12; $month++) {
        $month = sprintf("%02d", $month);
        // Check for each of the days in the $days array
        foreach ($days as $day) {
            $day = sprintf("%02d", $day);
            // Find first image for that day taken after $hour
            $image_datepart = find_first_image_after_time($year, $month, $day, $hour, 0, 0);
            if ($image_datepart) {
                // Something was found.
                $minute = substr($image_datepart, 10, 2);
                debug("Found image: $image_datepart");
                $image_filename = $year . "$month$day/" . "image-" . $image_datepart . ".jpg";
                debug("Filename: $image_filename");
                // Print it!
                if ($count == 0) {
                    print "<p>\n";
                }
                debug("size:" . $size);
                if ($size == "small") {
                    // Print small images.
                    print "<a href=\"?type=one&image=$year$month$day$hour$minute$seconds\">";
                    if (file_exists("$year$month$day/small/image-$image_datepart.jpg")) {
                        debug("file_exists($year$month$day/small/image-$image_datepart.jpg");
                        // If the small version has been created: Use that.
                        print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"160\" height=\"120\" src=\"$year$month$day/small/image-$image_datepart.jpg\"/></a>\n";
                    } else {
                        // If not: scale down the large version.
                        print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"160\" height=\"120\" src=\"$image\"/></a>\n";
                    }
                } else if ($size == "large") {
                    // Print large images.
                    print "<a href=\"?type=one&image=$year$month$day$hour$minute$seconds\">";
                    print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"640\" height=\"480\" src=\"$image\_filename\"/></a><br/>\n";
                }
                $count += 1;
            }
        }
    }
    if ($count > 0) {
        print "</p>\n";
    } else {
        print "<p>(No photos to display for " .  date("Y", mktime(12, 0, 0, 1, 1, $year)) . ")</p>\n"; // No pictures found for this year.
    }
    footer($count, $previous, $next, $up, $down);
}

// Print images for all years
// ------------------------------------------------------------
function print_all_years() {
    debug("<br/>print_all_years()");
    global $size;
    //$days = array(1, 8, 15, 23); // Four days per month.
    $days = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31); // I don't have that many images yet..
    $hour = 11;

    // Find previous and next year, and create the links to them.
    $previous = false;
    $next = false;
    $up = false;
    $down = false;

    // Find first year with images.
    $first_year_with_images = find_first_year_with_images();

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

    page_header("Lillevik Lofoten webcam: All days", $previous, $next, $up, $down);
    print "<a href=\"?\">Today: " . date("M d") . "</a>.\n";
    print "</p>\n\n";

    // Helpers for loops below.
    $count = 0;
    $image_datepart = "";
    $image_filename = "";

    // Loop through all years, please.
    for ($year = $first_year_with_images; $year <= date('Y'); $year++) {
        // Loop through all months (1-12) for this year and print images for the $days if they exist.
        for ($month = 1; $month <= 12; $month++) {
            $month = sprintf("%02d", $month);
            // Check for each of the days in the $days array
            foreach ($days as $day) {
                $day = sprintf("%02d", $day);
                // Find first image for that day taken after $hour
                $image_datepart = find_first_image_after_time($year, $month, $day, $hour, 0, 0);
                if ($image_datepart) {
                    $minute = substr($image_datepart, 10, 2);
                    // Something was found.
                    debug("Found image: $image_datepart");
                    $image_filename = $year . "$month$day/" . "image-" . $image_datepart . ".jpg";
                    debug("Filename: $image_filename");
                    // Print it!
                    if ($count == 0) {
                        print "<p>\n";
                    }
                    if ($size == "small") {
                        // Print small images.
                        print "<a href=\"?type=one&image=$year$month$day$hour$minute$seconds\">";
                        if (file_exists("$year$month$day/small/image-$image_datepart.jpg")) {
                            // If the small version has been created: Use that.
                            print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"160\" height=\"120\" src=\"$year$month$day/small/image-$image_datepart.jpg\"/></a>\n";
                        } else {
                            // If not: scale down the large version.
                            print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"160\" height=\"120\" src=\"$image\"/></a>\n";
                        }
                    } else if ($size == "large") {
                        // Print large images.
                        print "<a href=\"?type=one&image=$year$month$day$hour$minute$seconds\">";
                        print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour\" title=\"$year-$month-$day $hour:$minute\" width=\"640\" height=\"480\" src=\"$image\_filename\"/></a><br/>\n";
                    }
                    $count += 1;
                }
            }
        }
    }
    if ($count > 0) {
        print "</p>\n";
    } else {
        print "<p>(No photos to display - at all!</p>\n"; // No pictures found for this year.
    }
    footer($count, $previous, $next, $up, $down);
}

// Print links to small and large images
// ------------------------------------------------------------
function print_small_large_links($timestamp, $size) {
    $year = date('Y', $timestamp);
    $month = date('m', $timestamp);
    $day = date('d', $timestamp);
    print "<p>\n";
    if ($size == "large" || empty($size)) { // Link to small if we showed large, or don't know.
        print "<a href=\"?type=day&date=$year$month$day&size=small\">Small photos</a>. ";
    }
    if ($size == "small" || empty($size)) { // Links to large if we showed small, or don't know.
        print "<a href=\"?type=day&date=$year$month$day&size=large\">Large photos</a>. ";
    }
    print "</p>\n\n";
}

// Returns only the date part of an image filename (removes directory "image-" and ".jpg").
// ------------------------------------------------------------
function get_date_part_of_image_filename($image_filename) {
    debug("get_date_part_of_image_filename($image_filename)");
    // Full image filename: 20151202/image-2015120210451101.jpg
    $image_filename = preg_replace("/^.*image-/", '', $image_filename); // Remove everything up to and including the '/'.
    $image_filename = preg_replace('/.jpg/', '', $image_filename); // Remove the .jpg suffix.
    debug("datepart: $image_filename");
    return $image_filename;
}

// Finds the latest "*jpg" file in the newsst "2*" directory. Returns only date part of filename.
// ------------------------------------------------------------
function find_latest_image() {
    // Find newest directory with the right name format
    $directories = array_reverse(glob("2*")); // Get the latest first. 2* works until the year 3000.
    $directory = $directories[0];
    // Find newest image in the newest directory
    $images = array_reverse(glob("$directory/image*jpg")); // Get the latest *jpg file in the directory.
    // Getting 20151202/image-2015120209401201.jpg
    $image = $images[0];
    debug("<br>find_latest_image()<br/>directory: $directory<br/>image: $image");
    $image = get_date_part_of_image_filename($image);
    debug("image (datepart): $image");
    // Now: 2015120209401201
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

// Gets all images in the directory for a specific day (20151202).
// ------------------------------------------------------------
function get_all_images_in_directory($directory) {
    $images = glob("$directory/image-*.jpg");
    debug("<br/>get_all_images_in_directory($directory/image-*.jpg): " . count($images) . " images found.");
    return $images;
}

// Gets all images in the directory for a specific day. Returns date part: 2015120209401201 .
// ------------------------------------------------------------
function get_latest_image_in_directory_by_date_hour($directory, $hour) {
    $images = glob("$directory/image-$directory$hour*.jpg");
    debug("<br/>get_latest_image_in_directory_by_date_hour($directory, $hour)<br/>Found " . count($images) . "images, returning " . $images[0]);
    return $images[0];
}

// Find the first image after a given time. Used when going to the first image in a day.
// ------------------------------------------------------------
function find_first_image_after_time($year, $month, $day, $hour, $minute, $seconds) {
    if ($minute < 10) {
        $minute = sprintf("%02d", $minute);
    }
    if ($seconds < 10) {
        $seconds = sprintf("%02d", $seconds);
    }
    debug("<br/>find_first_image_after_time($year, $month, $day, $hour, $minute, $seconds)");
    // Find all images for the specified date and hour (the minutes are checked further below).
    $image = "";
    $images = glob("$year$month$day/image-$year$month$day$hour*");
    debug("Looking in directory: $year$month$day/image-$year$month$day$hour*");
    // Check if minutes are after the minutes passed as parameter (do not return a "too early" image).
    foreach ($images as $image) {
        debug("Now checking $image");
        // Get the date info for this image.
        list($year_split, $month_split, $day_split, $hour_split, $minute_split, $seconds_split) = split_image_filename($image);
        $seconds_split_compare = substr($seconds_split, 0, 2); // Not comparing with subseconds.
        if ("$hour$minute_split$seconds_split_compare" >= "$hour$minute$seconds") {
            // The image we are checking is taken after the time passed as parameter.
            $image = "$year$month$day$hour$minute_split$seconds_split"; // Now we need the subseconds.
            debug("Success ($hour:$minute_split:$seconds_split >= $hour:$minute:$seconds): New image name: $image");
            break; // Success! This image was taken after the hour and minute passed as parameter.
        } else if ($hour_split > $hour) {
            $image = ""; // We have tried all images taken that hour.
            debug("No image found for that hour, and all have been checked: $hour_split > $hour");
            break;
        }
    }
    if ($image) {
        $image = get_date_part_of_image_filename($image);
        // Now have 2015120209401201
    } else {
        debug("No image found for $year$month$day/image-$year$month$day$hour$minute");
    }
    return $image;
}

// Print a single image, specified by the date part of the filename (no .jpg suffix, no path)
// ------------------------------------------------------------
function print_single_image($image_filename) {
    // Works for 201511281504 and 2015112815 (minutes missing if this was arrow-down to get the first image)
    debug("<br/>print_single_image($image_filename)");

    if (strlen($image_filename) < strlen("YYYYMMDDHHMMSSSS")) {
        // We do not have the hour or the minutes. This is the first image in a day (arrow-down from full day).
        debug("Short filename! No seconds. Will find dawn and use minutes from there.");
        // Making timestamp, then finding dawn for this day.
        list($year, $month, $day, $hour, $minute, $seconds) = split_image_filename($image_filename);
        $timestamp = mktime($hour, $minute, 0, $month, $day, $year); // Using 0 for minutes to get the one(s) before too.
        // Find out when dawn is.
        list($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night) = find_sun_times($timestamp);
        // We now have dawn. Find the first image after dawn, using the hours and minutes and even seconds.
        $image_filename = find_first_image_after_time($year, $month, $day, $hour, date('i', $dawn), date('s', $dawn));
        debug("Filename fixed (added minutes:" . date('i', $dawn) . " and seconds:" . date('s', $dawn) . "): $image_filename");
    } else {
        debug("Filename was ok (minutes in filename): $image_filename");
    }
    // We have the full filename, with minutes.
    list($year, $month, $day, $hour, $minute, $seconds) = split_image_filename($image_filename);
    $timestamp = mktime($hour, $minute, 0, $month, $day, $year);

    // Get previous and next image: First get all images for the same day as the images passed as parameter.
    $directory = "$year$month$day";
    // Loop through all images in this day's directory and look for the one passed as parameter.
    $images = get_all_images_in_directory($directory);

    $previous_image = false;
    $next_image = false;
    $number_of_images = count($images); // Avoid counting in every iteration below.
    $i = 0;
    foreach($images as $image) {
        if (strpos($images[$i], $image_filename) !== false) { // Faster than preg_match().
            // We found the one passed as paramter, now get previous and next.
            debug("MATCH: $image_filename == $images[$i]");
            $image_filename = "image-" . get_date_part_of_image_filename($images[$i]) . ".jpg";
            debug("Full name of found file: $image_filename");
            // We found the image that was passed as a parameter.
            if ($i != 0) {
                // This was not the first image in the array, get the previous one.
                $previous_image = $images[$i - 1];
            }
            if ($i != $number_of_images) {
                // This was not the last image in the array, get the next one.
                $next_image = $images[$i + 1];
            }
            break;
        }
        $i += 1;
    }

    // Links to previous, next, up, down.
    if ($previous_image) {
        $previous_datepart = get_date_part_of_image_filename($previous_image);
        $previous = "?type=one&image=$previous_datepart"; // Only date for the link.
    }
    if ($next_image) {
        $next_datepart = get_date_part_of_image_filename($next_image);
        $next = "?type=one&image=$next_datepart"; // Only date for the link.
    }
    $up_datepart = get_date_part_of_image_filename($image_filename);
    $up = "?type=day&date=$up_datepart"; // The full day.
    $down = false; // Already showing a single image, not possible to go lower.

    // Print!
    $title = "Lillevik Lofoten webcam: " . date("Y-m-d H:i", $timestamp);
    page_header($title, $previous, $next, $up, $down);
    list($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night) = find_sun_times($timestamp);
    print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, false);
    print_full_day_link($timestamp);
    print "<p>";
    if ($previous_datepart) {
        print "<a href=\"$previous\">Previous: " . substr($previous_datepart, 8, 2) . ":" . substr($previous_datepart, 10, 2) . "</a>.\n";
    }
    if ($next_datepart) {
        print "<a href=\"$next\">Next: " . substr($next_datepart, 8, 2) . ":" . substr($next_datepart, 10, 2) . "</a>.\n";
    }
    debug("Showing image: $year$month$day/$image_filename");
    print "\n<p>";
    print "<a href=\"?type=day&date=$year$month$day\">";
    print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"640\" height=\"480\" src=\"$year$month$day/$image_filename\"/>";
    print "</a>";
    print "</p>\n";
    footer($count, $previous, $next, $up, $down);
}

// Print details about the sun, and what images are shown.
// ------------------------------------------------------------
function print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, $include_interval) {
    global $monthly_day;
    print "<p>";
    if ($midnight_sun) {
        print "Midnight sun &#9728;";
    } else if ($polar_night) {
        print "Polar night";
    } else {
        print "Sunrise: " . date('H:i', $sunrise) . ". Sunset: " . date('H:i', $sunset);
    }
    if ($include_interval == "day") {
        print ". Displaying photos taken between " . date('H:i', $dawn) . " and " . date('H:i', $dusk);
    } else if ($include_interval == "average") {
        print " (calculated for " . date('M', $dawn) . " $monthly_day)";
    }
    print ".</p>\n\n";
}

// Find the previous and next month, even for January and December.
// ------------------------------------------------------------
function find_previous_and_next_month($year, $month) {
    $month_previous = "";
    $year_previous = $year;
    $month_next = "";
    $year_next = $year;
    // Find previous month
    if ($month == 1) {
        $month_previous = 12;
        $year_previous = sprintf("%4d", $year - 1);
    } else {
        $month_previous = sprintf("%02d", $month - 1);
    }
    // Find next month
    if ($month == 12) {
        $month_next = "01";
        $year_next = sprintf("%4d", $year + 1);
    } else {
        $month_next = sprintf("%02d", $month  + 1);
    }
    debug("<br/>find_previous_and_next_month($year, $month)<br/>year_previous: $year_previous<br/>month_previous: $month_previous<br/>year_next: $year_next<br/>month_next: $month_next");
    return array($year_previous, $month_previous, $year_next, $month_next);
}

// Links to previsou and next year.
// ------------------------------------------------------------
function print_previous_next_year_links($year) {
    print "<p><a href=\"?type=year&year=" . ($year - 1) . "\">Previous (" . ($year - 1) . ")</a>.\n";
    if ($year < date('Y')) {
        print "<a href=\"?type=year&year=" . ($year + 1) . "\">Next (" . ($year + 1) . ")</a>.\n";
    }
    print "<p>\n";
}

// Links to yesterday and (possibly) tomorrow.
// ------------------------------------------------------------
function print_yesterday_tomorrow_links($timestamp, $is_full_month) {
    global $size;

    if ($is_full_month) {
        // Not links to yesterday and tomorrow, but the the previous and next months. Easy.
        list($year_previous, $month_previous, $year_next, $month_next) = find_previous_and_next_month(date('Y', $timestamp), date('m', $timestamp));
        print "<p><a href=\"?type=month&year=$year_previous&month=$month_previous\">Previous: " . date("M", mktime(0, 0, 0, $month_previous, 1, $year_previous)) . "</a>. \n";
        //mktime(0, 0, 0, $month_previous, 1, $year);
        print "<a href=\"?type=month&year=$year_next&month=$month_next\">Next: " .  date("M", mktime(0, 0, 0, $month_next, 1, $year_previous)) . "</a>. \n";
        $this_month = date('Y-m'); // 2015-12
        $previous_month = date('Y-m', time() - 60 * 60 * 24 * 30); // 2015-11
        $requested_month = date('Y-m', $timestamp);
        if ($requested_month != $this_month) {
            print "<a href=\"?type=month&year=" . date('Y') . "&month=" . date('m') . "\">Now: " . date("M") .  "</a>. \n";
        }
        print "<a href=\"?\">Today: " . date("M d") . "</a>.\n";
        print "<a href=\"?type=year&year=" . date('Y', $timestamp) . "\">Entire " . date('Y', $timestamp) . "</a>.\n";
        //print "<a href=\"?type=4ever\">Everything</a>.\n";
    } else {
        // Work hard to find the days.
        // Yesterday always exists.
        $yesterday_timestamp = $timestamp - 60 * 60 * 24;
        print "<p>\n<a href=\"?type=day&date=" . date('Ymd', $yesterday_timestamp) . "&size=$size\">Previous: " . date("M d", $yesterday_timestamp). "</a>.\n";
        // Is there a tomorrow, based on the selected day?
        $tomorrow_timestamp = $timestamp + 60 * 60 * 24;
        if (date('Y-m-d', $tomorrow_timestamp) > date('Y-m-d')) {
            // The next day is after the current day, so there will be no images to show.
        } else if (date('Ymd', $tomorrow_timestamp) != date('Y-m-d', $timestamp)) {
            // The next day is a day that we have images for.
            print "<a href=\"?type=day&date=" . date('Ymd', $tomorrow_timestamp) . "\">Next: " . date("M d", $tomorrow_timestamp) . "</a>.\n";
        }

        // Link to "today" if we are further back than the day before yesterday.
        $yesterday = strtotime("-1 day", time());
        $yesterday_formatted = date('Y-m-d', $yesterday);
        debug("yesterday_formatted: $yesterday_formatted");
        // Should we show both "next" and "today" links? Extra detailed, since this was confusing at the time.
        if ($yesterday_formatted == date('Y-m-d', $timestamp)) {
            // The day shown was the day before the current day (meaning: yesterday).
        } else if (date('Y-m-d') == date('Y-m-d', $timestamp)) {
            // The day shown was the current date.
        } else {
            // The day shown was the day before yesterday, or earlier.
            print "<a href=\"?\">Today: " . date("M d") . "</a>.\n";
        }
        // Link to the full month and year - and everything.
        //------------------------------------------------------------
        print "<a href=\"?type=month&year=" . date('Y', $timestamp) . "&month=" . date('m', $timestamp) . "\">Entire " . date("M") . "</a>.\n";
        print "<a href=\"?type=year&year=" . date('Y', $timestamp) . "\">Entire " . date('Y', $timestamp) . "</a>.\n";
        //print "<a href=\"?type=4ever\">Everything</a>.\n";
    }
    print "</p>\n\n";
}

// Print link to alle images for the day specified with a timestamp.
// ------------------------------------------------------------
function print_full_day_link($timestamp) {
    $year= date('Y', $timestamp);
    $month = date('m', $timestamp);
    $day = date('d', $timestamp);
    print "<p><a href=\"?type=day&date=$year$month$day\">The whole day</a>.</p>\n\n";
}

// Print all images in a diretory, between dawn and dusk, with small/large size, optionally limited by a number.
// ------------------------------------------------------------
function print_full_day($timestamp, $image_size, $number_of_images) {
    global $size;
    debug("print_full_day($timestamp, $image_size, $number_of_images)");

    list($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night) = find_sun_times($timestamp);

    // Set the navigation (we need $dusk from above).
    $previous = "?type=day&date=" . date('Ymd', $timestamp - 60 * 60 * 24) . "&size=$size"; // The previous day.
    $next = "?type=day&date=" . date('Ymd', $timestamp + 60 * 60 * 24) . "&size=$size"; // The next day.
    $up = "?type=month&year=" . date('Y', $timestamp) . "&month=" . date('m', $timestamp); // Full month.
    $down = "?type=one&image=" . date('Ymd', $timestamp) . date('H', $dawn); // First image this day (no minutes, as image may not be taken exactly at dawn).

    // Print header now that we have the details for it.
    $title = "Lillevik Lofoten webcam: " . date('Y-m-d', $timestamp);
    if ($number_of_images == 1) {
        // We are printintg just the latest image, so include hour and minute too.
        $title .= " " . date('H', $timestamp) . ":" . date('i', $timestamp);
    }

    page_header($title, $previous, $next, $up, $down);
    print_sunrise_sunset_info($sunrise, $sunset, $dawn, $dusk, $midnight_sun, $polar_night, $number_of_images != 1);
    print_small_large_links($timestamp, $size);
    print_yesterday_tomorrow_links($timestamp, false);

    // Get all *jpg images in "today's" image directory.
    $directory = date('Ymd', $timestamp);
    $count = 0;
    debug("Getting images from directory: <a href=\"$directory\">$directory</a>");
    if (file_exists($directory)) {
        debug("Directory exists: ". $directory);  
        $images = glob("$directory/*.jpg");
        // Loop through all images. Reverse sort to start with the latest image at the top.
        foreach(array_reverse($images) as $image) {
            // Each filename is of this type: 20151123/image-2015112319140001.jpg
            debug("Foreach: image: " . $image);
            $image_datepart = get_date_part_of_image_filename($image); // Get the "2015112319140001" part.
            list($year, $month, $day, $hour, $minute, $seconds) = split_image_filename($image_datepart); // Split into variables.
            // Create timestamp top check if this image is from between dawn and dusk.
            $image_timestamp = mktime($hour, $minute, substr($seconds, 0, 2), $month, $day, $year); // Skip the subseconds.
            debug("image_timestamp = mktime($hour, $minute, " . substr($seconds, 0, 2) . ", $month, $day, $year)");
            debug("image_timestamp: $image_timestamp<br/>dawn: $dawn<br/>dusk: $dusk");
            if (($image_timestamp <= $dusk) && ($image_timestamp >= $dawn)) {
                debug("Image timestamp inside dusk and dawn: Dawn: " . date('H:i:s', $dawn) . " Image timestamp: " . date('H:i:s', $image_timestamp) . " Dusk: " . date('H:i:s', $dusk));
                //debug(": " . date('H:i:s', $dawn) . " / " . date('H:i:s', $image_timestamp) . " / " . date('H:i:s', $dusk));
                if ($count == 0) {
                    print "<p>\n";
                }
                if ($image_size == "large") {
                    // Print full size with linebreaks.
                    print "<p>";
                    print "$hour:$minute<br/>";
                    print "<a href=\"?type=one&image=$year$month$day$hour$minute$seconds\">";
                    print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$hour:$minute\" width=\"640\" height=\"480\" src=\"$image\"/></a>";
                    print "</p>\n";
                } else {
                    // Default: Small (25%) without linebreaks.
                    print "<a href=\"?type=one&image=$year$month$day$hour$minute$seconds\">";
                    if (file_exists("$year$month$day/small/image-$image_datepart.jpg")) {
                        // If the small version has been created: Use that.
                        print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"160\" height=\"120\" src=\"$year$month$day/small/image-$image_datepart.jpg\"/></a>\n";
                    } else {
                        // If not: scale down the large version.
                        print "<img alt=\"Lillevik Lofoten webcam: $year-$month-$day $hour:$minute\" title=\"$year-$month-$day $hour:$minute\" width=\"160\" height=\"120\" src=\"$image\"/></a>\n";
                    }
                }

                $count += 1;
                if ($count >= $number_of_images) {
                    print "</p>\n";
                    break;
                }
            } else {
                debug("Image timestamp outside dusk and dawn: Dawn: " . date('H:i:s', $dawn) . " Image timestamp: " . date('H:i:s', $image_timestamp) . " Dusk: " . date('H:i:s', $dusk));
            }
        }
    }
    if ($count > 0) {
        print "</p>\n";
    } else {
        print "<p>(No photos to display for " . date("Y-m-d", $timestamp) . ")</p>\n";
    }
    footer($count, $previous, $next, $up, $down);
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
$size = "small";
$type = "one";
$monthly_day = 1; // The day to use for full month view.
$monthly_hour = 11; // Time of day to use when showing full months.
$max_images = 1000; // Unless we are showing less.

// Debug: Set the date to something else than today.
// ------------------------------------------------------------
if ($debug) {
    $debug_year = "2023";
    $debug_month = "07";
    $debug_day = "19";
    $timestamp = mktime(0, 0, 0, $debug_month, $debug_day, $debug_year);
    print "Today (set in debug): " . date('Y-m-d H:i', $timestamp) . "<br/>\n";
}

// Sort out the QUERY_STRING
// ------------------------------------------------------------
if ($_SERVER['QUERY_STRING'] == 1) {
    $type = "last";
    debug("LAST");
} else if (empty($_SERVER['QUERY_STRING'])) {
    $type = "day";
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
debug("QUERY_STRING: " . $_SERVER['QUERY_STRING']);
debug("type: $type<br/>date: $date<br/>year: $year</br>month: $month</br>size: $size<br/>image: $image<br/>last_image: $last_image");

//$type = "day";

// Check the type, do the right thing
// ------------------------------------------------------------
if ($type == "last") {
    // Only the last image, even if it is after both sunset and dusk.
    $latest_image = find_latest_image();
    $latest_image_filename = get_date_part_of_image_filename($latest_image);
    print_single_image($latest_image_filename);
} else if ($type == "one") {
    // One specific image, the datepart is in the $image parameter (no path or .jpg): 2015112613051901
    print_single_image($image);
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
    /*
      }
      else if ($type == "4ever") {
      // One image for every day, 4-evah.
      print_all_years($year);
    */
} else {
    // Unknown type.
    page_header("Error", false, false, false, false);
    print "<p>Unknown type: \"$type\".</p>";
    print "<p><a href=\"javascript:history.back()\">Back</a>.</p>\n";
    footer();
}

?>

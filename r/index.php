<?php

include "../App/config.php";
include "../App/ShortUrl.php";

if(isset($_GET['c'])) {
    $code = $_GET["c"];
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS,'hometest');
    }
    catch (Exception $e) {
        print($e);
        trigger_error("Error: Failed to establish connection to database.");
        exit;
    }

    $shortUrl = new ShortUrl($conn);
    try {
        $url = $shortUrl->shortCodeToUrl($code);
        header("Location: " . $url);
        exit;
    }
    catch (Exception $e) {
        header("Location: /error");
        exit;
    }
} else {
    print <<<_HTML_
your url si not valid or expired
_HTML_;
}
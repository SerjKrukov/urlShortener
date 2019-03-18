<?php

include "./App/config.php";
include "./App/ShortUrl.php";

if(!isset($_POST['link'])) {
    print <<<_HTML_
<form method="post" action="$_SERVER[PHP_SELF]">
Your url: <input type="text" name="link" />
<br/>
short url ttl (s): <input type="number" name="ttl" />
<br>
<button type="submit">Say Hello</button>
</form>
_HTML_;
} else {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS,'hometest');
    }
    catch (Exception $e) {
        print($e);
        trigger_error("Error: Failed to establish connection to database.");
        exit;
    }

    $shortUrl = new ShortUrl($conn);
//    try {
        $code = $shortUrl->urlToShort($_POST['link'], $_POST['ttl']);
        printf('<p><strong>Short URL:</strong> <a href="%s" target="_blank">%1$s</a></p>',
            SHORTURL_PREFIX . $code);
        exit;
//    }
//    catch (Exception $e) {
//        header("Location: /error");
//        print_r($e);
//        exit;
//    }
}






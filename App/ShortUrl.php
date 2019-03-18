<?php
/**
 * Created by PhpStorm.
 * User: serj
 * Date: 17.03.19
 * Time: 14:43
 */

//namespace App;


//use mysql_xdevapi\Exception;

class ShortUrl
{
    protected static $chars = '1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
    protected static $table = 'short_urls';
    protected static $checkUrlExists = true;

    protected $pdo;
    protected $timestamp;

//    public function __construct(PDO $pdo)
    public function __construct(mysqli $pdo)
    {
//        $this->pdo = $pdo;
        $this->pdo = $pdo;
        $this->timestamp = $_SERVER['REQUEST_TIME'];
    }

    public function urlToShort($url, $ttl)
    {
        if (empty($url)) throw new Exception('You dont provide url');
        if ($this->validateUrl($url) == false) throw new Exception('Url not valid');
        if (self::$checkUrlExists) {
            if (!$this->urlExists($url)) throw new Exception("URL does not appear to exist.");
        }

        $shortCode = $this->urlExistsDb($url);
        if ($shortCode == false) {
            $shortCode = $this->createShortUrl($url, $ttl);
        }
        return $shortCode;
    }

    protected function validateUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    protected function urlExists($url)
    {
        $check = curl_init();
        curl_setopt($check, CURLOPT_URL, $url);
        curl_setopt($check, CURLOPT_NOBODY, true);
        curl_setopt($check, CURLOPT_RETURNTRANSFER, true);
        curl_exec($check);
        $response = curl_getinfo($check, CURLINFO_HTTP_CODE);
        curl_close($check);
        return (!(empty($response)) && $response != 404);
    }

    protected function urlExistsDB($url)
    {

        if ($stmt = $this->pdo->prepare("SELECT url_short FROM short_urls WHERE url_origin = ? LIMIT 1")) {
            $stmt->bind_param("s", $url);
            $stmt->execute();
            if ($stmt->error) print($stmt->error . " error select short<br>");
//            $result = $stmt->fetch();
            $stmt->bind_result($result);
            $stmt->fetch();
            $stmt->close();
            return (empty($result) ? false : $result);
        }

    }

    protected function createShortUrl($url, $ttl)
    {
        $id = $this->insertUrl($url, $ttl);
        $short = $this->convertUrlToShort($id);
        $this->insertShortUrl($id, $short);

        return $short;
    }

    protected function insertUrl($url, $ttl)
    {
        if(intval($ttl) < 1) $ttl = 1000 * 3600;
        if ($stmt = $this->pdo->prepare("INSERT INTO short_urls (url_origin, date_created, ttl) VALUES(?, ?, ?)")) {
            $stamp = $this->timestamp;
            print($ttl. ' default ttl');
            $ttlstamp = $stamp + $ttl;
            $dt = new DateTime("@$stamp");
            $dtf = $dt->format('Y-m-d H:i:s');
            $dtttl = new DateTime("@$ttlstamp");
            $dtttlf = $dtttl->format('Y-m-d H:i:s');
            $stmt->bind_param("sss", $url, $dtf, $dtttlf);
            $stmt->execute();
            if ($stmt->error) print($stmt->error . " error insert long<br>");
            $stmt->close();
            return $this->pdo->insert_id;
        } else {
            print($this->pdo->error);
        }
    }

    protected function insertShortUrl($id, $short)
    {
        if ($id == null || $short == null) throw new Exception('Input parameters invalid');
        if ($stmt = $this->pdo->prepare("UPDATE short_urls SET url_short = ? WHERE id = ?")) {
            $stmt->bind_param("si", $short, $id);
            $stmt->execute();
            if ($stmt->error) print($stmt->error . " error update short<br>");
            $stmt->close();
            print($short. "in short");
            if ($stmt->affected_rows < 1) print($stmt->error . " no rows affected<br>");
            return true;
        }
    }

    protected function convertUrlToShort($id)
    {
        $id = intval($id);
        if ($id < 1) throw new Exception('Not valid id');
        $length = strlen(self::$chars);
        if ($length < 10) throw new Exception('too short chars set');
        $code = "";

        while ($id > $length - 1) {
            $code = self::$chars[fmod($id, $length)] . $code;
            $id = floor($id / $length);
        }
        $code = self::$chars[$id] . $code;
        return $code;
    }

    public function shortCodeToUrl($code, $increment = true)
    {
        if (empty($code)) throw new Exception("No short code was supplied.");


        if ($this->validateShort($code) == false) throw new Exception("Short code does not have a valid format.");


        $urlRow = $this->getUrlFromDb($code);
        if (empty($urlRow)) throw new Exception("Short code does not appear to exist.");


        if ($increment == true) $this->incrementCounter($urlRow["id"]);

        return $urlRow["url_origin"];
    }

    protected function validateShort($code)
    {
        return preg_match("|[" . self::$chars . "]+|", $code);
    }

    protected function getUrlFromDb($code)
    {
        if ($stmt = $this->pdo->prepare("SELECT id, url_origin FROM short_urls WHERE url_short = ? AND ttl > ? LIMIT 1")) {
            $stamp = $this->timestamp;
            $dt = new DateTime("@$stamp");
            $dtf = $dt->format('Y-m-d H:i:s');
            $stmt->bind_param("ss", $code, $dtf);
            $stmt->execute();
            if ($stmt->error) print($stmt->error . " error select short<br>");
//            $result = $stmt->fetch();
            $rows = array();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free_result();
            print ($stmt->affected_rows);
            $stmt->close();
            return (empty($rows[0]) ? false : $rows[0]);
        }
//        $query = "SELECT id, url_origin FROM " . self::$table .
//            " WHERE url_short = :short LIMIT 1";
//        $stmt = $this->pdo->prepare($query);
//        $params = array(
//            "short" => $code
//        );
//        $stmt->execute($params);
//
//        $result = $stmt->fetch();
//        return (empty($result)) ? false : $result;
    }

    protected function incrementCounter($id)
    {
        if ($id == null ) throw new Exception('invalid id in counter update');
        if ($stmt = $this->pdo->prepare("UPDATE short_urls SET counter = counter + 1 WHERE id = ?")) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if ($stmt->error) print($stmt->error . " error update counter<br>");
            $stmt->close();
            if ($stmt->affected_rows < 1)  print($stmt->error . " error update counter<br>");
            return true;
        }
//        $query = "UPDATE " . self::$table .
//            " SET counter = counter + 1 WHERE id = :id";
//        $stmt = $this->pdo->prepare($query);
//        $params = array(
//            "id" => $id
//        );
//        $stmt->execute($params);
    }
}
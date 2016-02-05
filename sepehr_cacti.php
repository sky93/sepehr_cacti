<?php

/**
  ______________________________________________________________________
 \                                                                     \
 \ Sepehr Cacti script                                                 \
 \ Version 1.0 (2/5/2016)                                              \
 \_____________________________________________________________________\

 */
// Config:
$sepehr_env_path = 'E:\programming\PHP\sepehr\sepehr\.env';
$aria2_host      = '127.0.0.1';
$aria2_port      = '6800';




if (! file_exists($sepehr_env_path) || ! is_readable($sepehr_env_path)) {
    echo ".env file is not readable or does not exist";
    die();
}

$lines =  file($sepehr_env_path, FILE_IGNORE_NEW_LINES);
$config = [];

foreach ($lines as $line) {
    if ($line == '') {
        continue;
    }
    $a = explode('=', $line);
    $config[$a[0]] = $a[1];
}

class aria2{
    private $server;
    private $ch;

    function __construct($aria2_host, $aria2_port)
    {
        $host = $aria2_host;
        $port = $aria2_port;
        $route = 'jsonrpc';
        $server= "$host:$port/$route";
        $this->server = $server;
        $this->ch = curl_init($server);
        curl_setopt_array($this->ch,[
            CURLOPT_POST=>true,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HEADER=>false,
        ]);
    }

    function __destruct()
    {
        curl_close($this->ch);
    }

    private function req($data)
    {
        curl_setopt($this->ch,CURLOPT_POSTFIELDS,$data);
        return curl_exec($this->ch);
    }

    function __call($name,$arg)
    {
        if (substr($name, 0, 10) === 'JSON_INPUT') {
            $data = '{
                "jsonrpc":"2.0",
                "id":"1",
                "method":"aria2.' . substr($name, 10) . '",
                "params":' . $arg[0] . '
            }';
        } else {
            $data = [
                'jsonrpc' => '2.0',
                'id' => '1',
                'method' => 'aria2.' . $name,
                'params' => $arg,
            ];
            $data = json_encode($data);
        }
        return json_decode($this->req($data), 1);
    }
}


error_reporting(E_ERROR);
//Checks if mysqli library is installed
if (! function_exists('mysqli_connect')) {
    die("mysqli does not installed! Install it first!");
}

// Connect to database
$hostname = $config['DB_HOST'];
$database = $config['DB_DATABASE'];
$username = $config['DB_USERNAME'];
$password = $config['DB_PASSWORD'];

$conn = new mysqli($hostname, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Get registered users. Who has an email address
if ($result = mysqli_query($conn, "SELECT id FROM users WHERE email <> ''")) {
    $row_cnt = mysqli_num_rows($result);
    echo "registered_users:" . $row_cnt;
    mysqli_free_result($result);
} else {
    die ("Something went wrong when was trying to get registered users.");
}


// Get online users
$time = date("Y-m-d H:i:s", time() - 60);
if ($result = mysqli_query($conn, "SELECT id FROM users WHERE last_seen > '$time'")) {
    $row_cnt = mysqli_num_rows($result);
    echo " online_users:" . $row_cnt;
    mysqli_free_result($result);
} else {
    die ("Something went wrong when was trying to get online users.");
}


// Get number of downloads in queue
$time = date("Y-m-d H:i:s", time() - 60);
if ($result = mysqli_query($conn, "SELECT id FROM download_list WHERE state is NULL and deleted = 0")) {
    $row_cnt = mysqli_num_rows($result);
    echo " downloads:" . $row_cnt;
    mysqli_free_result($result);
} else {
    die ("Something went wrong when was trying to get number of downloads in queue.");
}


// Get length of downloads in queue
$time = date("Y-m-d H:i:s", time() - 60);
if ($result = mysqli_query($conn, "SELECT sum(length) AS length, sum(completed_length) AS completed_length FROM download_list WHERE state is NULL and deleted = 0")) {
    $row = mysqli_fetch_assoc($result);
    echo " ttl_dl_length:" . $row['length'] . " cmpltd_dl_length:" .  $row['completed_length'];
    mysqli_free_result($result);
} else {
    die ("Something went wrong when was trying to get number of downloads in queue.");
}


// Get number of downloaded file in storage
$time = date("Y-m-d H:i:s", time() - 60);
if ($result = mysqli_query($conn, "SELECT id FROM download_list WHERE state = 0 and deleted = 0")) {
    $row_cnt = mysqli_num_rows($result);
    echo " storage_files_ct:" . $row_cnt;
    mysqli_free_result($result);
} else {
    die ("Something went wrong when was trying to get number of downloaded file in storage.");
}


// Get length of downloaded files
$time = date("Y-m-d H:i:s", time() - 60);
if ($result = mysqli_query($conn, "SELECT sum(length) AS length FROM download_list WHERE state = 0 and deleted = 0")) {
    $row = mysqli_fetch_assoc($result);
    echo " cmp_files_length:" . $row['length'];
    mysqli_free_result($result);
} else {
    die ("Something went wrong when was trying to get length of downloaded files.");
}

// Get aria2 speed
// Checks if aria2 is online
$host = $url = preg_replace("(^https?://)", "", $aria2_host);
if (! @fsockopen($host, $aria2_port, $errno, $errstr, 1)) {
    echo " aria2_speed:0";
}else {
    $aria2 = new aria2($aria2_host, $aria2_port);
    echo " aria2_speed:" . ($aria2->getGlobalStat()['result']['downloadSpeed'] * 8);
}

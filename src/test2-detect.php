<?php

$host = 'yahoo.com';
$port = 443;

echo "Creating TCP/IP socket\n";
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    echo "Error: Unable to create socket\n";
    exit;
}

echo "Connecting to server\n";
$result = socket_connect($socket, $host, $port);
if ($result === false) {
    echo "Error: Unable to connect to server\n";
    exit;
}
echo "\nResults:\n\n";
print_r($result);

echo "\nSending HTTP/2 client hello\n";
$http2_client_hello = "Hello";
// $http2_client_hello = "\x00\x02h2"; // ALPN identifier for HTTP/2
socket_write($socket, $http2_client_hello);

echo "Receiving ALPN response\n";
$alpn_response = socket_read($socket, 1024);
if ($alpn_response === false) {
    echo "Error: Unable to read ALPN response\n";
    exit;
}
echo "\nResponse:\n\n";
print_r($alpn_response);

echo "Check if HTTP/2 is supported\n";
if (strpos($alpn_response, 'h2') !== false) {
    echo "HTTP/2 is supported\n";
} else {
    echo "HTTP/2 is not supported\n";
}

socket_close($socket);

<?php

$serverAddress = "localhost"; 
$serverPort = 8080; 

echo "Creating a socket...\n";
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    echo "Failed to create socket: " . socket_strerror(socket_last_error()) . "\n";
    exit;
}
echo "Socket created...\n";

echo "Connecting to the socket...\n";
$result = socket_connect($socket, $serverAddress, $serverPort);
if ($result === false) {
    echo "Failed to connect to server: " . socket_strerror(socket_last_error($socket)) . "\n";
    exit;
}
echo "Connected...\n";
$request = "GET / HTTP/1.1\r\nHost: $serverAddress\r\nConnection: close\r\n\r\n";
socket_write($socket, $request, strlen($request));

echo "Fetching the negotiated protocol...\n";
$response = '';
while ($buffer = socket_read($socket, 1024)) {
    $response .= $buffer;
}

socket_close($socket);

//Fetch the protocol and display it
$responseLines = explode("\r\n", $response);
$firstLine = $responseLines[0];

$parts = explode(" ", $firstLine);
$protocolInUse = $parts[0];

echo "\nProtocol in use : " . $protocolInUse . "\n";

?>

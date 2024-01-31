<?php

function http1_request_parse($request_string) {
    $lines = explode("\r\n", $request_string);

    // Extract method, path, and protocol
    list($method, $path, $protocol) = explode(' ', array_shift($lines));

    // Extract body 
    array_pop($lines);
    $body = array_pop($lines);

    // Parse headers
    $headers = [];
    foreach ($lines as $line) {
        if (empty($line)) {
            break;
        }
        list($key, $value) = explode(': ', $line, 2);
        $headers[$key] = $value;
    }

    // // Extract body
    // $body = implode("\r\n", $lines);

    return compact('method', 'path', 'protocol', 'headers', 'body');
}

function http1_to_http2_hex($request_array) {
    if ($request_array['method'] == 'GET') {
        $hframe = createHeadersFrame($request_array, true);
        $dframe = "";
    }
    if ($request_array['method'] == 'POST') {
        $hframe = createHeadersFrame($request_array, false);
        $dframe = createDataFrame($request_array);
    }
    // $hframe = replaceHeaderWithInfo($hframe);
    return $hframe."\n\n".$dframe;
    // return bin2hex(json_encode($frame));
}

function createHeadersFrame($request_array, $lastFrame) {

    $header_frame = "HEADERS\n";
    $priorityFlag = 0;

    $type = getTypeInBinary($request_array['method']);

    if ($priorityFlag)
        $streamId = getStreamIdentifier();
    else
        $streamId = str_pad(decbin(0), 31, '0', STR_PAD_LEFT);

    if($lastFrame)
        $flag = getFlagsInBinary(1, 1, 0, 0); //endStream, endHeader, padding, priority
    else
        $flag = getFlagsInBinary(0, 1, 0, 0); //endStream, endHeader, padding, priority

    $header_frame .= ":method = ".$request_array['method']."\n";
    $header_frame .= ":scheme = ".$request_array['protocol']."\n";
    $header_frame .= ":path = ".$request_array['path']."\n";
    foreach ($request_array['headers'] as $headerKey => $headerValue) {
        $header_frame .= $headerKey . " = " . $headerValue . "\n";
    }
    $length = strlen($header_frame) - 8;
    // Convert the number to 24-bit binary representation
    $length = str_pad(decbin($length), 24, '0', STR_PAD_LEFT);
    

    $headerContent = "" . $length . $type . $flag . decbin(0) . $streamId;
    $count = 1;
    $header_frame_bin = str_replace("HEADERS", $headerContent, $header_frame, $count);
    
    return ($headerContent);
    // return $header_frame_bin;
}

function createDataFrame($request_array) {
    $data_frame = "DATA \n+ END_STREAM\n";
    $data_frame .= $request_array['body'];
    return $data_frame;
}

function getTypeInBinary($type) {
    if ($type == 'GET')
        return str_pad(decbin(0), 8, '0', STR_PAD_LEFT);
    if ($type == 'POST')
        return str_pad(decbin(1), 8, '0', STR_PAD_LEFT);
    return "00001111"; //invalid
}

// | DATA          | 0x0  |
// | HEADERS       | 0x1  |
// | PRIORITY      | 0x2  |
// | RST_STREAM    | 0x3  |
// | SETTINGS      | 0x4  |
// | PUSH_PROMISE  | 0x5  |
// | PING          | 0x6  |
// | GOAWAY        | 0x7  |
// | WINDOW_UPDATE | 0x8  |
// | CONTINUATION  | 0x9  |

function getStreamIdentifier() {
    return str_pad(decbin(0), 31, '0', STR_PAD_LEFT);
}

function getFlagsInBinary($endStreamFlag, $endHeaderFlag, $paddingFlag, $priorityFlag) {
    $bin = str_pad(decbin(0), 8, '0', STR_PAD_LEFT);
    if ($endStreamFlag) 
        $bin = setBit($bin, 0);
    if ($endHeaderFlag) 
        $bin = setBit($bin, 2);
    if ($paddingFlag) 
        $bin = setBit($bin, 3);
    if ($priorityFlag) 
        $bin = setBit($bin, 5);

    return $bin;
}

// Function to set a bit to 1 at a specific position
function setBit($binaryVar, $bitPosition) {
    // Convert binary string to an array for easy manipulation
    $binaryArray = str_split($binaryVar);
    
    // Set the specified bit to 1
    $binaryArray[$bitPosition] = '1';
    
    // Convert the array back to a binary string
    $binaryVar = implode('', $binaryArray);
    
    return $binaryVar;
}

// Example usage

echo "Example usage 1: \n";
$http1_request_string = "GET /path HTTP/1.1\r\nHost: example.com\r\n\r\n";
print("Original request: \n");
echo $http1_request_string;
$request_array = http1_request_parse($http1_request_string);
$http2_hex_format = http1_to_http2_hex($request_array);

// Output the result
print("\nConverted request: \n");
echo $http2_hex_format;

echo "\nExample usage 2: \n";
$http1_request_string = "POST /resource HTTP/1.1\r\nHost: example.com\r\nContent-Type: image/jpeg\r\nContent-Length: 123 \r\n1010101010101010101010000011001010111101010\r\n";
print("\nOriginal request: \n");
echo $http1_request_string;
$request_array = http1_request_parse($http1_request_string);
$http2_hex_format = http1_to_http2_hex($request_array);

// Output the result
print("\nConverted request: \n");
echo $http2_hex_format;

// $request = "GET / HTTP/1.1\nHost: www.google.com\nUser-Agent: Apidog/1.0.0 (https://apidog.com)\nAccept: */*\nConnection: keep-alive";

// $lines = explode("\n", $request);

// foreach ($lines as $line) {
//     print($line . "\n");
// }


//========================================================================
//pg 57
// GET /resource HTTP/1.1           HEADERS
//      Host: example.org          ==>     + END_STREAM
//      Accept: image/jpeg                 + END_HEADERS
//                                           :method = GET
//                                           :scheme = https
//                                           :path = /resource
//                                           host = example.org
//                                           accept = image/jpeg

<?php

function parseH1Request($request_string) {

    $lines = explode("\r\n", $request_string);
    list($method, $path, $protocol) = explode(' ', array_shift($lines));
    $headers = array();

    // Find the index where headers end and body starts
    $bodyStartIndex = 0;
    foreach ($lines as $index => $line) {
        if (empty($line)) {
            $bodyStartIndex = $index + 1;
            break;
        }
        // Split the line into key-value pair for headers
        list($key, $value) = explode(': ', $line, 2);
        $headers[$key] = $value;
    }

    // Remove headers from $lines
    $lines = array_slice($lines, $bodyStartIndex);

    // Join the remaining lines to form the body
    $body = implode("\r\n", $lines);

    $parsedRequest = array(
        'method' => $method,
        'path' => $path,
        'protocol' => $protocol,
        'headers' => $headers,
        'body' => $body
    );

    return $parsedRequest;
}

function createFrames($request_array) {

    $frame = [];
    //initializing flags for the frames
    $END_STREAM_FLAG = 0;
    $END_HEADERS_FLAG = 0;

    // if ($request_array['method'] == 'GET') {
    //     $END_STREAM_FLAG = 1;
    //     $END_HEADERS_FLAG = 1;
    //     $frame['headerFrame'] = createHeadersFrame($request_array, $END_STREAM_FLAG, $END_HEADERS_FLAG);
    //     $frame['dataFrame'] = [];
    // }
    // if ($request_array['method'] == 'POST') {
    $END_HEADERS_FLAG = 1;
    $frame['headerFrame'] = createHeadersFrame($request_array, $END_STREAM_FLAG, $END_HEADERS_FLAG);
    $END_STREAM_FLAG = 1;
    $frame['dataFrame'] = createDataFrame($request_array, $END_STREAM_FLAG, $END_HEADERS_FLAG);
    // }
    
    return $frame;

}
//not set value => field will not be present in the final frame
function createHeadersFrame($request_array, $END_STREAM_FLAG, $END_HEADERS_FLAG) {

    //flags #=4
    $PADDED_FLAG = 0;
    $PRIOR_FLAG = 0;
    
    //the frame as an array
    $frame = [];

    //common fields
    $frame['length'] = "not set"; //24 bits
    $frame['type'] = "0x1"; //8 bits
    $frame['flags'] = ['end_stream_flag' => $END_STREAM_FLAG, 'end_header_flag' => $END_HEADERS_FLAG, 'padded_flag' => $PADDED_FLAG, 'prior_flag' => $PRIOR_FLAG]; //8bits
    $frame['R'] = 0; // semantics of this bit are undefined, must remain unset always //1 bit
    $frame['streamID'] = bindec(getStreamIdentifier()); // 31 bits

    //specific fields
    $frame['padLength'] = "not set"; //8 bits
    $frame['E'] = "not set"; // 1 bit
    $frame['StreamDepenedency'] = "not set"; // 31 bits
    $frame['weight'] = "not set"; // 8 bits
    $frame['requestHeaders'] = "not set"; 
    $frame['padding'] = "not set";

    //Adding request headers as value of the '$frame['requestHeaders']' field
    $reqHeaders = ":method = ".$request_array['method']."\n";
    $reqHeaders .= ":scheme = ".$request_array['protocol']."\n";
    $reqHeaders .= ":path = ".$request_array['path']."\n";
    foreach ($request_array['headers'] as $headerKey => $headerValue) {
        $reqHeaders .= $headerKey . " = " . $headerValue . "\n";
    }

    $frame['requestHeaders'] = $reqHeaders; 

    //Updating length field - by counting the length of string(reqHeaders) 
    $frame['length'] = strlen($reqHeaders);

    //Updating fields based on flags
    if($PADDED_FLAG == 1) {
        // $frame['padLength'] = <--value>;
        // $frame['padding'] = <--value>;
    }
    if($PRIOR_FLAG == 1) {
        // $frame['E'] = <--value>;
        // $frame['StreamDepenedency'] = <--value>;
        // $frame['weight'] = <--value>;
    }

    return $frame;
    
}

//not set value => field will not be present in the final frame
function createDataFrame($request_array, $END_STREAM_FLAG, $END_HEADERS_FLAG) {

    //flags #=4
    $PADDED_FLAG = 0;
    $PRIOR_FLAG = 0;
    
    //the frame as an array
    $frame = [];

    //common fields
    $frame['length'] = "not set"; //24 bits
    $frame['type'] = "0x0"; //8 bits
    $frame['flags'] = ['end_stream_flag' => $END_STREAM_FLAG, 'end_header_flag' => $END_HEADERS_FLAG, 'padded_flag' => $PADDED_FLAG, 'prior_flag' => $PRIOR_FLAG]; //8bits
    $frame['R'] = 0; // semantics of this bit are undefined, must remain unset always //1 bit
    $frame['streamID'] = bindec(getStreamIdentifier()); // 31 bits

    //specific fields
    $frame['padLength'] = "not set"; //8 bits
    $frame['data'] = $request_array['body']; 
    $frame['padding'] = "not set";

    //Updating length field - by counting the length of string(reqHeaders) 
    $frame['length'] = strlen($request_array['body']);

    //Updating fields based on flags
    if($PADDED_FLAG == 1) {
        // $frame['padLength'] = <--value>;
        // $frame['padding'] = <--value>;
    }
    return $frame;   
}

function convertFrameToBin($http2_frames) {
    $frames = [];
    foreach ($http2_frames as $key => $frame) {
        // print("\n\n***********************\n\n");
        if ($frame == [])
            continue;
        // print("\n\n***********************\n\n");
        //converting common frame fields
        $length = intval($frame['length']);
        // $len = strlen(decbin());
        $binString = str_pad(decbin($length), (24), '0', STR_PAD_LEFT);
        // $binString .= decbin($frame['length']);
        $frame['length'] = "not set";

        $binString .= getTypeInBinary($frame['type']);
        $frame['type'] = "not set";

        $binString .= getFlagsInBinary($frame['flags']['end_stream_flag'], $frame['flags']['end_header_flag'], $frame['flags']['padded_flag'], $frame['flags']['prior_flag']);
        $frame['flags'] = "not set";

        $binString .= str_pad(decbin(0), 1, '0', STR_PAD_LEFT);
        $frame['R'] = "not set";

        $binString .= getStreamIdentifier();
        $frame['streamID'] = "not set";

        //looping over remaining fields
        foreach ($frame as $key => $value) {

            if($value == "not set")
                continue;
            $binString .= asciiToBinary($value);

        }
        //add binary string version of the frame to frames array
        $frames[] = $binString;
    }
    return $frames;
}

function asciiToBinary($string) {
    $binaryString = '';
    for ($i = 0; $i < strlen($string); $i++) {
        // Get the ASCII value of the character
        $asciiValue = ord($string[$i]);
        // Convert the ASCII value to binary and append it to the binary string
        $binaryString .= decbin($asciiValue);
    }
    return $binaryString;
}

function getTypeInBinary($type) {
    if ($type == '0x0')
        return str_pad(decbin(0), 8, '0', STR_PAD_LEFT);        //DATA
    if ($type == '0x1')
        return str_pad(decbin(1), 8, '0', STR_PAD_LEFT);        //HEADERS
    if ($type == '0x2')
        return str_pad(decbin(2), 8, '0', STR_PAD_LEFT);        //PRIORITY
    if ($type == '0x3')
        return str_pad(decbin(3), 8, '0', STR_PAD_LEFT);        //RST_STREAM
    if ($type == '0x4')
        return str_pad(decbin(4), 8, '0', STR_PAD_LEFT);        //SETTINGS
    if ($type == '0x5')
        return str_pad(decbin(5), 8, '0', STR_PAD_LEFT);        //PUSH_PROMISE
    if ($type == '0x6')
        return str_pad(decbin(6), 8, '0', STR_PAD_LEFT);        //PING
    if ($type == '0x7')
        return str_pad(decbin(7), 8, '0', STR_PAD_LEFT);        //GOAWAY
    if ($type == '0x8')
        return str_pad(decbin(8), 8, '0', STR_PAD_LEFT);        //WINDOW_UPDATE
    if ($type == '0x9')
        return str_pad(decbin(9), 8, '0', STR_PAD_LEFT);        //CONTINUATION
    else
        return 00001111;                                        //invalid
}

function getStreamIdentifier() {
    return str_pad(decbin(18943), 31, '0', STR_PAD_LEFT);
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

function setBit($binaryVar, $bitPosition) {
    $binaryArray = str_split($binaryVar);
    // Set the specified bit to 1
    $binaryArray[$bitPosition] = '1';
    $binaryVar = implode('', $binaryArray);
    
    return $binaryVar;
}

function binToHex($arr) {
    $hexArr = [];
    $i = 0;
    foreach ($arr as $str) {
        $i = $i + 1;
        $name = "Frame " . ($i);
        $chunks = str_split($str, 8);

        $hexString = '';
        foreach ($chunks as $chunk) {
            $hexString .= " " . sprintf("%02X", bindec($chunk));
        }

        $hexArr[$name] = $hexString; 
    }
    return $hexArr;
}

// Example usage 1

// $http1_request_string = "GET /doc/test.html HTTP/1.1\r\nHost: www.test101.com\r\nAccept: image/gif, image/jpeg, */*\r\nContent-Length: 35\r\n\r\nbookId=12345&author=paulo+Coehlo";
// $request_array = parseH1Request($http1_request_string);
// $http2_frames = createFrames($request_array);
// $bin_msg = convertFrameToBin($http2_frames);
// $hex_msg = binToHex($bin_msg);

// // Print the step by step result 
// echo "Example usage 1:" . "\n";
// echo "****************";
// print("\n\nOriginal request: \n\n");
// echo $http1_request_string;
// print("\n\nParsed request array: \n\n");
// print_r($request_array);
// print("\n\nFrames created: \n\n");
// print_r($http2_frames);
// print("\n\nHexadecimal version: \n\n");
// print_r($hex_msg);
// print("\nBinary version: \n\n");
// print_r($bin_msg);

// Example usage 2

// $http1_request_string2 = "POST /api/login HTTP/1.1\r\nHost: example.com\r\nContent-Type: application/json\r\nContent-Length: 42\r\nAuthorization: Bearer token123\r\n\r\n{\r\n\"username\": \"example_user\",\r\n\"password\": \"example_password\"\r\n}";
$http1_request_string2 = "GET / HTTP/1.1\r\nHost: yahoo.com\r\n\r\n";
$request_array2 = parseH1Request($http1_request_string2);
$http2_frames2 = createFrames($request_array2);
$bin_msg2 = convertFrameToBin($http2_frames2);
$hex_msg2 = binToHex($bin_msg2);

// Print the step by step result 
echo "Example usage 2:" . "\n";
echo "****************";
print("\n\nOriginal request: \n\n");
echo $http2_request_string;
print("\n\nParsed request array: \n\n");
print_r($request_array2);
print("\n\nFrames created: \n\n");
print_r($http2_frames2);
print("\n\nHexadecimal version: \n\n");
print_r($hex_msg2);
print("\nBinary version: \n\n");
print_r($bin_msg2);
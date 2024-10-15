<?php

class HPack {
    // HPACK Static Table as per RFC 7541
    private const STATIC_TABLE = [
        1  => [':authority', ''],
        2  => [':method', 'GET'],
        3  => [':method', 'POST'],
        4  => [':path', '/'],
        5  => [':path', '/index.html'],
        6  => [':scheme', 'http'],
        7  => [':scheme', 'https'],
        8  => [':status', '200'],
        9  => [':status', '204'],
        10 => [':status', '206'],
        11 => [':status', '304'],
        12 => [':status', '400'],
        13 => [':status', '404'],
        14 => [':status', '500'],
        15 => ['accept-charset', ''],
        16 => ['accept-encoding', 'gzip, deflate'],
        17 => ['accept-language', ''],
        18 => ['accept-ranges', ''],
        19 => ['accept', ''],
        20 => ['access-control-allow-origin', ''],
        21 => ['age', ''],
        22 => ['allow', ''],
        23 => ['authorization', ''],
        24 => ['cache-control', ''],
        25 => ['content-disposition', ''],
        26 => ['content-encoding', ''],
        27 => ['content-language', ''],
        28 => ['content-length', ''],
        29 => ['content-location', ''],
        30 => ['content-range', ''],
        31 => ['content-type', ''],
        32 => ['cookie', ''],
        33 => ['date', ''],
        34 => ['etag', ''],
        35 => ['expect', ''],
        36 => ['expires', ''],
        37 => ['from', ''],
        38 => ['host', ''],
        39 => ['if-match', ''],
        40 => ['if-modified-since', ''],
        41 => ['if-none-match', ''],
        42 => ['if-range', ''],
        43 => ['if-unmodified-since', ''],
        44 => ['last-modified', ''],
        45 => ['link', ''],
        46 => ['location', ''],
        47 => ['max-forwards', ''],
        48 => ['proxy-authenticate', ''],
        49 => ['proxy-authorization', ''],
        50 => ['range', ''],
        51 => ['referer', ''],
        52 => ['refresh', ''],
        53 => ['retry-after', ''],
        54 => ['server', ''],
        55 => ['set-cookie', ''],
        56 => ['strict-transport-security', ''],
        57 => ['transfer-encoding', ''],
        58 => ['user-agent', ''],
        59 => ['vary', ''],
        60 => ['via', ''],
        61 => ['www-authenticate', '']
    ];

    private $dynamic_table = [];
    private $max_table_size = 4096; 
    private $current_table_size = 0;
    public function __construct(int $max_size = 4096) {
        $this->max_table_size = $max_size;
    }

    // Encode headers into an HPACK-compressed format
    public function encode(array $headers): string {
        $encoded = '';
        foreach ($headers as $name => $value) {
            $index = $this->find_in_static_table($name, $value);
            if ($index !== null) {
                $encoded .= $this->encode_indexed_header_field($index);
            } else {
                $encoded .= $this->encode_literal_header_field($name, $value);
                $this->add_to_dynamic_table($name, $value);
            }
        }
        return $encoded;
    }

    // Decode HPACK-compressed headers
    public function decode(string $input): array {
        $headers = [];
        $offset = 0;
        while ($offset < strlen($input)) {
            $byte = ord($input[$offset]);
            
            if ($this->is_indexed_header_field($byte)) {
                $index = $this->decode_indexed_header_field($input, $offset);
                [$name, $value] = $this->get_header_from_index($index);
            } else {
                [$name, $value, $offset] = $this->decode_literal_header_field($input, $offset);
            }

            $headers[$name] = $value;
        }

        return $headers;
    }

    // Find header in static table
    private function find_in_static_table(string $name, string $value = ''): ?int {
        foreach (self::STATIC_TABLE as $index => [$header_name, $header_value]) {
            if ($header_name === $name && ($header_value === '' || $header_value === $value)) {
                return $index;
            }
        }
        return null;
    }

    // Encode an indexed header field
    private function encode_indexed_header_field(int $index) {
        return chr(0x80 | $index);
    }

    // Decode an indexed header field
    private function decode_indexed_header_field(string $input, int &$offset) {
        $index = ord($input[$offset]) & 0x7F;
        $offset++;
        return $index;
    }

    // Encode a literal header field
    private function encode_literal_header_field(string $name, string $value) {
        $encoded = chr(0x40);
        $encoded .= $this->encode_string($name);
        $encoded .= $this->encode_string($value);
        return $encoded;
    }

    // Decode a literal header field
    private function decode_literal_header_field(string $input, int $offset) {
        $name_length = ord($input[$offset + 1]); 
        $name = $this->huffman_decode($name);

        $value_length = ord($input[$offset + 2 + $name_length]); 
        $value = $this->huffman_decode($value);

        $offset += 3 + $name_length + $value_length;
        return [$name, $value, $offset];
    }

    // Helper to encode a string (with Huffman encoding optional)
    private function encode_string(string $input): string {
        $encoded_string = $this->huffman_encode($input);
        $length = strlen($encoded_string);

        // Set the highest bit in the length byte to indicate Huffman encoding (0x80).
        return chr(0x80 | $length) . $encoded_string;
    }

    // Add a header to the dynamic table
    private function add_to_dynamic_table(string $name, string $value): void {
        $header_size = strlen($name) + strlen($value) + 32; // HPACK overhead
        if ($header_size > $this->max_table_size) {
            return; // Header is too large for the table
        }

        $this->dynamic_table[] = [$name, $value];
        $this->current_table_size += $header_size;

        // Evict entries if necessary
        while ($this->current_table_size > $this->max_table_size) {
            $evicted = array_shift($this->dynamic_table);
            $this->current_table_size -= strlen($evicted[0]) + strlen($evicted[1]) + 32;
        }
    }

    // Retrieve a header by its index from static or dynamic table
    private function get_header_from_index(int $index): array {
        if ($index <= count(self::STATIC_TABLE)) {
            return self::STATIC_TABLE[$index];
        }

        $dynamic_index = $index - count(self::STATIC_TABLE);
        return $this->dynamic_table[$dynamic_index - 1];
    }

    private function is_indexed_header_field(int $byte): bool {
        return ($byte & 0x80) === 0x80;
    }

    private function huffman_encode(string $input): string {
        // Load Huffman codes from the external file
        $huffman_codes = include 'HuffmanCodes.php';
    
        $encoded = '';
        
        // Loop through each character of the input
        foreach (str_split($input) as $char) {
            if (isset($huffman_codes[$char])) {
                // Convert each hex representation to its binary equivalent
                foreach ($huffman_codes[$char] as $hex) {
                    $encoded .= str_pad(decbin(ord($hex)), 8, '0', STR_PAD_LEFT); // Convert to binary
                }
            } else {
                throw new Exception("Character not found in Huffman table: $char");
            }
        }
        
        // Convert the binary string into a packed binary format
        $output = '';
        for ($i = 0; $i < strlen($encoded); $i += 8) {
            $byte = substr($encoded, $i, 8);
            $output .= chr(bindec($byte)); // Pack into bytes
        }
        
        return $output;
    }

    private function huffman_decode(string $input): string {
        // Load Huffman lookup table from the external file
        $huffman_lookup = include 'HuffmanLookup.php';
    
        $binary_string = '';
        
        // Convert the input byte string to a binary string
        foreach (str_split($input) as $byte) {
            $binary_string .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT); // Convert to binary
        }
    
        $decoded = '';
        $buffer = ''; // Buffer to accumulate bits
        
        // Traverse the binary string and decode it using the Huffman lookup table
        for ($i = 0; $i < strlen($binary_string); $i++) {
            $buffer .= $binary_string[$i]; // Add bit to buffer
            
            // Check if the buffer matches a Huffman code in the lookup table
            if (isset($huffman_lookup[$buffer])) {
                $decoded .= chr($huffman_lookup[$buffer][0]); // Append decoded character
                $buffer = ''; // Reset buffer
            }
        }
        
        return $decoded;
    }
    
}

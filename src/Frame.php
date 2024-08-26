<?php

namespace seekquarry\atto;

use Exception;

class Frame {

    // Properties
    protected $defined_flags = [];
    protected $type = null;
    protected $stream_association = null;
    public $stream_id;
    public $flags;
    public $body_len = 0;

    // Constants
    const STREAM_ASSOC_HAS_STREAM = "has-stream";
    const STREAM_ASSOC_NO_STREAM = "no-stream";
    const STREAM_ASSOC_EITHER = "either";

    const FRAME_MAX_LEN = (2 ** 14); //initial
    const FRAME_MAX_ALLOWED_LEN = (2 ** 24) - 1; //max-allowed

    // Constructor
    public function __construct($stream_id, $flags = [])
    {
        $this->stream_id = $stream_id;
        $this->flags = new Flags($this->defined_flags);

        foreach ($flags as $flag) {
            $this->flags->add($flag);
        }

        if (!$this->stream_id && $this->stream_association == self::STREAM_ASSOC_HAS_STREAM) {
            throw new Exception("Stream ID must be non-zero for " . get_class($this));
        }
        if ($this->stream_id && $this->stream_association == self::STREAM_ASSOC_NO_STREAM) {
            throw new Exception("Stream ID must be zero for " . get_class($this) . " with stream_id=" . $this->stream_id);
        }
    }

    // toString equivalent
    public function __toString()
    {
        return get_class($this) . "(stream_id=" . $this->stream_id . ", flags=" . $this->flags . "): " . $this->body_repr();
    }

    // Method to serialize the body (not implemented in base class)
    public function serialize_body()
    {
        throw new Exception("Not implemented");
    }

    // Method to serialize the frame
    public function serialize()
    {
        echo "\nserialize L57\n";
        // var_dump($this);
        $body = $this->serialize_body();
        $this->body_len = strlen($body);

        // Build the common frame header
        $flags = 0;
        foreach ($this->defined_flags as $flag => $flag_bit) {
            if ($this->flags->contains($flag)) {
                $flags |= $flag_bit;
            }
        }

        $header = pack("nCCCN", 
            ($this->body_len >> 8) & 0xFFFF, 
            $this->body_len & 0xFF, 
            $this->type, 
            $flags,
            $this->stream_id & 0x7FFFFFFF
        );

        return $header . $body;
    }

    // Method to parse frame header
    public static function parseFrameHeader($header)
    {
        echo "\n\nparseFrameHeader\n\n";

        if (strlen($header) != 9) {
            echo "Invalid frame header: length should be 9, received " . strlen($header);
            return;
        }

        $header = bin2hex($header);

        $fields['length'] = $length = hexdec(substr($header, 0, 6));
        $fields['type'] = $type = hexdec(substr($header, 6, 2));
        $fields['flags'] = $flags = substr($header, 8, 2);
        $fields['stream_id'] = $stream_id = hexdec(substr($header, 10, 8));

        if (!isset(FrameFactory::$frames[$type])) {
            throw new Exception("Unknown frame type: " . $type);
            return;
        } else {
            $frame = new FrameFactory::$frames[$type]($stream_id);
        }

        $frame->parse_flags($flags);
        return [$frame, $length];
    }

    // Method to parse flags
    public function parse_flags($flag_byte)
    {
        foreach ($this->defined_flags as $flag => $flag_bit) {
            if ($flag_byte & $flag_bit) {
                $this->flags->add($flag);
            }
        }
        return $this->flags;
    }

    // Method to parse the body (not implemented in base class)
    public function parse_body($data)
    {
        throw new Exception("Not implemented");
    }

    // Helper method for body representation (for debugging)
    public function body_repr()
    {
        // Fallback shows the serialized (and truncated) body content.
        return $this->raw_data_repr($this->serialize_body());
    }

    // Helper method for raw data representation (for debugging)
    private function raw_data_repr($data)
    {
        if (!$data) {
            return "None";
        }
        $r = bin2hex($data);
        if (strlen($r) > 20) {
            $r = substr($r, 0, 20) . "...";
        }
        return "<hex:" . $r . ">";
    }
}

/* Mapping of frame types to classes
 * usage:
 *  $frameType = 0x0; // Suppose this is the type of frame you want to create
 *  $frameClass = FrameFactory::$frames[$frameType]; // Look up the class name
 *  $frameObject = new $frameClass(); // Create a new instance of the class
 */
class FrameFactory {
    public static $frames = [
        0x0 => DataFrame::class,
        0x1 => HeaderFrame::class,
        // 0x2 => PriorityFrame::class,
        // 0x3 => RstStreamFrame::class,
        0x4 => SettingsFrame::class,
        // 0x5 => PushPromiseFrame::class,
        // 0x6 => PingFrame::class,
        // 0x7 => GoAwayFrame::class,
        // 0x8 => WindowUpdateFrame::class,
        // 0x9 => ContinuationFrame::class,
        // => AltSvcFrame::class,
    ];
}

/*
// Step 1: Create a SettingsFrame instance
$streamId = 0; // Settings frames usually have stream_id = 0
$settings = [
    SettingsFrame::HEADER_TABLE_SIZE => 4096,
    SettingsFrame::ENABLE_PUSH => 1,
    SettingsFrame::MAX_CONCURRENT_STREAMS => 100,
];

$frame = new SettingsFrame($streamId, $settings);

// Step 2: Optionally, set flags (e.g., ACK)
$frame->flags->add('ACK');

// Step 3: Serialize the frame into a byte string
$serializedFrame = $frame->serialize();

// The $serializedFrame is now ready to be sent over the network

// Step 4: Parsing a received frame (for example)
$receivedData =  the byte string received from the network;
list($parsedFrame, $length) = Frame::parse_frame_header($receivedData);

// Continue processing the parsed frame as needed
 */
class SettingsFrame extends Frame {

    protected $defined_flags = [
        'ACK' => 0x01
    ];
    protected $type = 0x04;
    protected $stream_association = '_STREAM_ASSOC_NO_STREAM';

    const PAYLOAD_SETTINGS = [
        0x01 => 'HEADER_TABLE_SIZE',
        0x02 => 'ENABLE_PUSH',
        0x03 => 'MAX_CONCURRENT_STREAMS',
        0x04 => 'INITIAL_WINDOW_SIZE',
        0x05 => 'MAX_FRAME_SIZE',
        0x06 => 'MAX_HEADER_LIST_SIZE',
        0x08 => 'ENABLE_CONNECT_PROTOCOL',
    ];
    
    protected $settings = [];

    /**
     * Constructor for SettingsFrame.
     *
     * @param int $stream_id
     * @param array $settings
     * @param array $flags
     * @throws InvalidDataError
     */
    public function __construct(int $stream_id = 0, array $settings = [], array $flags = []) {
        parent::__construct($stream_id, $flags);

        if (!empty($settings) && in_array('ACK', $flags)) {
            throw new InvalidDataError("Settings must be empty if ACK flag is set.");
        }

        $this->settings = $settings;
    }

    protected function _body_repr() {
        return 'settings=' . json_encode($this->settings);
    }

    public function serialize_body() {
        echo "\n\nserialize_body\n\n";
        $body = '';
        foreach ($this->settings as $setting => $value) {
            echo "\n$setting --> $value\n";
            $body .= pack('nN', $setting, $value);
            var_dump(bin2hex($body));
        }
        return $body;
    }

    /**
     * Parses body of the frame. Body of SETTINGS frame can contain settings parameters only.
     * If any of these parameters are found, they are set as settings for the object.
     * If ACK flag is set, payload must be empty.
     *
     * @param string $data
     */
    public function parse_body($data) {
        echo "\n\nparseBody\n\n";
        
        if (in_array('ACK', $this->flags->getFlags()) && strlen($data) > 0) {
            echo "ERROR: SETTINGS ack frame must not have payload: got " . strlen($data) . " bytes";
        }
        $data = bin2hex($data);
        $entries = str_split($data, 12); // 12 hex characters = 6 bytes

        $body_len = 0;
        foreach ($entries as $entry) {
            $identifier = hexdec(substr($entry, 0, 4));
            $value = hexdec(substr($entry, 4, 8));
            $identifier_name = SettingsFrame::PAYLOAD_SETTINGS[$identifier] ?? 'UNKNOWN-SETTING';
            $this->settings[$identifier] = $value;
            // echo "Added $identifier_name with value $value\n";
            $body_len += 6;
        }
        
        $this->body_len = $body_len;
    }
}

/*
Header frame class implementation 
 */
class HeaderFrame extends Frame {

    use Padding;

    protected $defined_flags = [
        'END_STREAM' => 0x01,
        'END_HEADERS' => 0x04,
        'PADDED' => 0x08,
        'PRIORITY' => 0x20
    ];
    protected $type = 0x01;
    protected $stream_association = '_STREAM_ASSOC_HAS_STREAM';
    
    public $data;

    public function __construct(int $stream_id = 0, string $data = '', array $flags = []) {
        parent::__construct($stream_id, $flags);
        $this->data = $data;
    }

    protected function _bodyRepr() {
        return sprintf(
            "exclusive=%s, depends_on=%s, stream_weight=%s, data=%s",
            $this->exclusive,
            $this->depends_on,
            $this->stream_weight,
            $this->_raw_data_repr($this->data)
        );
    }

    public function serializeBody() {
        $padding_data = $this->serialize_padding_data();
        $padding = str_repeat("\0", $this->pad_length);

        if (in_array('PRIORITY', $this->flags)) {
            $priority_data = $this->serialize_priority_data();
        } else {
            $priority_data = '';
        }

        return implode('', [$padding_data, $priority_data, $this->data, $padding]);
    }

    public function parseBody($data) {

        // $binaryString = pack('H*', bin2hex($data));
        // $byteArray = unpack('C*', $binaryString);

        // Initialize the HPACK decoder
        $hpack = new HPack();
        // Decode the headers
        $headers = $hpack->decode($data, 4096);
        // Output the decoded headers
        foreach ($headers as $name => $value) {
            echo "$name: $value" . PHP_EOL;
        }
        // echo "byte array:\n";
        // foreach ($byteArray as $index => $byte) {
        //     echo "Byte {$index}: Decimal: {$byte}, Hex: " . dechex($byte) . PHP_EOL;
        // }
        // echo "\n\n";
        // var_dump($this->flags->getFlags());

        // $paddedData_length = $this->parsePaddingData($byteArray);
        // $data = substr($data, $paddedData_length);

        // if (in_array('PRIORITY', $this->flags->getFlags())) {
        //     $priorityData_length = $this->parsePriorityData($byteArray);
        // } else {
        //     $priorityData_length = 0;
        // }

        // $this->body_len = strlen($data);
        // $this->data = substr($data, $priorityData_length, $this->body_len - $this->pad_length);

        // if ($this->pad_length && $this->pad_length >= $this->body_len) {
        //     throw new InvalidPaddingError("Padding is too long.");
        // }
    }
}

class Flag {
    public string $name;
    public int $bit;

    public function __construct(string $name, int $bit)
    {
        $this->name = $name;
        $this->bit = $bit;
    }
}

class Flags {
    private array $validFlags;
    private array $flags;

    /**
     * Constructor to initialize the valid flags.
     * @param Flag[] $definedFlags
     */
    public function __construct(array $definedFlags) {
        echo "\n\n__construct\n\n";
        // var_dump($definedFlags);
        $this->validFlags = [];
        foreach ($definedFlags as $name => $bit) {
            $this->validFlags[] = $name;
        }
        $this->flags = [];
    }

    /**
     * Represent the object as a string.
     * @return string
     */
    public function __toString() {
        $sortedFlags = $this->flags;
        sort($sortedFlags);
        return implode(", ", $sortedFlags);
    }

    /**
     * Check if a flag is in the set.
     * @param string $flag
     * @return bool
     */
    public function contains(string $flag) {
        return in_array($flag, $this->flags);
    }

    /**
     * Add a flag to the set.
     * @param string $flag
     * @throws InvalidArgumentException
     * @return void
     */
    public function add(string $flag) {
        if (!in_array($flag, $this->validFlags)) {
            throw new InvalidArgumentException(sprintf(
                'Unexpected flag: %s. Valid flags are: %s',
                $flag,
                implode(', ', $this->validFlags)
            ));
        }

        if (!in_array($flag, $this->flags)) {
            $this->flags[] = $flag;
        }
    }

    /**
     * Remove a flag from the set.
     * @param string $flag
     * @return void
     */
    public function discard(string $flag) {
        $this->flags = array_diff($this->flags, [$flag]);
    }

    /**
     * Count the number of flags in the set.
     * @return int
     */
    public function count() {
        return count($this->flags);
    }

    // Method to return the $flags array
    public function getFlags() {
        return $this->flags;
    }

    /**
     * Get an iterator for the flags.
     * @return Traversable
     */
    public function getIterator() {
        return new ArrayIterator($this->flags);
    }
}

trait Padding
{
    protected $pad_length;

    public function __construct(int $stream_id, int $pad_length = 0) {
        $this->pad_length = $pad_length;
    }

    public function serializePaddingData() {
        if (in_array('PADDED', $this->flags)) {
            return pack('C', $this->pad_length);
        }
        return '';
    }

    public function parsePaddingData($data) {
        if (in_array('PADDED', $this->flags->getFlags())) {
            if (strlen($data) < 1) {
                throw new InvalidFrameError("Invalid Padding data");
            }
            $this->pad_length = unpack('C', $data[0])[1];
            $data = substr($data, 1); // Remove the parsed byte from data
            return 1;
        }
        return 0;
    }
}

?>

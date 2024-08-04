import h2.config
import h2.connection
import h2.events
import h2.exceptions
import h2.settings
import h2.stream

import socket
import ssl
import binascii

def encode_frame(frame):
    return binascii.unhexlify(frame.replace(" ", ""))

def send_request(sock, frames):
    for frame in frames:
        sock.sendall(frame)

def main():
    # Establish a TCP connection to yahoo.com on port 80
    sock = socket.create_connection(("yahoo.com", 80))
    sock = ssl.wrap_socket(sock, ssl_version=ssl.PROTOCOL_SSLv23)

    # Create an HTTP/2 connection object
    config = h2.config.H2Configuration(client_side=True)
    conn = h2.connection.H2Connection(config=config)
    conn.initiate_connection()

    # Prepare HTTP/1.1 Upgrade request
    upgrade_request = (
        b"GET / HTTP/1.1\r\n"
        b"Host: yahoo.com\r\n"
        b"Connection: Upgrade, HTTP2-Settings\r\n"
        b"Upgrade: h2c\r\n"
        b"HTTP2-Settings: AAMAAABkAARAAAAAAAIAAAAA\r\n"
        b"\r\n"
    )

    # Send the Upgrade request
    sock.sendall(upgrade_request)

    # Receive and parse HTTP/1.1 Upgrade response
    response = sock.recv(65535)
    print(response.decode())

    # Prepare HTTP/2 request frames
    frames = [
        encode_frame("00 00 3C 01 20 00 00 49 FF EB 6E 5E 9A 37 E4 83 D8 23 C5 A9 5D 73 C7 A3 2E DC B0 7B 04 8A 95 28 5F 8D D8 D7 5C 30 F4 D1 07 B0 5F 52 37 F3 E9 07 B0 79 C3 A3 7E FB B1 EF DB 02"),
        encode_frame("00 00 00 00 A0 00 00 49 FF")
    ]

    # Send HTTP/2 request frames
    send_request(sock, frames)

    # Receive HTTP/2 response frames
    while True:
        data = sock.recv(65535)
        if not data:
            break
        events = conn.receive_data(data)
        for event in events:
            if isinstance(event, h2.events.ResponseReceived):
                print("Received HTTP/2 response headers:")
                for name, value in event.headers:
                    print(f"{name}: {value}")
            elif isinstance(event, h2.events.DataReceived):
                print("Received HTTP/2 response data:", event.data)

if __name__ == "__main__":
    main()

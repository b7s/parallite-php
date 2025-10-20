<?php

declare(strict_types=1);

namespace Parallite\Service\Parallite;

use Closure;
use MessagePack\MessagePack;
use RuntimeException;
use Socket;
use Throwable;

/**
 * Service responsible for socket communication with Parallite daemon
 *
 * Supports both Unix domain sockets (Linux/macOS) and TCP sockets (Windows).
 * Socket type is auto-detected based on path format:
 * - Unix socket: /path/to/socket.sock
 * - TCP socket: host:port (e.g., 127.0.0.1:9876)
 */
final readonly class SocketService
{
    private const MAX_PORT_ATTEMPTS = 128;
    private const PORT_RANGE_END = 65535;

    public function __construct(
        private string $socketPath,
        private bool   $enableBenchmark = false
    )
    {
    }

    /**
     * Submit a task for parallel execution
     *
     * @param Closure $closure The closure to execute
     * @return array{socket: Socket, task_id: string}
     * @throws RuntimeException
     */
    public function submitTask(Closure $closure): array
    {
        if (ConfigService::isWindows()) {
            // TCP socket (Windows or explicit TCP)
            // Expected format: host:port (e.g., 127.0.0.1:9876)
            $parts = explode(':', $this->socketPath);
            if (count($parts) !== 2) {
                throw new RuntimeException('Invalid TCP socket path format. Expected host:port');
            }

            [$host, $port] = $parts;
            $port = (int)$port;

            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

            if ($socket === false) {
                throw new RuntimeException('Failed to create TCP socket');
            }

            $attempts = 0;
            $connected = false;
            $lastError = '';
            
            // Try to connect to the original port first
            if (@socket_connect($socket, $host, $port)) {
                $connected = true;
            } else {
                $lastError = socket_strerror(socket_last_error($socket));
                socket_clear_error($socket);
                
                // If it fails, try alternative ports
                $currentPort = $port + 1;
                $attempts = 1;
                
                while ($attempts < self::MAX_PORT_ATTEMPTS && $currentPort <= self::PORT_RANGE_END) {
                    socket_close($socket);
                    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                    
                    if ($socket === false) {
                        $lastError = 'Failed to create TCP socket';
                        break;
                    }
                    
                    if (@socket_connect($socket, $host, $currentPort)) {
                        $connected = true;
                        $port = $currentPort; // Update the current port to the one that worked
                        break;
                    }
                    
                    $lastError = socket_strerror(socket_last_error($socket));
                    socket_clear_error($socket);
                    
                    $currentPort++;
                    $attempts++;
                }
            }

            if (!$connected) {
                if ($socket !== false) {
                    socket_close($socket);
                }

                throw new RuntimeException(sprintf(
                    'Failed to connect to daemon after %d attempts. Last error: %s',
                    $attempts,
                    $lastError
                ));
            }
        } else {
            // Unix domain socket (Linux/macOS)
            $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

            if ($socket === false) {
                throw new RuntimeException('Failed to create Unix socket - ' . socket_strerror(socket_last_error()));
            }

            if (!@socket_connect($socket, $this->socketPath)) {
                throw new RuntimeException('Failed to connect to daemon at: `' . $this->socketPath . '` - ' . socket_strerror(socket_last_error()));
            }
        }

        if ($socket === false) {
            throw new RuntimeException('Failed to write to socket - ' . socket_strerror(socket_last_error()));
        }

        $taskId = $this->generateTaskId();
        $serialized = \Opis\Closure\serialize($closure);

        $messageData = [
            'type' => 'submit',
            'task_id' => $taskId,
            'payload' => $serialized,
            'context' => [],
        ];

        if ($this->enableBenchmark) {
            $messageData['enable_benchmark'] = true;
        }

        try {
            $message = MessagePack::pack($messageData);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to encode message: ' . $e->getMessage());
        }

        $length = pack('N', strlen($message));
        $fullMessage = $length . $message;

        $bytesSent = socket_write($socket, $fullMessage, strlen($fullMessage));

        if ($bytesSent === false) {
            if ($socket !== false) {
                socket_close($socket);
            }
            throw new RuntimeException('Failed to send message - ' . socket_strerror(socket_last_error()));
        }

        return ['socket' => $socket, 'task_id' => $taskId];
    }

    /**
     * Await the result of a previously submitted task
     *
     * @param array{socket: Socket|null, task_id: string, benchmark?: array<string, mixed>}     $future
     * @param-out array{socket: Socket|null, task_id: string, benchmark?: array<string, mixed>} $future
     * @return mixed
     * @throws RuntimeException|Throwable
     */
    public function awaitTask(array &$future): mixed
    {
        if (!isset($future['socket'])) {
            throw new RuntimeException('No socket provided');
        }

        $socket = $future['socket'];

        try {
            $response = $this->readFrame($socket);
        } finally {
            socket_close($socket);
        }

        try {
            $data = MessagePack::unpack($response);
        } catch (Throwable $e) {
            throw new RuntimeException('Invalid response from daemon: ' . $e->getMessage());
        }

        if (!is_array($data)) {
            throw new RuntimeException('Invalid response from daemon: not an array');
        }

        if (!isset($data['ok']) || $data['ok'] !== true) {
            throw new RuntimeException('Task failed (awaitTask): ' . ($data['error'] ?? 'unknown error'));
        }

        if (isset($data['benchmark']) && is_array($data['benchmark'])) {
            /** @var array<string, mixed> $benchmarkData */
            $benchmarkData = $data['benchmark'];
            $future['benchmark'] = $benchmarkData;
        }

        return $data['result'] ?? null;
    }

    /**
     * Read a frame from socket (4-byte big-endian length + data)
     *
     * @throws RuntimeException
     */
    private function readFrame(Socket $socket): string
    {
        $lengthData = '';
        $remaining = 4;

        while ($remaining > 0) {
            $chunk = @socket_read($socket, $remaining);

            if ($chunk === false) {
                $error = socket_strerror(socket_last_error($socket));
                throw new RuntimeException("Failed to read length: {$error}");
            }

            if ($chunk === '') {
                throw new RuntimeException('Failed to read response: EOF {1}');
            }

            $lengthData .= $chunk;
            $remaining -= strlen($chunk);
        }

        $unpacked = unpack('N', $lengthData);
        if ($unpacked === false) {
            throw new RuntimeException('Failed to unpack length');
        }
        $length = $unpacked[1];

        $data = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = @socket_read($socket, $remaining);

            if ($chunk === false) {
                $error = socket_strerror(socket_last_error($socket));
                throw new RuntimeException("Failed to read data: {$error}");
            }

            if ($chunk === '') {
                throw new RuntimeException('Failed to read response: EOF {2}');
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    /**
     * Generate random task ID (UUID v4 format)
     */
    private function generateTaskId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

<?php

declare(strict_types=1);

namespace Parallite\Service\Parallite;

use Closure;
use MessagePack\MessagePack;
use Random\RandomException;
use ReflectionException;
use ReflectionFunction;
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
final class SocketService
{
    private const int MAX_PORT_ATTEMPTS = 128;

    private const int PORT_RANGE_END = 65535;

    private const int MAX_PAYLOAD_SIZE = 10 * 1024 * 1024;

    private const int SOCKET_TIMEOUT_SEC = 30;

    private const int MAX_SERIALIZATION_CACHE_SIZE = 1000;

    private array $serializationCache = [];

    public function __construct(
        private readonly string $socketPath,
        private readonly bool   $enableBenchmark = false
    ) {
        ConfigService::validateSocketPath($socketPath);
    }

    /**
     * Submit a task for parallel execution
     *
     * @param  Closure  $closure  The closure to execute
     * @return array{socket: Socket, task_id: string}
     *
     * @throws RuntimeException
     */
    public function submitTask(Closure $closure): array
    {
        $socket = null;

        try {
            if (ConfigService::isWindows()) {
                $socket = $this->connectWithBackoff();

                if ($socket === false) {
                    throw new RuntimeException('Failed to write to socket - '.socket_strerror(socket_last_error()));
                }
            } else {
                $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

                if ($socket === false) {
                    throw new RuntimeException('Failed to create Unix socket - '.socket_strerror(socket_last_error()));
                }

                if (! @socket_connect($socket, $this->socketPath)) {
                    throw new RuntimeException('Failed to connect to daemon at: `'.$this->socketPath.'` - '.socket_strerror(socket_last_error()));
                }
            }

            $taskId = $this->generateTaskId();
            $serialized = $this->serializeClosure($closure);

            $messageData = [
                'type' => 'submit',
                'task_id' => $taskId,
                'payload' => $serialized,
                'context' => [],
            ];

            if ($this->enableBenchmark) {
                $messageData['enable_benchmark'] = true;
            }

            $this->validateMessageData($messageData);

            try {
                $message = MessagePack::pack($messageData);
            } catch (Throwable $e) {
                throw new RuntimeException('Failed to encode message: '.$e->getMessage());
            }

            if (strlen($message) > self::MAX_PAYLOAD_SIZE) {
                throw new RuntimeException('Message too large: '.strlen($message).' bytes (max: '.self::MAX_PAYLOAD_SIZE.')');
            }

            $length = pack('N', strlen($message));
            $fullMessage = $length.$message;

            $bytesSent = socket_write($socket, $fullMessage, strlen($fullMessage));

            if ($bytesSent === false) {
                throw new RuntimeException('Failed to send message - '.socket_strerror(socket_last_error()));
            }

            return ['socket' => $socket, 'task_id' => $taskId];
        } catch (Throwable $e) {
            if ($socket !== null && is_resource($socket)) {
                @socket_close($socket);
            }
            throw $e;
        }
    }

    private function validateMessageData(array $data): void
    {
        if (! isset($data['type']) || ! is_string($data['type'])) {
            throw new RuntimeException('Invalid message: missing or invalid type field');
        }

        $allowedTypes = ['submit', 'response', 'error'];
        if (! in_array($data['type'], $allowedTypes, true)) {
            throw new RuntimeException('Invalid message type: '.$data['type']);
        }

        if (isset($data['payload']) && is_string($data['payload'])) {
            if (strlen($data['payload']) > self::MAX_PAYLOAD_SIZE) {
                throw new RuntimeException('Payload too large: '.strlen($data['payload']).' bytes (max: '.self::MAX_PAYLOAD_SIZE.')');
            }
        }

        if (isset($data['task_id'])) {
            if (! is_string($data['task_id']) || preg_match('/^[a-f0-9-]{36}$/', $data['task_id']) !== 1) {
                throw new RuntimeException('Invalid task_id format');
            }
        }
    }

    private function validateUnpackedData(array $data): void
    {
        if (! isset($data['ok'])) {
            throw new RuntimeException('Invalid response from daemon: missing ok field');
        }

        if ($data['ok'] !== true) {
            throw new RuntimeException('Task failed: '.($data['error'] ?? 'unknown error'));
        }

        if (isset($data['benchmark']) && ! is_array($data['benchmark'])) {
            throw new RuntimeException('Invalid benchmark data format');
        }
    }

    /**
     * @throws ReflectionException
     */
    private function serializeClosure(Closure $closure): string
    {
        $reflection = new ReflectionFunction($closure);
        $code = $reflection->getFileName().':'.$reflection->getStartLine();
        $hash = md5($code);

        if (isset($this->serializationCache[$hash])) {
            return $this->serializationCache[$hash];
        }

        $serialized = \Opis\Closure\serialize($closure);

        if (count($this->serializationCache) >= self::MAX_SERIALIZATION_CACHE_SIZE) {
            array_shift($this->serializationCache);
        }

        $this->serializationCache[$hash] = $serialized;

        return $serialized;
    }

    public function clearSerializationCache(): void
    {
        $this->serializationCache = [];
    }

    /**
     * Await the result of a previously submitted task
     *
     * @param  array{socket: Socket|null, task_id: string, benchmark?: array<string, mixed>}  $future
     *
     * @param-out array{socket: Socket|null, task_id: string, benchmark?: array<string, mixed>} $future
     *
     * @throws RuntimeException|Throwable
     */
    public function awaitTask(array &$future): mixed
    {
        if (! isset($future['socket'])) {
            throw new RuntimeException('No socket provided');
        }

        $socket = $future['socket'];

        try {
            $response = $this->readFrame($socket);
        } finally {
            socket_close($socket);
        }

        if (strlen($response) > self::MAX_PAYLOAD_SIZE) {
            throw new RuntimeException('Response too large: '.strlen($response).' bytes');
        }

        try {
            $unpacked = MessagePack::unpack($response);
        } catch (Throwable $e) {
            throw new RuntimeException('Invalid response from daemon: '.$e->getMessage());
        }

        if (! is_array($unpacked)) {
            throw new RuntimeException('Invalid response from daemon: not an array');
        }

        $this->validateUnpackedData($unpacked);

        if (isset($unpacked['benchmark']) && is_array($unpacked['benchmark'])) {
            $future['benchmark'] = $unpacked['benchmark'];
        }

        return $unpacked['result'] ?? null;
    }

    /**
     * Read a frame from socket (4-byte big-endian length + data)
     *
     * @throws RuntimeException
     */
    private function readFrame(Socket $socket): string
    {
        $timeout = ['sec' => self::SOCKET_TIMEOUT_SEC, 'usec' => 0];
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $timeout);

        $lengthData = '';
        $remaining = 4;

        while ($remaining > 0) {
            $chunk = @socket_read($socket, $remaining);

            if ($chunk === false) {
                $error = socket_strerror(socket_last_error($socket));
                throw new RuntimeException("Failed to read length: {$error}");
            }

            if ($chunk === '') {
                throw new RuntimeException('Failed to read response: EOF');
            }

            $lengthData .= $chunk;
            $remaining -= strlen($chunk);
        }

        $unpacked = unpack('N', $lengthData);
        if ($unpacked === false) {
            throw new RuntimeException('Failed to unpack length');
        }
        $length = $unpacked[1];

        if ($length > self::MAX_PAYLOAD_SIZE) {
            throw new RuntimeException("Response too large: {$length} bytes (max: ".self::MAX_PAYLOAD_SIZE.')');
        }

        $data = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = @socket_read($socket, $remaining);

            if ($chunk === false) {
                $error = socket_strerror(socket_last_error($socket));
                throw new RuntimeException("Failed to read data: {$error}");
            }

            if ($chunk === '') {
                throw new RuntimeException('Failed to read response: EOF');
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    /**
     * Connect to daemon with exponential backoff for Windows TCP sockets
     *
     *
     * @throws RuntimeException
     */
    private function connectWithBackoff(): Socket
    {
        $parts = explode(':', $this->socketPath);
        if (count($parts) !== 2) {
            throw new RuntimeException('Invalid TCP socket path format. Expected host:port');
        }

        [$host, $port] = $parts;
        $port = (int) $port;

        $maxAttempts = 5;
        $baseDelay = 10000;
        $attempts = 0;
        $lastError = '';

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

            if ($socket === false) {
                throw new RuntimeException('Failed to create TCP socket');
            }

            if (@socket_connect($socket, $host, $port)) {
                return $socket;
            }

            $attempts = $attempt + 1;
            $lastError = socket_strerror(socket_last_error($socket));
            socket_close($socket);

            if ($attempt < $maxAttempts - 1) {
                $delay = $baseDelay * (2 ** $attempt);
                usleep($delay);
            }
        }

        throw new RuntimeException(
            sprintf(
                'Failed to connect to daemon after %d attempts. Last error: %s',
                $attempts,
                $lastError
            )
        );
    }

    /**
     * Generate random task ID (UUID v4 format)
     *
     * @throws RandomException
     */
    private function generateTaskId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xFFFF), random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0x0FFF) | 0x4000,
            random_int(0, 0x3FFF) | 0x8000,
            random_int(0, 0xFFFF), random_int(0, 0xFFFF), random_int(0, 0xFFFF)
        );
    }

    /**
     * Await multiple tasks in parallel using socket_select for non-blocking reads
     *
     * @param  array<array{socket: Socket, task_id: string, benchmark?: array<string, mixed>}>  $futures
     *
     * @param-out  array<array{socket: Socket|null, task_id: string, benchmark?: array<string, mixed>}>  $futures
     *
     * @return array<mixed>
     *
     * @throws RuntimeException
     * @throws Throwable
     */
    public function awaitAll(array &$futures): array
    {
        if (count($futures) === 0) {
            return [];
        }

        if (count($futures) === 1) {
            return [$this->awaitTask($futures[0])];
        }

        $sockets = [];
        $futureMap = [];

        foreach ($futures as $index => $future) {
            if (! isset($future['socket'])) {
                throw new RuntimeException('No socket provided');
            }

            $socket = $future['socket'];
            $sockets[(int) $socket] = $socket;
            $futureMap[(int) $socket] = [
                'index' => $index,
                'future' => $future,
            ];
        }

        $results = [];
        $timeout = ['sec' => self::SOCKET_TIMEOUT_SEC, 'usec' => 0];

        while (! empty($sockets)) {
            $read = array_values($sockets);
            $write = $except = null;

            $changed = @socket_select($read, $write, $except, $timeout['sec'], $timeout['usec']);

            if ($changed === false) {
                throw new RuntimeException('Socket select failed');
            }

            if ($changed === 0) {
                foreach ($sockets as $socket) {
                    $index = $futureMap[(int) $socket]['index'];
                    $results[$index] = null;
                    socket_close($socket);
                }
                break;
            }

            foreach ($read as $socket) {
                try {
                    $response = $this->readFrame($socket);
                    socket_close($socket);

                    if (strlen($response) > self::MAX_PAYLOAD_SIZE) {
                        throw new RuntimeException('Response too large: '.strlen($response).' bytes');
                    }

                    $unpacked = MessagePack::unpack($response);

                    if (! is_array($unpacked)) {
                        throw new RuntimeException('Invalid response from daemon: not an array');
                    }

                    $this->validateUnpackedData($unpacked);

                    $index = $futureMap[(int) $socket]['index'];
                    if (isset($unpacked['benchmark']) && is_array($unpacked['benchmark'])) {
                        $futures[$index]['benchmark'] = $unpacked['benchmark'];
                        $futures[$index]['socket'] = null;
                    }
                    $results[$index] = $unpacked['result'] ?? null;

                    unset($sockets[(int) $socket]);
                } catch (Throwable $e) {
                    $index = $futureMap[(int) $socket]['index'];
                    socket_close($socket);
                    $futures[$index]['socket'] = null;
                    $results[$index] = $e;
                    unset($sockets[(int) $socket]);
                }
            }
        }

        ksort($results);

        $finalResults = [];
        foreach ($results as $result) {
            if ($result instanceof Throwable) {
                throw $result;
            }
            $finalResults[] = $result;
        }

        return $finalResults;
    }
}

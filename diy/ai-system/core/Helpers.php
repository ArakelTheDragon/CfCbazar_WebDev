<?php

declare(strict_types=1);

/**
 * Shared utility functions for the lightweight PHP AI system.
 *
 * Responsibilities are intentionally limited to:
 * - HTTP POST requests using cURL
 * - Safe JSON encoding/decoding
 * - OpenRouter fact-response validation
 * - Basic string cleaning
 * - Consistent error arrays
 *
 * No AI routing, memory handling, or response-generation logic belongs here.
 */

if (!class_exists('Helpers', false)) {

    class Helpers
    {
        /**
         * Perform an HTTP POST request with a JSON payload.
         *
         * The return format remains an array so existing callers do not need
         * to change. Transport/API failures are returned through error().
         *
         * @param string $url
         * @param array $headers
         * @param array $payload
         * @return array|null
         */
        public static function httpPostJson(string $url, array $headers, array $payload): ?array
        {
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                return self::error('Invalid HTTP URL.');
            }

            if (!function_exists('curl_init')) {
                return self::error('cURL is not available on this PHP host.');
            }

            try {
                $encodedPayload = json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
            } catch (JsonException $e) {
                return self::error('JSON encode error: ' . $e->getMessage());
            }

            $ch = curl_init($url);

            if ($ch === false) {
                return self::error('Unable to initialize cURL.');
            }

            $httpHeaders = ['Content-Type: application/json'];
            foreach ($headers as $header) {
                $header = trim((string)$header);
                if ($header === '') {
                    continue;
                }
                // Skip empty bearer tokens
                if (preg_match('/^Authorization:\s*Bearer\s*$/i', $header)) {
                    continue;
                }
                // Avoid duplicate Content-Type
                if (stripos($header, 'Content-Type:') === 0) {
                    continue;
                }
                $httpHeaders[] = $header;
            }

            $options = [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $httpHeaders,
                CURLOPT_POSTFIELDS     => $encodedPayload,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 60,
            ];

            if (!curl_setopt_array($ch, $options)) {
                curl_close($ch);
                return self::error('Unable to configure cURL request.');
            }

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($response === false || $curlError !== '') {
                $message = $curlError !== ''
                    ? $curlError
                    : 'Unknown cURL error.';

                return self::error(
                    'HTTP error' . ($curlErrno > 0 ? ' (' . $curlErrno . ')' : '') . ': ' . $message
                );
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $decodedError = self::safeJsonDecode($response);
                $apiMessage = '';

                if (is_array($decodedError) && isset($decodedError['error'])) {
                    if (is_array($decodedError['error'])) {
                        $apiMessage = isset($decodedError['error']['message'])
                            ? (string) $decodedError['error']['message']
                            : '';
                    } elseif (is_string($decodedError['error'])) {
                        $apiMessage = $decodedError['error'];
                    }
                }

                $message = 'HTTP request returned status ' . $httpCode . '.';

                if ($apiMessage !== '') {
                    $message .= ' ' . self::cleanString($apiMessage);
                }

                return self::error($message);
            }

            return self::safeJsonDecode($response);
        }

        /**
         * Safely decode a JSON string into an associative array.
         *
         * @param string $json
         * @return array|null
         */
        public static function safeJsonDecode(string $json): ?array
        {
            $json = trim($json);

            if ($json === '') {
                return self::error('JSON response is empty.');
            }

            try {
                $data = json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                );
            } catch (JsonException $e) {
                return self::error('JSON decode error: ' . $e->getMessage());
            }

            if (!is_array($data)) {
                return self::error('JSON response must decode to an object or array.');
            }

            return $data;
        }

        /**
         * Validate that an OpenRouter response contains JSON facts.
         *
         * This method validates structure only. It does not determine whether
         * the extracted facts are true; that responsibility belongs to the
         * system design above this utility layer.
         *
         * @param array $data
         * @return array
         */
        public static function validateFactJson(array $data): array
        {
            if (!isset($data['choices'][0]['message']['content'])) {
                return self::error('Invalid OpenRouter response structure.');
            }

            $content = $data['choices'][0]['message']['content'];

            if (is_array($content)) {
                return self::error('OpenRouter returned an unexpected content structure.');
            }

            $content = trim((string) $content);

            if ($content === '') {
                return self::error('OpenRouter returned empty content.');
            }

            // Some models may wrap otherwise-valid JSON in a Markdown fence.
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
            $content = trim($content);

            $facts = self::safeJsonDecode($content);

            if ($facts === null || isset($facts['error'])) {
                return self::error('OpenRouter returned non-JSON fact content.');
            }

            return $facts;
        }

        /**
         * Clean a string from excessive whitespace and control characters.
         */
        public static function cleanString(string $text): string
        {
            $text = str_replace(["\r", "\n", "\t", "\0", "\x0B"], ' ', $text);
            $cleaned = preg_replace('/\s+/u', ' ', $text);

            return trim($cleaned ?? $text);
        }

        /**
         * Return an error array in a consistent format.
         */
        public static function error(string $message): array
        {
            return [
                'error'   => true,
                'message' => self::cleanString($message),
            ];
        }
    }
}

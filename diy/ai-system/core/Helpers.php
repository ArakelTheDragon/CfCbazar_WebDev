<?php

/**
 * Helpers
 *
 * Shared utility functions used across the AI system:
 * - HTTP POST requests (curl)
 * - Safe JSON decoding
 * - JSON validation
 * - String cleaning
 * - Error formatting
 *
 * Pure PHP. No Composer. No vendor folder.
 */

class Helpers
{
    /**
     * Perform an HTTP POST request with JSON payload.
     *
     * @param string $url
     * @param array $headers
     * @param array $payload
     * @return array|null
     */
    public static function httpPostJson(string $url, array $headers, array $payload): ?array
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
            ["Content-Type: application/json"],
            $headers
        ));

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $error    = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return self::error("HTTP error: " . $error);
        }

        return self::safeJsonDecode($response);
    }

    /**
     * Safely decode JSON into an array.
     *
     * @param string $json
     * @return array|null
     */
    public static function safeJsonDecode(string $json): ?array
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return self::error("JSON decode error: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Validate that the OpenRouter response contains structured facts.
     *
     * @param array $data
     * @return array
     */
    public static function validateFactJson(array $data): array
    {
        if (!isset($data['choices'][0]['message']['content'])) {
            return self::error("Invalid OpenRouter response structure.");
        }

        $content = $data['choices'][0]['message']['content'];

        // Content must be JSON
        $facts = self::safeJsonDecode($content);

        if (!is_array($facts)) {
            return self::error("OpenRouter returned non-JSON content.");
        }

        return $facts;
    }

    /**
     * Clean strings from whitespace, control chars, etc.
     *
     * @param string $text
     * @return string
     */
    public static function cleanString(string $text): string
    {
        return trim(
            preg_replace('/\s+/', ' ', $text)
        );
    }

    /**
     * Return an error array in a consistent format.
     *
     * @param string $message
     * @return array
     */
    public static function error(string $message): array
    {
        return [
            "error" => true,
            "message" => $message
        ];
    }
}
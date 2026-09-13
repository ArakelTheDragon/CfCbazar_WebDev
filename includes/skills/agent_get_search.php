<?php
// ============================================================================
// Function Definitions (Safe to include anywhere)
// ============================================================================

if (!function_exists('agent_get_search')) {
    /**
     * Fetches search results from SerpApi and parses key structural components.
     */
    function agent_get_search(string $query, string $apiKey = '', string $location = 'Austin, Texas, United States'): array {
        $params = [
            'q'             => $query,
            'location'      => $location,
            'hl'            => 'en',
            'gl'            => 'us',
            'google_domain' => 'google.com'
        ];

        if (!empty($apiKey)) {
            $params['api_key'] = $apiKey;
        }

        $endpoint = 'https://serpapi.com/search.json?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'PHP-AI-Agent-Search/1.0'
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error       = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            return [
                'status'  => 'error',
                'message' => $error ?: "HTTP request failed with status code {$httpCode}"
            ];
        }

        $data = json_decode($rawResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status'  => 'error',
                'message' => 'Failed to parse JSON response: ' . json_last_error_msg()
            ];
        }

        return extract_structured_data($data);
    }
}

if (!function_exists('extract_structured_data')) {
    /**
     * Filters raw SerpApi JSON down to essential AI agent context fields.
     */
    function extract_structured_data(array $data): array {
        $output = [
            'status'          => $data['search_metadata']['status'] ?? 'Success',
            'query'           => $data['search_parameters']['q'] ?? '',
            'location'        => $data['search_parameters']['location_used'] ?? '',
            'local_places'    => [],
            'organic_results' => [],
            'products'        => []
        ];

        if (!empty($data['local_results']['places'])) {
            foreach ($data['local_results']['places'] as $place) {
                $output['local_places'][] = [
                    'title'       => $place['title'] ?? '',
                    'type'        => $place['type'] ?? '',
                    'address'     => $place['address'] ?? '',
                    'rating'      => $place['rating'] ?? null,
                    'reviews'     => $place['reviews'] ?? 0,
                    'price'       => $place['price'] ?? '',
                    'description' => $place['description'] ?? ''
                ];
            }
        }

        if (!empty($data['organic_results'])) {
            foreach ($data['organic_results'] as $result) {
                $output['organic_results'][] = [
                    'position' => $result['position'] ?? null,
                    'title'    => $result['title'] ?? '',
                    'link'     => $result['link'] ?? '',
                    'source'   => $result['source'] ?? '',
                    'snippet'  => $result['snippet'] ?? ''
                ];
            }
        }

        if (!empty($data['immersive_products'])) {
            foreach ($data['immersive_products'] as $product) {
                $output['products'][] = [
                    'title'   => $product['title'] ?? '',
                    'source'  => $product['source'] ?? '',
                    'price'   => $product['price'] ?? '',
                    'rating'  => $product['rating'] ?? null,
                    'reviews' => $product['reviews'] ?? 0
                ];
            }
        }

        return $output;
    }
}

// ============================================================================
// Endpoint Request Handler
// Runs ONLY if accessed directly in browser/AJAX, NOT when included as library
// ============================================================================

$currentScript = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
$thisFile      = realpath(__FILE__);

if ($currentScript && $thisFile && $currentScript === $thisFile) {
    ini_set('display_errors', 0);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    $query    = $_GET['q'] ?? '';
    $location = $_GET['location'] ?? 'Austin, Texas, United States';
    $apiKey   = defined('SERPAPI_KEY') ? SERPAPI_KEY : ($_GET['api_key'] ?? '');

    if ($query === '') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Query parameter "q" is required.'
        ]);
        exit;
    }

    $response = agent_get_search($query, $apiKey, $location);

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

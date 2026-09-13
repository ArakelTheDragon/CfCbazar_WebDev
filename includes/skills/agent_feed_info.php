<?php
// ============================================================================
// CfCbazar Agent Feed
// File: /includes/agent_feed_info.php
// ============================================================================

if (!function_exists('process_agent_feed_info')) {

    /**
     * Process information supplied by the CfCbazar AI Agent.
     *
     * This function prepares feed information for storage/display.
     *
     * @param string $category
     * @param string $infoData
     * @return array
     */
    function process_agent_feed_info(
        string $category,
        string $infoData
    ): array {

        // --------------------------------------------------------------------
        // Clean input
        // --------------------------------------------------------------------

        $category = trim($category);
        $infoData = trim($infoData);


        // --------------------------------------------------------------------
        // Validate
        // --------------------------------------------------------------------

        if ($category === '') {

            return [
                'status' =>
                    'error',

                'error' =>
                    'Feed category cannot be empty.'
            ];
        }


        if ($infoData === '') {

            return [
                'status' =>
                    'error',

                'error' =>
                    'Feed information cannot be empty.'
            ];
        }


        // --------------------------------------------------------------------
        // Length protection
        // --------------------------------------------------------------------

        if (
            function_exists('mb_strlen') &&
            mb_strlen($category, 'UTF-8') > 100
        ) {

            return [
                'status' =>
                    'error',

                'error' =>
                    'Feed category is too long.'
            ];
        }


        if (
            function_exists('mb_strlen') &&
            mb_strlen($infoData, 'UTF-8') > 50000
        ) {

            return [
                'status' =>
                    'error',

                'error' =>
                    'Feed information is too long.'
            ];
        }


        // --------------------------------------------------------------------
        // Do NOT HTML-escape data before storing it.
        //
        // htmlspecialchars() should be applied when the feed is displayed,
        // not when the data is stored.
        // --------------------------------------------------------------------

        $safeCategory =
            $category;

        $safeData =
            $infoData;


        // --------------------------------------------------------------------
        // Timestamp
        // --------------------------------------------------------------------

        $timestamp =
            date('Y-m-d H:i:s');


        // --------------------------------------------------------------------
        // Return processed feed item
        //
        // Database insertion can be added here later.
        // --------------------------------------------------------------------

        return [

            'status' =>
                'success',

            'category' =>
                $safeCategory,

            'info' =>
                $safeData,

            'bytes' =>
                strlen($safeData),

            'timestamp' =>
                $timestamp
        ];
    }
}

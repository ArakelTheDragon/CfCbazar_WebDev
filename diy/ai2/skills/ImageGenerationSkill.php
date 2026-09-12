<?php

class ImageGenerationSkill implements AISkillInterface {

    public function getKeywords(): array {
        return [
            'generate image', 'draw', 'create picture', 'make an image',
            'paint', 'render', 'illustration of', 'photo of', 'draw me'
        ];
    }

    public function getPriority(): int {
        return 90; // High priority to capture explicit visual requests
    }

    public function execute(string $input, array &$memory): string {
        $inputTrimmed = trim($input);
        
        // 1. EXTRACT & ENHANCE VISUAL PROMPT
        $enhancedPrompt = $this->enhancePromptWithLLM($inputTrimmed);
        
        // 2. GENERATE IMAGE VIA API
        // Option A: Free/Instant Endpoint (Pollinations.ai - No API key needed)
        $imageUrl = $this->generatePollinationsImage($enhancedPrompt);
        
        // Option B: OpenRouter / DALL-E 3 (Uncomment if using OpenRouter API key)
        // $imageUrl = $this->generateOpenRouterImage($enhancedPrompt);

        if (!$imageUrl) {
            return "Unable to generate image at this time. Please try again later.";
        }

        // Save generation metadata to session memory
        $topicKey = 'img_' . substr(md5($inputTrimmed), 0, 8);
        $memory["knowledge"][$topicKey] = [
            'original_prompt' => $inputTrimmed,
            'enhanced_prompt' => $enhancedPrompt,
            'url'             => $imageUrl,
            'timestamp'        => time()
        ];

        // 3. RETURN FORMATTED RESPONSE
        return "Here is your generated image based on the prompt: **\"" . htmlspecialchars($enhancedPrompt) . "\"**\n\n" .
               "![Generated Image](" . $imageUrl . ")\n\n" .
               "*Direct link:* [" . $imageUrl . "](" . $imageUrl . ")";
    }

    /**
     * Uses LLM via OpenRouter to expand short user queries into detailed visual prompts.
     */
    private function enhancePromptWithLLM(string $input): string {
        $prompt = "Convert this request into a detailed image generation prompt (include art style, lighting, mood, color palette, and detail quality). Output ONLY the prompt text without explanation:\n\"{$input}\"";
        
        $enhanced = queryOpenRouter($prompt);
        if ($enhanced && !str_contains($enhanced, 'OpenRouter Error')) {
            return trim(str_replace(['"', "\n"], '', $enhanced));
        }

        // Fallback: Strip common trigger words manually
        return preg_replace('/^(generate image|draw|create picture|make an image|paint|render)\s+(of\s+)?/i', '', $input);
    }

    /**
     * Generates an image URL using Pollinations.ai (Free, fast, web-friendly)
     */
    private function generatePollinationsImage(string $prompt): string {
        $encodedPrompt = urlencode($prompt);
        // Parameters: width=1024, height=1024, seed for variability, nologo=true
        $seed = rand(1000, 999999);
        return "https://image.pollinations.ai/prompt/{$encodedPrompt}?width=1024&height=1024&seed={$seed}&nologo=true";
    }

    /**
     * Alternative: OpenRouter API / Standard Image API payload generator
     */
    private function generateOpenRouterImage(string $prompt): ?string {
        $apiKey = getenv('OPENROUTER_API_KEY'); // Or your global API key constant
        if (!$apiKey) return null;

        $url = 'https://openrouter.ai/api/v1/images/generations';
        $payload = json_encode([
            'model'  => 'stabilityai/stable-diffusion-3-medium', // Or 'openai/dall-e-3'
            'prompt' => $prompt,
            'n'      => 1,
            'size'   => '1024x1024'
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer {$apiKey}"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['data'][0]['url'])) {
                return $data['data'][0]['url'];
            }
        }

        return null;
    }
}

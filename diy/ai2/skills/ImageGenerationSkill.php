<?php
/* ============================================================
   CfCbazar Group — ImageGenerationSkill.php
   File: /diy/ai2/skills/ImageGenerationSkill.php
   ============================================================ */

if (!interface_exists('AISkillInterface')) {
    interface AISkillInterface {
        public function getKeywords(): array;
        public function getPriority(): int;
        public function execute(string $input, array &$memory): string;
    }
}

class ImageGenerationSkill implements AISkillInterface {

    public function getKeywords(): array {
        return [
            'generate image',
            'generate an image',
            'draw',
            'draw an image',
            'create image',
            'create an image',
            'picture of',
            'image of',
            'photo of',
            'paint'
        ];
    }

    public function getPriority(): int {
        return 10;
    }

    public function execute(string $input, array &$memory): string {
        $inputClean = trim($input);
        
        // Strip common command phrases to isolate the core visual prompt
        $searchPhrases = [
            '/^generate an image of/i',
            '/^generate image of/i',
            '/^generate an image on/i',
            '/^generate image on/i',
            '/^generate an image/i',
            '/^generate image/i',
            '/^draw me a picture of/i',
            '/^draw an image of/i',
            '/^draw/i',
            '/^create an image of/i',
            '/^create image/i'
        ];

        $imagePrompt = preg_replace($searchPhrases, '', $inputClean);
        $imagePrompt = trim(preg_replace('/[^a-zA-Z0-9\s,.-]/', '', $imagePrompt));

        if (empty($imagePrompt) || strlen($imagePrompt) < 3) {
            $randomTopics = [
                'a futuristic cyberpunk city in the rain with neon lights',
                'a cozy cabin in a snowy pine forest at sunset',
                'a floating fantasy island in the clouds with waterfalls',
                'a sleek retro 80s synthwave sports car driving on a grid road'
            ];
            $imagePrompt = $randomTopics[array_rand($randomTopics)];
        }

        $encodedPrompt = urlencode($imagePrompt);
        $imageUrl = "https://image.pollinations.ai/prompt/" . $encodedPrompt . "?width=1024&height=768&nologo=true";

        $output = "### Image Generation Result\n\n";
        $output .= "**Prompt:** *" . htmlspecialchars($imagePrompt) . "*\n\n";
        $output .= "![" . htmlspecialchars($imagePrompt) . "](" . $imageUrl . ")\n\n";
        $output .= "[Direct Image Link](" . $imageUrl . ")";

        return $output;
    }
}

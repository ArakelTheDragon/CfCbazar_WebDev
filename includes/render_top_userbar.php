<?php
/**
 * CfCbazar UI Navigation Helper Library
 * File: /includes/render_top_userbar.php
 *
 * Renders a full-width sticky advert top bar positioned below the main menu 
 * with a Left-to-Right rotating text transition for CfCbazar ecosystem links.
 */

declare(strict_types=1);

if (!function_exists('render_top_userbar')) {
    /**
     * Render the fixed full-width top advert bar with left-to-right rotating text links.
     *
     * @return void
     */
    function render_top_userbar(): void
    {
        // Define your rotating text advert slides here (Display Text -> Target URL)
        $slides = [
            [
                'text' => '🛠️ DIY Open Source Projects',
                'url'  => 'https://cfcbazar.42web.io/diy/index.php'
            ],
            [
                'text' => '🛍️ Smart Deals Facebook Group',
                'url'  => 'https://www.facebook.com/groups/195994786555718/'
            ],
            [
                'text' => '🎮 Play Online Games',
                'url'  => 'https://cfcbazar.42web.io/games/index.php'
            ],
            [
                'text' => '📺 Free TV & Music',
                'url'  => 'https://www.youtube.com/@cfcbazar/playlists'
            ],
            [
                'text' => '🛒 Visit Our Official eBay Store',
                'url'  => 'https://ebay.us/m/DM1tRs'
            ],
            [
                'text' => '💳 Donate a $1 on PayPal',
                'url'  => 'https://www.paypal.com/donate/?hosted_button_id=CM8VXGMXAC39A'
            ],
        ];

        // Build HTML for text slides
        $slidesHtml = '';
        foreach ($slides as $index => $slide) {
            $activeClass = ($index === 0) ? 'cfc-text-active' : '';
            $textEsc = htmlspecialchars($slide['text'], ENT_QUOTES, 'UTF-8');
            $urlEsc = htmlspecialchars($slide['url'], ENT_QUOTES, 'UTF-8');

            $slidesHtml .= <<<HTML
            <a href="{$urlEsc}" class="cfc-text-slide {$activeClass}" target="_blank" rel="noopener">
                {$textEsc}
            </a>
            HTML;
        }

        echo <<<HTML
        <style>
            .cfc-topbar-wrapper {
                position: fixed;
                top: 60px; /* Positioned below sticky main-nav */
                left: 0;
                right: 0;
                width: 100vw;
                background: #111418;
                z-index: 999;
                border-bottom: 3px solid var(--primary, #28a745);
                box-sizing: border-box;
                padding: 10px 0;
                overflow: hidden;
            }

            .cfc-text-slider {
                position: relative;
                width: 100%;
                height: 36px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

            .cfc-text-slide {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                opacity: 0;
                transform: translateX(-100%); /* Start off-screen to the left */
                transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.6s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #ffffff;
                font-family: inherit;
                font-size: 1.2rem;
                font-weight: 700;
                text-decoration: none;
                text-align: center;
                white-space: nowrap;
                padding: 0 16px;
                box-sizing: border-box;
            }

            .cfc-text-slide:hover {
                color: var(--primary, #28a745);
                text-decoration: underline;
            }

            /* Active State: Slides into the center */
            .cfc-text-slide.cfc-text-active {
                opacity: 1;
                transform: translateX(0);
                z-index: 2;
            }

            /* Exit State: Slides out off-screen to the right */
            .cfc-text-slide.cfc-text-exit {
                opacity: 0;
                transform: translateX(100%);
                z-index: 1;
            }

            /* Responsive Adjustments for Mobile Screens */
            @media (max-width: 768px) {
                .cfc-topbar-wrapper {
                    top: 56px;
                    padding: 8px 0;
                }
                .cfc-text-slider {
                    height: 30px;
                }
                .cfc-text-slide {
                    font-size: 1rem;
                    white-space: normal; /* Allows 2 lines on small screens if text is long */
                    line-height: 1.25;
                }
            }

            @media (max-width: 480px) {
                .cfc-text-slide {
                    font-size: 0.92rem;
                }
            }
        </style>

        <div class="cfc-topbar-wrapper">
            <div class="cfc-text-slider" id="cfcTextSlider">
                {$slidesHtml}
            </div>
        </div>

        <script>
            (function() {
                let textSlides = document.querySelectorAll('#cfcTextSlider .cfc-text-slide');
                if (textSlides.length <= 1) return;

                let currentIndex = 0;

                setInterval(function() {
                    let prevIndex = currentIndex;
                    currentIndex = (currentIndex + 1) % textSlides.length;

                    // Slide outgoing item out to the right
                    textSlides[prevIndex].classList.remove('cfc-text-active');
                    textSlides[prevIndex].classList.add('cfc-text-exit');

                    // Reset exit class after transition completes so it can re-enter from left
                    setTimeout(function() {
                        textSlides[prevIndex].classList.remove('cfc-text-exit');
                    }, 600);

                    // Slide incoming item in from the left
                    textSlides[currentIndex].classList.add('cfc-text-active');
                }, 4000); // Rotates every 4 seconds
            })();
        </script>
        HTML;
    }
}

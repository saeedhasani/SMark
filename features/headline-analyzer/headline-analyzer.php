<?php
/**
 * Headline Analyzer Feature
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

class SMarkHeadlineAnalyzer {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_SMARK_analyze_headline', array($this, 'ajax_analyze_headline'));
    }

    private static function log_debug($message, $context = array()) {
        if (class_exists('SMarkLogger', false)) {
            SMarkLogger::debug($message, $context);
        }
    }

    private static function log_info($message, $context = array()) {
        if (class_exists('SMarkLogger', false)) {
            SMarkLogger::info($message, $context);
        }
    }

    private static function log_warning($message, $context = array()) {
        if (class_exists('SMarkLogger', false)) {
            SMarkLogger::warning($message, $context);
        }
    }
    
    /**
     * Add submenu page (hidden from menu)
     */
    public function add_submenu_page() {
        add_submenu_page(
            null, // Hidden from menu
            __('Headline Analyzer', 'smark'),
            __('Headline Analyzer', 'smark'),
            'smark_access',
            'smark-headline-analyzer',
            array($this, 'render_page')
        );
    }
    
    /**
     * Analyze headline via AJAX
     */
    public function ajax_analyze_headline() {
        self::log_debug('Headline Analyzer - AJAX Request Started');
        
        // Check nonce
        check_ajax_referer('SMARK_headline_analyzer', 'nonce');
        
        // Check permissions
        if (!current_user_can('smark_access')) {
            self::log_warning('Headline Analyzer - Permission denied');
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'smark')));
        }
        
        // Get headline
        $headline = isset($_POST['headline']) ? sanitize_text_field(wp_unslash($_POST['headline'])) : '';
        self::log_debug('Headline Analyzer - Received headline', array('headline' => $headline));
        
        if (empty($headline)) {
            wp_send_json_error(array('message' => __('Please enter a headline to analyze.', 'smark')));
        }
        
        // Analyze headline
        $analysis = $this->analyze_headline($headline);
        
        // Send response
        wp_send_json_success($analysis);
    }
    
    /**
     * Analyze headline and return results
     */
    /**
     * Get Gemini API Key from Gemini App
     */
    private function get_gemini_api_key() {
        global $SMARK_gemini_app;
        if (isset($SMARK_gemini_app)) {
            return $SMARK_gemini_app->get_api_key();
        }
        return '';
    }
    
    /**
     * Get selected Gemini model
     */
    private function get_gemini_model() {
        return get_option('SMARK_gemini_model', 'gemini-1.5-flash');
    }
    
    /**
     * Get output language for Gemini API based on panel language
     * This is a public method so other features can use it
     */
    public function get_gemini_output_language() {
        $current_lang = get_option('SMARK_panel_language', 'en');
        return ($current_lang === 'fa') ? 'Persian (Farsi)' : 'English';
    }
    
    /**
     * Check if headline has Gains Creators & Pains Reliever using Gemini AI
     * Includes retry mechanism with exponential backoff for handling rate limits and overload errors
     */
    private function check_gains_pains_with_gemini($headline) {
        $api_key = $this->get_gemini_api_key();
        
        // Log the API key status
        self::log_debug('Headline Analyzer - Gains/Pains Check: API Key status', array('is_empty' => empty($api_key)));
        
        // Get output language based on panel language
        $output_language = $this->get_gemini_output_language();
        self::log_debug('Headline Analyzer - Output language', array('output_language' => $output_language));
        
        // Optimized prompt using prompt engineering best practices
        $prompt = 'Analyze this headline for Value Proposition elements:

Headline: "' . $headline . '"

Task: Determine if it contains Gains Creators (benefits, positive outcomes) AND/OR Pains Relievers (problem solutions, anxiety reducers).

Output format (JSON only):
{
  "has_gains_pains": true/false,
  "explanation": "Brief reason (max 250 chars)"
}

Rules:
- Gains Creators: promises, benefits, improvements, achievements
- Pains Relievers: solves problems, reduces fears, removes obstacles
- Response must be valid JSON
- Explanation under 250 characters
- Answer in ' . $output_language;
        
        if (empty($api_key) || $api_key === 'YOUR_API_KEY_HERE') {
            self::log_warning('Headline Analyzer - Gains/Pains Check: API Key not configured or placeholder');
            
            // Save error to Gemini App logs
            $this->save_to_gemini_logs($prompt, 'Error: API Key not configured. Please set your Gemini API key in the code.', 'headline-analyzer-error');
            
            return array(
                'has_gains_pains' => false,
                'explanation' => 'API Key not configured',
                'error' => true
            );
        }
        
        $model = $this->get_gemini_model();
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
        
        self::log_debug('Headline Analyzer - Using model', array('model' => $model));
        self::log_debug('Headline Analyzer - Analyzing headline', array('headline' => $headline));
        
        $body = json_encode(array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.2,
                'maxOutputTokens' => 2048,
                'responseMimeType' => 'application/json'
            )
        ));
        
        // Retry mechanism with exponential backoff
        // Increased delay to handle model overload (503 errors)
        $max_retries = 4;        // 4 attempts total (1 initial + 3 retries)
        $retry_delay = 10;       // Start with 10 seconds (increased from 1)
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            self::log_debug('Headline Analyzer - Request attempt', array('attempt' => $attempt, 'max_retries' => $max_retries));
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => $body,
                'timeout' => 15,
            ));
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                self::log_warning('Headline Analyzer - Connection error', array('attempt' => $attempt, 'error' => $error_message));
                
                // If this is the last attempt, return error
                if ($attempt === $max_retries) {
                    $this->save_to_gemini_logs($prompt, 'Error: Connection failed after ' . $max_retries . ' attempts - ' . $error_message, 'headline-analyzer-error');
                    
                    return array(
                        'has_gains_pains' => false,
                        'explanation' => 'Connection error after retries',
                        'error' => true
                    );
                }
                
                // Wait before retry (exponential backoff)
                sleep($retry_delay);
                $retry_delay *= 2; // Double the delay for next attempt
                continue; // Try again
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            self::log_debug('Headline Analyzer - Response', array('attempt' => $attempt, 'code' => $response_code, 'body_preview' => substr($response_body, 0, 500)));
            
            // Check if we should retry (503 overloaded, 429 rate limit, 500 internal error)
            $retryable_codes = array(429, 500, 503);
            
            if (in_array($response_code, $retryable_codes)) {
                $error_data = json_decode($response_body, true);
                $error_detail = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
                self::log_warning('Headline Analyzer - Retryable error', array('attempt' => $attempt, 'code' => $response_code, 'detail' => $error_detail));
                
                // If this is the last attempt, return error
                if ($attempt === $max_retries) {
                    $this->save_to_gemini_logs(
                        $prompt, 
                        'Error: API request failed after ' . $max_retries . ' attempts (HTTP ' . $response_code . ') - ' . $error_detail . "\n\nFull response: " . $response_body, 
                        'headline-analyzer-error'
                    );
                    
                    return array(
                        'has_gains_pains' => false,
                        'explanation' => 'API temporarily unavailable',
                        'error' => true
                    );
                }
                
                // Wait before retry (exponential backoff)
                self::log_debug('Headline Analyzer - Waiting before retry', array('seconds' => $retry_delay));
                sleep($retry_delay);
                $retry_delay *= 2; // Double the delay for next attempt
                continue; // Try again
            }
            
            // Non-retryable error (e.g., 400, 401, 403, 404)
            if ($response_code !== 200) {
                $error_data = json_decode($response_body, true);
                $error_detail = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
                self::log_warning('Headline Analyzer - Non-retryable API error', array('code' => $response_code, 'detail' => $error_detail));
                
                $this->save_to_gemini_logs(
                    $prompt, 
                    'Error: API request failed (HTTP ' . $response_code . ') - ' . $error_detail . "\n\nFull response: " . $response_body, 
                    'headline-analyzer-error'
                );
                
                return array(
                    'has_gains_pains' => false,
                    'explanation' => 'API error: ' . $error_detail,
                    'error' => true
                );
            }
            
            // Success! Break out of retry loop
            break;
        }
        
        $data = json_decode($response_body, true);
        
        // Check if response was cut off due to max tokens
        if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'MAX_TOKENS') {
            self::log_warning('Headline Analyzer - Response incomplete: MAX_TOKENS reached');
            
            // Save error to Gemini App logs
            $this->save_to_gemini_logs(
                $prompt, 
                'Error: Response incomplete - MAX_TOKENS limit reached. Increase maxOutputTokens. Response: ' . wp_json_encode($data),
                'headline-analyzer-error'
            );
            
            return array(
                'has_gains_pains' => false,
                'explanation' => 'Response incomplete - token limit reached',
                'error' => true
            );
        }
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            self::log_warning('Headline Analyzer - Invalid API response structure', array('response' => $data));
            
            // Save error to Gemini App logs
            $this->save_to_gemini_logs(
                $prompt, 
                'Error: Invalid API response structure. Response: ' . wp_json_encode($data),
                'headline-analyzer-error'
            );
            
            return array(
                'has_gains_pains' => false,
                'explanation' => 'Invalid API response',
                'error' => true
            );
        }
        
        $gemini_text = $data['candidates'][0]['content']['parts'][0]['text'];
        self::log_debug('Headline Analyzer - Gemini response text', array('text' => $gemini_text));
        
        // Extract JSON from response (Gemini might wrap it in markdown)
        if (preg_match('/\{[\s\S]*\}/', $gemini_text, $matches)) {
            $json_str = $matches[0];
            $result = json_decode($json_str, true);
            
            self::log_debug('Headline Analyzer - Parsed JSON', array('result' => $result));
            
            if ($result && isset($result['has_gains_pains'])) {
                self::log_info('Headline Analyzer - Analysis successful!');
                
                // Save to Gemini App logs (SUCCESS)
                $this->save_to_gemini_logs($prompt, $gemini_text, 'headline-analyzer');
                
                // Get explanation and handle multi-byte characters properly
                $explanation = isset($result['explanation']) ? trim($result['explanation']) : 'No explanation provided';
                
                // Use mb_substr for proper UTF-8 handling (increase limit to 250 chars for Persian)
                $max_length = 250;
                if (mb_strlen($explanation) > $max_length) {
                    $explanation = mb_substr($explanation, 0, $max_length) . '...';
                }
                
                return array(
                    'has_gains_pains' => (bool) $result['has_gains_pains'],
                    'explanation' => $explanation,
                    'error' => false
                );
            }
        }
        
        self::log_warning('Headline Analyzer - Failed to parse JSON from response');
        
        // Save error to Gemini App logs
        $this->save_to_gemini_logs($prompt, 'Error: Failed to parse JSON from response. Raw response: ' . $gemini_text, 'headline-analyzer-error');
        
        return array(
            'has_gains_pains' => false,
            'explanation' => 'Failed to parse response',
            'error' => true
        );
    }
    
    /**
     * Save analysis to Gemini App logs
     */
    private function save_to_gemini_logs($input, $output, $feature_source) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'SMARK_gemini_app';
        
        // Get the model used for this request
        $model = $this->get_gemini_model();
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $table_name,
            array(
                'input' => $input,
                'output' => $output,
                'feature_source' => $feature_source,
                'model' => $model,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        
        self::log_info('Headline Analyzer - Saved to Gemini logs', array('insert_id' => $wpdb->insert_id, 'model' => $model));
    }
    
    /**
     * Count words in Persian text properly
     */
    private function count_words_persian($text) {
        // Remove extra whitespace and normalize
        $text = trim($text);
        if (empty($text)) {
            return 0;
        }
        
        // Split by whitespace and filter out empty strings
        $words = preg_split('/\s+/', $text);
        $words = array_filter($words, function($word) {
            return !empty(trim($word));
        });
        
        return count($words);
    }
    
    /**
     * Analyze headline and return results
     * This method is public so other features can use it as a microservice
     */
    public function analyze_headline($headline) {
        // Character count (informational only)
        $char_count = mb_strlen($headline);
        $word_count = $this->count_words_persian($headline);
        
        // Check if headline has numbers
        $has_numbers = preg_match('/\d/', $headline) ? true : false;
        
        // Check for Gains Creators & Pains Relievers using Gemini AI
        $gains_pains_result = $this->check_gains_pains_with_gemini($headline);
        
        // Calculate score based on two factors (50 points each):
        // 1. Has numbers = 50 points
        // 2. Has Gains Creators & Pain Relievers = 50 points
        // Total possible score = 100 points
        $score = 0;
        
        if ($has_numbers) {
            $score += 50;
        }
        
        if ($gains_pains_result['has_gains_pains']) {
            $score += 50;
        }
        
        return array(
            'headline' => $headline,
            'score' => $score,
            'char_count' => $char_count,
            'word_count' => $word_count,
            'has_numbers' => $has_numbers,
            'has_gains_pains' => $gains_pains_result['has_gains_pains'],
            'gains_pains_explanation' => $gains_pains_result['explanation'],
            'gains_pains_error' => $gains_pains_result['error']
        );
    }
    
    /**
     * Get translation
     */
    private function get_translation($key) {
        $lang = get_option('SMARK_panel_language', 'en');
        
        $translations = array(
            'en' => array(
                'gains_pains_label' => 'Gain Creators & Pain Relievers:',
                'ai_analysis_label' => 'AI Analysis:',
            ),
            'fa' => array(
                'gains_pains_label' => 'منفعت‌ساز و دردسرکاه:',
                'ai_analysis_label' => 'تحلیل هوش مصنوعی:',
            )
        );
        
        return isset($translations[$lang][$key]) ? $translations[$lang][$key] : $key;
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'admin_page_smark-headline-analyzer') {
            return;
        }
        
        wp_enqueue_style('smark-headline-analyzer', SMARK_PLUGIN_URL . 'features/headline-analyzer/assets/analyzer.css', array(), SMARK_VERSION . '.' . time());
        wp_enqueue_script('smark-headline-analyzer', SMARK_PLUGIN_URL . 'features/headline-analyzer/assets/analyzer.js', array('jquery'), SMARK_VERSION . '.' . time(), true);
        
        // Localize script
        wp_localize_script('smark-headline-analyzer', 'smarkHeadlineAnalyzer', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('SMARK_headline_analyzer'),
            'strings' => array(
                'analyzing' => __('Analyzing...', 'smark'),
                'analyze' => __('Analyze Headline', 'smark'),
                'error' => __('An error occurred. Please try again.', 'smark')
            )
        ));
    }
    
    /**
     * Render the analyzer page
     */
    public function render_page() {
        ?>
        <div class="wrap smark-headline-analyzer-page">
            <div class="smark-page-header">
                <h1><?php echo esc_html__('Headline Analyzer', 'smark'); ?></h1>
                <p class="description"><?php echo esc_html__('Analyze your headlines and get suggestions to improve engagement and SEO.', 'smark'); ?></p>
            </div>
            
            <div class="smark-breadcrumb">
                <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html__('SMark Dashboard', 'smark'); ?></a> 
                <span class="separator">›</span> 
                <span class="current"><?php echo esc_html__('Headline Analyzer', 'smark'); ?></span>
            </div>
            
            <div class="smark-analyzer-content">
                <div class="analyzer-form-section">
                    <div class="analyzer-form-card">
                        <div class="analyzer-card">
                            <div class="form-header">
                                <h2><?php echo esc_html__('Enter Your Headline', 'smark'); ?></h2>
                                <p><?php echo esc_html__('Type your headline below and click analyze to get instant feedback', 'smark'); ?></p>
                            </div>
                            
                            <div class="form-body">
                                <div class="form-group">
                                    <label for="headline_input"><?php echo esc_html__('Headline', 'smark'); ?></label>
                                    <input type="text" 
                                           id="headline_input" 
                                           class="form-control headline-input" 
                                           placeholder="<?php echo esc_attr__('Enter your headline here...', 'smark'); ?>"
                                           maxlength="200">
                                    <div class="character-counter">
                                        <span class="current-count">0</span> / 200 <?php echo esc_html__('characters', 'smark'); ?>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" id="analyze_btn" class="btn btn-primary">
                                        <span class="dashicons dashicons-search"></span>
                                        <?php echo esc_html__('Analyze Headline', 'smark'); ?>
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="location.href='<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>'">
                                        <span class="dashicons dashicons-arrow-left-alt"></span>
                                        <?php echo esc_html__('Back to Dashboard', 'smark'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="analyzer-results-section" id="results_section" style="display: none;">
                    <div class="results-card">
                        <div class="analyzer-card">
                            <div class="results-header">
                                <h2><?php echo esc_html__('Analysis Results', 'smark'); ?></h2>
                            </div>
                            
                            <div class="results-body">
                                <div class="score-section">
                                    <div class="score-circle">
                                        <svg width="120" height="120">
                                            <circle cx="60" cy="60" r="50" class="score-bg"></circle>
                                            <circle cx="60" cy="60" r="50" class="score-progress" id="score_circle"></circle>
                                        </svg>
                                        <div class="score-text">
                                            <span class="score-value" id="score_value">0</span>
                                            <span class="score-label"><?php echo esc_html__('Score', 'smark'); ?></span>
                                        </div>
                                    </div>
                                    <div class="score-details">
                                        <div class="detail-item">
                                            <span class="detail-label"><?php echo esc_html__('Characters:', 'smark'); ?></span>
                                            <span class="detail-value" id="char_count">0</span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label"><?php echo esc_html__('Words:', 'smark'); ?></span>
                                            <span class="detail-value" id="word_count">0</span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label"><?php echo esc_html__('Has Numbers:', 'smark'); ?></span>
                                            <span class="detail-value" id="has_numbers">-</span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label"><?php echo esc_html($this->get_translation('gains_pains_label')); ?></span>
                                            <span class="detail-value" id="has_gains_pains">-</span>
                                        </div>
                                    </div>
                                    
                                    <!-- AI Analysis Explanation -->
                                    <div class="ai-explanation" id="gains_pains_explanation" style="display: none;">
                                        <div class="explanation-header">
                                            <span class="dashicons dashicons-lightbulb"></span>
                                            <strong><?php echo esc_html($this->get_translation('ai_analysis_label')); ?></strong>
                                </div>
                                        <p class="explanation-text" id="explanation_text"></p>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the analyzer and make it globally accessible
// $GLOBALS['SMARK_headline_analyzer'] = SMarkHeadlineAnalyzer::get_instance();

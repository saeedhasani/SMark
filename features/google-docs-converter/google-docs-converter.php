<?php
/**
 * Google Docs to Blog Post Converter Feature
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

class SMarkGoogleDocsConverter {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'handle_page_access'));
    }
    
    /**
     * Add submenu page (hidden from menu)
     */
    public function add_submenu_page() {
        add_submenu_page(
            null, // Hidden from menu
            __('Google Docs Converter', 'smark'),
            __('Google Docs Converter', 'smark'),
            'smark_access',
            'smark-google-docs-converter',
            array($this, 'render_page')
        );
    }
    
    /**
     * Handle page access
     */
    public function handle_page_access() {
        if (isset($_GET['page']) && $_GET['page'] === 'smark-google-docs-converter') {
            if (!current_user_can('smark_access')) {
                wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
            }
        }
        
        // Handle form submission
        if (isset($_POST['convert_docs'])) {
            check_admin_referer('SMARK_convert_docs', 'SMARK_nonce');
            $this->handle_conversion();
        }
    }
    
    /**
     * Handle Google Docs conversion
     */
    private function handle_conversion() {
        check_admin_referer('SMARK_convert_docs', 'SMARK_nonce');

        $docs_url = isset($_POST['google_docs_url']) ? esc_url_raw(wp_unslash($_POST['google_docs_url'])) : '';
        
        if (empty($docs_url) || !filter_var($docs_url, FILTER_VALIDATE_URL)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a valid Google Docs URL.', 'smark') . '</p></div>';
            });
            return;
        }
        
        // Check if it's a Google Docs URL
        if (strpos($docs_url, 'docs.google.com/document') === false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a valid Google Docs document URL.', 'smark') . '</p></div>';
            });
            return;
        }
        
        // Extract document ID from URL
        $document_id = $this->extract_document_id($docs_url);
        if (!$document_id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Could not extract document ID from the URL. Please make sure the document is publicly accessible.', 'smark') . '</p></div>';
            });
            return;
        }
        
        // Fetch and convert the document
        $result = $this->convert_google_docs_to_post($document_id, $docs_url);
        
        if ($result['success']) {
            // Store the URL for future processing
            update_option('SMARK_last_docs_url', $docs_url);
            
            add_action('admin_notices', function() use ($result) {
                $edit_link = get_edit_post_link($result['post_id']);
                $view_link = get_permalink($result['post_id']);

                $message = sprintf(
                    /* translators: 1: Edit post URL, 2: View post URL. */
                    __('Successfully converted Google Docs to WordPress post! <a href="%1$s" target="_blank" rel="noopener noreferrer">Edit Post</a> | <a href="%2$s" target="_blank" rel="noopener noreferrer">View Post</a>', 'smark'),
                    esc_url($edit_link),
                    esc_url($view_link)
                );
                echo '<div class="notice notice-success"><p>' . 
                     wp_kses_post($message) .
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            });
        }
    }
    
    /**
     * Extract document ID from Google Docs URL
     */
    private function extract_document_id($url) {
        $pattern = '/\/document\/d\/([a-zA-Z0-9-_]+)/';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : false;
    }
    
    /**
     * Convert Google Docs to WordPress post
     */
    private function convert_google_docs_to_post($document_id, $original_url) {
        try {
            // Convert Google Docs URL to export format
            $export_url = "https://docs.google.com/document/d/{$document_id}/export?format=html";
            
            // Fetch the HTML content
            $response = wp_remote_get($export_url, array(
                'timeout' => 30,
                'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => __('Failed to fetch document content. Please make sure the document is publicly accessible.', 'smark')
                );
            }
            
            $html_content = wp_remote_retrieve_body($response);
            
            if (empty($html_content)) {
                return array(
                    'success' => false,
                    'message' => __('Document appears to be empty or inaccessible. Please check the document permissions.', 'smark')
                );
            }
            
            // Parse and clean the HTML content
            $clean_content = $this->parse_google_docs_html($html_content);
            
            // Extract title from content or use default
            $title = $this->extract_title_from_content($clean_content);
            if (empty($title)) {
                /* translators: %s: Import timestamp. */
                $title = sprintf(__('Imported from Google Docs - %s', 'smark'), gmdate('Y-m-d H:i:s'));
            }
            
            // Create WordPress post
            $post_data = array(
                'post_title' => $title,
                'post_content' => $clean_content,
                'post_status' => 'draft', // Save as draft for review
                'post_type' => 'post',
                'post_author' => get_current_user_id(),
                'meta_input' => array(
                    'SMARK_google_docs_url' => $original_url,
                    'SMARK_google_docs_id' => $document_id,
                    'SMARK_imported_date' => current_time('mysql'),
                    'SMARK_original_html' => $html_content // Store original HTML for debugging
                )
            );
            
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create WordPress post.', 'smark')
                );
            }
            
            return array(
                'success' => true,
                'post_id' => $post_id,
                'message' => __('Document successfully converted to WordPress post!', 'smark')
            );
            
        } catch (Exception $e) {
            $error_message = wp_strip_all_tags((string) $e->getMessage());
            return array(
                'success' => false,
                /* translators: %s: error message. */
                'message' => sprintf(__('Conversion failed: %s', 'smark'), $error_message)
            );
        }
    }
    
    /**
     * Parse and clean Google Docs HTML content
     */
    private function parse_google_docs_html($html) {
        // Load HTML into DOMDocument for parsing
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        
        // Set encoding and load HTML
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Find the main content body
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return $html; // Fallback to original HTML
        }
        
        // Clean and format the content
        $clean_content = $this->clean_google_docs_content($body, $dom);
        
        // Apply additional formatting improvements
        $clean_content = $this->enhance_content_formatting($clean_content);
        
        return $clean_content;
    }
    
    /**
     * Clean Google Docs content for WordPress
     */
    private function clean_google_docs_content($body, $dom) {
        $content = '';
        
        foreach ($body->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $tag_name = $node->nodeName;
                
                // Process the node and preserve all formatting
                $processed_html = $this->process_node_with_formatting($node, $dom);
                
                // Convert Google Docs tags to WordPress-friendly tags
                switch ($tag_name) {
                    case 'h1':
                    case 'h2':
                    case 'h3':
                    case 'h4':
                    case 'h5':
                    case 'h6':
                        $content .= "<{$tag_name}>{$processed_html}</{$tag_name}>\n";
                        break;
                    case 'p':
                        // Check if paragraph has style attributes that need preservation
                        $p_attributes = $this->get_element_attributes($node);
                        if (isset($p_attributes['style'])) {
                            $styles = $this->parse_css_styles($p_attributes['style']);
                            $style_string = '';
                            
                            // Preserve important paragraph styles
                            $preserved_styles = array();
                            if (isset($styles['text-align']) && $styles['text-align'] !== 'left') {
                                $preserved_styles[] = "text-align: " . $styles['text-align'];
                            }
                            if (isset($styles['margin']) && $styles['margin'] !== '0px') {
                                $preserved_styles[] = "margin: " . $styles['margin'];
                            }
                            if (isset($styles['padding']) && $styles['padding'] !== '0px') {
                                $preserved_styles[] = "padding: " . $styles['padding'];
                            }
                            if (isset($styles['line-height']) && $styles['line-height'] !== 'normal' && $styles['line-height'] !== '1') {
                                $preserved_styles[] = "line-height: " . $styles['line-height'];
                            }
                            
                            if (!empty($preserved_styles)) {
                                $style_string = ' style="' . implode('; ', $preserved_styles) . '"';
                            }
                            
                            $content .= "<p{$style_string}>{$processed_html}</p>\n";
                        } else {
                            $content .= "<p>{$processed_html}</p>\n";
                        }
                        break;
                    case 'div':
                        // Handle divs as paragraphs if they contain text
                        if (trim(wp_strip_all_tags($processed_html))) {
                            // Check if div has style attributes that need preservation
                            $div_attributes = $this->get_element_attributes($node);
                            if (isset($div_attributes['style'])) {
                                $styles = $this->parse_css_styles($div_attributes['style']);
                                $style_string = '';
                                
                                // Preserve important div styles
                                $preserved_styles = array();
                                if (isset($styles['text-align']) && $styles['text-align'] !== 'left') {
                                    $preserved_styles[] = "text-align: " . $styles['text-align'];
                                }
                                if (isset($styles['margin']) && $styles['margin'] !== '0px') {
                                    $preserved_styles[] = "margin: " . $styles['margin'];
                                }
                                if (isset($styles['padding']) && $styles['padding'] !== '0px') {
                                    $preserved_styles[] = "padding: " . $styles['padding'];
                                }
                                
                                if (!empty($preserved_styles)) {
                                    $style_string = ' style="' . implode('; ', $preserved_styles) . '"';
                                }
                                
                                $content .= "<p{$style_string}>{$processed_html}</p>\n";
                            } else {
                                $content .= "<p>{$processed_html}</p>\n";
                            }
                        }
                        break;
                    case 'ul':
                        $content .= "<ul>{$processed_html}</ul>\n";
                        break;
                    case 'ol':
                        $content .= "<ol>{$processed_html}</ol>\n";
                        break;
                    case 'li':
                        $content .= "<li>{$processed_html}</li>";
                        break;
                    case 'blockquote':
                        $content .= "<blockquote>{$processed_html}</blockquote>\n";
                        break;
                    case 'table':
                        $content .= "<table>{$processed_html}</table>\n";
                        break;
                    case 'tr':
                        $content .= "<tr>{$processed_html}</tr>";
                        break;
                    case 'td':
                        $content .= "<td>{$processed_html}</td>";
                        break;
                    case 'th':
                        $content .= "<th>{$processed_html}</th>";
                        break;
                    case 'br':
                        $content .= "<br>";
                        break;
                    default:
                        // For other tags, preserve the content with formatting
                        if (trim(wp_strip_all_tags($processed_html))) {
                            $content .= $processed_html . "\n";
                        }
                        break;
                }
            }
        }
        
        // Clean up extra whitespace and empty paragraphs
        $content = preg_replace('/<p>\s*<\/p>/', '', $content);
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        
        return trim($content);
    }
    
    /**
     * Process node and preserve all formatting including styles
     */
    private function process_node_with_formatting($node, $dom) {
        $html = '';
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                // Handle text nodes - preserve whitespace for proper formatting
                $text = $child->textContent;
                // Only escape HTML characters, preserve whitespace
                $html .= htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                // Handle element nodes with full formatting preservation
                $tag_name = $child->nodeName;
                $attributes = $this->get_element_attributes($child);
                $inner_html = $this->process_node_with_formatting($child, $dom);
                
                // Apply formatting based on styles and attributes
                $formatted_html = $this->apply_formatting_to_element($tag_name, $attributes, $inner_html);
                $html .= $formatted_html;
            }
        }
        
        return $html;
    }
    
    /**
     * Get element attributes as associative array
     */
    private function get_element_attributes($element) {
        $attributes = array();
        
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attr) {
                $attributes[$attr->name] = $attr->value;
            }
        }
        
        return $attributes;
    }
    
    /**
     * Apply formatting to element based on styles and attributes
     */
    private function apply_formatting_to_element($tag_name, $attributes, $inner_html) {
        // Handle span elements with style attributes
        if ($tag_name === 'span' && isset($attributes['style'])) {
            return $this->apply_span_formatting($attributes['style'], $inner_html);
        }
        
        // Handle other elements with preserved attributes
        switch ($tag_name) {
            case 'strong':
            case 'b':
                return "<strong>{$inner_html}</strong>";
            case 'em':
            case 'i':
                return "<em>{$inner_html}</em>";
            case 'u':
                return "<u>{$inner_html}</u>";
            case 's':
            case 'strike':
                return "<s>{$inner_html}</s>";
            case 'a':
                $href = isset($attributes['href']) ? $attributes['href'] : '#';
                return "<a href=\"{$href}\">{$inner_html}</a>";
            case 'sup':
                return "<sup>{$inner_html}</sup>";
            case 'sub':
                return "<sub>{$inner_html}</sub>";
            case 'code':
                return "<code>{$inner_html}</code>";
            case 'pre':
                return "<pre>{$inner_html}</pre>";
            default:
                // Check if element has style attribute that might contain bold formatting
                if (isset($attributes['style'])) {
                    $styles = $this->parse_css_styles($attributes['style']);
                    if ($this->is_bold_text($styles)) {
                        $inner_html = "<strong>{$inner_html}</strong>";
                    }
                }
                
                // For other elements, preserve the tag with attributes
                $attr_string = '';
                foreach ($attributes as $name => $value) {
                    $attr_string .= " {$name}=\"" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\"";
                }
                return "<{$tag_name}{$attr_string}>{$inner_html}</{$tag_name}>";
        }
    }
    
    /**
     * Apply formatting based on span style attributes
     */
    private function apply_span_formatting($style, $inner_html) {
        $formatted_html = $inner_html;
        
        // Parse CSS styles
        $styles = $this->parse_css_styles($style);
        
        // Collect all style properties that need to be preserved
        $preserved_styles = array();
        
        // Apply bold formatting - improved detection
        if ($this->is_bold_text($styles)) {
            $formatted_html = "<strong>{$formatted_html}</strong>";
        }
        
        // Apply italic formatting
        if (isset($styles['font-style']) && strtolower($styles['font-style']) === 'italic') {
            $formatted_html = "<em>{$formatted_html}</em>";
        }
        
        // Apply underline formatting
        if (isset($styles['text-decoration']) && strpos($styles['text-decoration'], 'underline') !== false) {
            $formatted_html = "<u>{$formatted_html}</u>";
        }
        
        // Apply strikethrough formatting
        if (isset($styles['text-decoration']) && strpos($styles['text-decoration'], 'line-through') !== false) {
            $formatted_html = "<s>{$formatted_html}</s>";
        }
        
        // Apply text alignment
        if (isset($styles['text-align']) && $styles['text-align'] !== 'left') {
            $preserved_styles[] = "text-align: " . $styles['text-align'];
        }
        
        // Apply line height
        if (isset($styles['line-height']) && $styles['line-height'] !== 'normal' && $styles['line-height'] !== '1') {
            $preserved_styles[] = "line-height: " . $styles['line-height'];
        }
        
        // Apply letter spacing
        if (isset($styles['letter-spacing']) && $styles['letter-spacing'] !== 'normal' && $styles['letter-spacing'] !== '0px') {
            $preserved_styles[] = "letter-spacing: " . $styles['letter-spacing'];
        }
        
        // Apply word spacing
        if (isset($styles['word-spacing']) && $styles['word-spacing'] !== 'normal' && $styles['word-spacing'] !== '0px') {
            $preserved_styles[] = "word-spacing: " . $styles['word-spacing'];
        }
        
        // Apply text transform
        if (isset($styles['text-transform']) && $styles['text-transform'] !== 'none') {
            $preserved_styles[] = "text-transform: " . $styles['text-transform'];
        }
        
        // Apply text shadow
        if (isset($styles['text-shadow']) && $styles['text-shadow'] !== 'none') {
            $preserved_styles[] = "text-shadow: " . $styles['text-shadow'];
        }
        
        // Collect styles that need to be preserved in a span
        if (isset($styles['background-color']) && 
            $styles['background-color'] !== 'transparent' && 
            $styles['background-color'] !== 'rgb(255, 255, 255)' &&
            $styles['background-color'] !== '#ffffff' &&
            $styles['background-color'] !== '#fff') {
            $preserved_styles[] = "background-color: " . $styles['background-color'];
        }
        
        if (isset($styles['color']) && 
            $styles['color'] !== 'inherit' && 
            $styles['color'] !== 'rgb(0, 0, 0)' &&
            $styles['color'] !== '#000000' &&
            $styles['color'] !== '#000') {
            $preserved_styles[] = "color: " . $styles['color'];
        }
        
        if (isset($styles['font-size']) && 
            $styles['font-size'] !== 'inherit' && 
            $styles['font-size'] !== '12pt' &&
            $styles['font-size'] !== '16px' &&
            $styles['font-size'] !== '1em') {
            $preserved_styles[] = "font-size: " . $styles['font-size'];
        }
        
        if (isset($styles['font-family']) && 
            $styles['font-family'] !== 'inherit' &&
            $styles['font-family'] !== 'Arial' &&
            $styles['font-family'] !== 'sans-serif') {
            $preserved_styles[] = "font-family: " . $styles['font-family'];
        }
        
        // Apply margin and padding
        if (isset($styles['margin']) && $styles['margin'] !== '0px') {
            $preserved_styles[] = "margin: " . $styles['margin'];
        }
        
        if (isset($styles['padding']) && $styles['padding'] !== '0px') {
            $preserved_styles[] = "padding: " . $styles['padding'];
        }
        
        if (isset($styles['vertical-align']) && $styles['vertical-align'] !== 'baseline') {
            if ($styles['vertical-align'] === 'super') {
                $formatted_html = "<sup>{$formatted_html}</sup>";
            } elseif ($styles['vertical-align'] === 'sub') {
                $formatted_html = "<sub>{$formatted_html}</sub>";
            } else {
                $preserved_styles[] = "vertical-align: " . $styles['vertical-align'];
            }
        }
        
        // Apply preserved styles if any
        if (!empty($preserved_styles)) {
            $style_string = implode('; ', $preserved_styles);
            $formatted_html = "<span style=\"{$style_string}\">{$formatted_html}</span>";
        }
        
        return $formatted_html;
    }
    
    /**
     * Check if text should be bold based on CSS styles
     */
    private function is_bold_text($styles) {
        // Check font-weight property
        if (isset($styles['font-weight'])) {
            $font_weight = strtolower(trim($styles['font-weight']));
            
            // Direct bold keywords
            if (in_array($font_weight, ['bold', 'bolder'])) {
                return true;
            }
            
            // Numeric values
            if (is_numeric($font_weight)) {
                $weight = intval($font_weight);
                if ($weight >= 700) {
                    return true;
                }
            }
            
            // String numeric values
            if (in_array($font_weight, ['700', '800', '900'])) {
                return true;
            }
        }
        
        // Check for Google Docs specific bold indicators
        if (isset($styles['font-variant']) && strtolower($styles['font-variant']) === 'small-caps') {
            // Sometimes Google Docs uses small-caps for emphasis
            return false; // Don't treat as bold
        }
        
        // Check for text-shadow that might indicate bold (Google Docs sometimes uses this)
        if (isset($styles['text-shadow']) && $styles['text-shadow'] !== 'none') {
            // If there's a text shadow, it might be for bold effect
            // But we'll be conservative and not assume it's bold
            return false;
        }
        
        return false;
    }
    
    /**
     * Parse CSS style string into associative array
     */
    private function parse_css_styles($style_string) {
        $styles = array();
        
        // Remove extra spaces and split by semicolon
        $style_string = trim($style_string);
        $style_declarations = explode(';', $style_string);
        
        foreach ($style_declarations as $declaration) {
            $declaration = trim($declaration);
            if (empty($declaration)) continue;
            
            $parts = explode(':', $declaration, 2);
            if (count($parts) === 2) {
                $property = trim($parts[0]);
                $value = trim($parts[1]);
                
                // Normalize property names (remove vendor prefixes and convert to lowercase)
                $property = strtolower($property);
                $property = preg_replace('/^-webkit-|-moz-|-ms-|-o-/', '', $property);
                
                // Clean up values
                $value = trim($value, ' "\'');
                
                $styles[$property] = $value;
            }
        }
        
        return $styles;
    }
    
    /**
     * Enhance content formatting for better WordPress compatibility
     */
    private function enhance_content_formatting($content) {
        // Fix list numbering and formatting
        $content = $this->fix_list_formatting($content);
        
        // Process and preserve span tags with styles instead of removing them
        $content = $this->process_remaining_spans($content);
        
        // Preserve line breaks within paragraphs
        $content = preg_replace('/<br\s*\/?>/i', '<br>', $content);
        
        // Clean up multiple consecutive line breaks
        $content = preg_replace('/<br>\s*<br>/i', '<br><br>', $content);
        
        // Ensure proper spacing between elements
        $content = preg_replace('/<\/h[1-6]>\s*<h[1-6]>/i', '</h1>\n\n<h2>', $content);
        $content = preg_replace('/<\/p>\s*<p>/i', '</p>\n\n<p>', $content);
        
        // Clean up extra whitespace
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        
        return $content;
    }
    
    /**
     * Process remaining span tags and preserve their styles
     */
    private function process_remaining_spans($content) {
        // Find all span tags that weren't processed in the main parsing
        $content = preg_replace_callback('/<span([^>]*)>(.*?)<\/span>/is', function($matches) {
            $attributes = $matches[1];
            $inner_content = $matches[2];
            
            // Extract style attribute
            if (preg_match('/style\s*=\s*["\']([^"\']*)["\']/', $attributes, $style_matches)) {
                $style = $style_matches[1];
                return $this->apply_span_formatting($style, $inner_content);
            }
            
            // If no style attribute, just return the inner content
            return $inner_content;
        }, $content);
        
        return $content;
    }
    
    /**
     * Fix list formatting to preserve numbering and bullets
     */
    private function fix_list_formatting($content) {
        // Fix ordered lists - ensure proper numbering
        $content = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function($matches) {
            $list_content = $matches[1];
            // Remove any existing numbering from list items
            $list_content = preg_replace('/<li[^>]*>\s*\d+\.\s*/', '<li>', $list_content);
            return '<ol>' . $list_content . '</ol>';
        }, $content);
        
        // Fix unordered lists - ensure proper bullets
        $content = preg_replace_callback('/<ul[^>]*>(.*?)<\/ul>/is', function($matches) {
            $list_content = $matches[1];
            // Remove any existing bullets from list items
            $list_content = preg_replace('/<li[^>]*>\s*[•·‣⁃]\s*/', '<li>', $list_content);
            return '<ul>' . $list_content . '</ul>';
        }, $content);
        
        // Ensure list items have proper structure
        $content = preg_replace('/<li[^>]*>\s*(.*?)\s*<\/li>/is', '<li>$1</li>', $content);
        
        return $content;
    }
    
    /**
     * Extract title from content
     */
    private function extract_title_from_content($content) {
        // Look for the first heading
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches)) {
            return trim(wp_strip_all_tags($matches[1]));
        }
        
        if (preg_match('/<h2[^>]*>(.*?)<\/h2>/i', $content, $matches)) {
            return trim(wp_strip_all_tags($matches[1]));
        }
        
        // If no heading found, extract from first paragraph
        if (preg_match('/<p[^>]*>(.*?)<\/p>/i', $content, $matches)) {
            $title = trim(wp_strip_all_tags($matches[1]));
            // Limit title length
            if (strlen($title) > 100) {
                $title = substr($title, 0, 97) . '...';
            }
            return $title;
        }
        
        return '';
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'admin_page_smark-google-docs-converter') {
            return;
        }
        
        wp_enqueue_style('smark-converter', SMARK_PLUGIN_URL . 'features/google-docs-converter/assets/converter.css', array(), SMARK_VERSION);
        wp_enqueue_script('smark-converter', SMARK_PLUGIN_URL . 'features/google-docs-converter/assets/converter.js', array('jquery'), SMARK_VERSION, true);
    }
    
    /**
     * Render the converter page
     */
    public function render_page() {
        ?>
        <div class="wrap smark-converter-page">
            <div class="smark-page-header">
                <h1><?php echo esc_html__('Google Docs to Blog Post Converter', 'smark'); ?></h1>
                <p class="description"><?php echo esc_html__('Convert your Google Docs documents into WordPress blog posts easily.', 'smark'); ?></p>
            </div>
            
            <div class="smark-breadcrumb">
                <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html__('SMark Dashboard', 'smark'); ?></a> 
                <span class="separator">›</span> 
                <span class="current"><?php echo esc_html__('Google Docs Converter', 'smark'); ?></span>
            </div>
            
            <div class="smark-converter-form-section">
                <div class="converter-form-header">
                    <h2><?php echo esc_html__('Enter Google Docs Link', 'smark'); ?></h2>
                    <p><?php echo esc_html__('Paste your Google Docs document link below to convert it to a WordPress blog post', 'smark'); ?></p>
                </div>
                
                <div class="converter-form-card">
                    <div class="card">
                        <form class="smark-converter-form" method="post" action="">
                            <?php wp_nonce_field('SMARK_convert_docs', 'SMARK_nonce'); ?>
                            <div class="form-group">
                                <label for="google_docs_url"><?php echo esc_html__('Google Docs URL', 'smark'); ?></label>
                                <input type="url" 
                                       id="google_docs_url" 
                                       name="google_docs_url" 
                                       class="form-control" 
                                       placeholder="https://docs.google.com/document/d/your-document-id/edit"
                                       value="<?php echo esc_attr(get_option('SMARK_last_docs_url', '')); ?>"
                                       required>
                                <small class="form-help"><?php echo esc_html__('Copy and paste the full Google Docs URL here', 'smark'); ?></small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="convert_docs" class="btn btn-primary">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php echo esc_html__('Convert to Blog Post', 'smark'); ?>
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="location.href='<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>'">
                                    <span class="dashicons dashicons-arrow-left-alt"></span>
                                    <?php echo esc_html__('Back to Dashboard', 'smark'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <div class="conversion-info">
                            <div class="info-box">
                                <h4><?php echo esc_html__('How to get your Google Docs URL:', 'smark'); ?></h4>
                                <ol>
                                    <li><?php echo esc_html__('Open your Google Docs document', 'smark'); ?></li>
                                    <li><?php echo esc_html__('Click on "Share" button in the top-right corner', 'smark'); ?></li>
                                    <li><?php echo esc_html__('Make sure the document is accessible (public or anyone with link)', 'smark'); ?></li>
                                    <li><?php echo esc_html__('Copy the URL from your browser address bar', 'smark'); ?></li>
                                    <li><?php echo esc_html__('Paste it in the field above', 'smark'); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the converter
// new SaeedGoogleDocsConverter();

<?php
/**
 * Plugin Name: WP Error Doctor
 * Description: An SEO-ready WordPress security, speed, and website health checker with lead capture.
 * Version: 2.5.1
 * Author: Jawad Ilyas
 * Author URI: https://jawadjd.dev
 * Text Domain: wp-error-doctor
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class WPD_Lead_Widget {
    const VERSION = '2.5.1';
    const OPTION = 'wpd_widget_settings';

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'wpd_leads';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, email varchar(190) NOT NULL, website_url text NOT NULL, scan_id varchar(32) DEFAULT '', score smallint DEFAULT NULL, status varchar(30) DEFAULT 'pending', report_key varchar(32) DEFAULT '', consent tinyint(1) NOT NULL DEFAULT 0, consent_at datetime DEFAULT NULL, source_url text, created_at datetime NOT NULL, last_contacted_at datetime DEFAULT NULL, PRIMARY KEY (id), KEY email (email), KEY created_at (created_at), KEY status (status)) {$charset};");
        $existing = get_page_by_path('website-diagnostic-report');
        $id = $existing ? $existing->ID : wp_insert_post(['post_title'=>'Website Diagnostic Report','post_name'=>'website-diagnostic-report','post_status'=>'publish','post_type'=>'page','post_content'=>'[wp_error_doctor_report]']);
        if ($id && !is_wp_error($id)) update_option('wpd_report_page_id', (int)$id);
        $scanner = get_page_by_path('wordpress-website-health-check');
        $scanner_id = $scanner ? $scanner->ID : wp_insert_post(['post_title'=>'Free WordPress Website Security & Speed Check','post_name'=>'wordpress-website-health-check','post_status'=>'publish','post_type'=>'page','post_content'=>'[wp_error_doctor_scanner]']);
        if ($scanner_id && !is_wp_error($scanner_id)) update_option('wpd_scanner_page_id', (int)$scanner_id);
        $settings = get_option(self::OPTION, []); $settings['enabled'] = '0'; update_option(self::OPTION, $settings);
        update_option('wpd_db_version', self::VERSION);
    }

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('wp_footer', [$this, 'render']);
        add_action('wp_footer', [$this, 'render_chatbot']);
        add_action('rest_api_init', [$this, 'routes']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'ensure_report_page']);
        add_action('admin_post_wpd_follow_up', [$this, 'send_follow_up']);
        add_shortcode('wp_error_doctor_report', [$this, 'report_page']);
        add_shortcode('wp_error_doctor_scanner', [$this, 'scanner_page']);
        add_filter('document_title_parts', [$this, 'seo_title']);
        add_action('wp_head', [$this, 'seo_head']);
        add_filter('template_include', [$this, 'scanner_template']);
        add_filter('wp_nav_menu_items', [$this, 'tools_menu_link'], 20, 2);
    }

    public function settings() {
        return wp_parse_args(get_option(self::OPTION, []), [
            'enabled' => '0', 'email' => 'jawad.productions@gmail.com',
            'accent' => '#22d3ee', 'position' => 'right',
            'button_text' => 'Diagnose My Website',
            'headline' => 'Is your WordPress site having problems?',
            'chat_enabled' => '1', 'openai_key' => '', 'openai_model' => 'gpt-5-mini',
        ]);
    }

    public function ensure_report_page() { if (!get_option('wpd_report_page_id') || get_option('wpd_db_version') !== self::VERSION) self::activate(); }

    public function assets() {
        $s = $this->settings();
        if (!is_admin()) wp_enqueue_style('wpd-menu', plugin_dir_url(__FILE__) . 'assets/menu.css', [], self::VERSION);
        $scanner_page = (int)get_option('wpd_scanner_page_id');
        $report_page = (int)get_option('wpd_report_page_id');
        if (($s['enabled'] !== '1' && $s['chat_enabled'] !== '1' && !is_page($scanner_page) && !is_page($report_page)) || is_admin()) return;
        wp_enqueue_style('wpd-widget', plugin_dir_url(__FILE__) . 'assets/widget.css', [], self::VERSION);
        wp_enqueue_style('wpd-widget-fun', plugin_dir_url(__FILE__) . 'assets/widget-fun.css', ['wpd-widget'], self::VERSION);
        wp_enqueue_style('wpd-report', plugin_dir_url(__FILE__) . 'assets/report.css', ['wpd-widget'], self::VERSION);
        wp_enqueue_style('wpd-marketing', plugin_dir_url(__FILE__) . 'assets/marketing.css', ['wpd-widget'], self::VERSION);
        wp_enqueue_style('wpd-form-v2', plugin_dir_url(__FILE__) . 'assets/form-v2.css', ['wpd-marketing'], self::VERSION);
        wp_enqueue_style('wpd-page', plugin_dir_url(__FILE__) . 'assets/page.css', ['wpd-form-v2'], self::VERSION);
        wp_enqueue_style('wpd-page-premium', plugin_dir_url(__FILE__) . 'assets/page-premium.css', ['wpd-page'], self::VERSION);
        wp_enqueue_style('wpd-page-v3', plugin_dir_url(__FILE__) . 'assets/page-v3.css', ['wpd-page-premium'], self::VERSION);
        wp_enqueue_style('wpd-page-v4', plugin_dir_url(__FILE__) . 'assets/page-v4.css', ['wpd-page-v3'], self::VERSION);
        wp_enqueue_style('wpd-page-readable', plugin_dir_url(__FILE__) . 'assets/page-readable.css', ['wpd-page-v4'], self::VERSION);
        wp_enqueue_style('wpd-multistep', plugin_dir_url(__FILE__) . 'assets/multistep.css', ['wpd-page-readable'], self::VERSION);
        wp_enqueue_style('wpd-form-clinical', plugin_dir_url(__FILE__) . 'assets/form-clinical.css', ['wpd-multistep'], self::VERSION);
        wp_enqueue_style('wpd-form-clinical-results', plugin_dir_url(__FILE__) . 'assets/form-clinical-results.css', ['wpd-form-clinical'], self::VERSION);
        wp_enqueue_script('wpd-widget', plugin_dir_url(__FILE__) . 'assets/widget.js', [], self::VERSION, true);
        if ($s['chat_enabled'] === '1') { wp_enqueue_style('wpd-chat', plugin_dir_url(__FILE__) . 'assets/chat.css', [], self::VERSION); wp_enqueue_style('wpd-chat-enhance', plugin_dir_url(__FILE__) . 'assets/chat-enhance.css', ['wpd-chat'], self::VERSION); wp_enqueue_style('wpd-chat-readable', plugin_dir_url(__FILE__) . 'assets/chat-readable.css', ['wpd-chat-enhance'], self::VERSION); wp_enqueue_style('wpd-chat-v2', plugin_dir_url(__FILE__) . 'assets/chat-v2.css', ['wpd-chat-readable'], self::VERSION); wp_enqueue_script('wpd-chat', plugin_dir_url(__FILE__) . 'assets/chat.js', [], self::VERSION, true); }
        wp_localize_script('wpd-widget', 'WPDWidget', [
            'root' => esc_url_raw(rest_url('wp-error-doctor/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'accent' => sanitize_hex_color($s['accent']) ?: '#22d3ee',
            'position' => $s['position'] === 'left' ? 'left' : 'right',
        ]);
        wp_localize_script('wpd-chat', 'WPDChat', ['endpoint'=>esc_url_raw(rest_url('wp-error-doctor/v1/chat')),'nonce'=>wp_create_nonce('wp_rest'),'whatsapp'=>'https://wa.me/923316388373?text='.rawurlencode('Hi Jawad, I visited your website and would like to discuss my project.'),'email'=>'mailto:jawad.productions@gmail.com?subject='.rawurlencode('Website project enquiry')]);
    }

    public function render($inline = false) {
        $s = $this->settings();
        if ((!$inline && $s['enabled'] !== '1') || is_admin()) return;
        if ($inline) ob_start();
        ?>
        <div id="wpd-widget" class="wpd-widget <?php echo $inline?'wpd-inline':'wpd-'.esc_attr($s['position']); ?>" style="--wpd-accent:<?php echo esc_attr(sanitize_hex_color($s['accent'])); ?>">
            <?php if ( ! $inline ) : ?><button class="wpd-launch" type="button" aria-haspopup="dialog" aria-controls="wpd-dialog"><span class="wpd-pulse"></span><span class="wpd-launch-icon">+</span><b><?php echo esc_html($s['button_text']); ?></b></button><?php endif; ?>
            <section id="wpd-dialog" class="wpd-dialog" role="dialog" aria-modal="true" aria-labelledby="wpd-title" <?php echo $inline?'':'hidden'; ?>>
                <header><div class="wpd-brand"><span>W</span><div><b>WP Error Doctor</b><small>by Jawad Ilyas</small></div></div><button class="wpd-close" type="button" aria-label="Close">×</button></header>
                <div class="wpd-view wpd-start">
                    <span class="wpd-kicker">FREE WEBSITE HEALTH CHECK</span>
                    <h2 id="wpd-title"><?php echo esc_html($s['headline']); ?></h2>
                    <p>Run a safe public scan for server errors and common WordPress failures. No login required.</p>
                    <input id="wpd-marketing" type="checkbox" checked hidden>
                    <form class="wpd-scan-form"><div class="wpd-step-head"><span class="is-active">1</span><i></i><span>2</span><b>Website</b><b>Email</b></div><div class="wpd-form-step wpd-step-url"><div class="wpd-primary-field"><label for="wpd-url">Enter your WordPress website</label><div class="wpd-input wpd-url-input"><span>◎</span><input id="wpd-url" type="text" inputmode="url" placeholder="yourwebsite.com" required></div><small>We’ll check the public website for errors and common issues.</small></div><button class="wpd-next-step" type="button">Continue <span>→</span></button></div><div class="wpd-form-step wpd-step-email" hidden><button class="wpd-step-back" type="button">← Change website</button><div class="wpd-selected-site"><small>WEBSITE TO DIAGNOSE</small><b></b></div><div class="wpd-secondary-field"><label for="wpd-email">Your email <em>Required to start diagnosis</em></label><div class="wpd-input wpd-email-input"><span>@</span><input id="wpd-email" type="email" autocomplete="email" placeholder="you@company.com" required></div><small>Used to save your scan and contact you about relevant website help. You can opt out anytime.</small></div><button type="submit">Start Free Diagnosis <span>→</span></button></div><small>◆ Secure public scan. No passwords or private data requested.</small></form>
                </div>
                <div class="wpd-view wpd-progress" hidden><div class="wpd-radar"><i></i><b>⌁</b></div><span class="wpd-kicker">DIAGNOSTIC SCAN</span><h2>Checking your website…</h2><p class="wpd-stage">Validating website URL</p><div class="wpd-progress-line"><i></i></div></div>
                <div class="wpd-view wpd-result" hidden></div>
                <div class="wpd-view wpd-lead" hidden>
                    <button class="wpd-back" type="button">← Back to report</button><span class="wpd-kicker">SEND REPORT TO JAWAD</span><h2>Get professional help</h2><p>Your public diagnostic findings will be included automatically.</p>
                    <form class="wpd-lead-form"><label>Name<input name="name" required autocomplete="name"></label><label>Email<input name="email" type="email" required autocomplete="email"></label><label>What happened?<textarea name="message" placeholder="When did the issue begin?"></textarea></label><label class="wpd-consent"><input name="consent" type="checkbox" required> I give Jawad permission to contact me.</label><button type="submit">Send Report & Request Help →</button><small>No passwords or private logs are shared.</small></form>
                </div>
            </section>
        </div>
        <?php
        if ($inline) return ob_get_clean();
    }

    public function routes() {
        register_rest_route('wp-error-doctor/v1', '/scan', ['methods' => 'POST', 'callback' => [$this, 'scan'], 'permission_callback' => '__return_true']);
        register_rest_route('wp-error-doctor/v1', '/capture', ['methods' => 'POST', 'callback' => [$this, 'capture'], 'permission_callback' => '__return_true']);
        register_rest_route('wp-error-doctor/v1', '/lead', ['methods' => 'POST', 'callback' => [$this, 'lead'], 'permission_callback' => '__return_true']);
        register_rest_route('wp-error-doctor/v1', '/chat', ['methods' => 'POST', 'callback' => [$this, 'chat'], 'permission_callback' => '__return_true']);
    }

    public function render_chatbot() { $s=$this->settings(); if($s['chat_enabled']!=='1' || is_admin()) return; ?>
      <div id="wpd-ai-chat" class="wpd-ai-chat"><button class="wpd-chat-launch" type="button" aria-controls="wpd-chat-panel"><span class="wpd-chat-orb">✦</span><b>Ask Jawad’s AI</b><i></i></button><section id="wpd-chat-panel" class="wpd-chat-panel" aria-label="Jawad's AI website assistant" hidden><header><div><span>✦</span><p><b>Jawad’s AI Assistant</b><small>Website & WordPress guidance</small></p></div><button type="button" aria-label="Close chat">×</button></header><div class="wpd-chat-messages" aria-live="polite"></div><div class="wpd-chat-quick"><button type="button">I need a website</button><button type="button">Fix my WordPress</button><button type="button">Improve site speed</button></div><form><textarea rows="1" maxlength="1000" placeholder="Tell me about your website…" required></textarea><button type="submit" aria-label="Send message">↑</button></form><footer><span>AI assistant · Replies may be imperfect</span></footer></section></div>
    <?php }

    public function chat(WP_REST_Request $request) {
        if($this->rate_limited('chat',20)) return new WP_Error('rate_limit','You’ve reached the chat limit. Please contact Jawad directly.',['status'=>429]);
        $messages=$request->get_param('messages'); if(!is_array($messages)) return new WP_Error('invalid_chat','Invalid conversation.',['status'=>400]); $messages=array_slice($messages,-10); $input=[];
        foreach($messages as $m){ $role=($m['role']??'')==='assistant'?'assistant':'user'; $text=mb_substr(sanitize_textarea_field($m['content']??''),0,1200); if($text) $input[]=['role'=>$role,'content'=>$text]; }
        $s=$this->settings(); $key=defined('OPENAI_API_KEY')?OPENAI_API_KEY:trim($s['openai_key']); $latest=strtolower(end($input)['content']??''); $handoff=(bool)preg_match('/contact|whatsapp|email|hire|quote|urgent|call|talk to jawad|speak to jawad/',$latest);
        if(!$key) return rest_ensure_response(['reply'=>$this->guided_reply($latest),'handoff'=>$handoff]);
        $instructions="You are the helpful website assistant for Jawad Ilyas, an experienced WordPress and full-stack developer. Your goal is to understand the visitor's actual needs, give concise useful guidance, qualify project type, website URL, problem, urgency, and approximate scope, and help them decide whether Jawad is a good fit. Never pretend to be Jawad. Never claim you inspected a site unless scan data is provided. Do not pressure, manipulate, or fabricate scarcity. Keep replies under 90 words, warm and professional. Ask only one useful question at a time. After understanding the need, naturally offer: WhatsApp Jawad at https://wa.me/923316388373 or email jawad.productions@gmail.com. For urgent broken WordPress sites, offer the handoff sooner. Do not request passwords, API keys, payment data, or sensitive access.";
        $model=sanitize_text_field($s['openai_model'])?:'gpt-5-mini'; $response=$this->openai_response($key,$model,$instructions,$input);
        if(is_wp_error($response)) return new WP_Error('ai_unavailable','The AI service could not be reached. Please try again shortly.',['status'=>503]); $data=json_decode(wp_remote_retrieve_body($response),true); $code=wp_remote_retrieve_response_code($response);
        if($code>=400 && $model!=='gpt-5-mini'){ $response=$this->openai_response($key,'gpt-5-mini',$instructions,$input); $data=json_decode(wp_remote_retrieve_body($response),true); $code=wp_remote_retrieve_response_code($response); }
        if($code>=400){ $type=$data['error']['type']??''; $message=$data['error']['message']??''; error_log('WP Error Doctor OpenAI: '.$type.' - '.$message); if($code===401) $public='The OpenAI API key is invalid or inactive.'; elseif($code===429) $public='The OpenAI account has no available quota. Check billing and usage limits.'; elseif(stripos($message,'model')!==false) $public='The selected AI model is unavailable to this API project.'; else $public='The AI connection needs attention. Check the plugin settings and OpenAI billing.'; return new WP_Error('ai_error',$public,['status'=>503]); } $reply=''; foreach(($data['output']??[]) as $out) foreach(($out['content']??[]) as $part) if(($part['type']??'')==='output_text') $reply.=$part['text']??'';
        return rest_ensure_response(['reply'=>$reply?:$this->guided_reply($latest),'handoff'=>$handoff]);
    }

    private function openai_response($key,$model,$instructions,$input){ return wp_remote_post('https://api.openai.com/v1/responses',['timeout'=>30,'headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'],'body'=>wp_json_encode(['model'=>$model,'instructions'=>$instructions,'input'=>$input,'max_output_tokens'=>260])]); }

    private function guided_reply($text){ $t=strtolower($text); if(strpos($t,'speed')!==false) return 'Slow WordPress sites are often affected by hosting response time, heavy plugins, images, or page-builder assets. What website would you like Jawad to review?'; if(strpos($t,'error')!==false||strpos($t,'broken')!==false) return 'I’m sorry your site is having trouble. What error do you see, and when did it begin? Please don’t share passwords here. For urgent help, you can WhatsApp Jawad directly.'; if(strpos($t,'website')!==false||strpos($t,'build')!==false) return 'Great—Jawad builds WordPress, WooCommerce, landing pages, and custom websites. What kind of business is it for, and what should the website help visitors do?'; return 'I can help you plan a website, troubleshoot WordPress, improve speed, or understand the best next step. What would you like to build or fix?'; }

    public function scanner_page() {
        ob_start(); ?>
        <main class="wpd-seo-page wpd-v3-page">
          <section class="wpd-v3-hero">
            <div class="wpd-v3-copy"><p class="wpd-seo-kicker"><span></span> FREE WORDPRESS HEALTH CHECK</p><h1>Is your website<br><em>quietly losing</em><br>customers?</h1><p>Paste your website URL to uncover public security risks, speed problems, WordPress errors, mobile issues, and technical SEO gaps.</p><div class="wpd-v3-proof"><div><strong>20+</strong><span>public checks</span></div><div><strong>~60s</strong><span>to diagnose</span></div><div><strong>0</strong><span>passwords needed</span></div></div>
            </div>
            <div class="wpd-v3-tool"><div class="wpd-v3-tool-label"><span>●</span> LIVE WEBSITE DIAGNOSTIC</div><?php echo $this->render(true); ?><div class="wpd-v3-safe"><span>◆</span><p><b>Safe public scan</b><small>We never log in, change files, or request passwords.</small></p></div></div>
          </section>
          <section id="checks" class="wpd-v3-checks"><div><p class="wpd-seo-kicker">WHAT YOU’LL DISCOVER</p><h2>One URL. A clearer picture of your website.</h2><p>The scan turns public technical signals into plain-language findings and practical next steps.</p></div><div class="wpd-v3-list"><article><span>01</span><div><h3>Security signals</h3><p>HTTPS, mixed content, exposed errors, and browser security headers.</p></div></article><article><span>02</span><div><h3>Speed & performance</h3><p>Response time, page weight, scripts, styles, and performance warning signs.</p></div></article><article><span>03</span><div><h3>WordPress health</h3><p>REST availability, server errors, maintenance mode, and fatal-error signatures.</p></div></article><article><span>04</span><div><h3>Mobile & SEO readiness</h3><p>Responsive configuration, metadata, canonical URL, sitemap, and headings.</p></div></article></div></section>
          <section id="faq" class="wpd-seo-faq"><p class="wpd-seo-kicker">BEFORE YOU SCAN</p><h2>What website owners ask</h2><details><summary>Is this website scan safe?</summary><p>Yes. It only requests public pages available to normal visitors. It never logs in, exploits endpoints, or changes website files.</p></details><details><summary>Will this find the exact plugin causing an error?</summary><p>Public signals can narrow down the failure type. Private PHP and WordPress logs are usually required to confirm an exact plugin, theme, file, or line number.</p></details><details><summary>Can I check a non-WordPress website?</summary><p>Yes. Security, speed, availability, mobile, and SEO checks still work, while WordPress-specific diagnosis will be limited.</p></details></section>
          <section class="wpd-seo-cta"><div><p class="wpd-seo-kicker">NEED A HUMAN EXPERT?</p><h2>Turn the findings into a fix.</h2><p>Send your report to Jawad for a careful review and a practical next step.</p></div><a href="<?php echo esc_url(home_url('/#contact')); ?>">Hire Jawad to Fix It →</a></section>
        </main><?php return ob_get_clean();
    }

    private function is_scanner_page() { return is_page((int)get_option('wpd_scanner_page_id')); }
    public function tools_menu_link($items,$args) { $location=$args->theme_location??''; if($location && !preg_match('/primary|header|main|menu-1/i',$location)) return $items; $url=get_permalink((int)get_option('wpd_scanner_page_id')); if(!$url) return $items; return $items.'<li class="menu-item wpd-tools-menu"><a href="'.esc_url($url).'"><span>Free Web Tools</span><i>NEW</i></a></li>'; }
    public function scanner_template($template) { if($this->is_scanner_page()) { $custom=plugin_dir_path(__FILE__).'templates/scanner-page.php'; if(file_exists($custom)) return $custom; } return $template; }
    public function seo_title($parts) { if($this->is_scanner_page()) $parts['title']='Free WordPress Security, Speed & Website Health Check'; return $parts; }
    public function seo_head() { if(!$this->is_scanner_page()) return; $url=get_permalink((int)get_option('wpd_scanner_page_id')); $description='Check your WordPress website for security signals, speed problems, HTTP errors, mobile readiness, and technical SEO issues with a free public scan.'; ?>
      <meta name="description" content="<?php echo esc_attr($description); ?>">
      <meta name="robots" content="index,follow,max-image-preview:large">
      <link rel="canonical" href="<?php echo esc_url($url); ?>">
      <meta property="og:title" content="Free WordPress Security, Speed & Website Health Check">
      <meta property="og:description" content="<?php echo esc_attr($description); ?>">
      <meta property="og:type" content="website"><meta property="og:url" content="<?php echo esc_url($url); ?>">
      <script type="application/ld+json"><?php echo wp_json_encode(['@context'=>'https://schema.org','@type'=>'WebApplication','name'=>'WP Error Doctor','url'=>$url,'applicationCategory'=>'SecurityApplication','operatingSystem'=>'Web','description'=>$description,'offers'=>['@type'=>'Offer','price'=>'0','priceCurrency'=>'USD'],'provider'=>['@type'=>'Person','name'=>'Jawad Ilyas','url'=>'https://jawadjd.dev']]); ?></script>
      <script type="application/ld+json"><?php echo wp_json_encode(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>[['@type'=>'Question','name'=>'Is this WordPress security scanner safe?','acceptedAnswer'=>['@type'=>'Answer','text'=>'Yes. It only checks public pages and never attempts to log in or change website files.']],['@type'=>'Question','name'=>'Can it find the exact plugin causing an error?','acceptedAnswer'=>['@type'=>'Answer','text'=>'A public scan can identify failure patterns, but backend logs are required to confirm an exact plugin, theme, file, or line number.']],['@type'=>'Question','name'=>'Can I check a non-WordPress website?','acceptedAnswer'=>['@type'=>'Answer','text'=>'Basic security, speed, availability, responsive, and SEO checks can run, but WordPress-specific diagnosis will be limited.']]]]); ?></script>
    <?php }

    public function capture(WP_REST_Request $request) {
        global $wpdb;
        if ($this->rate_limited('capture', 6)) return new WP_Error('rate_limit', 'Too many requests. Please try again shortly.', ['status'=>429]);
        $email = sanitize_email((string)$request->get_param('email')); $raw=trim((string)$request->get_param('url')); $consent=(bool)$request->get_param('consent');
        if (!preg_match('#^https?://#i',$raw)) $raw='https://'.$raw;
        $url=wp_http_validate_url($raw);
        if (!is_email($email) || !$url || !$consent) return new WP_Error('invalid_capture','Enter a valid email and public website, then confirm permission.',['status'=>400]);
        $wpdb->insert($wpdb->prefix.'wpd_leads',['email'=>$email,'website_url'=>esc_url_raw($url),'consent'=>1,'consent_at'=>current_time('mysql'),'source_url'=>esc_url_raw(wp_get_referer()?:home_url('/')),'created_at'=>current_time('mysql')],['%s','%s','%d','%s','%s','%s']);
        return rest_ensure_response(['lead_id'=>(int)$wpdb->insert_id]);
    }

    private function rate_limited($action, $limit = 8) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $key = 'wpd_' . $action . '_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= $limit) return true;
        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return false;
    }

    public function scan(WP_REST_Request $request) {
        if ($this->rate_limited('scan')) return new WP_Error('rate_limit', 'Too many scans. Please try again shortly.', ['status' => 429]);
        $raw = trim((string) $request->get_param('url'));
        if (!preg_match('#^https?://#i', $raw)) $raw = 'https://' . $raw;
        $url = wp_http_validate_url($raw);
        if (!$url) return new WP_Error('invalid_url', 'Enter a valid public website URL.', ['status' => 400]);
        $parts = wp_parse_url($url);
        if (empty($parts['host']) || !preg_match('/\./', $parts['host'])) return new WP_Error('invalid_host', 'Local and internal websites cannot be scanned.', ['status' => 400]);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . absint($parts['port']) : '');
        $checks = [['Homepage','/'],['WordPress REST API','/wp-json/'],['Admin path','/wp-admin/'],['Robots','/robots.txt'],['WordPress sitemap','/wp-sitemap.xml']];
        $results = [];
        $homepage_body = '';
        $homepage_headers = [];
        $homepage_time = 0;
        $rest_status = 0;
        foreach ($checks as $check) {
            $start = microtime(true);
            $response = wp_safe_remote_get($origin . $check[1], ['timeout' => 8, 'redirection' => 5, 'user-agent' => 'WP-Error-Doctor/1.0']);
            $ms = round((microtime(true) - $start) * 1000);
            if (is_wp_error($response)) { $results[] = ['name'=>$check[0], 'status'=>'ERR', 'time'=>$ms, 'state'=>'critical', 'finding'=>'Connection failed or timed out']; continue; }
            $code = wp_remote_retrieve_response_code($response);
            if ($check[1] === '/') { $homepage_body = strtolower(substr((string) wp_remote_retrieve_body($response), 0, 500000)); $homepage_headers = wp_remote_retrieve_headers($response); $homepage_time = $ms; }
            if ($check[1] === '/wp-json/') $rest_status = $code;
            $admin_hidden = $check[1] === '/wp-admin/' && in_array($code, [401,403,404], true);
            $state = $admin_hidden ? 'neutral' : ($code >= 500 ? 'critical' : ($code >= 400 ? 'warning' : 'healthy'));
            $finding = $admin_hidden ? 'Protected or renamed (inconclusive)' : ($code >= 500 ? 'Server-side error' : ($code >= 400 ? 'Endpoint unavailable' : 'Accessible'));
            $results[] = ['name'=>$check[0], 'status'=>(string)$code, 'time'=>$ms, 'state'=>$state, 'finding'=>$finding];
        }
        $home = $results[0];
        $id = 'WPD-' . strtoupper(wp_generate_password(7, false, false));
        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        $is_jawad = $host === 'jawadjd.dev';
        $is_wordpress = $rest_status >= 200 && $rest_status < 400;
        if (!$is_wordpress && $homepage_body) $is_wordpress = strpos($homepage_body, '/wp-content/') !== false || strpos($homepage_body, '/wp-includes/') !== false || strpos($homepage_body, 'generator" content="wordpress') !== false;
        $fun_message = $is_jawad ? 'Hey, that’s Jawad’s own website — the doctor is checking its own heartbeat! 🩺' : ((!$is_wordpress && $home['status'] === '200') ? 'Plot twist: this website appears healthy, but it doesn’t look like WordPress. Our stethoscope is tuned for WP sites! 🕵️' : '');
        $findings = $this->analyze_page($homepage_body, $homepage_headers, $homepage_time, $parts['scheme']);
        $counts = ['critical'=>0,'warning'=>0,'passed'=>0];
        foreach ($findings as $finding) $counts[$finding['state']]++;
        $score = max(0, min(100, 100 - ($counts['critical'] * 18) - ($counts['warning'] * 6)));
        $report = ['scan_id'=>$id, 'url'=>esc_url_raw($url), 'online'=>$home['status']==='200', 'is_wordpress'=>$is_wordpress, 'is_jawad'=>$is_jawad, 'fun_message'=>$fun_message, 'results'=>$results, 'findings'=>$findings, 'counts'=>$counts, 'score'=>$score, 'scanned_at'=>current_time('mysql')];
        $report_key = strtolower(substr($id, 4));
        set_transient('wpd_report_' . $report_key, $report, DAY_IN_SECONDS);
        $lead_id=absint($request->get_param('lead_id')); if($lead_id){ global $wpdb; $wpdb->update($wpdb->prefix.'wpd_leads',['scan_id'=>$id,'score'=>$score,'status'=>$home['status']==='200'?'online':'attention','report_key'=>$report_key],['id'=>$lead_id],['%s','%d','%s','%s'],['%d']); }
        $report['report_url'] = add_query_arg('report', strtolower(substr($id, 4)), $this->report_page_url());
        return rest_ensure_response($report);
    }

    private function email_scan_report($lead_id, $report, $report_key) {
        global $wpdb; $lead=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wpd_leads WHERE id=%d",$lead_id)); if(!$lead) return false;
        $to='jawad.productions@gmail.com'; $host=wp_parse_url($report['url'],PHP_URL_HOST); $subject="New WP Error Doctor lead: {$host}"; $report_url=add_query_arg('report',$report_key,$this->report_page_url());
        $body="A visitor completed a website diagnosis.\n\nEmail: {$lead->email}\nWebsite: {$report['url']}\nScan ID: {$report['scan_id']}\nHealth score: {$report['score']}/100\nStatus: ".($report['online']?'Online':'Needs attention')."\nFull report: {$report_url}\n\nCritical: {$report['counts']['critical']}\nRecommended: {$report['counts']['warning']}\nPassed: {$report['counts']['passed']}\n\nThe visitor consented to relevant follow-up.";
        return wp_mail($to,$subject,$body,['Reply-To: '.$lead->email]);
    }

    private function analyze_page($html, $headers, $time, $scheme) {
        $has = function($needle) use ($html) { return strpos($html, $needle) !== false; };
        $header = function($name) use ($headers) { return is_object($headers) ? $headers->offsetGet($name) : ($headers[$name] ?? ''); };
        $count = function($pattern) use ($html) { preg_match_all($pattern, $html, $m); return count($m[0]); };
        $items = [];
        $add = function($category,$title,$state,$detail,$action='') use (&$items){ $items[]=['category'=>$category,'title'=>$title,'state'=>$state,'detail'=>$detail,'action'=>$action]; };
        $add('Responsive','Mobile viewport',$has('name="viewport') || $has("name='viewport")?'passed':'critical',$has('name="viewport') || $has("name='viewport")?'A mobile viewport is configured.':'No mobile viewport tag was detected.','Add a responsive viewport meta tag and test key templates on mobile.');
        $add('Responsive','Fixed-width risk',preg_match('/width\s*[:=]\s*["\']?[1-9][0-9]{3,}px/', $html)?'warning':'passed',preg_match('/width\s*[:=]\s*["\']?[1-9][0-9]{3,}px/', $html)?'Large fixed pixel widths may cause horizontal scrolling.':'No obvious large fixed-width markup was found.','Review the page at 375px, 768px, and 1440px widths.');
        $size = strlen($html); $scripts=$count('/<script\b/i'); $styles=$count('/<link[^>]+stylesheet/i'); $images=$count('/<img\b/i');
        $add('Performance','Server response',$time > 2000?'critical':($time>900?'warning':'passed'),"Homepage responded in {$time} ms.",'Investigate hosting, caching, database queries, and slow plugins.');
        $add('Performance','HTML document size',$size>300000?'warning':'passed','The public HTML document is ' . size_format($size) . '.','Reduce excessive page-builder markup and unused page content.');
        $add('Performance','Frontend requests',$scripts+$styles>35?'warning':'passed',"Detected {$scripts} scripts and {$styles} stylesheets in page markup.",'Remove unused assets and delay non-critical scripts.');
        $add('Performance','Image markup',$images>40?'warning':'passed',"Detected {$images} images on the homepage.",'Compress images, use modern formats, and lazy-load below the fold.');
        $add('Security','HTTPS',$scheme==='https'?'passed':'critical',$scheme==='https'?'The submitted page uses HTTPS.':'The submitted page does not use HTTPS.','Install SSL and redirect all HTTP traffic to HTTPS.');
        $mixed = $scheme==='https' && preg_match('#(?:src|href)=["\']http://#i',$html);
        $add('Security','Mixed content',$mixed?'critical':'passed',$mixed?'Insecure HTTP asset references were found on an HTTPS page.':'No obvious mixed-content asset references were found.','Replace HTTP asset URLs with HTTPS.');
        foreach ([['Content security policy','content-security-policy'],['Clickjacking protection','x-frame-options'],['Content type protection','x-content-type-options']] as $h) $add('Security',$h[0],$header($h[1])?'passed':'warning',$header($h[1])?'Header detected.':'Recommended security header was not detected.','Ask your developer or host to review security headers.');
        $add('SEO','Page title',preg_match('/<title[^>]*>\s*[^<]{10,}/i',$html)?'passed':'warning',preg_match('/<title[^>]*>\s*[^<]{10,}/i',$html)?'A descriptive title appears to be present.':'The page title is missing or unusually short.','Write a unique, descriptive title for the homepage.');
        $add('SEO','Meta description',$has('name="description') || $has("name='description")?'passed':'warning',$has('name="description') || $has("name='description")?'A meta description was detected.':'No meta description was detected.','Add a clear search description that explains the service.');
        $add('SEO','Canonical URL',$has('rel="canonical') || $has("rel='canonical")?'passed':'warning',$has('rel="canonical') || $has("rel='canonical")?'A canonical URL was detected.':'No canonical URL was detected.','Add a self-referencing canonical URL.');
        $add('SEO','Primary heading',$count('/<h1\b/i')===1?'passed':'warning','Detected ' . $count('/<h1\b/i') . ' H1 headings.','Use one clear primary heading per page.');
        $add('WordPress','Public error signatures',preg_match('/critical error|error establishing a database connection|allowed memory size exhausted|fatal error|maximum execution time exceeded/i',$html)?'critical':'passed',preg_match('/critical error|error establishing a database connection|allowed memory size exhausted|fatal error|maximum execution time exceeded/i',$html)?'A public WordPress or PHP error signature was detected.':'No common public fatal-error signature was found.','Review PHP and WordPress debug logs before changing plugins.');
        return $items;
    }

    private function report_page_url() { $id=(int)get_option('wpd_report_page_id'); return $id ? get_permalink($id) : home_url('/website-diagnostic-report/'); }

    public function report_page() {
        $key = isset($_GET['report']) ? sanitize_key(wp_unslash($_GET['report'])) : '';
        $r = $key ? get_transient('wpd_report_' . $key) : false;
        wp_enqueue_style('wpd-report', plugin_dir_url(__FILE__) . 'assets/report.css', [], self::VERSION);
        if (!$r) return '<div class="wpd-full wpd-empty"><p class="wpd-label">WP ERROR DOCTOR</p><h1>This report has expired.</h1><p>Diagnostic reports remain available for 24 hours. Please run a new scan.</p></div>';
        ob_start(); ?>
        <main class="wpd-full"><header class="wpd-report-head"><div><p class="wpd-label">WEBSITE DIAGNOSTIC REPORT</p><h1><?php echo esc_html(wp_parse_url($r['url'], PHP_URL_HOST)); ?></h1><p><?php echo esc_html($r['scan_id']); ?> · <?php echo esc_html($r['scanned_at']); ?></p></div><div class="wpd-score"><strong><?php echo absint($r['score']); ?></strong><span>HEALTH SCORE</span></div></header>
        <section class="wpd-report-status <?php echo $r['online']?'good':'bad'; ?>"><span><?php echo $r['online']?'✓':'!'; ?></span><div><p class="wpd-label">OVERALL STATUS</p><h2><?php echo $r['online']?'Website is online':'Website needs attention'; ?></h2><p><?php echo $r['online']?'The homepage is publicly accessible. Review the opportunities below for improvements.':'The homepage did not respond successfully and may require urgent investigation.'; ?></p></div></section>
        <div class="wpd-summary"><div><strong><?php echo absint($r['counts']['critical']); ?></strong><span>Critical</span></div><div><strong><?php echo absint($r['counts']['warning']); ?></strong><span>Recommended</span></div><div><strong><?php echo absint($r['counts']['passed']); ?></strong><span>Passed</span></div></div>
        <?php foreach (['Responsive','Performance','Security','SEO','WordPress'] as $category): $group=array_filter($r['findings'],function($f)use($category){return $f['category']===$category;}); ?>
        <section class="wpd-report-section"><div class="wpd-section-title"><p class="wpd-label">PUBLIC CHECK</p><h2><?php echo esc_html($category); ?></h2></div><div class="wpd-findings"><?php foreach($group as $f): ?><article><i class="<?php echo esc_attr($f['state']); ?>"><?php echo $f['state']==='passed'?'✓':($f['state']==='critical'?'!':'•'); ?></i><div><h3><?php echo esc_html($f['title']); ?></h3><p><?php echo esc_html($f['detail']); ?></p><?php if($f['state']!=='passed' && $f['action']): ?><small>RECOMMENDED: <?php echo esc_html($f['action']); ?></small><?php endif; ?></div><b><?php echo esc_html(strtoupper($f['state'])); ?></b></article><?php endforeach; ?></div></section>
        <?php endforeach; ?>
        <section class="wpd-report-cta"><div><p class="wpd-label">WORDPRESS DEVELOPER · 10+ YEARS EXPERIENCE</p><h2>Want Jawad to fix these issues?</h2><p>Send this report for a careful, professional review. No passwords are needed to start.</p></div><a href="<?php echo esc_url(home_url('/#contact')); ?>">Send Report to Jawad →</a></section>
        <p class="wpd-disclaimer">This report uses public website signals and does not confirm private backend causes. Visual responsive testing requires rendered browser checks.</p></main>
        <?php return ob_get_clean();
    }

    public function lead(WP_REST_Request $request) {
        if ($this->rate_limited('lead', 4)) return new WP_Error('rate_limit', 'Too many requests. Please try again shortly.', ['status' => 429]);
        $name = sanitize_text_field((string)$request->get_param('name'));
        $email = sanitize_email((string)$request->get_param('email'));
        $message = sanitize_textarea_field((string)$request->get_param('message'));
        $report = $request->get_param('report');
        if (!$name || !is_email($email) || !$request->get_param('consent') || !is_array($report)) return new WP_Error('invalid_lead', 'Please complete all required fields.', ['status'=>400]);
        $s = $this->settings();
        $subject = sprintf('WordPress repair lead: %s', sanitize_text_field($report['url'] ?? 'Website report'));
        $body = "New WP Error Doctor lead\n\nName: {$name}\nEmail: {$email}\nWebsite: " . esc_url_raw($report['url'] ?? '') . "\nScan ID: " . sanitize_text_field($report['scan_id'] ?? '') . "\n\nMessage:\n{$message}\n\nPublic scan:\n";
        foreach (($report['results'] ?? []) as $row) $body .= sanitize_text_field($row['name'] ?? '') . ': HTTP ' . sanitize_text_field($row['status'] ?? '') . ' — ' . sanitize_text_field($row['finding'] ?? '') . "\n";
        $sent = wp_mail('jawad.productions@gmail.com', $subject, $body, ['Reply-To: ' . $name . ' <' . $email . '>']);
        if (!$sent) return new WP_Error('mail_failed', 'The report could not be sent. Please contact Jawad directly.', ['status'=>500]);
        return rest_ensure_response(['success'=>true, 'message'=>'Your report was sent to Jawad. He will contact you soon.']);
    }

    public function admin_menu() { add_menu_page('WP Error Doctor','Error Doctor','manage_options','wp-error-doctor',[$this,'dashboard_page'],'dashicons-heart','58.6'); add_submenu_page('wp-error-doctor','Lead Dashboard','Lead Dashboard','manage_options','wp-error-doctor',[$this,'dashboard_page']); add_submenu_page('wp-error-doctor','Settings','Settings','manage_options','wp-error-doctor-settings',[$this,'settings_page']); }

    public function send_follow_up() {
        if(!current_user_can('manage_options')) wp_die('Unauthorized'); check_admin_referer('wpd_follow_up'); global $wpdb; $id=absint($_POST['lead_id']??0); $lead=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wpd_leads WHERE id=%d",$id));
        $sent=false; if($lead && $lead->consent){ $subject='A quick follow-up about '.wp_parse_url($lead->website_url,PHP_URL_HOST); $body="Hi,\n\nYou recently used WP Error Doctor to scan {$lead->website_url}. I wanted to check whether you need help with any of the findings.\n\nIf you reply to this email, I’ll take a look and suggest the safest next step.\n\nRegards,\nJawad Ilyas\nWordPress Developer\nhttps://jawadjd.dev"; $sent=wp_mail($lead->email,$subject,$body,['From: Jawad Ilyas <jawad.productions@gmail.com>','Reply-To: jawad.productions@gmail.com']); if($sent) $wpdb->update($wpdb->prefix.'wpd_leads',['last_contacted_at'=>current_time('mysql')],['id'=>$id]); }
        wp_safe_redirect(admin_url('admin.php?page=wp-error-doctor&mail_status='.($sent?'sent':'failed'))); exit;
    }

    public function dashboard_page() { global $wpdb; $table=$wpdb->prefix.'wpd_leads'; $leads=$wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200"); $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}"); $week=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)"); $attention=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status='attention'"); $avg=(int)$wpdb->get_var("SELECT AVG(score) FROM {$table} WHERE score IS NOT NULL"); $mail_status=sanitize_key($_GET['mail_status']??''); ?>
    <?php if($mail_status==='sent'):?><div class="notice notice-success"><p>Follow-up email sent successfully.</p></div><?php elseif($mail_status==='failed'):?><div class="notice notice-error"><p>Email delivery failed. Configure SMTP for jawad.productions@gmail.com, then try again.</p></div><?php endif;?>
    <div class="wpd-admin"><header><div><p>LEAD INTELLIGENCE</p><h1>WP Error Doctor</h1><span>Qualified website diagnostic leads and follow-up activity.</span></div><a href="<?php echo esc_url(home_url('/')); ?>" target="_blank">View website ↗</a></header><?php if(isset($_GET['followed'])):?><div class="wpd-notice">Follow-up email sent successfully.</div><?php endif;?><section class="wpd-stats"><article><span>TOTAL LEADS</span><strong><?php echo $total;?></strong><small>All captured scans</small></article><article><span>LAST 7 DAYS</span><strong><?php echo $week;?></strong><small>Recent opportunities</small></article><article><span>NEEDS ATTENTION</span><strong><?php echo $attention;?></strong><small>Potential repair leads</small></article><article><span>AVG. HEALTH</span><strong><?php echo $avg?:'—';?></strong><small>Across completed scans</small></article></section><section class="wpd-leads"><div class="wpd-table-head"><div><p>LEAD PIPELINE</p><h2>Recent diagnostic leads</h2></div><span><?php echo count($leads);?> shown</span></div><div class="wpd-table"><div class="wpd-tr wpd-th"><span>CONTACT</span><span>WEBSITE</span><span>RESULT</span><span>CAPTURED</span><span>ACTION</span></div><?php if(!$leads):?><div class="wpd-empty-row">No leads yet. Run a test scan from your website.</div><?php endif; foreach($leads as $lead):?><div class="wpd-tr"><span><b><?php echo esc_html($lead->email);?></b><small><?php echo $lead->consent?'✓ Marketing consent':'No consent';?></small></span><span><a href="<?php echo esc_url($lead->website_url);?>" target="_blank"><?php echo esc_html(wp_parse_url($lead->website_url,PHP_URL_HOST));?> ↗</a><small><?php echo esc_html($lead->scan_id?:'Scan pending');?></small></span><span><i class="<?php echo esc_attr($lead->status);?>"><?php echo esc_html(strtoupper($lead->status));?></i><small><?php echo $lead->score!==null?'Health score '.$lead->score:'Awaiting report';?></small></span><span><?php echo esc_html(human_time_diff(strtotime($lead->created_at),current_time('timestamp')));?> ago<small><?php echo $lead->last_contacted_at?'Contacted '.human_time_diff(strtotime($lead->last_contacted_at),current_time('timestamp')).' ago':'Not contacted';?></small></span><span class="wpd-actions"><?php if($lead->report_key):?><a href="<?php echo esc_url(add_query_arg('report',$lead->report_key,$this->report_page_url()));?>" target="_blank">Report</a><?php endif;?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><?php wp_nonce_field('wpd_follow_up');?><input type="hidden" name="action" value="wpd_follow_up"><input type="hidden" name="lead_id" value="<?php echo absint($lead->id);?>"><button <?php disabled(!$lead->consent);?>>Follow up</button></form></span></div><?php endforeach;?></div></section></div><?php $this->admin_css(); }

    private function admin_css(){?><style>.wpd-admin{margin:0 0 0 -20px;min-height:100vh;padding:42px;background:#05070d;color:#f8fafc;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wpd-admin *{box-sizing:border-box}.wpd-admin header{display:flex;justify-content:space-between;align-items:end;max-width:1400px}.wpd-admin header p,.wpd-table-head p{font:10px ui-monospace,monospace;letter-spacing:.14em;color:#22d3ee;margin:0}.wpd-admin h1{color:#f8fafc;font-size:44px;letter-spacing:-.05em;margin:8px 0}.wpd-admin header span{color:#94a3b8}.wpd-admin header>a{border:1px solid #29485d;padding:12px 16px;color:#c7d7e2;text-decoration:none}.wpd-notice{margin-top:20px;border:1px solid #236552;background:#092019;padding:13px;color:#34d399}.wpd-stats{max-width:1400px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:38px 0}.wpd-stats article{border:1px solid #1d3144;background:#0b1220;padding:23px}.wpd-stats span{font:9px ui-monospace,monospace;color:#7f91a1}.wpd-stats strong{display:block;font-size:34px;color:#f8fafc;margin:12px 0}.wpd-stats small{color:#66788a}.wpd-leads{max-width:1400px;border:1px solid #1d3144;background:#0b1220}.wpd-table-head{display:flex;justify-content:space-between;align-items:center;padding:22px;border-bottom:1px solid #1d3144}.wpd-table-head h2{color:#f8fafc;margin:5px 0}.wpd-table-head>span{color:#6f8190}.wpd-tr{display:grid;grid-template-columns:1.4fr 1.2fr .8fr .9fr 1fr;gap:18px;align-items:center;padding:16px 20px;border-bottom:1px solid #152536}.wpd-th{font:9px ui-monospace,monospace;color:#6e8191}.wpd-tr span{color:#c7d2da;font-size:12px;min-width:0}.wpd-tr b,.wpd-tr small{display:block}.wpd-tr small{color:#687b8b;font-size:9px;margin-top:5px}.wpd-tr a{color:#22d3ee;text-decoration:none}.wpd-tr i{font:9px ui-monospace,monospace;font-style:normal;padding:5px 7px;border:1px solid #35516a}.wpd-tr i.online{color:#34d399;border-color:#235e4c}.wpd-tr i.attention{color:#fb7185;border-color:#63303b}.wpd-actions{display:flex;gap:6px}.wpd-actions form{margin:0}.wpd-actions a,.wpd-actions button{display:inline-block;border:1px solid #29485d;background:transparent;color:#c9d6df!important;padding:7px 9px;font-size:10px;cursor:pointer}.wpd-empty-row{padding:45px;text-align:center;color:#718291}@media(max-width:1000px){.wpd-stats{grid-template-columns:1fr 1fr}.wpd-table{overflow-x:auto}.wpd-tr{min-width:950px}}</style><?php }
    public function register_settings() {
        register_setting('wpd_settings', self::OPTION, ['sanitize_callback'=>function($v){ return [
            'enabled'=>!empty($v['enabled'])?'1':'0', 'email'=>sanitize_email($v['email']??''), 'accent'=>sanitize_hex_color($v['accent']??'')?:'#22d3ee',
            'position'=>($v['position']??'right')==='left'?'left':'right', 'button_text'=>sanitize_text_field($v['button_text']??''), 'headline'=>sanitize_text_field($v['headline']??''),
            'chat_enabled'=>!empty($v['chat_enabled'])?'1':'0','openai_key'=>sanitize_text_field($v['openai_key']??''),'openai_model'=>sanitize_text_field($v['openai_model']??'gpt-5-mini')]; }]);
    }
    public function settings_page() { $s=$this->settings(); ?>
        <div class="wrap"><h1>WP Error Doctor</h1><p>Configure the floating diagnostic lead widget.</p><form method="post" action="options.php"><?php settings_fields('wpd_settings'); ?><table class="form-table">
        <tr><th>Enable widget</th><td><label><input type="checkbox" name="<?php echo self::OPTION; ?>[enabled]" value="1" <?php checked($s['enabled'],'1'); ?>> Show on the public website</label></td></tr>
        <tr><th>Lead email</th><td><input class="regular-text" type="email" name="<?php echo self::OPTION; ?>[email]" value="<?php echo esc_attr($s['email']); ?>"></td></tr>
        <tr><th>Accent color</th><td><input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>"></td></tr>
        <tr><th>Position</th><td><select name="<?php echo self::OPTION; ?>[position]"><option value="right" <?php selected($s['position'],'right'); ?>>Bottom right</option><option value="left" <?php selected($s['position'],'left'); ?>>Bottom left</option></select></td></tr>
        <tr><th>Button text</th><td><input class="regular-text" name="<?php echo self::OPTION; ?>[button_text]" value="<?php echo esc_attr($s['button_text']); ?>"></td></tr>
        <tr><th>Headline</th><td><input class="large-text" name="<?php echo self::OPTION; ?>[headline]" value="<?php echo esc_attr($s['headline']); ?>"></td></tr>
        <tr><th>AI sales assistant</th><td><label><input type="checkbox" name="<?php echo self::OPTION; ?>[chat_enabled]" value="1" <?php checked($s['chat_enabled'],'1'); ?>> Show the AI chatbot on the public website</label></td></tr>
        <tr><th>OpenAI API key</th><td><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo self::OPTION; ?>[openai_key]" value="<?php echo esc_attr($s['openai_key']); ?>"><p class="description">Stored in WordPress options and never sent to the browser. For stronger security, define OPENAI_API_KEY in wp-config.php.</p></td></tr>
        <tr><th>AI model</th><td><input class="regular-text" name="<?php echo self::OPTION; ?>[openai_model]" value="<?php echo esc_attr($s['openai_model']); ?>"><p class="description">Recommended: gpt-5-mini. Unavailable models automatically retry with gpt-5-mini.</p></td></tr>
        </table><?php submit_button(); ?></form></div><?php }
}
register_activation_hook(__FILE__, ['WPD_Lead_Widget', 'activate']);
new WPD_Lead_Widget();

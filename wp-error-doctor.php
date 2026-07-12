<?php
/**
 * Plugin Name: WP Error Doctor Lead Widget
 * Description: A floating website diagnostic widget that captures qualified WordPress repair leads.
 * Version: 1.0.0
 * Author: Jawad Ilyas
 * Author URI: https://jawadjd.dev
 * Text Domain: wp-error-doctor
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

final class WPD_Lead_Widget {
    const VERSION = '1.0.0';
    const OPTION = 'wpd_widget_settings';

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('wp_footer', [$this, 'render']);
        add_action('rest_api_init', [$this, 'routes']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function settings() {
        return wp_parse_args(get_option(self::OPTION, []), [
            'enabled' => '1', 'email' => get_option('admin_email'),
            'accent' => '#22d3ee', 'position' => 'right',
            'button_text' => 'Diagnose My Website',
            'headline' => 'Is your WordPress site having problems?',
        ]);
    }

    public function assets() {
        $s = $this->settings();
        if ($s['enabled'] !== '1' || is_admin()) return;
        wp_enqueue_style('wpd-widget', plugin_dir_url(__FILE__) . 'assets/widget.css', [], self::VERSION);
        wp_enqueue_script('wpd-widget', plugin_dir_url(__FILE__) . 'assets/widget.js', [], self::VERSION, true);
        wp_localize_script('wpd-widget', 'WPDWidget', [
            'root' => esc_url_raw(rest_url('wp-error-doctor/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'accent' => sanitize_hex_color($s['accent']) ?: '#22d3ee',
            'position' => $s['position'] === 'left' ? 'left' : 'right',
        ]);
    }

    public function render() {
        $s = $this->settings();
        if ($s['enabled'] !== '1' || is_admin()) return;
        ?>
        <div id="wpd-widget" class="wpd-widget wpd-<?php echo esc_attr($s['position']); ?>" style="--wpd-accent:<?php echo esc_attr(sanitize_hex_color($s['accent'])); ?>">
            <button class="wpd-launch" type="button" aria-haspopup="dialog" aria-controls="wpd-dialog"><span class="wpd-pulse"></span><span class="wpd-launch-icon">+</span><b><?php echo esc_html($s['button_text']); ?></b></button>
            <section id="wpd-dialog" class="wpd-dialog" role="dialog" aria-modal="true" aria-labelledby="wpd-title" hidden>
                <header><div class="wpd-brand"><span>W</span><div><b>WP Error Doctor</b><small>by Jawad Ilyas</small></div></div><button class="wpd-close" type="button" aria-label="Close">×</button></header>
                <div class="wpd-view wpd-start">
                    <span class="wpd-kicker">FREE WEBSITE HEALTH CHECK</span>
                    <h2 id="wpd-title"><?php echo esc_html($s['headline']); ?></h2>
                    <p>Run a safe public scan for server errors and common WordPress failures. No login required.</p>
                    <form class="wpd-scan-form"><label for="wpd-url">Website URL</label><div class="wpd-input"><span>◎</span><input id="wpd-url" type="text" inputmode="url" placeholder="yourwebsite.com" required></div><button type="submit">Scan My Website <span>→</span></button><small>◆ Public checks only. No passwords requested.</small></form>
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
    }

    public function routes() {
        register_rest_route('wp-error-doctor/v1', '/scan', ['methods' => 'POST', 'callback' => [$this, 'scan'], 'permission_callback' => '__return_true']);
        register_rest_route('wp-error-doctor/v1', '/lead', ['methods' => 'POST', 'callback' => [$this, 'lead'], 'permission_callback' => '__return_true']);
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
        foreach ($checks as $check) {
            $start = microtime(true);
            $response = wp_safe_remote_get($origin . $check[1], ['timeout' => 8, 'redirection' => 5, 'user-agent' => 'WP-Error-Doctor/1.0']);
            $ms = round((microtime(true) - $start) * 1000);
            if (is_wp_error($response)) { $results[] = ['name'=>$check[0], 'status'=>'ERR', 'time'=>$ms, 'state'=>'critical', 'finding'=>'Connection failed or timed out']; continue; }
            $code = wp_remote_retrieve_response_code($response);
            $admin_hidden = $check[1] === '/wp-admin/' && in_array($code, [401,403,404], true);
            $state = $admin_hidden ? 'neutral' : ($code >= 500 ? 'critical' : ($code >= 400 ? 'warning' : 'healthy'));
            $finding = $admin_hidden ? 'Protected or renamed (inconclusive)' : ($code >= 500 ? 'Server-side error' : ($code >= 400 ? 'Endpoint unavailable' : 'Accessible'));
            $results[] = ['name'=>$check[0], 'status'=>(string)$code, 'time'=>$ms, 'state'=>$state, 'finding'=>$finding];
        }
        $home = $results[0];
        $id = 'WPD-' . strtoupper(wp_generate_password(7, false, false));
        return rest_ensure_response(['scan_id'=>$id, 'url'=>esc_url_raw($url), 'online'=>$home['status']==='200', 'results'=>$results]);
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
        $sent = wp_mail(sanitize_email($s['email']), $subject, $body, ['Reply-To: ' . $name . ' <' . $email . '>']);
        if (!$sent) return new WP_Error('mail_failed', 'The report could not be sent. Please contact Jawad directly.', ['status'=>500]);
        return rest_ensure_response(['success'=>true, 'message'=>'Your report was sent to Jawad. He will contact you soon.']);
    }

    public function admin_menu() { add_options_page('WP Error Doctor', 'WP Error Doctor', 'manage_options', 'wp-error-doctor', [$this, 'settings_page']); }
    public function register_settings() {
        register_setting('wpd_settings', self::OPTION, ['sanitize_callback'=>function($v){ return [
            'enabled'=>!empty($v['enabled'])?'1':'0', 'email'=>sanitize_email($v['email']??''), 'accent'=>sanitize_hex_color($v['accent']??'')?:'#22d3ee',
            'position'=>($v['position']??'right')==='left'?'left':'right', 'button_text'=>sanitize_text_field($v['button_text']??''), 'headline'=>sanitize_text_field($v['headline']??'')]; }]);
    }
    public function settings_page() { $s=$this->settings(); ?>
        <div class="wrap"><h1>WP Error Doctor</h1><p>Configure the floating diagnostic lead widget.</p><form method="post" action="options.php"><?php settings_fields('wpd_settings'); ?><table class="form-table">
        <tr><th>Enable widget</th><td><label><input type="checkbox" name="<?php echo self::OPTION; ?>[enabled]" value="1" <?php checked($s['enabled'],'1'); ?>> Show on the public website</label></td></tr>
        <tr><th>Lead email</th><td><input class="regular-text" type="email" name="<?php echo self::OPTION; ?>[email]" value="<?php echo esc_attr($s['email']); ?>"></td></tr>
        <tr><th>Accent color</th><td><input type="color" name="<?php echo self::OPTION; ?>[accent]" value="<?php echo esc_attr($s['accent']); ?>"></td></tr>
        <tr><th>Position</th><td><select name="<?php echo self::OPTION; ?>[position]"><option value="right" <?php selected($s['position'],'right'); ?>>Bottom right</option><option value="left" <?php selected($s['position'],'left'); ?>>Bottom left</option></select></td></tr>
        <tr><th>Button text</th><td><input class="regular-text" name="<?php echo self::OPTION; ?>[button_text]" value="<?php echo esc_attr($s['button_text']); ?>"></td></tr>
        <tr><th>Headline</th><td><input class="large-text" name="<?php echo self::OPTION; ?>[headline]" value="<?php echo esc_attr($s['headline']); ?>"></td></tr>
        </table><?php submit_button(); ?></form></div><?php }
}
new WPD_Lead_Widget();

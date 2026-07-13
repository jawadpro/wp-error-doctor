<?php defined('ABSPATH') || exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('wpd-standalone-body'); ?>><?php wp_body_open(); ?>
<header class="wpd-site-head"><a class="wpd-site-brand" href="<?php echo esc_url(home_url('/')); ?>"><span>W</span><div><b>WP Error Doctor</b><small>by Jawad Ilyas</small></div></a><nav><a href="#checks">What it checks</a><a href="#faq">FAQ</a><a class="wpd-head-cta" href="<?php echo esc_url(home_url('/')); ?>">Back to jawadjd.dev ↗</a></nav></header>
<?php echo do_shortcode('[wp_error_doctor_scanner]'); ?>
<footer class="wpd-site-footer"><a class="wpd-site-brand" href="<?php echo esc_url(home_url('/')); ?>"><span>W</span><div><b>WP Error Doctor</b><small>Evidence-based website diagnostics</small></div></a><p>Public checks only. No passwords requested. No changes made.</p><small>© <?php echo esc_html(date('Y')); ?> Jawad Ilyas</small></footer>
<?php wp_footer(); ?></body></html>

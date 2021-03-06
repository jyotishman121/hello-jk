<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Exit if no permission to add shortcodes
if (
  !(\TheLion\UseyourDrive\Helpers::check_user_role($this->settings['permissions_add_shortcodes']))
) {
    die();
}

$this->load_scripts();
$this->load_styles();
$this->load_custom_css();

function UseyourDrive_remove_all_scripts()
{
    global $wp_scripts;
    $wp_scripts->queue = [];

    wp_enqueue_script('jquery-effects-fade');
    wp_enqueue_script('jquery-ui-accordion');
    wp_enqueue_script('jquery');
    wp_enqueue_script('UseyourDrive');
    wp_enqueue_script('UseyourDrive.ShortcodeBuilder');
}

function UseyourDrive_remove_all_styles()
{
    global $wp_styles;
    $wp_styles->queue = [];
    wp_enqueue_style('UseyourDrive.ShortcodeBuilder');
    wp_enqueue_style('UseyourDrive');
    wp_enqueue_style('Awesome-Font-5-css');
}

add_action('wp_print_scripts', 'UseyourDrive_remove_all_scripts', 1000);
add_action('wp_print_styles', 'UseyourDrive_remove_all_styles', 1000);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title><?php _e('Shortcode Previewer','wpcloudplugins'); ?></title>
     <?php wp_print_scripts(); ?>
    <?php wp_print_styles(); ?>
</head>

<body>
  <?php

  $atts = $_REQUEST;
  echo $this->get_processor()->create_from_shortcode($atts);

  wp_footer();
  ?>
</body>
</html>
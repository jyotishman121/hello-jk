<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Exit if no permission to embed files
if (
  !(\TheLion\UseyourDrive\Helpers::check_user_role($this->settings['permissions_add_links']))
) {
    die();
}

// Add own styles and script and remove default ones
$this->load_scripts();
$this->load_styles();
$this->load_custom_css();

function UseyourDrive_remove_all_scripts()
{
    global $wp_scripts;
    $wp_scripts->queue = [];

    wp_enqueue_script('jquery-effects-fade');
    wp_enqueue_script('jquery');
    wp_enqueue_script('UseyourDrive');
    wp_enqueue_script('UseyourDrive.DocumentLinker');
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
  <title><?php _e('Insert Direct links', 'wpcloudplugins'); ?></title>
  <?php wp_print_scripts(); ?>
  <?php wp_print_styles(); ?>
</head>

<body class="useyourdrive">

  <form action="#" data-callback="<?php echo isset($_REQUEST['callback']) ? $_REQUEST['callback'] : ''; ?>">

    <div class="wrap">
      <div class="useyourdrive-header">
        <div class="useyourdrive-logo"><a href="https://www.wpcloudplugins.com" target="_blank"><img src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/wpcp-logo-dark.svg" height="64" width="64"/></a></div>
        <div class="useyourdrive-form-buttons">
          <div id="do_link" class="simple-button default" name="insert"><?php _e('Insert Links', 'wpcloudplugins'); ?>&nbsp;<i class="fas fa-chevron-circle-right" aria-hidden="true"></i></div>
          <div class="tippy-content-holder">
            <ul class='link-list'>
              <li class="link-item">
                <a class="link-preview" data-type="preview" title="<?php _e('Link to preview', 'wpcloudplugins'); ?>">
                  <i class='fas fa-eye'></i> <?php _e('Link to preview', 'wpcloudplugins'); ?>
                </a>
              </li>
              <li class="link-item">
                <a class="link-download" data-type="download" title="<?php _e('Link to download', 'wpcloudplugins'); ?>">
                  <i class='fas fa-arrow-down'></i> <?php _e('Link to download', 'wpcloudplugins'); ?>
                </a>
              </li>
            </ul>
          </div>
        </div>

        <div class="useyourdrive-title"><?php _e('Insert Direct links', 'wpcloudplugins'); ?></div>

      </div>

      <div class="useyourdrive-panel useyourdrive-panel-full">
        <p><?php _e('Please note that the embedded files need to be public (with link)', 'wpcloudplugins'); ?></p>
        <?php

        $atts = [
            'singleaccount' => '0',
            'dir' => 'drive',
            'mode' => 'files',
            'showfiles' => '1',
            'upload' => '0',
            'delete' => '0',
            'rename' => '0',
            'addfolder' => '0',
            'viewrole' => 'all',
            'candownloadzip' => '0',
            'showsharelink' => '0',
            'previewinline' => '0',
            'mcepopup' => 'links',
            'includeext' => '*',
            '_random' => 'embed',
        ];

        $user_folder_backend = apply_filters('useyourdrive_use_user_folder_backend', $this->settings['userfolder_backend']);

        if ('No' !== $user_folder_backend) {
            $atts['userfolders'] = $user_folder_backend;

            $private_root_folder = $this->settings['userfolder_backend_auto_root'];
            if ('auto' === $user_folder_backend && !empty($private_root_folder) && isset($private_root_folder['id'])) {
                if (!isset($private_root_folder['account']) || empty($private_root_folder['account'])) {
                    $main_account = $this->get_processor()->get_accounts()->get_primary_account();
                    $atts['account'] = $main_account->get_id();
                } else {
                    $atts['account'] = $private_root_folder['account'];
                }

                $atts['dir'] = $private_root_folder['id'];

                if (!isset($private_root_folder['view_roles']) || empty($private_root_folder['view_roles'])) {
                    $private_root_folder['view_roles'] = ['none'];
                }
                $atts['viewuserfoldersrole'] = implode('|', $private_root_folder['view_roles']);
            }
        }

        echo $this->create_template($atts);
        ?>
      </div>

      <div class="footer"></div>
    </div>
  </form>

</body>
</html>
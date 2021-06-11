<?php
$page = isset($_GET['page']) ? '?page='.$_GET['page'] : '';
$location = get_admin_url(null, 'admin.php'.$page);
$admin_nonce = wp_create_nonce('useyourdrive-admin-action');
$network_wide_authorization = $this->get_processor()->is_network_authorized();

function wp_roles_and_users_input($name, $selected = [])
{
    if (!is_array($selected)) {
        $selected = ['administrator'];
    }

    // Workaround: Add temporarily selected value to prevent an empty selection in Tagify when only user ID 0 is selected
    $selected[] = '_______PREVENT_EMPTY_______';

    // Create value for imput field
    $value = implode(', ', $selected);

    // Input Field
    echo "<input class='useyourdrive-option-input-large useyourdrive-tagify useyourdrive-permissions-placeholders' type='text' name='{$name}' value='{$value}' placeholder='' />";
}

function create_color_boxes_table($colors, $settings)
{
    if (0 === count($colors)) {
        return '';
    }

    $table_html = '<table class="color-table">';

    foreach ($colors as $color_id => $color) {
        $value = isset($settings['colors'][$color_id]) ? sanitize_text_field($settings['colors'][$color_id]) : $color['default'];

        $table_html .= '<tr>';
        $table_html .= "<td>{$color['label']}</td>";
        $table_html .= "<td><input value='{$value}' data-default-color='{$color['default']}'  name='use_your_drive_settings[colors][{$color_id}]' id='colors-{$color_id}' type='text'  class='useyourdrive-color-picker' data-alpha-enabled='true' ></td>";
        $table_html .= '</tr>';
    }

    $table_html .= '</table>';

    return $table_html;
}

function create_upload_button_for_custom_images($option)
{
    $field_value = $option['value'];
    $button_html = '<div class="upload_row">';

    $button_html .= '<div class="screenshot" id="'.$option['id'].'_image">'."\n";

    if ('' !== $field_value) {
        $button_html .= '<img src="'.$field_value.'" alt="" />'."\n";
        $button_html .= '<a href="javascript:void(0)" class="upload-remove">'.__('Remove', 'wpcloudplugins').'</a>'."\n";
    }

    $button_html .= '</div>';

    $button_html .= '<input id="'.esc_attr($option['id']).'" class="upload useyourdrive-option-input-large" type="text" name="'.esc_attr($option['name']).'" value="'.esc_attr($field_value).'" autocomplete="off" />';
    $button_html .= '<input class="upload_button simple-button blue" type="button" value="'.__('Select Image', 'wpcloudplugins').'" title="'.__('Upload or select a file from the media library', 'wpcloudplugins').'" />';

    if ($field_value !== $option['default']) {
        $button_html .= '<input id="default_image_button" class="default_image_button simple-button" type="button" value="'.__('Default', 'wpcloudplugins').'" title="'.__('Fallback to the default value', 'wpcloudplugins').'"  data-default="'.$option['default'].'"/>';
    }

    $button_html .= '</div>'."\n";

    return $button_html;
}
?>

<div class="useyourdrive admin-settings">
  <form id="useyourdrive-options" method="post" action="options.php">
    <?php settings_fields('use_your_drive_settings'); ?>

    <div class="wrap">
      <div class="useyourdrive-header">
        <div class="useyourdrive-logo"><a href="https://www.wpcloudplugins.com" target="_blank"><img src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/wpcp-logo-dark.svg" height="64" width="64"/></a></div>
        <div class="useyourdrive-form-buttons"> <div id="save_settings" class="simple-button default save_settings" name="save_settings"><?php _e('Save Settings', 'wpcloudplugins'); ?>&nbsp;<div class='uyd-spinner'></div></div></div>
        <div class="useyourdrive-title"><?php _e('Settings', 'wpcloudplugins'); ?></div>
      </div>


      <div id="" class="useyourdrive-panel useyourdrive-panel-left">      
        <div class="useyourdrive-nav-header"><?php _e('Settings', 'wpcloudplugins'); ?> <a href="<?php echo admin_url('update-core.php'); ?>">(Ver: <?php echo USEYOURDRIVE_VERSION; ?>)</a></div>

        <ul class="useyourdrive-nav-tabs">
          <li id="settings_general_tab" data-tab="settings_general" class="current"><a ><?php _e('General', 'wpcloudplugins'); ?></a></li>

          <?php
          if ($this->is_activated()) {
              ?>
              <li id="settings_layout_tab" data-tab="settings_layout" ><a ><?php _e('Layout', 'wpcloudplugins'); ?></a></li>
              <li id="settings_userfolders_tab" data-tab="settings_userfolders" ><a ><?php _e('Private Folders', 'wpcloudplugins'); ?></a></li>
              <li id="settings_advanced_tab" data-tab="settings_advanced" ><a ><?php _e('Advanced', 'wpcloudplugins'); ?></a></li>
              <li id="settings_integrations_tab" data-tab="settings_integrations" ><a><?php _e('Integrations', 'wpcloudplugins'); ?></a></li>
              <li id="settings_notifications_tab" data-tab="settings_notifications" ><a ><?php _e('Notifications', 'wpcloudplugins'); ?></a></li>
              <li id="settings_permissions_tab" data-tab="settings_permissions" ><a><?php _e('Permissions', 'wpcloudplugins'); ?></a></li>
              <li id="settings_stats_tab" data-tab="settings_stats" ><a><?php _e('Statistics', 'wpcloudplugins'); ?></a></li>
              <li id="settings_tools_tab" data-tab="settings_tools" ><a><?php _e('Tools', 'wpcloudplugins'); ?></a></li>
              <?php
          }
          ?>
          <li id="settings_system_tab" data-tab="settings_system" ><a><?php _e('System information', 'wpcloudplugins'); ?></a></li>
          <li id="settings_help_tab" data-tab="settings_help" ><a><?php _e('Support', 'wpcloudplugins'); ?></a></li>
        </ul>

        <div class="useyourdrive-nav-header" style="margin-top: 50px;"><?php _e('Other Cloud Plugins', 'wpcloudplugins'); ?></div>
        <ul class="useyourdrive-nav-tabs">
          <li id="settings_help_tab" data-tab="settings_help"><a href="https://1.envato.market/vLjyO" target="_blank" style="color:#522058;">Dropbox <i class="fas fa-external-link-square-alt" aria-hidden="true"></i></a></li>
          <li id="settings_help_tab" data-tab="settings_help"><a href="https://1.envato.market/yDbyv" target="_blank" style="color:#522058;">OneDrive <i class="fas fa-external-link-square-alt" aria-hidden="true"></i></a></li>
          <li id="settings_help_tab" data-tab="settings_help"><a href="https://1.envato.market/M4B53" target="_blank" style="color:#522058;">Box <i class="fas fa-external-link-square-alt" aria-hidden="true"></i></a></li>
        </ul> 

        <div class="useyourdrive-nav-footer">
          <a href="https://www.wpcloudplugins.com/" target="_blank">
            <img alt="" height="auto" src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/wpcloudplugins-logo-dark.png">
          </a>
        </div>
      </div>


      <div class="useyourdrive-panel useyourdrive-panel-right">

        <!-- General Tab -->
        <div id="settings_general" class="useyourdrive-tab-panel current">

          <div class="useyourdrive-tab-panel-header"><?php _e('General', 'wpcloudplugins'); ?></div>

          <?php if ($this->is_activated()) { ?>
              <div class="useyourdrive-option-title"><?php _e('Accounts', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-accounts-list">
                <?php
                if (false === $this->get_processor()->is_network_authorized() || ($this->get_processor()->is_network_authorized() && true === is_network_admin())) {
                    $app = $this->get_app();
                    //$app->get_client()->setPrompt('select_account');
                    $app->get_client()->setAccessType('offline');
                    $app->get_client()->setApprovalPrompt('force'); ?>
                    <div class='account account-new'>
                      <img class='account-image' src='<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/google_drive_logo.svg'/>
                      <div class='account-info-container'>
                        <div class='account-info'>
                          <div class='account-actions'>
                            <div id='add_drive_button' type='button' class='simple-button blue' data-url="<?php echo $app->get_auth_url(); ?>" title="<?php _e('Add account', 'wpcloudplugins'); ?>"><i class='fas fa-plus-circle' aria-hidden='true'></i>&nbsp;<?php _e('Add account', 'wpcloudplugins'); ?></div>
                          </div>
                          <div class="account-info-name">
                            <?php _e('Add account', 'wpcloudplugins'); ?>
                          </div>
                          <span class="account-info-space"><?php _e('Link a new account to the plugin', 'wpcloudplugins'); ?></span>
                        </div>
                      </div>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class='account account-new'>
                      <img class='account-image' src='<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/google_drive_logo.svg'/>
                      <div class='account-info-container'>
                        <div class='account-info'>
                          <span class="account-info-space"><?php echo sprintf(__("The authorization is managed by the Network Admin via the <a href='%s'>Network Settings Page</a> of the plugin", 'wpcloudplugins'), network_admin_url('admin.php?page=UseyourDrive_network_settings')); ?>.</span>
                        </div>
                      </div>
                    </div>   
                    <?php
                }

                foreach ($this->get_main()->get_accounts()->list_accounts() as $account_id => $account) {
                    echo $this->get_plugin_authorization_box($account);
                }
                ?>
              </div>
              <?php
          }
          ?>
          <div class="useyourdrive-option-title"><?php _e('Plugin License', 'wpcloudplugins'); ?></div>
          <?php
          echo $this->get_plugin_activated_box();
          ?>
        </div>
        <!-- End General Tab -->


        <!-- Layout Tab -->
        <div id="settings_layout"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Layout', 'wpcloudplugins'); ?></div>

          <div class="useyourdrive-accordion">

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Loading Spinner & Images', 'wpcloudplugins'); ?>         </div>
            <div>

              <div class="useyourdrive-option-title"><?php _e('Select Loader Spinner', 'wpcloudplugins'); ?></div>
              <select type="text" name="use_your_drive_settings[loaders][style]" id="loader_style">
                <option value="beat" <?php echo 'beat' === $this->settings['loaders']['style'] ? "selected='selected'" : ''; ?>><?php _e('Beat', 'wpcloudplugins'); ?></option>
                <option value="spinner" <?php echo 'spinner' === $this->settings['loaders']['style'] ? "selected='selected'" : ''; ?>><?php _e('Spinner', 'wpcloudplugins'); ?></option>
                <option value="custom" <?php echo 'custom' === $this->settings['loaders']['style'] ? "selected='selected'" : ''; ?>><?php _e('Custom Image (selected below)', 'wpcloudplugins'); ?></option>
              </select>

              <div class="useyourdrive-option-title"><?php _e('General Loader', 'wpcloudplugins'); ?></div>
              <?php
              $button = ['value' => $this->settings['loaders']['loading'], 'id' => 'loaders_loading', 'name' => 'use_your_drive_settings[loaders][loading]', 'default' => USEYOURDRIVE_ROOTPATH.'/css/images/loader_loading.gif'];
              echo create_upload_button_for_custom_images($button);
              ?>
              <div class="useyourdrive-option-title"><?php _e('Upload Loader', 'wpcloudplugins'); ?></div>
              <?php
              $button = ['value' => $this->settings['loaders']['upload'], 'id' => 'loaders_upload', 'name' => 'use_your_drive_settings[loaders][upload]', 'default' => USEYOURDRIVE_ROOTPATH.'/css/images/loader_upload.gif'];
              echo create_upload_button_for_custom_images($button);
              ?>
              <div class="useyourdrive-option-title"><?php _e('No Results', 'wpcloudplugins'); ?></div>
              <?php
              $button = ['value' => $this->settings['loaders']['no_results'], 'id' => 'loaders_no_results', 'name' => 'use_your_drive_settings[loaders][no_results]', 'default' => USEYOURDRIVE_ROOTPATH.'/css/images/loader_no_results.png'];
              echo create_upload_button_for_custom_images($button);
              ?>
              <div class="useyourdrive-option-title"><?php _e('Access Forbidden', 'wpcloudplugins'); ?></div>
              <?php
              $button = ['value' => $this->settings['loaders']['protected'], 'id' => 'loaders_protected', 'name' => 'use_your_drive_settings[loaders][protected]', 'default' => USEYOURDRIVE_ROOTPATH.'/css/images/loader_protected.png'];
              echo create_upload_button_for_custom_images($button);
              ?>
              <div class="useyourdrive-option-title"><?php _e('Error', 'wpcloudplugins'); ?></div>
              <?php
              $button = ['value' => $this->settings['loaders']['error'], 'id' => 'loaders_error', 'name' => 'use_your_drive_settings[loaders][error]', 'default' => USEYOURDRIVE_ROOTPATH.'/css/images/loader_error.png'];
              echo create_upload_button_for_custom_images($button);
              ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Color Palette', 'wpcloudplugins'); ?></div>
            <div>

              <div class="useyourdrive-option-title"><?php _e('Theme Style', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php _e('Select the general style of your theme', 'wpcloudplugins'); ?>.</div>
              <select name="skin_selectbox" id="content_skin_selectbox" class="ddslickbox">
                <option value="dark" <?php echo 'dark' === $this->settings['colors']['style'] ? "selected='selected'" : ''; ?> data-imagesrc="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/skin-dark.png" data-description=""><?php _e('Dark', 'wpcloudplugins'); ?></option>
                <option value="light" <?php echo 'light' === $this->settings['colors']['style'] ? "selected='selected'" : ''; ?> data-imagesrc="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/skin-light.png" data-description=""><?php _e('Light', 'wpcloudplugins'); ?></option>
              </select>
              <input type="hidden" name="use_your_drive_settings[colors][style]" id="content_skin" value="<?php echo esc_attr($this->settings['colors']['style']); ?>">

              <?php
              $colors = [
                  'background' => [
                      'label' => __('Content Background Color', 'wpcloudplugins'),
                      'default' => '#f2f2f2',
                  ],
                  'accent' => [
                      'label' => __('Accent Color', 'wpcloudplugins'),
                      'default' => '#522058',
                  ],
                  'black' => [
                      'label' => __('Black', 'wpcloudplugins'),
                      'default' => '#222',
                  ],
                  'dark1' => [
                      'label' => __('Dark 1', 'wpcloudplugins'),
                      'default' => '#666666',
                  ],
                  'dark2' => [
                      'label' => __('Dark 2', 'wpcloudplugins'),
                      'default' => '#999999',
                  ],
                  'white' => [
                      'label' => __('White', 'wpcloudplugins'),
                      'default' => '#fff',
                  ],
                  'light1' => [
                      'label' => __('Light 1', 'wpcloudplugins'),
                      'default' => '#fcfcfc',
                  ],
                  'light2' => [
                      'label' => __('Light 2', 'wpcloudplugins'),
                      'default' => '#e8e8e8',
                  ],
              ];

              echo create_color_boxes_table($colors, $this->settings);
              ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Icons', 'wpcloudplugins'); ?></div>
            <div>

              <div class="useyourdrive-option-title"><?php _e('Icon Set', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php _e(sprintf('Location to the icon set you want to use. When you want to use your own set, just make a copy of the default icon set folder (<code>%s</code>) and place it in the <code>wp-content/</code> folder', USEYOURDRIVE_ROOTPATH.'/css/icons/'), 'wpcloudplugins'); ?>.</div>

              <div class="uyd-warning">
                <i><strong><?php _e('NOTICE', 'wpcloudplugins'); ?></strong>: <?php _e('Modifications to the default icons set will be lost during an update.', 'wpcloudplugins'); ?>.</i>
              </div>

              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[icon_set]" id="icon_set" value="<?php echo esc_attr($this->settings['icon_set']); ?>">  
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Lightbox', 'wpcloudplugins'); ?></div>
            <div>
              <div class="useyourdrive-option-title"><?php _e('Lightbox Skin', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php _e('Select which skin you want to use for the Inline Preview', 'wpcloudplugins'); ?>.</div>
              <select name="lightbox_skin_selectbox" id="lightbox_skin_selectbox" class="ddslickbox">
                <?php
                foreach (new DirectoryIterator(USEYOURDRIVE_ROOTDIR.'/includes/iLightBox/') as $fileInfo) {
                    if ($fileInfo->isDir() && !$fileInfo->isDot() && (false !== strpos($fileInfo->getFilename(), 'skin'))) {
                        if (file_exists(USEYOURDRIVE_ROOTDIR.'/includes/iLightBox/'.$fileInfo->getFilename().'/skin.css')) {
                            $selected = '';
                            $skinname = str_replace('-skin', '', $fileInfo->getFilename());

                            if ($skinname === $this->settings['lightbox_skin']) {
                                $selected = 'selected="selected"';
                            }

                            $icon = file_exists(USEYOURDRIVE_ROOTDIR.'/includes/iLightBox/'.$fileInfo->getFilename().'/thumb.jpg') ? USEYOURDRIVE_ROOTPATH.'/includes/iLightBox/'.$fileInfo->getFilename().'/thumb.jpg' : '';
                            echo '<option value="'.$skinname.'" data-imagesrc="'.$icon.'" data-description="" '.$selected.'>'.$fileInfo->getFilename()."</option>\n";
                        }
                    }
                }
                ?>
              </select>
              <input type="hidden" name="use_your_drive_settings[lightbox_skin]" id="lightbox_skin" value="<?php echo esc_attr($this->settings['lightbox_skin']); ?>">


              <div class="useyourdrive-option-title">Lightbox Scroll</div>
              <div class="useyourdrive-option-description"><?php _e("Sets path for switching windows. Possible values are 'vertical' and 'horizontal' and the default is 'vertical", 'wpcloudplugins'); ?>.</div>
              <select type="text" name="use_your_drive_settings[lightbox_path]" id="lightbox_path">
                <option value="horizontal" <?php echo 'horizontal' === $this->settings['lightbox_path'] ? "selected='selected'" : ''; ?>><?php _e('Horizontal', 'wpcloudplugins'); ?></option>
                <option value="vertical" <?php echo 'vertical' === $this->settings['lightbox_path'] ? "selected='selected'" : ''; ?>><?php _e('Vertical', 'wpcloudplugins'); ?></option>
              </select>

              <div class="useyourdrive-option-title">Lightbox <?php _e('Image Source', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php _e('Select the source of the images. Large thumbnails load fast, orignal files will take some time to load', 'wpcloudplugins'); ?>.</div>
              <select type="text" name="use_your_drive_settings[loadimages]" id="loadimages">
                <option value="googlethumbnail" <?php echo 'googlethumbnail' === $this->settings['loadimages'] ? "selected='selected'" : ''; ?>><?php _e('Fast - Large preview thumbnails', 'wpcloudplugins'); ?></option>
                <option value="original" <?php echo 'original' === $this->settings['loadimages'] ? "selected='selected'" : ''; ?>><?php _e('Slow - Show original files', 'wpcloudplugins'); ?></option>
              </select>

              <div class="useyourdrive-option-title"><?php _e('Allow Mouse Click on Image', 'wpcloudplugins'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[lightbox_rightclick]'/>
                  <input type="checkbox" name="use_your_drive_settings[lightbox_rightclick]" id="lightbox_rightclick" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['lightbox_rightclick']) ? 'checked="checked"' : ''; ?>/>
                  <label class="useyourdrive-onoffswitch-label" for="lightbox_rightclick"></label>
                </div>
              </div>
              <div class="useyourdrive-option-description"><?php _e('Should people be able to access the right click context menu to e.g. save the image?', 'wpcloudplugins'); ?>.</div>

              <div class="useyourdrive-option-title"><?php _e('Lightbox Caption', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php _e('Choose when the caption containing the title and (if available) description are shown', 'wpcloudplugins'); ?>.</div>
              <select type="text" name="use_your_drive_settings[lightbox_showcaption]" id="lightbox_showcaption">
                <option value="click" <?php echo 'click' === $this->settings['lightbox_showcaption'] ? "selected='selected'" : ''; ?>><?php _e('Show caption after clicking on the Lightbox', 'wpcloudplugins'); ?></option>
                <option value="mouseenter" <?php echo 'mouseenter' === $this->settings['lightbox_showcaption'] ? "selected='selected'" : ''; ?>><?php _e('Show caption when Lightbox opens', 'wpcloudplugins'); ?></option>
              </select>              
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Media Player', 'wpcloudplugins'); ?></div>
            <div>
              <div class="useyourdrive-option-description"><?php _e('Select which Media Player you want to use', 'wpcloudplugins'); ?>.</div>
              <select name="mediaplayer_skin_selectbox" id="mediaplayer_skin_selectbox" class="ddslickbox">
                <?php
                foreach (new DirectoryIterator(USEYOURDRIVE_ROOTDIR.'/skins/') as $fileInfo) {
                    if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                        if (file_exists(USEYOURDRIVE_ROOTDIR.'/skins/'.$fileInfo->getFilename().'/js/Player.js')) {
                            $selected = '';
                            if ($fileInfo->getFilename() === $this->settings['mediaplayer_skin']) {
                                $selected = 'selected="selected"';
                            }

                            $icon = file_exists(USEYOURDRIVE_ROOTDIR.'/skins/'.$fileInfo->getFilename().'/Thumb.jpg') ? USEYOURDRIVE_ROOTPATH.'/skins/'.$fileInfo->getFilename().'/Thumb.jpg' : '';
                            echo '<option value="'.$fileInfo->getFilename().'" data-imagesrc="'.$icon.'" data-description="" '.$selected.'>'.$fileInfo->getFilename()."</option>\n";
                        }
                    }
                }
                ?>
              </select>
              <input type="hidden" name="use_your_drive_settings[mediaplayer_skin]" id="mediaplayer_skin" value="<?php echo esc_attr($this->settings['mediaplayer_skin']); ?>">
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Custom CSS', 'wpcloudplugins'); ?></div>
            <div>
              <div class="useyourdrive-option-description"><?php _e("If you want to modify the looks of the plugin slightly, you can insert here your custom CSS. Don't edit the CSS files itself, because those modifications will be lost during an update.", 'wpcloudplugins'); ?>.</div>
              <textarea name="use_your_drive_settings[custom_css]" id="custom_css" cols="" rows="10"><?php echo esc_attr($this->settings['custom_css']); ?></textarea> 
            </div>
          </div>

        </div>
        <!-- End Layout Tab -->

        <!-- UserFolders Tab -->
        <div id="settings_userfolders"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Private Folders', 'wpcloudplugins'); ?></div>

          <div class="useyourdrive-accordion">
            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Global settings Automatically linked Private Folders', 'wpcloudplugins'); ?> </div>
            <div>

              <div class="uyd-warning">
                <i><strong>NOTICE</strong>: <?php _e('The following settings are only used for all shortcodes with automatically linked Private Folders', 'wpcloudplugins'); ?>. </i>
              </div>

              <div class="useyourdrive-option-title"><?php _e('Create Private Folders on registration', 'wpcloudplugins'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[userfolder_oncreation]'/>
                  <input type="checkbox" name="use_your_drive_settings[userfolder_oncreation]" id="userfolder_oncreation" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['userfolder_oncreation']) ? 'checked="checked"' : ''; ?>/>
                  <label class="useyourdrive-onoffswitch-label" for="userfolder_oncreation"></label>
                </div>
              </div>
              <div class="useyourdrive-option-description"><?php _e('Create a new Private Folders automatically after a new user has been created', 'wpcloudplugins'); ?>.</div>

              <div class="useyourdrive-option-title"><?php _e('Create all Private Folders on first visit', 'wpcloudplugins'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[userfolder_onfirstvisit]'/>
                  <input type="checkbox" name="use_your_drive_settings[userfolder_onfirstvisit]" id="userfolder_onfirstvisit" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['userfolder_onfirstvisit']) ? 'checked="checked"' : ''; ?>/>
                  <label class="useyourdrive-onoffswitch-label" for="userfolder_onfirstvisit"></label>
                </div>
              </div>
              <div class="useyourdrive-option-description"><?php _e('Create all Private Folders the first time the page with the shortcode is visited', 'wpcloudplugins'); ?>.</div>
              <div class="uyd-warning">
                <i><strong><?php _e('NOTICE', 'wpcloudplugins'); ?></strong>: <?php _e("Creating User Folders takes around 1 sec per user, so it isn't recommended to create those on first visit when you have tons of users", 'wpcloudplugins'); ?>.</i>
              </div>


              <div class="useyourdrive-option-title"><?php _e('Update Private Folders after profile update', 'wpcloudplugins'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[userfolder_update]'/>
                  <input type="checkbox" name="use_your_drive_settings[userfolder_update]" id="userfolder_update" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['userfolder_update']) ? 'checked="checked"' : ''; ?>/>
                  <label class="useyourdrive-onoffswitch-label" for="userfolder_update"></label>
                </div>
              </div>
              <div class="useyourdrive-option-description"><?php _e('Update the folder name of the user after they have updated their profile', 'wpcloudplugins'); ?>.</div>

              <div class="useyourdrive-option-title"><?php _e('Remove Private Folders after account removal', 'wpcloudplugins'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[userfolder_remove]'/>
                  <input type="checkbox" name="use_your_drive_settings[userfolder_remove]" id="userfolder_remove" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['userfolder_remove']) ? 'checked="checked"' : ''; ?> />
                  <label class="useyourdrive-onoffswitch-label" for="userfolder_remove"></label>
                </div>
              </div>
              <div class="useyourdrive-option-description"><?php _e('Try to remove Private Folders after they are deleted', 'wpcloudplugins'); ?>.</div>

              <div class="useyourdrive-option-title"><?php _e('Name Template', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php echo __('Template name for automatically created Private Folders.', 'wpcloudplugins').' '.sprintf(__('Available placeholders: %s', 'wpcloudplugins'), '').'<code>%user_login%</code>, <code>%user_firstname%</code>, <code>%user_lastname%</code>, <code>%user_email%</code>, <code>%display_name%</code>, <code>%ID%</code>, <code>%user_role%</code>, <code>%jjjj-mm-dd%</code>'; ?>.</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[userfolder_name]" id="userfolder_name" value="<?php echo esc_attr($this->settings['userfolder_name']); ?>">  
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Global settings Manually linked Private Folders', 'wpcloudplugins'); ?> </div>
            <div>

              <div class="uyd-warning">
                <i><strong>NOTICE</strong>: <?php echo sprintf(__('You can manually link users to their Private Folder via the %s[Link Private Folders]%s menu page', 'wpcloudplugins'), '<a href="'.admin_url('admin.php?page=UseyourDrive_settings_linkusers').'" target="_blank">', '</a>'); ?>. </i>
              </div>

              <div class="useyourdrive-option-title"><?php _e('Access Forbidden notice', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php _e("Message that is displayed when an user is visiting a shortcode with the Private Folders feature set to 'Manual' mode while it doesn't have Private Folder linked to its account", 'wpcloudplugins'); ?>.</div>

              <?php
              ob_start();
              wp_editor($this->settings['userfolder_noaccess'], 'use_your_drive_settings_userfolder_noaccess', [
                  'textarea_name' => 'use_your_drive_settings[userfolder_noaccess]',
                  'teeny' => true,
                  'tinymce' => false,
                  'textarea_rows' => 15,
                  'media_buttons' => false,
              ]);
              echo ob_get_clean();
              ?>

            </div>
            <?php
            $main_account = $this->get_processor()->get_accounts()->get_primary_account();

            if (!empty($main_account)) {
                ?>
                <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Private Folders in WP Admin Dashboard', 'wpcloudplugins'); ?> </div>
                <div>

                  <div class="uyd-warning">
                    <i><strong>NOTICE</strong>: <?php _e('This setting only restrict access of the File Browsers in the Admin Dashboard (e.g. the ones in the Shortcode Builder and the File Browser menu). To enable Private Folders for your own Shortcodes, use the Shortcode Builder', 'wpcloudplugins'); ?>. </i>
                  </div>

                  <div class="useyourdrive-option-description"><?php _e('Enable Private Folders in the Shortcode Builder and Back-End File Browser', 'wpcloudplugins'); ?>.</div>
                  <select type="text" name="use_your_drive_settings[userfolder_backend]" id="userfolder_backend" data-div-toggle="private-folders-auto" data-div-toggle-value="auto">
                    <option value="No" <?php echo 'No' === $this->settings['userfolder_backend'] ? "selected='selected'" : ''; ?>>No</option>
                    <option value="manual" <?php echo 'manual' === $this->settings['userfolder_backend'] ? "selected='selected'" : ''; ?>><?php _e('Yes, I link the users Manually', 'wpcloudplugins'); ?></option>
                    <option value="auto" <?php echo 'auto' === $this->settings['userfolder_backend'] ? "selected='selected'" : ''; ?>><?php _e('Yes, let the plugin create the User Folders for me', 'wpcloudplugins'); ?></option>
                  </select>
                  <div class="useyourdrive-suboptions private-folders-auto <?php echo ('auto' === ($this->settings['userfolder_backend'])) ? '' : 'hidden'; ?> ">
                    <div class="useyourdrive-option-title"><?php _e('Root folder for Private Folders', 'wpcloudplugins'); ?></div>
                    <div class="useyourdrive-option-description"><?php _e('Select in which folder the Private Folders should be created', 'wpcloudplugins'); ?>. <?php _e('Current selected folder', 'wpcloudplugins'); ?>:</div>
                    <?php
                    $private_auto_folder = $this->settings['userfolder_backend_auto_root'];

                if (empty($private_auto_folder)) {
                    $this->get_processor()->set_current_account($main_account);

                    try {
                        $root = $this->get_processor()->get_client()->get_root_folder();
                    } catch (\Exception $ex) {
                        $root = false;
                    }

                    if (false === $root) {
                        $private_auto_folder = [
                            'account' => $main_account->get_id(),
                            'id' => '',
                            'name' => '',
                            'view_roles' => ['administrator'],
                        ];
                    } else {
                        $private_auto_folder = [
                            'account' => $main_account->get_id(),
                            'id' => $root->get_entry()->get_id(),
                            'name' => $root->get_entry()->get_name(),
                            'view_roles' => ['administrator'],
                        ];
                    }
                }

                if (!isset($private_auto_folder['account']) || empty($private_auto_folder['account'])) {
                    $private_auto_folder['account'] = $main_account->get_id();
                }

                $account = $this->get_processor()->get_accounts()->get_account_by_id($private_auto_folder['account']);
                if (null !== $account) {
                    $this->get_processor()->set_current_account($account);
                } ?>
                    <input class="useyourdrive-option-input-large private-folders-auto-current" type="text" value="<?php echo $private_auto_folder['name']; ?>" disabled="disabled">
                    <input class="private-folders-auto-input-account" type='hidden' value='<?php echo $private_auto_folder['account']; ?>' name='use_your_drive_settings[userfolder_backend_auto_root][account]'/>
                    <input class="private-folders-auto-input-id" type='hidden' value='<?php echo $private_auto_folder['id']; ?>' name='use_your_drive_settings[userfolder_backend_auto_root][id]'/>
                    <input class="private-folders-auto-input-name" type='hidden' value='<?php echo $private_auto_folder['name']; ?>' name='use_your_drive_settings[userfolder_backend_auto_root][name]'/>
                    <div id="root_folder_button" type="button" class="button-primary private-folders-auto-button"><?php _e('Select Folder', 'wpcloudplugins'); ?>&nbsp;<div class='uyd-spinner'></div></div>

                    <div id='uyd-embedded' style='clear:both;display:none'>
                      <?php
                      try {
                          echo $this->get_processor()->create_from_shortcode(
                              [
                                  'mode' => 'files',
                                  'singleaccount' => '0',
                                  'dir' => 'drive',
                                  'showfiles' => '1',
                                  'filesize' => '0',
                                  'filedate' => '0',
                                  'upload' => '0',
                                  'delete' => '0',
                                  'rename' => '0',
                                  'addfolder' => '0',
                                  'showbreadcrumb' => '1',
                                  'showfiles' => '0',
                                  'downloadrole' => 'none',
                                  'candownloadzip' => '0',
                                  'showsharelink' => '0',
                                  'mcepopup' => 'linktobackendglobal',
                                  'search' => '0',
                              ]
                          );
                      } catch (\Exception $ex) {
                      } ?>
                    </div>

                    <br/><br/>
                    <div class="useyourdrive-option-title"><?php _e('Full Access', 'wpcloudplugins'); ?></div>
                    <div class="useyourdrive-option-description"><?php _e('By default only Administrator users will be able to navigate through all Private Folders', 'wpcloudplugins'); ?>. <?php _e('When you want other User Roles to be able do browse to the Private Folders as well, please check them below', 'wpcloudplugins'); ?>.</div>

                    <?php
                    $selected = (isset($private_auto_folder['view_roles'])) ? $private_auto_folder['view_roles'] : [];
                wp_roles_and_users_input('use_your_drive_settings[userfolder_backend_auto_root][view_roles]', $selected); ?>
                  </div>
                </div>
                <?php
            }
            ?>
          </div>
        </div>
        <!-- End UserFolders Tab -->


        <!--  Advanced Tab -->
        <div id="settings_advanced"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Advanced', 'wpcloudplugins'); ?></div>

          <?php if (false === $network_wide_authorization) { ?>
              <div class="useyourdrive-option-title"><?php _e('"Lost Authorization" notification', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php _e('If the plugin somehow loses its authorization, a notification email will be send to the following email address', 'wpcloudplugins'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[lostauthorization_notification]" id="lostauthorization_notification" value="<?php echo esc_attr($this->settings['lostauthorization_notification']); ?>">  

              <div class="useyourdrive-option-title"><?php _e('Own App', 'wpcloudplugins'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[googledrive_app_own]'/>
                  <input type="checkbox" name="use_your_drive_settings[googledrive_app_own]" id="googledrive_app_own" class="useyourdrive-onoffswitch-checkbox" <?php echo (empty($this->settings['googledrive_app_client_id']) || empty($this->settings['googledrive_app_client_secret'])) ? '' : 'checked="checked"'; ?> data-div-toggle="own-app"/>
                  <label class="useyourdrive-onoffswitch-label" for="googledrive_app_own"></label>
                </div>
              </div>

              <div class="useyourdrive-suboptions own-app <?php echo (empty($this->settings['googledrive_app_client_id']) || empty($this->settings['googledrive_app_client_secret'])) ? 'hidden' : ''; ?> ">
                <div class="useyourdrive-option-description">
                  <strong>Using your own Google App is <u>optional</u></strong>. For an easy setup you can just use the default App of the plugin itself by leaving the ID and Secret empty. The advantage of using your own app is limited. If you decided to create your own Google App anyway, please enter your settings. In the <a href="https://florisdeleeuwnl.zendesk.com/hc/en-us/articles/201804806--How-do-I-create-my-own-Google-Drive-App-" target="_blank">documentation</a> you can find how you can create a Google App.
                  <br/><br/>
                  <div class="uyd-warning">
                    <i><strong><?php _e('NOTICE', 'wpcloudplugins'); ?></strong>: <?php _e('If you encounter any issues when trying to use your own App, please fall back on the default App by disabling this setting', 'wpcloudplugins'); ?>.</i>
                  </div>
                </div>

                <div class="useyourdrive-option-title">Google Client ID</div>
                <div class="useyourdrive-option-description"><?php _e('<strong>Only</strong> if you want to use your own App, insert your Client ID here', 'wpcloudplugins'); ?>.</div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[googledrive_app_client_id]" id="googledrive_app_client_id" value="<?php echo esc_attr($this->settings['googledrive_app_client_id']); ?>" placeholder="<--- <?php _e('Leave empty for easy setup', 'wpcloudplugins'); ?> --->" >

                <div class="useyourdrive-option-title">Google Client Secret</div>
                <div class="useyourdrive-option-description"><?php _e('If you want to use your own App, insert your Client Secret here', 'wpcloudplugins'); ?>.</div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[googledrive_app_client_secret]" id="googledrive_app_client_secret" value="<?php echo esc_attr($this->settings['googledrive_app_client_secret']); ?>" placeholder="<--- <?php _e('Leave empty for easy setup', 'wpcloudplugins'); ?> --->" >   

                <div>
                  <div class="useyourdrive-option-title">OAuth 2.0 Redirect URI</div>
                  <div class="useyourdrive-option-description"><?php _e('Set the redirect URI in your application to the following', 'wpcloudplugins'); ?>:</div>
                  <code style="user-select:initial">
                    <?php
                    if ($this->get_app()->has_plugin_own_app()) {
                        echo $this->get_app()->get_redirect_uri();
                    } else {
                        _e('Enter Client ID and Secret, save settings and reload the page to see the Redirect URI you will need', 'wpcloudplugins');
                    }
                    ?>
                  </code>
                </div>
              </div>

              <?php
              $using_gsuite = (!empty($this->settings['permission_domain']) || 'Yes' === $this->settings['teamdrives']);
              ?>

              <div class="useyourdrive-option-title"><?php _e('Using Google Workspace?', 'wpcloudplugins'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[gsuite]'/>
                  <input type="checkbox" name="use_your_drive_settings[gsuite]" id="gsuite" class="useyourdrive-onoffswitch-checkbox" <?php echo ($using_gsuite) ? 'checked="checked"' : ''; ?> data-div-toggle="gsuite"/>
                  <label class="useyourdrive-onoffswitch-label" for="gsuite"></label>
                </div>
              </div>

              <div class="useyourdrive-suboptions gsuite <?php echo ($using_gsuite) ? '' : 'hidden'; ?> ">
                <div class="useyourdrive-option-title"><?php _e('Your Google Workspace Domain', 'wpcloudplugins'); ?></div>
                <div class="useyourdrive-option-description"><?php _e('If you have a Google Workspace Domain and you want to share your documents ONLY with users having an account in your Google Workspace Domain, please insert your domain. If you want your documents to be accessible to the public, leave this setting empty.', 'wpcloudplugins'); ?>.</div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[permission_domain]" id="permission_domain" value="<?php echo esc_attr($this->settings['permission_domain']); ?>">   

                <div class="useyourdrive-option-title"><?php _e('Enable Shared Drives', 'wpcloudplugins'); ?>
                  <div class="useyourdrive-onoffswitch">
                    <input type='hidden' value='No' name='use_your_drive_settings[teamdrives]'/>
                    <input type="checkbox" name="use_your_drive_settings[teamdrives]" id="teamdrives" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['teamdrives']) ? 'checked="checked"' : ''; ?> />
                    <label class="useyourdrive-onoffswitch-label" for="teamdrives"></label>
                  </div>
                </div>
              </div>

          <?php } ?>

          <div class="useyourdrive-option-title"><?php _e('Manage Permission', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[manage_permissions]'/>
              <input type="checkbox" name="use_your_drive_settings[manage_permissions]" id="manage_permissions" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['manage_permissions']) ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="manage_permissions"></label>
            </div>
            <div class="useyourdrive-option-description"><?php _e('If you want to manage the sharing permissions by manually yourself, disable the -Manage Permissions- function.', 'wpcloudplugins'); ?>.</div>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Load Javascripts on all pages', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[always_load_scripts]'/>
              <input type="checkbox" name="use_your_drive_settings[always_load_scripts]" id="always_load_scripts" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['always_load_scripts']) ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="always_load_scripts"></label>
            </div>
            <div class="useyourdrive-option-description"><?php _e('By default the plugin will only load it scripts when the shortcode is present on the page. If you are dynamically loading content via AJAX calls and the plugin does not show up, please enable this setting', 'wpcloudplugins'); ?>.</div>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Enable Font Awesome Library v4 compatibility', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[fontawesomev4_shim]'/>
              <input type="checkbox" name="use_your_drive_settings[fontawesomev4_shim]" id="fontawesomev4_shim" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['fontawesomev4_shim']) ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="fontawesomev4_shim"></label>
            </div>
            <div class="useyourdrive-option-description"><?php _e('If your theme is loading the old Font Awesome icon library (v4), it can cause conflict with the (v5) icons of this plugin. If you are having trouble with the icons, please enable this setting for backwards compatibility', 'wpcloudplugins'); ?>. <?php _e('To disable the Font Awesome library of this plugin completely, add this to your wp-config.php file', 'wpcloudplugins'); ?>: <code>define('WPCP_DISABLE_FONTAWESOME', true);</code></div>
          </div>            

          <div class="useyourdrive-option-title"><?php _e('Enable Gzip compression', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[gzipcompression]'/>
              <input type="checkbox" name="use_your_drive_settings[gzipcompression]" id="gzipcompression" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['gzipcompression']) ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="gzipcompression"></label>
            </div>
          </div>

          <div class="useyourdrive-option-description"><?php _e("Enables gzip-compression if the visitor's browser can handle it. This will increase the performance of the plugin if you are displaying large amounts of files and it reduces bandwidth usage as well. It uses the PHP <code>ob_gzhandler()</code> callback. Please use this setting with caution. Always test if the plugin still works on the Front-End as some servers are already configured to gzip content!", 'wpcloudplugins'); ?></div>

          <div class="option"  style="display:none">
            <select type="text" name="use_your_drive_settings[cache]" id="cache">
              <option value="filesystem" <?php echo 'filesystem' === $this->settings['cache'] ? "selected='selected'" : ''; ?>><?php _e('File Based Cache', 'wpcloudplugins'); ?></option>
              <option value="database" <?php echo 'database' === $this->settings['cache'] ? "selected='selected'" : ''; ?>><?php _e('Database Based Cache', 'wpcloudplugins'); ?></option>
            </select>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Nonce Validation', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[nonce_validation]'/>
              <input type="checkbox" name="use_your_drive_settings[nonce_validation]" id="nonce_validation" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['nonce_validation']) ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="nonce_validation"></label>
            </div></div>
          <div class="useyourdrive-option-description"><?php _e('The plugin uses, among others, the WordPress Nonce system to protect you against several types of attacks including CSRF. Disable this in case you are encountering a conflict with a plugin that alters this system', 'wpcloudplugins'); ?>. </div>
          <div class="uyd-warning">
            <i><strong>NOTICE</strong>: Please use this setting with caution! Only disable it when really necessary.</i>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Synchronize via WP-Cron', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[cache_update_via_wpcron]'/>
              <input type="checkbox" name="use_your_drive_settings[cache_update_via_wpcron]" id="cache_update_via_wpcron" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['cache_update_via_wpcron']) ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="cache_update_via_wpcron"></label>
            </div>
            </div>
          <div class="useyourdrive-option-description"><?php _e('Use WP-Cron to synchronize the cache of the plugin with the linked cloud account. If you are using tens of shortcodes and encounter performance issues, try to disable this setting', 'wpcloudplugins'); ?>. </div>
          <div class="uyd-updated">
            <i><strong>TIP</strong>: <?php echo __('As WP-Cron does not run continuously, you can increase the loading performance by creating a Cron job via your server configuration panel!', 'wpcloudplugins'); ?> <a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/" target="_blank"><?php echo __('More information'); ?></a></i>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Download method', 'wpcloudplugins'); ?></div>
          <div class="useyourdrive-option-description"><?php _e('Select the method that should be used to download your files. Default is to redirect the user to a temporarily url. If you want to use your server as a proxy just set it to Download via Server', 'wpcloudplugins'); ?>.</div>
          <select type="text" name="use_your_drive_settings[download_method]" id="download_method">
            <option value="redirect" <?php echo 'redirect' === $this->settings['download_method'] ? "selected='selected'" : ''; ?>><?php _e('Redirect to download url (fast)', 'wpcloudplugins'); ?></option>
            <option value="proxy" <?php echo 'proxy' === $this->settings['download_method'] ? "selected='selected'" : ''; ?>><?php _e('Use your Server as proxy (slow)', 'wpcloudplugins'); ?></option>
          </select>   

          <div class="useyourdrive-option-title"><?php _e('Delete settings on Uninstall', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[uninstall_reset]'/>
              <input type="checkbox" name="use_your_drive_settings[uninstall_reset]" id="uninstall_reset" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['uninstall_reset']) ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="uninstall_reset"></label>
            </div>
            </div>
          <div class="useyourdrive-option-description"><?php _e('When you uninstall the plugin, what do you want to do with your settings? You can save them for next time, or wipe them back to factory settings.', 'wpcloudplugins'); ?>. </div>
          <div class="uyd-warning">
            <i><strong>NOTICE</strong>: <?php echo __('When you reset the settings, the plugin will not longer be linked to your accounts, but their authorization will not be revoked', 'wpcloudplugins').'. '.__('You can revoke the authorization via the General tab', 'wpcloudplugins').'.'; ?></a></i>
          </div>

        </div>
        <!-- End Advanced Tab -->

        <!-- Integrations Tab -->
        <div id="settings_integrations"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Integrations', 'wpcloudplugins'); ?></div>

          <div class="useyourdrive-accordion">
            <div class="useyourdrive-accordion-title useyourdrive-option-title">Social Sharing Buttons</div>
            <div>
              <div class="useyourdrive-option-description"><?php _e('Select which sharing buttons should be accessible via the sharing dialogs of the plugin.', 'wpcloudplugins'); ?></div>

              <div class="shareon shareon-settings">
                <?php foreach ($this->settings['share_buttons'] as $button => $value) {
                    $title = ucfirst($button);
                    echo "<button type='button' class='shareon-setting-button {$button} shareon-{$value} ' title='{$title}'></button>";
                    echo "<input type='hidden' value='{$value}' name='use_your_drive_settings[share_buttons][{$button}]'/>";
                }
                ?>
              </div>
            </div>
          </div>


          <div class="useyourdrive-accordion">
            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Shortlinks API', 'wpcloudplugins'); ?></div>

            <div>
              <div class="useyourdrive-option-description"><?php _e('Select which Url Shortener Service you want to use', 'wpcloudplugins'); ?>.</div>
              <select type="text" name="use_your_drive_settings[shortlinks]" id="shortlinks">
                <option value="None"  <?php echo 'None' === $this->settings['shortlinks'] ? "selected='selected'" : ''; ?>>None</option>
                <!-- <option value="Firebase"  <?php echo 'Firebase' === $this->settings['shortlinks'] ? "selected='selected'" : ''; ?>>Google Firebase Dynamic Links</option> -->
                <option value="Shorte.st"  <?php echo 'Shorte.st' === $this->settings['shortlinks'] ? "selected='selected'" : ''; ?>>Shorte.st</option>
                <option value="Rebrandly"  <?php echo 'Rebrandly' === $this->settings['shortlinks'] ? "selected='selected'" : ''; ?>>Rebrandly</option>
                <option value="Bit.ly"  <?php echo 'Bit.ly' === $this->settings['shortlinks'] ? "selected='selected'" : ''; ?>>Bit.ly</option>
              </select>   

              <div class="useyourdrive-suboptions option shortest" <?php echo 'Shorte.st' !== $this->settings['shortlinks'] ? "style='display:none;'" : ''; ?>>
                <div class="useyourdrive-option-description"><?php _e('Sign up for Shorte.st', 'wpcloudplugins'); ?> and <a href="https://shorte<?php echo '.st/tools/api'; ?>" target="_blank">grab your API token</a></div>

                <div class="useyourdrive-option-title"><?php _e('API token', 'wpcloudplugins'); ?></div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[shortest_apikey]" id="shortest_apikey" value="<?php echo esc_attr($this->settings['shortest_apikey']); ?>">
              </div>

              <div class="useyourdrive-suboptions option bitly" <?php echo 'Bit.ly' !== $this->settings['shortlinks'] ? "style='display:none;'" : ''; ?>>
                <div class="useyourdrive-option-description"><a href="https://bitly.com/a/sign_up" target="_blank"><?php _e('Sign up for Bitly', 'wpcloudplugins'); ?></a> and <a href="http://bitly.com/a/your_api_key" target="_blank">generate an API key</a></div>

                <div class="useyourdrive-option-title">Bitly Login</div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[bitly_login]" id="bitly_login" value="<?php echo esc_attr($this->settings['bitly_login']); ?>">

                <div class="useyourdrive-option-title">Bitly API key</div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[bitly_apikey]" id="bitly_apikey" value="<?php echo esc_attr($this->settings['bitly_apikey']); ?>">
              </div> 

              <div class="useyourdrive-suboptions option rebrandly" <?php echo 'Rebrandly' !== $this->settings['shortlinks'] ? "style='display:none;'" : ''; ?>>
                <div class="useyourdrive-option-description"><a href="https://app.rebrandly.com/" target="_blank"><?php _e('Sign up for Rebrandly', 'wpcloudplugins'); ?></a> and <a href="https://app.rebrandly.com/account/api-keys" target="_blank">grab your API token</a></div>

                <div class="useyourdrive-option-title">Rebrandly API key</div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[rebrandly_apikey]" id="rebrandly_apikey" value="<?php echo esc_attr($this->settings['rebrandly_apikey']); ?>">

                <div class="useyourdrive-option-title">Rebrandly Domain (optional)</div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[rebrandly_domain]" id="rebrandly_domain" value="<?php echo esc_attr($this->settings['rebrandly_domain']); ?>">

                <div class="useyourdrive-option-title">Rebrandly WorkSpace ID (optional)</div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[rebrandly_workspace]" id="rebrandly_workspace" value="<?php echo esc_attr($this->settings['rebrandly_workspace']); ?>">
              </div>
            </div>
          </div> 
          
          <div class="useyourdrive-accordion">

            <div class="useyourdrive-accordion-title useyourdrive-option-title">ReCaptcha V3         </div>
            <div>

              <div class="useyourdrive-option-description"><?php _e('reCAPTCHA protects you against spam and other types of automated abuse. With this reCAPTCHA (V3) integration module, you can block abusive downloads of your files by bots. Create your own credentials via the link below.', 'wpcloudplugins'); ?> <br/><br/><a href="https://www.google.com/recaptcha/admin" target="_blank">Manage your reCAPTCHA API keys</a></div>

              <div class="useyourdrive-option-title"><?php _e('Site Key', 'wpcloudplugins'); ?></div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[recaptcha_sitekey]" id="recaptcha_sitekey" value="<?php echo esc_attr($this->settings['recaptcha_sitekey']); ?>">

              <div class="useyourdrive-option-title"><?php _e('Secret Key', 'wpcloudplugins'); ?></div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[recaptcha_secret]" id="recaptcha_secret" value="<?php echo esc_attr($this->settings['recaptcha_secret']); ?>">
            </div>
          </div>

          <div class="useyourdrive-accordion">

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Video Advertisements (IMA/VAST)', 'wpcloudplugins'); ?> </div>
            <div>
              <div class="useyourdrive-option-description"><?php _e('The mediaplayer of the plugin supports VAST XML advertisments to offer monetization options for your videos. You can enable advertisments for the complete site and per Media Player shortcode. Currently, this plugin only supports Linear elements with MP4', 'wpcloudplugins'); ?>.</div>

              <div class="useyourdrive-option-title"><?php echo 'VAST XML Tag Url'; ?></div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[mediaplayer_ads_tagurl]" id="mediaplayer_ads_tagurl" value="<?php echo esc_attr($this->settings['mediaplayer_ads_tagurl']); ?>" placeholder="<?php echo __('Leave empty to disable Ads', 'wpcloudplugins'); ?>" />

              <div class="uyd-warning">
                <i><strong><?php _e('NOTICE', 'wpcloudplugins'); ?></strong>: <?php _e('If you are unable to see the example VAST url below, please make sure you do not have an ad blocker enabled.', 'wpcloudplugins'); ?>.</i>
              </div>

              <a href="https://pubads.g.doubleclick.net/gampad/ads?sz=640x480&iu=/124319096/external/single_ad_samples&ciu_szs=300x250&impl=s&gdfp_req=1&env=vp&output=vast&unviewed_position_start=1&cust_params=deployment%3Ddevsite%26sample_ct%3Dskippablelinear&correlator=" rel="no-follow">Example Tag URL</a>

              <div class="useyourdrive-option-title"><?php _e('Enable Skip Button', 'wpcloudplugins'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[mediaplayer_ads_skipable]'/>
                  <input type="checkbox" name="use_your_drive_settings[mediaplayer_ads_skipable]" id="mediaplayer_ads_skipable" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['mediaplayer_ads_skipable']) ? 'checked="checked"' : ''; ?> data-div-toggle="ads_skipable"/>
                  <label class="useyourdrive-onoffswitch-label" for="mediaplayer_ads_skipable"></label>
                </div>
              </div>

              <div class="useyourdrive-suboptions ads_skipable <?php echo ('Yes' === $this->settings['mediaplayer_ads_skipable']) ? '' : 'hidden'; ?> ">
                <div class="useyourdrive-option-title"><?php _e('Skip button visible after (seconds)', 'wpcloudplugins'); ?></div>
                <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[mediaplayer_ads_skipable_after]" id="mediaplayer_ads_skipable_after" value="<?php echo esc_attr($this->settings['mediaplayer_ads_skipable_after']); ?>" placeholder="5">
                <div class="useyourdrive-option-description"><?php _e('Allow user to skip advertisment after after the following amount of seconds have elapsed', 'wpcloudplugins'); ?></div>
              </div>
            </div>
          </div>
        </div>  
        <!-- End Integrations info -->

        <!-- Notifications Tab -->
        <div id="settings_notifications"  class="useyourdrive-tab-panel">

          <div class="useyourdrive-tab-panel-header"><?php _e('Notifications', 'wpcloudplugins'); ?></div>

          <div class="useyourdrive-accordion">

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Download Notifications', 'wpcloudplugins'); ?>         </div>
            <div>

              <div class="useyourdrive-option-title"><?php _e('Subject download notification', 'wpcloudplugins'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[download_template_subject]" id="download_template_subject" value="<?php echo esc_attr($this->settings['download_template_subject']); ?>">

              <div class="useyourdrive-option-title"><?php _e('Subject zip notification', 'wpcloudplugins'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[download_template_subject_zip]" id="download_template_subject_zip" value="<?php echo esc_attr($this->settings['download_template_subject_zip']); ?>">

              <div class="useyourdrive-option-title"><?php _e('Template download', 'wpcloudplugins'); ?> (HTML):</div>
              <?php
              ob_start();
              wp_editor($this->settings['download_template'], 'use_your_drive_settings_download_template', [
                  'textarea_name' => 'use_your_drive_settings[download_template]',
                  'teeny' => true,
                  'tinymce' => false,
                  'textarea_rows' => 15,
                  'media_buttons' => false,
              ]);
              echo ob_get_clean();
              ?>

              <br/>


              <div class="useyourdrive-option-description"><?php echo sprintf(__('Available placeholders: %s', 'wpcloudplugins'), ''); ?>
                <code>%site_name%</code>, 
                <code>%number_of_files%</code>, 
                <code>%user_name%</code>, 
                <code>%user_email%</code>, 
                <code>%admin_email%</code>, 
                <code>%file_name%</code>, 
                <code>%file_size%</code>, 
                <code>%file_icon%</code>, 
                <code>%file_relative_path%</code>, 
                <code>%file_absolute_path%</code>,
                <code>%file_cloud_shortlived_download_url%</code>, 
                <code>%file_cloud_preview_url%</code>, 
                <code>%file_cloud_shared_url%</code>, 
                <code>%file_download_url%</code>,
                <code>%folder_name%</code>,
                <code>%folder_relative_path%</code>,
                <code>%folder_absolute_path%</code>,
                <code>%folder_url%</code>,
                <code>%ip%</code>, 
                <code>%location%</code>, 
              </div>
            </div>


            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Upload Notifications', 'wpcloudplugins'); ?></div>
            <div>
              <div class="useyourdrive-option-title"><?php _e('Subject upload notification', 'wpcloudplugins'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[upload_template_subject]" id="upload_template_subject" value="<?php echo esc_attr($this->settings['upload_template_subject']); ?>">

              <div class="useyourdrive-option-title"><?php _e('Template upload', 'wpcloudplugins'); ?> (HTML):</div>
              <?php
              ob_start();
              wp_editor($this->settings['upload_template'], 'use_your_drive_settings_upload_template', [
                  'textarea_name' => 'use_your_drive_settings[upload_template]',
                  'teeny' => true,
                  'tinymce' => false,
                  'textarea_rows' => 15,
                  'media_buttons' => false,
              ]);
              echo ob_get_clean();
              ?>

              <br/>

              <div class="useyourdrive-option-description"><?php echo sprintf(__('Available placeholders: %s', 'wpcloudplugins'), ''); ?>
                <code>%site_name%</code>, 
                <code>%number_of_files%</code>, 
                <code>%user_name%</code>, 
                <code>%user_email%</code>, 
                <code>%admin_email%</code>, 
                <code>%file_name%</code>, 
                <code>%file_size%</code>, 
                <code>%file_icon%</code>, 
                <code>%file_relative_path%</code>,
                <code>%file_absolute_path%</code>,
                <code>%file_cloud_shortlived_download_url%</code>, 
                <code>%file_cloud_preview_url%</code>, 
                <code>%file_cloud_shared_url%</code>, 
                <code>%file_download_url%</code>, 
                <code>%folder_name%</code>,
                <code>%folder_relative_path%</code>,
                <code>%folder_absolute_path%</code>,
                <code>%folder_url%</code>,
                <code>%ip%</code>, 
                <code>%location%</code>, 
              </div>            
            </div>


            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Delete Notifications', 'wpcloudplugins'); ?>         </div>
            <div>
              <div class="useyourdrive-option-title"><?php _e('Subject deletion notification', 'wpcloudplugins'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[delete_template_subject]" id="delete_template_subject" value="<?php echo esc_attr($this->settings['delete_template_subject']); ?>">
              <div class="useyourdrive-option-title"><?php _e('Template deletion', 'wpcloudplugins'); ?> (HTML):</div>

              <?php
              ob_start();
              wp_editor($this->settings['delete_template'], 'use_your_drive_settings_delete_template', [
                  'textarea_name' => 'use_your_drive_settings[delete_template]',
                  'teeny' => true,
                  'tinymce' => false,
                  'textarea_rows' => 15,
                  'media_buttons' => false,
              ]);
              echo ob_get_clean();
              ?>

              <br/>

              <div class="useyourdrive-option-description"><?php echo sprintf(__('Available placeholders: %s', 'wpcloudplugins'), ''); ?>
                <code>%site_name%</code>, 
                <code>%number_of_files%</code>, 
                <code>%user_name%</code>, 
                <code>%user_email%</code>, 
                <code>%admin_email%</code>, 
                <code>%file_name%</code>, 
                <code>%file_size%</code>, 
                <code>%file_icon%</code>, 
                <code>%file_relative_path%</code>,
                <code>%file_absolute_path%</code>,
                <code>%file_cloud_shortlived_download_url%</code>, 
                <code>%file_cloud_preview_url%</code>, 
                <code>%file_cloud_shared_url%</code>, 
                <code>%file_download_url%</code>,
                <code>%folder_name%</code>,
                <code>%folder_relative_path%</code>,
                <code>%folder_absolute_path%</code>,
                <code>%folder_url%</code>,
                <code>%ip%</code>, 
                <code>%location%</code>, 
              </div>

            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Template %filelist% placeholder', 'wpcloudplugins'); ?>         </div>
            <div>
              <div class="useyourdrive-option-description"><?php _e('Template for File item in File List in the download/upload/delete notification template', 'wpcloudplugins'); ?> (HTML).</div>
              <?php
              ob_start();
              wp_editor($this->settings['filelist_template'], 'use_your_drive_settings_filelist_template', [
                  'textarea_name' => 'use_your_drive_settings[filelist_template]',
                  'teeny' => true,
                  'tinymce' => false,
                  'textarea_rows' => 15,
                  'media_buttons' => false,
              ]);
              echo ob_get_clean();
              ?>

              <br/>

              <div class="useyourdrive-option-description"><?php echo sprintf(__('Available placeholders: %s', 'wpcloudplugins'), ''); ?>
                <code>%file_name%</code>, 
                <code>%file_size%</code>, 
                <code>%file_icon%</code>, 
                <code>%file_cloud_shortlived_download_url%</code>, 
                <code>%file_cloud_preview_url%</code>, 
                <code>%file_cloud_shared_url%</code>, 
                <code>%file_download_url%</code>,
                <code>%file_relative_path%</code>, 
                <code>%file_absolute_path%</code>, 
                <code>%folder_relative_path%</code>,
                <code>%folder_absolute_path%</code>,
                <code>%folder_url%</code>,
              </div>

            </div>
          </div>

          <div id="reset_notifications" type="button" class="simple-button blue"><?php _e('Reset to default notifications', 'wpcloudplugins'); ?>&nbsp;<div class="uyd-spinner"></div></div>

        </div>
        <!-- End Notifications Tab -->

        <!--  Permissions Tab -->
        <div id="settings_permissions"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Permissions', 'wpcloudplugins'); ?></div>

          <div class="useyourdrive-accordion">

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Change Plugin Settings', 'wpcloudplugins'); ?> </div>
            <div>
              <?php wp_roles_and_users_input('use_your_drive_settings[permissions_edit_settings]', $this->settings['permissions_edit_settings']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Link Users to Private Folders', 'wpcloudplugins'); ?></div>
            <div>
              <?php wp_roles_and_users_input('use_your_drive_settings[permissions_link_users]', $this->settings['permissions_link_users']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('See Reports', 'wpcloudplugins'); ?></div>
            <div>
              <?php wp_roles_and_users_input('use_your_drive_settings[permissions_see_dashboard]', $this->settings['permissions_see_dashboard']); ?>
            </div>                 

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('See Back-End Filebrowser', 'wpcloudplugins'); ?></div>
            <div>
              <?php wp_roles_and_users_input('use_your_drive_settings[permissions_see_filebrowser]', $this->settings['permissions_see_filebrowser']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Add Plugin Shortcodes', 'wpcloudplugins'); ?></div>
            <div>
              <?php wp_roles_and_users_input('use_your_drive_settings[permissions_add_shortcodes]', $this->settings['permissions_add_shortcodes']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Add Direct links', 'wpcloudplugins'); ?></div>
            <div>
              <?php wp_roles_and_users_input('use_your_drive_settings[permissions_add_links]', $this->settings['permissions_add_links']); ?>
            </div>

            <div class="useyourdrive-accordion-title useyourdrive-option-title"><?php _e('Embed Documents', 'wpcloudplugins'); ?></div>
            <div>
              <?php wp_roles_and_users_input('use_your_drive_settings[permissions_add_embedded]', $this->settings['permissions_add_embedded']); ?>
            </div>

          </div>

        </div>
        <!-- End Permissions Tab -->

        <!--  Statistics Tab -->
        <div id="settings_stats"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Statistics', 'wpcloudplugins'); ?></div>

          <div class="useyourdrive-option-title"><?php _e('Log Events', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[log_events]'/>
              <input type="checkbox" name="use_your_drive_settings[log_events]" id="log_events" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['log_events']) ? 'checked="checked"' : ''; ?> data-div-toggle="events_options"/>
              <label class="useyourdrive-onoffswitch-label" for="log_events"></label>
            </div>
          </div>
          <div class="useyourdrive-option-description"><?php _e('Register all plugin events', 'wpcloudplugins'); ?>.</div>

          <div class="useyourdrive-suboptions events_options <?php echo ('Yes' === $this->settings['log_events']) ? '' : 'hidden'; ?> ">
            <div class="useyourdrive-option-title"><?php _e('Summary Email', 'wpcloudplugins'); ?>
              <div class="useyourdrive-onoffswitch">
                <input type='hidden' value='No' name='use_your_drive_settings[event_summary]'/>
                <input type="checkbox" name="use_your_drive_settings[event_summary]" id="event_summary" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['event_summary']) ? 'checked="checked"' : ''; ?> data-div-toggle="event_summary"/>
                <label class="useyourdrive-onoffswitch-label" for="event_summary"></label>
              </div>
            </div>
            <div class="useyourdrive-option-description"><?php _e('Email a summary of all the events that are logged with the plugin', 'wpcloudplugins'); ?>.</div>

            <div class="event_summary <?php echo ('Yes' === $this->settings['event_summary']) ? '' : 'hidden'; ?> ">

              <div class="useyourdrive-option-title"><?php _e('Interval', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php _e('Please select the interval the summary needs to be send', 'wpcloudplugins'); ?>.</div>
              <select type="text" name="use_your_drive_settings[event_summary_period]" id="event_summary_period">
                <option value="daily"  <?php echo 'daily' === $this->settings['event_summary_period'] ? "selected='selected'" : ''; ?>><?php _e('Every day', 'wpcloudplugins'); ?></option>
                <option value="weekly"  <?php echo 'weekly' === $this->settings['event_summary_period'] ? "selected='selected'" : ''; ?>><?php _e('Weekly', 'wpcloudplugins'); ?></option>
                <option value="monthly"  <?php echo 'monthly' === $this->settings['event_summary_period'] ? "selected='selected'" : ''; ?>><?php _e('Monthly', 'wpcloudplugins'); ?></option>
              </select>   

              <div class="useyourdrive-option-title"><?php _e('Recipients', 'wpcloudplugins'); ?></div>
              <div class="useyourdrive-option-description"><?php _e('Send the summary to the following email address(es)', 'wpcloudplugins'); ?>:</div>
              <input class="useyourdrive-option-input-large" type="text" name="use_your_drive_settings[event_summary_recipients]" id="event_summary_recipients" value="<?php echo esc_attr($this->settings['event_summary_recipients']); ?>" placeholder="<?php echo get_option('admin_email'); ?>">  
            </div>
          </div>


          <div class="useyourdrive-option-title"><?php _e('Google Analytics', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[google_analytics]'/>
              <input type="checkbox" name="use_your_drive_settings[google_analytics]" id="google_analytics" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['google_analytics']) ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="google_analytics"></label>
            </div>
          </div>
          <div class="useyourdrive-option-description"><?php _e('Would you like to see some statistics in Google Analytics?', 'wpcloudplugins'); ?>. <?php echo sprintf(__('If you enable this feature, please make sure you already added your %s Google Analytics web tracking %s code to your site.', 'wpcloudplugins'), "<a href='https://support.google.com/analytics/answer/1008080' target='_blank'>", '</a>'); ?>.</div>
        </div>
        <!-- End Statistics Tab -->

        <!-- System info Tab -->
        <div id="settings_system"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('System information', 'wpcloudplugins'); ?></div>
          <?php echo $this->get_system_information(); ?>
        </div>
        <!-- End System info -->

        <!-- Tools Tab -->
        <div id="settings_tools"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Tools', 'wpcloudplugins'); ?></div>

          <div class="useyourdrive-option-title"><?php _e('Cache', 'wpcloudplugins'); ?></div>
          <?php echo $this->get_plugin_reset_cache_box(); ?>

          <div class="useyourdrive-option-title"><?php _e('Enable API log', 'wpcloudplugins'); ?>
            <div class="useyourdrive-onoffswitch">
              <input type='hidden' value='No' name='use_your_drive_settings[api_log]'/>
              <input type="checkbox" name="use_your_drive_settings[api_log]" id="api_log" class="useyourdrive-onoffswitch-checkbox" <?php echo ('Yes' === $this->settings['api_log']) ? 'checked="checked"' : ''; ?> />
              <label class="useyourdrive-onoffswitch-label" for="api_log"></label>
            </div>
            <div class="useyourdrive-option-description"><?php echo sprintf(__('When enabled, all API requests will be logged in the file <code>/wp-content/%s-cache/api.log</code>. Please note that this log file is not accessible via the browser on Apache servers.', 'wpcloudplugins'), 'use-your-drive'); ?>.</div>
          </div>

          <div class="useyourdrive-option-title"><?php _e('Reset to Factory Settings', 'wpcloudplugins'); ?></div>
          <?php echo $this->get_plugin_reset_plugin_box(); ?>

        </div>  
        <!-- End Tools -->

        <!-- Help Tab -->
        <div id="settings_help"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Support', 'wpcloudplugins'); ?></div>

          <div class="useyourdrive-option-title"><?php _e('Support & Documentation', 'wpcloudplugins'); ?></div>
          <div id="message">
            <p><?php _e('Check the documentation of the plugin in case you encounter any problems or are looking for support.', 'wpcloudplugins'); ?></p>
            <div id='documentation_button' type='button' class='simple-button blue'><?php _e('Open Documentation', 'wpcloudplugins'); ?></div>
          </div>
        </div>  
        <!-- End Help info -->
      </div>
    </div>
  </form>

  <script type="text/javascript" >
      var whitelist = <?php echo json_encode(TheLion\UseyourDrive\Helpers::get_all_users_and_roles()); ?>; /* Build Whitelist for permission selection */

      jQuery(document).ready(function ($) {
        var media_library;

        $('.useyourdrive-color-picker').wpColorPicker();

        $('#content_skin_selectbox').ddslick({
          width: '598px',
          background: '#f4f4f4',
          onSelected: function (item) {
            $("#content_skin").val($('#content_skin_selectbox').data('ddslick').selectedData.value);
          }
        });

        $('#lightbox_skin_selectbox').ddslick({
          width: '598px',
          background: '#f4f4f4',
          onSelected: function (item) {
            $("#lightbox_skin").val($('#lightbox_skin_selectbox').data('ddslick').selectedData.value);
          }
        });
        $('#mediaplayer_skin_selectbox').ddslick({
          width: '598px',
          background: '#f4f4f4',
          onSelected: function (item) {
            $("#mediaplayer_skin").val($('#mediaplayer_skin_selectbox').data('ddslick').selectedData.value);
          }
        });
        $('#shortlinks').on('change', function () {
          $('.option.bitly, .option.shortest, .option.rebrandly').hide();
          if ($(this).val() == 'Bit.ly') {
            $('.option.bitly').show();
          }
          if ($(this).val() == 'Shorte.st') {
            $('.option.shortest').show();
          }
          if ($(this).val() == 'Rebrandly') {
            $('.option.rebrandly').show();
          }

        });

        $('.shareon-setting-button').click(function (e) {
          e.stopImmediatePropagation();

          var current_value = $(this).next().val();

          if (current_value === 'disabled'){
            $(this).removeClass('shareon-disabled').addClass('shareon-enabled');
            $(this).next().val('enabled');
          } else {
            $(this).removeClass('shareon-enabled').addClass('shareon-disabled');
            $(this).next().val('disabled');          
          }
        });

        $('.upload_button').click(function () {
          var input_field = $(this).prev("input").attr("id");
          media_library = wp.media.frames.file_frame = wp.media({
            title: '<?php echo __('Select your image', 'wpcloudplugins'); ?>',
            button: {
              text: '<?php echo __('Use this Image', 'wpcloudplugins'); ?>'
            },
            multiple: false
          });
          media_library.on("select", function () {
            var attachment = media_library.state().get('selection').first().toJSON();

            var mime = attachment.mime;
            var regex = /^image\/(?:jpe?g|png|gif|svg)$/i;
            var is_image = mime.match(regex)

            if (is_image) {
              $("#" + input_field).val(attachment.url);
              $("#" + input_field).trigger('change');
            }

            $('.upload-remove').click(function () {
              $(this).hide();
              $(this).parent().parent().find(".upload").val('');
              $(this).parent().parent().find(".screenshot").slideUp();
            })
          })
          media_library.open()
        });

        $('.upload-remove').click(function () {
          $(this).hide();
          $(this).parent().parent().find(".upload").val('');
          $(this).parent().parent().find(".screenshot").slideUp();
        })

        $('.default_image_button').click(function () {
          $(this).parent().find(".upload").val($(this).attr('data-default'));
          $('input.upload').trigger('change');
        });

        $('input.upload').change(function () {
          var img = '<img src="' + $(this).val() + '" />'
          img += '<a href="javascript:void(0)" class="upload-remove">' + '<?php echo __('Remove', 'wpcloudplugins'); ?>' + "</a>";
          $(this).parent().find(".screenshot").slideDown().html(img);

          var default_button = $(this).parent().find(".default_image_button");
          default_button.hide();
          if ($(this).val() !== default_button.attr('data-default')) {
            default_button.fadeIn();
          }
        });

        $('#add_drive_button, .refresh_drive_button').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').fadeIn();
          $('#authorize_drive_options').fadeIn();
          popup = window.open($(this).attr('data-url'), "_blank", "toolbar=yes,scrollbars=yes,resizable=yes,width=600,height=900");

          var i = sessionStorage.length;
          while (i--) {
            var key = sessionStorage.key(i);
            if (/CloudPlugin/.test(key)) {
              sessionStorage.removeItem(key);
            }
          }
        });

        $('.revoke_drive_button, .delete_drive_button').click(function () {
          $(this).addClass('disabled');
          $(this).find('.uyd-spinner').show();
          $.ajax({type: "POST",
            url: '<?php echo USEYOURDRIVE_ADMIN_URL; ?>',
            data: {
              action: 'useyourdrive-revoke',
              account_id: $(this).attr('data-account-id'),
              force: $(this).attr('data-force'),
              _ajax_nonce: '<?php echo $admin_nonce; ?>'
            },
            complete: function (response) {
              location.reload(true)
            },
            dataType: 'json'
          });
        });

        $('#resetDrive_button').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').show();
          $.ajax({type: "POST",
            url: '<?php echo USEYOURDRIVE_ADMIN_URL; ?>',
            data: {
              action: 'useyourdrive-reset-cache',
              _ajax_nonce: '<?php echo $admin_nonce; ?>'
            },
            complete: function (response) {
              $button.removeClass('disabled');
              $button.find('.uyd-spinner').hide();
            },
            dataType: 'json'
          });

          var i = sessionStorage.length;
          while (i--) {
            var key = sessionStorage.key(i);
            if (/CloudPlugin/.test(key)) {
              sessionStorage.removeItem(key);
            }
          }
        });

        $('#resetSettings_button').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').show();
          $.ajax({type: "POST",
            url: '<?php echo USEYOURDRIVE_ADMIN_URL; ?>',
            data: {
              action: 'useyourdrive-factory-reset',
              _ajax_nonce: '<?php echo $admin_nonce; ?>'
            },
            complete: function (response) {
              location.reload(true);
            },
            dataType: 'json'
          });

          var i = sessionStorage.length;
          while (i--) {
            var key = sessionStorage.key(i);
            if (/CloudPlugin/.test(key)) {
              sessionStorage.removeItem(key);
            }
          }
        });

        

        $('#updater_button').click(function () {

          if ($('#purcase_code.useyourdrive-option-input-large').val()) {
            $('#useyourdrive-options').submit();
            return;
          }

          popup = window.open('https://www.wpcloudplugins.com/updates/activate.php?init=1&client_url=<?php echo strtr(base64_encode($location), '+/=', '-_~'); ?>&plugin_id=<?php echo $this->plugin_id; ?>', "_blank", "toolbar=yes,scrollbars=yes,resizable=yes,width=900,height=700");
        });
        $('#check_updates_button').click(function () {
          window.location = '<?php echo admin_url('update-core.php'); ?>';
        });
        $('#purcase_code.useyourdrive-option-input-large').focusout(function () {
          var purchase_code_regex = '^([a-z0-9]{8})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{4})-?([a-z0-9]{12})$';
          if ($(this).val().match(purchase_code_regex)) {
            $(this).css('color', 'initial');
          } else {
            $(this).css('color', '#dc3232');
          }
        });
        $('#deactivate_license_button').click(function () {
          $('#purcase_code').val('');
          $('#useyourdrive-options').submit();
        });

        $('#root_folder_button').click(function () {
          var $button = $(this);
          $(this).parent().addClass("thickbox_opener");
          $button.addClass('disabled');
          $button.find('.uyd-spinner').show();
          tb_show("Select Folder", '#TB_inline?height=450&amp;width=800&amp;inlineId=uyd-embedded');
        });

        $('#reset_notifications').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').fadeIn();
          $('#settings_notifications input[type="text"], #settings_notifications textarea').val('');
          $('#useyourdrive-options').submit();
        });

        $('#documentation_button').click(function () {
          popup = window.open('<?php echo USEYOURDRIVE_ROOTPATH.'/_documentation/index.html'; ?>', "_blank");
        });

        $('#save_settings').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').fadeIn();
          $('#useyourdrive-options').ajaxSubmit({
            success: function () {
              $button.removeClass('disabled');
              $button.find('.uyd-spinner').fadeOut();

              if (location.hash === '#settings_advanced' || location.hash === '#settings_notifications') {
                location.reload(true);
              }
            },
            error: function () {
              $button.removeClass('disabled');
              $button.find('.uyd-spinner').fadeOut();
              location.reload(true);
            },
          });
          //setTimeout("$('#saveMessage').hide('slow');", 5000);
          return false;
        });
      }
      );


  </script>
</div>
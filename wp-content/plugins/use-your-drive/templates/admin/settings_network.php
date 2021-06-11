<?php
$page = isset($_GET['page']) ? '?page='.$_GET['page'] : '';
$location = network_admin_url('admin.php'.$page);
$admin_nonce = wp_create_nonce('useyourdrive-admin-action');
$network_wide_authorization = $this->get_processor()->is_network_authorized();
?>

<div class="useyourdrive admin-settings">
  <form id="useyourdrive-options" method="post" action="<?php echo network_admin_url('edit.php?action='.$this->plugin_network_options_key); ?>">
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
          <?php if ($network_wide_authorization) { ?>
              <li id="settings_advanced_tab" data-tab="settings_advanced" ><a ><?php _e('Advanced', 'wpcloudplugins'); ?></a></li>
          <?php } ?>
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

          <div class="useyourdrive-option-title"><?php _e('Plugin License', 'wpcloudplugins'); ?></div>
          <?php
          echo $this->get_plugin_activated_box();
          ?>

          <?php if (is_plugin_active_for_network(USEYOURDRIVE_SLUG)) { ?>
              <div class="useyourdrive-option-title"><?php _e('Network Wide Authorization', 'wpcloudplugins'); ?>
                <div class="useyourdrive-onoffswitch">
                  <input type='hidden' value='No' name='use_your_drive_settings[network_wide]'/>
                  <input type="checkbox" name="use_your_drive_settings[network_wide]" id="network_wide" class="useyourdrive-onoffswitch-checkbox" <?php echo (empty($network_wide_authorization)) ? '' : 'checked="checked"'; ?> data-div-toggle="network_wide"/>
                  <label class="useyourdrive-onoffswitch-label" for="network_wide"></label>
                </div>
              </div>


              <?php
              if ($network_wide_authorization) {
                  ?>
                  <div class="useyourdrive-option-title"><?php _e('Accounts', 'wpcloudplugins'); ?></div>
                  <div class="useyourdrive-accounts-list">
                    <?php
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
                    foreach ($this->get_main()->get_accounts()->list_accounts() as $account_id => $account) {
                        echo $this->get_plugin_authorization_box($account);
                    } ?>
                  </div>
                  <?php
              }
              ?>

              <?php
          }
          ?>

        </div>
        <!-- End General Tab -->


        <!--  Advanced Tab -->
        <?php if ($network_wide_authorization) { ?>
            <div id="settings_advanced"  class="useyourdrive-tab-panel">
              <div class="useyourdrive-tab-panel-header"><?php _e('Advanced', 'wpcloudplugins'); ?></div>

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

                <div class="useyourdrive-option-title"Google Client ID</div>
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

            </div>
        <?php } ?>
        <!-- End Advanced Tab -->

        <!-- System info Tab -->
        <div id="settings_system"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('System information', 'wpcloudplugins'); ?></div>
          <?php echo $this->get_system_information(); ?>
        </div>
        <!-- End System info -->

        <!-- Help Tab -->
        <div id="settings_help"  class="useyourdrive-tab-panel">
          <div class="useyourdrive-tab-panel-header"><?php _e('Support', 'wpcloudplugins'); ?></div>

          <div class="useyourdrive-option-title"><?php _e('Support & Documentation', 'wpcloudplugins'); ?></div>
          <div id="message">
            <p><?php _e('Check the documentation of the plugin in case you encounter any problems or are looking for support.', 'wpcloudplugins'); ?></p>
            <div id='documentation_button' type='button' class='simple-button blue'><?php _e('Open Documentation', 'wpcloudplugins'); ?></div>
          </div>
          <br/>
          <div class="useyourdrive-option-title"><?php _e('Cache', 'wpcloudplugins'); ?></div>
          <?php echo $this->get_plugin_reset_cache_box(); ?>

        </div>  
      </div>
      <!-- End Help info -->
    </div>
  </form>
  <script type="text/javascript" >
      jQuery(document).ready(function ($) {

        $('#add_drive_button, .refresh_drive_button').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').fadeIn();
          $('#authorize_drive_options').fadeIn();
          popup = window.open($(this).attr('data-url'), "_blank", "toolbar=yes,scrollbars=yes,resizable=yes,width=900,height=900");

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


        $('#documentation_button').click(function () {
          popup = window.open('<?php echo USEYOURDRIVE_ROOTPATH.'/_documentation/index.html'; ?>', "_blank");
        });

        $('#network_wide').click(function () {
          $('#save_settings').trigger('click');
        });

        $('#save_settings').click(function () {
          var $button = $(this);
          $button.addClass('disabled');
          $button.find('.uyd-spinner').fadeIn();
          $('#useyourdrive-options').ajaxSubmit({
            success: function () {
              $button.removeClass('disabled');
              $button.find('.uyd-spinner').fadeOut();
              location.reload(true);
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
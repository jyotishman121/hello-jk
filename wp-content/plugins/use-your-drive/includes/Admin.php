<?php

namespace TheLion\UseyourDrive;

class Admin
{
    /**
     * @var \TheLion\UseyourDrive\Main
     */
    private $_main;
    private $settings_key = 'use_your_drive_settings';
    private $plugin_options_key = 'UseyourDrive_settings';
    private $plugin_network_options_key = 'UseyourDrive_network_settings';
    private $plugin_id = 6219776;
    private $networksettingspage;
    private $settingspage;
    private $filebrowserpage;
    private $shortcodebuilderpage;
    private $dashboardpage;
    private $userpage;

    /**
     * Construct the plugin object.
     */
    public function __construct(Main $main)
    {
        $this->_main = $main;

        // Check if plugin can be used
        if (false === $main->can_run_plugin()) {
            add_action('admin_notices', [&$this, 'get_admin_notice']);

            return;
        }

        // Init
        add_action('init', [&$this, 'load_settings']);
        add_action('admin_init', [&$this, 'register_settings']);
        add_action('admin_init', [&$this, 'check_for_updates']);
        add_action('admin_enqueue_scripts', [&$this, 'LoadAdmin']);

        // Add menu's
        add_action('admin_menu', [&$this, 'add_admin_menu']);
        add_action('network_admin_menu', [&$this, 'add_admin_network_menu']);

        // Network save settings call
        add_action('network_admin_edit_'.$this->plugin_network_options_key, [$this, 'save_settings_network']);

        // Save settings call
        add_filter('pre_update_option_'.$this->settings_key, [$this, 'save_settings'], 10, 2);

        // Notices
        add_action('admin_notices', [&$this, 'get_admin_notice_not_authorized']);
        add_action('admin_notices', [&$this, 'get_admin_notice_not_activated']);
        add_action('admin_notices', [&$this, 'get_admin_notice_php_requirement']);

        add_filter('admin_footer_text', [$this, 'admin_footer_text'], 1);

        // Add custom Update messages in plugin dashboard
        add_action('in_plugin_update_message-'.USEYOURDRIVE_SLUG, [$this, 'in_plugin_update_message'], 10, 2);
    }

    /**
     * @return \TheLion\UseyourDrive\Main
     */
    public function get_main()
    {
        return $this->_main;
    }

    /**
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor()
    {
        return $this->_main->get_processor();
    }

    /**
     * @return \TheLion\UseyourDrive\App
     */
    public function get_app()
    {
        return $this->get_processor()->get_app();
    }

    // Add custom Update messages in plugin dashboard

    public function in_plugin_update_message($data, $response)
    {
        if (isset($data['upgrade_notice'])) {
            printf(
                '<br /><br /><span style="display:inline-block;background-color: #522058; padding: 10px; color: white;"><span class="dashicons dashicons-warning"></span>&nbsp;<strong>UPGRADE NOTICE</strong> <br /><br />%s</span><br /><br />',
                $data['upgrade_notice']
            );
        }
    }

    public function LoadAdmin($hook)
    {
        if ($hook == $this->networksettingspage || $hook == $this->filebrowserpage || $hook == $this->userpage || $hook == $this->settingspage || $hook == $this->shortcodebuilderpage || $hook == $this->dashboardpage) {
            $this->get_main()->load_scripts();
            $this->get_main()->load_styles();

            wp_enqueue_script('jquery-effects-fade');
            wp_enqueue_script('WPCloudplugin.Libraries');

            wp_enqueue_style('UseyourDrive.ShortcodeBuilder');
            wp_enqueue_style('Awesome-Font-5-css');
        }

        if ($hook == $this->networksettingspage || $hook == $this->settingspage) {
            wp_enqueue_script('jquery-form');
            wp_enqueue_script('UseyourDrive.ShortcodeBuilder');
            wp_enqueue_script('wp-color-picker-alpha', plugins_url('/wp-color-picker-alpha/wp-color-picker-alpha.min.js', __FILE__), ['wp-color-picker'], '3.0.0', true);
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('jquery-ui-accordion');
            wp_enqueue_media();
            add_thickbox();
        }

        if ($hook == $this->userpage) {
            wp_enqueue_style('UseyourDrive');
            add_thickbox();
        }

        if ($hook == $this->dashboardpage) {
            wp_enqueue_script('UseyourDrive.Dashboard');
            wp_enqueue_style('UseyourDrive.Datatables.css');
            wp_dequeue_style('UseyourDrive');
        }
    }

    /**
     * add a menu.
     */
    public function add_admin_menu()
    {
        // Add a page to manage this plugin's settings
        $menuadded = false;

        if (Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
            add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, [&$this, 'load_settings_page'], plugin_dir_url(__FILE__).'../css/images/google_drive_logo_small.png');
            $menuadded = true;
            $this->settingspage = add_submenu_page($this->plugin_options_key, 'Use-your-Drive - '.__('Settings'), __('Settings'), 'read', $this->plugin_options_key, [&$this, 'load_settings_page']);
        }

        if (false === $this->is_activated()) {
            return;
        }

        if (Helpers::check_user_role($this->settings['permissions_see_dashboard']) && ('Yes' === $this->settings['log_events'])) {
            if (!$menuadded) {
                $this->dashboardpage = add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, [&$this, 'load_dashboard_page'], plugin_dir_url(__FILE__).'../css/images/google_drive_logo_small.png');
                $this->dashboardpage = add_submenu_page($this->plugin_options_key, __('Reports', 'wpcloudplugins'), __('Reports', 'wpcloudplugins'), 'read', $this->plugin_options_key, [&$this, 'load_dashboard_page']);
                $menuadded = true;
            } else {
                $this->dashboardpage = add_submenu_page($this->plugin_options_key, __('Reports', 'wpcloudplugins'), __('Reports', 'wpcloudplugins'), 'read', $this->plugin_options_key.'_dashboard', [&$this, 'load_dashboard_page']);
            }
        }

        if (Helpers::check_user_role($this->settings['permissions_add_shortcodes'])) {
            if (!$menuadded) {
                $this->shortcodebuilderpage = add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, [&$this, 'load_shortcodebuilder_page'], plugin_dir_url(__FILE__).'../css/images/google_drive_logo_small.png');
                $this->shortcodebuilderpage = add_submenu_page($this->plugin_options_key, __('Shortcode Builder', 'wpcloudplugins'), __('Shortcode Builder', 'wpcloudplugins'), 'read', $this->plugin_options_key, [&$this, 'load_shortcodebuilder_page']);
                $menuadded = true;
            } else {
                $this->shortcodebuilderpage = add_submenu_page($this->plugin_options_key, __('Shortcode Builder', 'wpcloudplugins'), __('Shortcode Builder', 'wpcloudplugins'), 'read', $this->plugin_options_key.'_shortcodebuilder', [&$this, 'load_shortcodebuilder_page']);
            }
        }

        if (Helpers::check_user_role($this->settings['permissions_link_users'])) {
            if (!$menuadded) {
                $this->userpage = add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, [&$this, 'load_linkusers_page'], plugin_dir_url(__FILE__).'../css/images/google_drive_logo_small.png');
                $this->userpage = add_submenu_page($this->plugin_options_key, __('Link Private Folders', 'wpcloudplugins'), __('Link Private Folders', 'wpcloudplugins'), 'read', $this->plugin_options_key, [&$this, 'load_linkusers_page']);
                $menuadded = true;
            } else {
                $this->userpage = add_submenu_page($this->plugin_options_key, __('Link Private Folders', 'wpcloudplugins'), __('Link Private Folders', 'wpcloudplugins'), 'read', $this->plugin_options_key.'_linkusers', [&$this, 'load_linkusers_page']);
            }
        }
        if (Helpers::check_user_role($this->settings['permissions_see_filebrowser'])) {
            if (!$menuadded) {
                $this->filebrowserpage = add_menu_page('Use-your-Drive', 'Use-your-Drive', 'read', $this->plugin_options_key, [&$this, 'load_filebrowser_page'], plugin_dir_url(__FILE__).'../css/images/google_drive_logo_small.png');
                $this->filebrowserpage = add_submenu_page($this->plugin_options_key, __('File Browser', 'wpcloudplugins'), __('File Browser', 'wpcloudplugins'), 'read', $this->plugin_options_key, [&$this, 'load_filebrowser_page']);
                $menuadded = true;
            } else {
                $this->filebrowserpage = add_submenu_page($this->plugin_options_key, __('File Browser', 'wpcloudplugins'), __('File Browser', 'wpcloudplugins'), 'read', $this->plugin_options_key.'_filebrowser', [&$this, 'load_filebrowser_page']);
            }
        }
    }

    public function add_admin_network_menu()
    {
        if (!is_plugin_active_for_network(USEYOURDRIVE_SLUG)) {
            return;
        }

        add_menu_page('Use-your-Drive', 'Use-your-Drive', 'manage_options', $this->plugin_network_options_key, [&$this, 'load_settings_network_page'], plugin_dir_url(__FILE__).'../css/images/google_drive_logo_small.png');

        $this->networksettingspage = add_submenu_page($this->plugin_network_options_key, 'Use-your-Drive - '.__('Settings'), __('Settings'), 'read', $this->plugin_network_options_key, [&$this, 'load_settings_network_page']);

        if ($this->get_processor()->is_network_authorized()) {
            $this->filebrowserpage = add_submenu_page($this->plugin_network_options_key, __('File Browser', 'wpcloudplugins'), __('File Browser', 'wpcloudplugins'), 'read', $this->plugin_network_options_key.'_filebrowser', [&$this, 'load_filebrowser_page']);
        }
    }

    public function register_settings()
    {
        register_setting($this->settings_key, $this->settings_key);
    }

    public function load_settings()
    {
        $this->settings = (array) get_option($this->settings_key);

        $updated = false;
        if (!isset($this->settings['googledrive_app_client_id'])) {
            $this->settings['googledrive_app_client_id'] = '';
            $this->settings['googledrive_app_client_secret'] = '';
            $updated = true;
        }

        if ($updated) {
            update_option($this->settings_key, $this->settings);
        }

        if ($this->get_processor()->is_network_authorized()) {
            $this->settings = array_merge($this->settings, get_site_option('useyourdrive_network_settings', []));
        }
    }

    public function load_settings_page()
    {
        if (!Helpers::check_user_role($this->settings['permissions_edit_settings'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpcloudplugins'));
        }

        include sprintf('%s/templates/admin/settings.php', USEYOURDRIVE_ROOTDIR);
    }

    public function load_settings_network_page()
    {
        include sprintf('%s/templates/admin/settings_network.php', USEYOURDRIVE_ROOTDIR);
    }

    public function save_settings($new_settings, $old_settings)
    {
        foreach ($new_settings as $setting_key => &$value) {
            if ('on' === $value) {
                $value = 'Yes';
            }

            if ('googledrive_app_own' === $setting_key && 'No' === $value) {
                $new_settings['googledrive_app_client_id'] = '';
                $new_settings['googledrive_app_client_secret'] = '';
            }

            if ('gsuite' === $setting_key && 'No' === $value) {
                $new_settings['permission_domain'] = '';
                $new_settings['teamdrives'] = 'No';
            }

            if ('colors' === $setting_key) {
                $value = $this->_check_colors($value, $old_settings['colors']);
            }

            // Store the ID of fields using tagify data
            if (is_string($value) && false !== strpos($value, '[{')) {
                $value = $this->_format_tagify_data($value);
            }

            if ('userfolder_backend_auto_root' === $setting_key && isset($value['view_roles'])) {
                $value['view_roles'] = $this->_format_tagify_data($value['view_roles']);
            }
        }

        if ($new_settings['teamdrives'] !== $old_settings['teamdrives']) {
            $this->get_processor()->reset_complete_cache();
        }

        $new_settings['icon_set'] = rtrim($new_settings['icon_set'], '/').'/';

        if ($new_settings['icon_set'] !== $old_settings['icon_set']) {
            $this->get_processor()->reset_complete_cache();
        }

        // Update Cron Job settings
        if ($new_settings['event_summary'] !== $old_settings['event_summary'] || $new_settings['event_summary_period'] !== $old_settings['event_summary_period']) {
            $summary_cron_job = wp_next_scheduled('useyourdrive_send_event_summary');
            if (false !== $summary_cron_job) {
                wp_unschedule_event($summary_cron_job, 'useyourdrive_send_event_summary');
            }
        }
        // If needed, a new cron job will be set when the plugin initiates again

        // Keep account data
        if (!isset($new_settings['accounts'])) {
            $new_settings['accounts'] = isset($old_settings['accounts']) ? $old_settings['accounts'] : [];
        }

        return $new_settings;
    }

    public function save_settings_network()
    {
        if (current_user_can('manage_network_options')) {
            update_site_option('useyourdrive_purchaseid', $_REQUEST['use_your_drive_settings']['purcase_code']);

            $settings = get_site_option('useyourdrive_network_settings', []);

            if (is_plugin_active_for_network(USEYOURDRIVE_SLUG) && 'on' === $_REQUEST['use_your_drive_settings']['network_wide']) {
                $settings['network_wide'] = 'Yes';
            } else {
                $settings['network_wide'] = 'No';
            }

            if ('Yes' === $settings['network_wide'] && isset($_REQUEST['use_your_drive_settings']['lostauthorization_notification'])) {
                $settings['lostauthorization_notification'] = $_REQUEST['use_your_drive_settings']['lostauthorization_notification'];
                $settings['googledrive_app_own'] = ('on' === $_REQUEST['use_your_drive_settings']['googledrive_app_own'] ? 'Yes' : 'No');

                if ('Yes' === $settings['googledrive_app_own']) {
                    $settings['googledrive_app_client_id'] = ($_REQUEST['use_your_drive_settings']['googledrive_app_client_id']);
                    $settings['googledrive_app_client_secret'] = ($_REQUEST['use_your_drive_settings']['googledrive_app_client_secret']);
                } else {
                    $settings['googledrive_app_client_id'] = '';
                    $settings['googledrive_app_client_secret'] = '';
                }

                if ('on' === $_REQUEST['use_your_drive_settings']['gsuite']) {
                    $settings['permission_domain'] = $_REQUEST['use_your_drive_settings']['permission_domain'];
                    $settings['teamdrives'] = ('on' === $_REQUEST['use_your_drive_settings']['teamdrives'] ? 'Yes' : 'No');
                } else {
                    $settings['permission_domain'] = '';
                    $settings['teamdrives'] = 'No';
                }
            }

            update_site_option('useyourdrive_network_settings', $settings);
        }

        wp_redirect(
            add_query_arg(
                ['page' => $this->plugin_network_options_key, 'updated' => 'true'],
                network_admin_url('admin.php')
            )
        );

        exit;
    }

    public function admin_footer_text($footer_text)
    {
        $rating_asked = get_option('use_your_drive_rating_asked', false);
        if (true == $rating_asked || false === (Helpers::check_user_role($this->settings['permissions_edit_settings']))) {
            return $footer_text;
        }

        $current_screen = get_current_screen();

        if (isset($current_screen->id) && in_array($current_screen->id, [$this->filebrowserpage, $this->userpage, $this->settingspage])) {
            $onclick = "jQuery.post( '".USEYOURDRIVE_ADMIN_URL."', { action: 'useyourdrive-rating-asked' });jQuery( this ).parent().text( jQuery( this ).data( 'rated' ) )";
            $footer_text = sprintf(
                __('If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'wpcloudplugins'),
                sprintf('<strong>%s</strong>', 'Use-your-Drive'),
                '<a href="https://1.envato.market/c/1260925/275988/4415?u=https%3A%2F%2Fcodecanyon.net%2Fitem%2Fuseyourdrive-google-drive-plugin-for-wordpress%2Freviews%2F6219776" target="_blank" class="useyourdrive-rating-link" data-rated="'.esc_attr__('Thanks :)', 'wpcloudplugins').'" onclick="'.$onclick.'">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
            );
        }

        return $footer_text;
    }

    public function is_activated()
    {
        if (defined('USEYOURDRIVE_3RD_PARTY_INSTALL')) {
            return true;
        }

        $purchase_code = $this->get_purchase_code();

        if (empty($purchase_code)) {
            delete_transient('useyourdrive_activation_validated');
            delete_site_transient('useyourdrive_activation_validated');

            return false;
        }

        if (false === get_transient('useyourdrive_activation_validated') && false === get_site_transient('useyourdrive_activation_validated')) {
            return $this->validate_activation();
        }

        return true;
    }

    public function validate_activation()
    {
        return true;
        
        $purchase_code = $this->get_purchase_code();
        $response = wp_remote_get('https://www.wpcloudplugins.com/updates/?action=get_metadata&slug=use-your-drive&purchase_code='.$purchase_code.'&plugin_id='.$this->plugin_id.'&siteurl='.rawurldecode(get_site_url()));

        $response_code = wp_remote_retrieve_response_code($response);

        if (empty($response_code)) {
            if (is_wp_error($response)) {
                error_log($response->get_error_message());
            }

            return false;
        }

        if (401 === $response_code) {
            // Revoke license if invalid
            $this->settings['purcase_code'] = '';
            update_option($this->settings_key, $this->settings);
            delete_transient('useyourdrive_activation_validated');

            delete_site_transient('useyourdrive_activation_validated');
            delete_site_option('useyourdrive_purchaseid');

            return false;
        }

        set_transient('useyourdrive_activation_validated', true, WEEK_IN_SECONDS);
        set_site_transient('useyourdrive_activation_validated', true, WEEK_IN_SECONDS);

        return true;
    }

    public function get_purchase_code()
    {
        return 'activated';

        $purchasecode = $this->settings['purcase_code'];
        if (is_multisite()) {
            $site_purchase_code = get_site_option('useyourdrive_purchaseid');

            if (!empty($site_purchase_code)) {
                $purchasecode = $site_purchase_code;
            }
        }

        return $purchasecode;
    }

    public function load_filebrowser_page()
    {
        if (!Helpers::check_user_role($this->settings['permissions_see_filebrowser'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpcloudplugins'));
        }

        include sprintf('%s/templates/admin/file_browser.php', USEYOURDRIVE_ROOTDIR);
    }

    public function load_linkusers_page()
    {
        if (!Helpers::check_user_role($this->settings['permissions_link_users'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpcloudplugins'));
        }
        $linkusers = new LinkUsers($this->get_main());
        $linkusers->render();
    }

    public function load_shortcodebuilder_page()
    {
        if (!Helpers::check_user_role($this->settings['permissions_add_shortcodes'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpcloudplugins'));
        }

        echo "<iframe src='".USEYOURDRIVE_ADMIN_URL."?action=useyourdrive-getpopup&type=shortcodebuilder&standalone' width='90%' height='1000' tabindex='-1' frameborder='0'></iframe>";
    }

    public function load_dashboard_page()
    {
        if (!Helpers::check_user_role($this->settings['permissions_see_dashboard'])) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpcloudplugins'));
        }

        include sprintf('%s/templates/admin/event_dashboard.php', USEYOURDRIVE_ROOTDIR);
    }

    public function get_plugin_activated_box()
    {
        $purchase_code = $this->get_purchase_code();

        // Check if Auto-update is being activated
        if (isset($_REQUEST['purchase_code'], $_REQUEST['plugin_id']) && ((int) $_REQUEST['plugin_id'] === $this->plugin_id)) {
            $purchase_code = $this->settings['purcase_code'] = sanitize_key($_REQUEST['purchase_code']);
            update_option($this->settings_key, $this->settings);

            if (is_multisite() && is_plugin_active_for_network(USEYOURDRIVE_SLUG)) {
                update_site_option('useyourdrive_purchaseid', sanitize_key($_REQUEST['purchase_code']));
            }
        }

        $box_class = 'uyd-updated';
        $box_input = '<input type="hidden" name="use_your_drive_settings[purcase_code]" id="purcase_code" value="'.esc_attr($purchase_code).'">';
        $box_text = __('Thanks for registering your product! The plugin is now <strong>Activated</strong> and the <strong>Auto-Updater</strong> enabled', 'wpcloudplugins').'. '.__('Your purchase code', 'wpcloudplugins').": <code style='user-select: initial;'>".esc_attr($purchase_code).'</code>';

        if (empty($purchase_code)) {
            $box_class = 'uyd-error';
            $box_text = __('The plugin is <strong>Not Activated</strong> and the <strong>Auto-Updater</strong> disabled', 'wpcloudplugins').'. '.__('Please activate your copy in order to use the plugin', 'wpcloudplugins').'. ';
            if (false === is_plugin_active_for_network(USEYOURDRIVE_SLUG) || true === is_network_admin()) {
                $box_text .= "</p><p><input id='updater_button' type='button' class='simple-button blue' value='".__('Activate', 'wpcloudplugins')."' /><span><a href='#' onclick='jQuery(\".useyourdrive_purchasecode_manual\").slideToggle()'>".__('Or insert your purchase code manually and press Activate', 'wpcloudplugins').'</a></span></p> ';
                $box_text .= '<div class="useyourdrive_purchasecode_manual" style="display:none" ><h3>Activate manually</h3><input name="use_your_drive_settings[purcase_code]" id="purcase_code" class="useyourdrive-option-input-large" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX" value="'.esc_attr($purchase_code).'"><a href="https://florisdeleeuwnl.zendesk.com/hc/en-us/articles/201834487" target="_blank">'.__('Where can I find my purchase code?', 'wpcloudplugins').'</a></div>';
                $box_input = '';
            }
        } else {
            $box_text .= "</p><p><input id='check_updates_button' type='button' class='simple-button blue' value='".__('Check for updates', 'wpcloudplugins')."' />";

            if (false === is_plugin_active_for_network(USEYOURDRIVE_SLUG) || true === is_network_admin()) {
                $box_text .= "<input id='deactivate_license_button' type='button' class='simple-button default' value='".__('Deactivate License', 'wpcloudplugins')."' />";
            }
        }

        return "<div id='message' class='{$box_class} useyourdrive-option-description'><p>{$box_text}</p>{$box_input}</div>";
    }

    public function get_plugin_authorization_box(Account $account)
    {
        $app = $this->get_app();

        $app->get_client()->setAccessType('offline');
        $app->get_client()->setApprovalPrompt('force');
        $app->get_client()->setLoginHint($account->get_email());

        $revokebutton = "<div type='button' class='revoke_drive_button simple-button default small' data-account-id='{$account->get_id()}' data-force='false' title='".__('Revoke', 'wpcloudplugins')."'><i class='fas fa-trash' aria-hidden='true'></i><div class='uyd-spinner'></div></div>";
        $deletebutton = "<div type='button' class='delete_drive_button simple-button default small' data-account-id='{$account->get_id()}' data-force='true' title='".__('Remove', 'wpcloudplugins')."'><i class='fas fa-trash' aria-hidden='true'></i><div class='uyd-spinner'></div></div>";
        $refreshbutton = "<div type='button' class='refresh_drive_button simple-button red small' data-account-id='{$account->get_id()}' data-url='{$app->get_auth_url()}' title='".__('Refresh', 'wpcloudplugins')."'><i class='fas fa-redo' aria-hidden='true'></i><div class='uyd-spinner'></div></div>";

        $account_info_html = "<div class='account-info-name'>{$account->get_name()} <code class='account-info-id'>ID: {$account->get_id()}</code></div><span class='account-info-email'>{$account->get_email()}</span>";
        $status_info_html = '';
        $errror_details_html = '';

        // Check if plugin is still linked
        if (false === $account->get_authorization()->has_access_token()) {
            $status_info_html = "<i class='fas fa-exclamation-triangle' aria-hidden='true'></i>&nbsp;".__("Account isn't linked to the plugin anymore.", 'wpcloudplugins').' '.__('Please re-authorize!', 'wpcloudplugins');

            return "<div class='account account-error'><img class='account-image' src='{$account->get_image()}' onerror='this.src=\"".USEYOURDRIVE_ROOTPATH."/css/images/google_drive_logo.png\"'/><div class='account-info-container'><div class='account-actions'>{$refreshbutton} {$deletebutton}</div><div class='account-info'>{$account_info_html}</div><div class='account-info-status'>{$status_info_html}</div><div class='account-info-error'>{$errror_details_html}</div></div></div>";
        }

        // Check Authorization
        $transient_name = 'useyourdrive_'.$account->get_id().'_is_authorized';
        $is_authorized = get_transient($transient_name);

        // Re-Check authorization if needed
        if (false === $is_authorized) {
            try {
                $this->get_processor()->set_current_account($account);
                $drive_info = $this->get_processor()->get_client()->get_drive_info();
                set_transient($transient_name, true, 5 * MINUTE_IN_SECONDS);
                $is_authorized = true;
            } catch (\Exception $ex) {
                $this->get_processor()->get_current_account()->get_authorization()->set_is_valid(false);
                set_transient($transient_name, false, 5 * MINUTE_IN_SECONDS);
                $status_info_html = "<i class='fas fa-exclamation-triangle' aria-hidden='true'></i>&nbsp;".__("Account isn't linked to the plugin anymore.", 'wpcloudplugins').' '.__('Please re-authorize!', 'wpcloudplugins');

                if ($app->has_plugin_own_app()) {
                    $status_info_html .= ' '.__('If the problem persists, fall back to the default App via the settings on the Advanced tab', 'wpcloudplugins').'.';
                }

                $errror_details_html = '<p>Error Details:</p><pre>'.$ex->getMessage().'</pre>';

                return "<div class='account account-error'><img class='account-image' src='{$account->get_image()}' onerror='this.src=\"".USEYOURDRIVE_ROOTPATH."/css/images/google_drive_logo.png\"'/><div class='account-info-container'><div class='account-actions'>{$refreshbutton} {$deletebutton}</div><div class='account-info'>{$account_info_html}</div><div class='account-info-status'>{$status_info_html}</div><div class='account-info-error'>{$errror_details_html}</div></div></div>";
            }
        }

        // Return information why authorization is invalid
        if (false === $is_authorized) {
            $status_info_html = "<i class='fas fa-exclamation-triangle' aria-hidden='true'></i>&nbsp;".__("Account isn't linked to the plugin anymore.", 'wpcloudplugins').' '.__('Please re-authorize!', 'wpcloudplugins');

            return "<div class='account account-error'><img class='account-image' src='{$account->get_image()}' onerror='this.src=\"".USEYOURDRIVE_ROOTPATH."/css/images/google_drive_logo.png\"'/><div class='account-info-container'><div class='account-actions'>{$refreshbutton} {$deletebutton}</div><div class='account-info'>{$account_info_html}</div><div class='account-info-status'>{$status_info_html}</div><div class='account-info-error'>{$errror_details_html}</div></div></div>";
        }

        if ($this->get_processor()->is_network_authorized() && false === is_network_admin()) {
            return "<div class='account'><img class='account-image' src='{$account->get_image()}' onerror='this.src=\"".USEYOURDRIVE_ROOTPATH."/css/images/google_drive_logo.png\"'/><div class='account-info-container'><div class='account-info'>{$account_info_html}</div><div class='account-info-error'>{$errror_details_html}</div></div></div>";
        }

        $storageinfo = $account->get_storage_info();
        $account_info_html = "<div class='account-info-name'>{$account->get_name()} <code class='account-info-id'>ID: {$account->get_id()}</code></div><span class='account-info-email'>{$account->get_email()}</span> - <span class='account-info-space'>{$storageinfo->get_quota_used()}/{$storageinfo->get_quota_total()}</span>";

        return "<div class='account'><img class='account-image' src='{$account->get_image()}' onerror='this.src=\"".USEYOURDRIVE_ROOTPATH."/css/images/google_drive_logo.png\"'/><div class='account-info-container'><div class='account-actions'>{$revokebutton}</div><div class='account-info'>{$account_info_html}</div><div class='account-info-error'>{$errror_details_html}</div></div></div>";
    }

    public function get_plugin_reset_cache_box()
    {
        $box_text = __('WP Cloud Plugins uses a cache to improve performance', 'wpcloudplugins').'. '.__('If the plugin somehow is causing issues, try to reset the cache first', 'wpcloudplugins').'.<br/>';

        $box_button = "<div id='resetDrive_button' type='button' class='simple-button blue'/>".__('Purge Cache', 'wpcloudplugins')."&nbsp;<div class='uyd-spinner'></div></div>";

        return "<div id='message'><div class='useyourdrive-option-description'>{$box_text}</div><p>{$box_button}</p></div>";
    }

    public function get_plugin_reset_plugin_box()
    {
        $box_text = __('Need to revert back to the default settings? This button will instantly reset your settings to the defaults', 'wpcloudplugins').'. '.__('When you reset the settings, the plugin will not longer be linked to your accounts, but their authorization will not be revoked', 'wpcloudplugins').'. '.__('You can revoke the authorization via the General tab', 'wpcloudplugins').'.<br/>';

        $box_button = "<div id='resetSettings_button' type='button' class='simple-button blue'/>".__('Reset Plugin', 'wpcloudplugins')."&nbsp;<div class='uyd-spinner'></div></div>";

        return "<div id='message'><div class='useyourdrive-option-description'>{$box_text}</div><p>{$box_button}</p></div>";
    }

    public function get_admin_notice($force = false)
    {
        if (version_compare(PHP_VERSION, '7.0') < 0) {
            echo '<div id="message" class="error"><p><strong>Use-your-Drive - Error: </strong>'.sprintf(__('You need at least PHP %s if you want to use this plugin', 'wpcloudplugins'), '7.0').'. '.
            __('You are using:', 'wpcloudplugins').' <u>'.phpversion().'</u></p></div>';
        } elseif (!function_exists('curl_init')) {
            echo '<div id="message" class="error"><p><strong>Use-your-Drive - Error: </strong>'.
            __("We are not able to connect to the API as you don't have the cURL PHP extension installed", 'wpcloudplugins').'. '.
            __('Please enable or install the cURL extension on your server', 'wpcloudplugins').'. '.
            '</p></div>';
        } elseif (class_exists('UYDGoogle_Client') && (!method_exists('UYDGoogle_Client', 'getLibraryVersion'))) {
            echo '<div id="message" class="error"><p><strong>Use-your-Drive - Error: </strong>'.
            __('We are not able to connect to the API as the plugin is interfering with an other plugin', 'wpcloudplugins').'. <br/><br/>'.
            __("The other plugin is using an old version of the Api-PHP-client that isn't capable of running multiple configurations", 'wpcloudplugins').'. '.
            __('Please disable this other plugin if you would like to use this plugin', 'wpcloudplugins').'. '.
            __("If you would like to use both plugins, ask the developer to update it's code", 'wpcloudplugins').'. '.
            '</p></div>';
        } elseif (!file_exists(USEYOURDRIVE_CACHEDIR) || !is_writable(USEYOURDRIVE_CACHEDIR)) {
            echo '<div id="message" class="error"><p><strong>Use-your-Drive - Error: </strong>'.sprintf(__('Cannot create the cache directory %s, or it is not writable', 'wpcloudplugins'), '<code>'.USEYOURDRIVE_CACHEDIR.'</code>').'. '.
            sprintf(__('Please check if the directory exists on your server and has %s writing permissions %s', 'wpcloudplugins'), '<a href="https://codex.wordpress.org/Changing_File_Permissions" target="_blank">', '</a>').'</p></div>';
        }
        if (!file_exists(USEYOURDRIVE_CACHEDIR.'/.htaccess')) {
            echo '<div id="message" class="error"><p><strong>Use-your-Drive - Error: </strong>'.sprintf(__('Cannot find .htaccess file in cache directory %s', 'wpcloudplugins'), '<code>'.USEYOURDRIVE_CACHEDIR.'</code>').'. '.
            sprintf(__('Please check if the file exists on your server or copy it from the %s folder', 'wpcloudplugins'), USEYOURDRIVE_ROOTDIR.'/cache').'</p></div>';
        }
    }

    public function get_admin_notice_not_authorized()
    {
        global $pagenow;
        if ('index.php' == $pagenow || 'plugins.php' == $pagenow) {
            if (current_user_can('manage_options') || current_user_can('edit_theme_options')) {
                $location = get_admin_url(null, 'admin.php?page=UseyourDrive_settings');

                foreach ($this->get_main()->get_accounts()->list_accounts() as $account_id => $account) {
                    if (false === $account->get_authorization()->has_access_token() || (false !== wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account_id]))) {
                        echo '<div id="message" class="error"><p><span class="dashicons dashicons-warning"></span>&nbsp;<strong>Use-your-Drive: </strong>'.sprintf(__("The plugin isn't longer linked to the %s account", 'wpcloudplugins'), '<strong>'.$account->get_email().'</strong>').'.</p>'.
                        "<p><a href='{$location}' class='button-primary'>".__('Refresh the authorization!', 'wpcloudplugins').'</a></p></div>';
                    }
                }
            }
        }
    }

    public function get_admin_notice_not_activated()
    {
        global $pagenow;

        if ($this->is_activated()) {
            return;
        }

        if ('index.php' == $pagenow || 'plugins.php' == $pagenow) {
            if (current_user_can('manage_options') || current_user_can('edit_theme_options')) {
                $location = get_admin_url(null, 'admin.php?page=UseyourDrive_settings');
                echo '<div id="message" class="error"><p><strong>Use-your-Drive: </strong>'.__('The plugin is not yet activated', 'wpcloudplugins').'. '.__('Please activate the plugin to manage the plugin settings', 'wpcloudplugins').'. '.
                "<a href='{$location}' class='button-primary'>".__('Activate the plugin!', 'wpcloudplugins').'</a></p></div>';
            }
        }
    }

    public function get_admin_notice_php_requirement()
    {
        // FOR FUTURE USE

        // global $pagenow;

        // if (version_compare(PHP_VERSION, '7') > 0) {
        //     return;
        // }

        // if ('index.php' == $pagenow || 'plugins.php' == $pagenow) {
        //     if (current_user_can('manage_options') || current_user_can('edit_theme_options')) {
        //         $location = 'https://wordpress.org/support/update-php/';
        //         echo '<div id="message" class="error"><p><strong>Use-your-Drive: </strong>'.__('The next major update will not longer be compatible with the PHP version your server is running', 'wpcloudplugins').'. '.__('You are using:', 'wpcloudplugins').' <u>'.phpversion().'</u>. '.__('Please upgrade your PHP version to at least PHP 7.0', 'wpcloudplugins').'.</p><p> '.
        //         "<a href='{$location}' target='_blank' class='button-primary'>".__('Learn move about updating PHP', 'wpcloudplugins').'</a></p></div>';
        //     }
        // }
    }

    public function check_for_updates()
    {
        // Updater
        $purchase_code = $this->get_purchase_code();

        if (!empty($purchase_code)) {
            require_once 'plugin-update-checker/plugin-update-checker.php';
            $updatechecker = \Puc_v4_Factory::buildUpdateChecker('https://www.wpcloudplugins.com/updates/?action=get_metadata&slug=use-your-drive&purchase_code='.$purchase_code.'&plugin_id='.$this->plugin_id, plugin_dir_path(__DIR__).'/use-your-drive.php');
        }
    }

    public function get_system_information()
    {
        // Figure out cURL version, if installed.
        $curl_version = '';
        if (function_exists('curl_version')) {
            $curl_version = curl_version();
            $curl_version = $curl_version['version'].', '.$curl_version['ssl_version'];
        } elseif (extension_loaded('curl')) {
            $curl_version = __('cURL installed but unable to retrieve version.', 'wpcloudplugins');
        }

        // WP memory limit.
        $wp_memory_limit = Helpers::return_bytes(WP_MEMORY_LIMIT);
        if (function_exists('memory_get_usage')) {
            $wp_memory_limit = max($wp_memory_limit, Helpers::return_bytes(@ini_get('memory_limit')));
        }

        // Return all environment info. Described by JSON Schema.
        $environment = [
            'home_url' => get_option('home'),
            'site_url' => get_option('siteurl'),
            'version' => USEYOURDRIVE_VERSION,
            'cache_directory' => USEYOURDRIVE_CACHEDIR,
            'cache_directory_writable' => (bool) @fopen(USEYOURDRIVE_CACHEDIR.'/test-cache.log', 'a'),
            'wp_version' => get_bloginfo('version'),
            'wp_multisite' => is_multisite(),
            'wp_memory_limit' => $wp_memory_limit,
            'wp_debug_mode' => (defined('WP_DEBUG') && WP_DEBUG),
            'wp_cron' => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
            'language' => get_locale(),
            'external_object_cache' => wp_using_ext_object_cache(),
            'server_info' => isset($_SERVER['SERVER_SOFTWARE']) ? wp_unslash($_SERVER['SERVER_SOFTWARE']) : '',
            'php_version' => phpversion(),
            'php_post_max_size' => Helpers::return_bytes(ini_get('post_max_size')),
            'php_max_execution_time' => ini_get('max_execution_time'),
            'php_max_input_vars' => ini_get('max_input_vars'),
            'curl_version' => $curl_version,
            'max_upload_size' => wp_max_upload_size(),
            'default_timezone' => date_default_timezone_get(),
            'curl_enabled' => (function_exists('curl_init') && function_exists('curl_exec')),
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'gzip_compression_enabled' => extension_loaded('zlib'),
            'mbstring_enabled' => extension_loaded('mbstring'),
            'flock' => (false === strpos(ini_get('disable_functions'), 'flock')),
            'zip_archive' => class_exists('ZipArchive'),
            'secure_connection' => is_ssl(),
            'openssl_encrypt' => (function_exists('openssl_encrypt') && in_array('aes-256-cbc', openssl_get_cipher_methods())),
            'hide_errors' => !(defined('WP_DEBUG') && defined('WP_DEBUG_DISPLAY') && WP_DEBUG && WP_DEBUG_DISPLAY) || 0 === intval(ini_get('display_errors')),
            'gravity_forms' => class_exists('GFForms'),
            'formidableforms' => class_exists('FrmAppHelper'),
            'gravity_pdf' => class_exists('GFPDF_Core'),
            'gravity_wpdatatables' => class_exists('WPDataTable'),
            'elementor' => defined('ELEMENTOR_VERSION'),
            'wpforms' => defined('WPFORMS_VERSION'),
            'contact_form_7' => defined('WPCF7_PLUGIN'),
            'woocommerce' => class_exists('WC_Integration'),
            'woocommerce_product_documents' => class_exists('WC_Product_Documents'),
        ];

        // Get Theme info
        $active_theme = wp_get_theme();

        // Get parent theme info if this theme is a child theme, otherwise
        // pass empty info in the response.
        if (is_child_theme()) {
            $parent_theme = wp_get_theme($active_theme->template);
            $parent_theme_info = [
                'parent_name' => $parent_theme->name,
                'parent_version' => $parent_theme->version,
                'parent_author_url' => $parent_theme->{'Author URI'},
            ];
        } else {
            $parent_theme_info = [
                'parent_name' => '',
                'parent_version' => '',
                'parent_version_latest' => '',
                'parent_author_url' => '',
            ];
        }

        $active_theme_info = [
            'name' => $active_theme->name,
            'version' => $active_theme->version,
            'author_url' => esc_url_raw($active_theme->{'Author URI'}),
            'is_child_theme' => is_child_theme(),
        ];

        $theme = array_merge($active_theme_info, $parent_theme_info);

        // Get Active plugins
        require_once ABSPATH.'wp-admin/includes/plugin.php';

        if (!function_exists('get_plugin_data')) {
            return [];
        }

        $active_plugins = (array) get_option('active_plugins', []);
        if (is_multisite()) {
            $network_activated_plugins = array_keys(get_site_option('active_sitewide_plugins', []));
            $active_plugins = array_merge($active_plugins, $network_activated_plugins);
        }

        $active_plugins_data = [];

        foreach ($active_plugins as $plugin) {
            $data = get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin);
            $active_plugins_data[] = [
                'plugin' => $plugin,
                'name' => $data['Name'],
                'version' => $data['Version'],
                'url' => $data['PluginURI'],
                'author_name' => $data['AuthorName'],
                'author_url' => esc_url_raw($data['AuthorURI']),
                'network_activated' => $data['Network'],
            ];
        }

        include sprintf('%s/templates/admin/system_information.php', USEYOURDRIVE_ROOTDIR);
    }

    private function _check_colors($colors, $old_colors)
    {
        $regex = '/(light|dark|transparent|#(?:[0-9a-f]{2}){2,4}|#[0-9a-f]{3}|(?:rgba?|hsla?)\((?:\d+%?(?:deg|rad|grad|turn)?(?:,|\s)+){2,3}[\s\/]*[\d\.]+%?\))/i';

        foreach ($colors as $color_id => &$color) {
            if (1 !== preg_match($regex, $color)) {
                $color = $old_colors[$color_id];
            }
        }

        return $colors;
    }

    private function _format_tagify_data($data, $field = 'id')
    {
        if (is_array($data)) {
            return $data;
        }

        $data_obj = json_decode($data);

        if (null === $data_obj) {
            return $data;
        }

        $new_data = [];

        foreach ($data_obj as $value) {
            $new_data[] = $value->{$field};
        }

        return $new_data;
    }
}

<?php

namespace TheLion\UseyourDrive;

class App
{
    /**
     * @var bool
     */
    private $_own_app = false;

    /**
     * @var string
     */
    private $_app_key = '538839470620-fvjmtsvik53h255bnu0qjmbr8kvd923i.apps.googleusercontent.com';

    /**
     * @var string
     */
    private $_app_secret = 'UZ1I3I-D4rPhXpnE8T1ggGhE';

    /**
     * @var string
     */
    private $_identifier;

    /**
     * @var \UYDGoogle_Service_Oauth2
     */
    private $_user_info_service;

    /**
     * @var \UYDGoogle_Service_Drive
     */
    private $_google_drive_service;

    /**
     * @var \UYDGoogle_Client
     */
    private $_client;

    /**
     * We don't save your data or share it.
     * This script just simply creates a redirect with your id and secret to Google Drive and returns the created token.
     * It is exactly the same script as the _authorizeApp.php file in the includes folder of the plugin,
     * and is used for an easy and one-click authorization process that will always work!
     *
     * @var string
     */
    private $_redirect_uri = 'https://www.wpcloudplugins.com/use-your-drive/index.php';

    /**
     * @var \TheLion\UseyourDrive\Processor
     */
    private $_processor;

    public function __construct(Processor $processor)
    {
        $this->_processor = $processor;

        // Call back for refresh token function in SDK client
        add_action('use-your-drive-refresh-token', [&$this, 'refresh_token'], 10, 1);

        if (!function_exists('uyd_google_api_php_client_autoload')) {
            require_once 'Google-sdk/src/Google/autoload.php';
        }

        if (!class_exists('UYDGoogle_Client') || (!method_exists('UYDGoogle_Client', 'getLibraryVersion'))) {
            $reflector = new \ReflectionClass('UYDGoogle_Client');
            $error = 'Conflict with other Google Library: '.$reflector->getFileName();

            throw new \Exception($error);
        }

        /** Preperation for new Google SDK
         * require_once USEYOURDRIVE_ROOTDIR . "/vendor/autoload.php";.
         */
        $own_key = $this->get_processor()->get_setting('googledrive_app_client_id');
        $own_secret = $this->get_processor()->get_setting('googledrive_app_client_secret');

        if (
                (!empty($own_key))
                && (!empty($own_secret))
        ) {
            $this->_app_key = $this->get_processor()->get_setting('googledrive_app_client_id');
            $this->_app_secret = $this->get_processor()->get_setting('googledrive_app_client_secret');
            $this->_own_app = true;
        }

        // Set right redirect URL
        $this->set_redirect_uri();

        // Process codes/tokens if needed
        $this->process_authorization();
    }

    public function process_authorization()
    {
        // CHECK IF THIS PLUGIN IS DOING THE AUTHORIZATION
        if (!isset($_REQUEST['action'])) {
            return false;
        }

        if ('useyourdrive_authorization' !== $_REQUEST['action']) {
            return false;
        }

        $this->get_processor()->reset_complete_cache();

        $redirect = admin_url('admin.php?page=UseyourDrive_settings');
        if (isset($_GET['network'])) {
            $redirect = network_admin_url('admin.php?page=UseyourDrive_network_settings');
        }

        if (isset($_GET['code'])) {
            $access_token = $this->create_access_token();
            // Echo To Popup
            echo '<script type="text/javascript">window.opener.parent.location.href = "'.$redirect.'"; window.close();</script>';

            exit();
        }
        if (isset($_GET['_token'])) {
            $new_access_token = $_GET['_token'];
            $access_token = $this->set_access_token($new_access_token);

            // Echo To Popup
            echo '<script type="text/javascript">window.opener.parent.location.href = "'.$redirect.'"; window.close();</script>';

            exit();
        }

        return false;
    }

    public function can_do_own_auth()
    {
        return true;
    }

    public function has_plugin_own_app()
    {
        return $this->_own_app;
    }

    public function get_auth_url()
    {
        return $this->get_client()->createAuthUrl();
    }

    /**
     * @return \UYDGoogle_Client
     */
    public function start_client(Account $account = null)
    {
        try {
            $this->_client = new \UYDGoogle_Client();
            $this->_client->getLibraryVersion();
        } catch (\Exception $ex) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Cannot start Google Client %s', $ex->getMessage()));

            return $ex;
        }

        /* NEW SDK
         *
         * $origin = isset($_SERVER["HTTP_ORIGIN"]) ? $_SERVER["HTTP_ORIGIN"] : null; // REQUIRED FOR CORS LIKE REQUEST (DIRECT UPLOAD)
         *
         * $this->client->setHttpClient(new \GuzzleHttp\Client(array(
         * 'verify' => USEYOURDRIVE_ROOTDIR . '/cacerts.pem',
         * 'headers' => array('Origin' => $origin)
         * )));
         */
        $this->_client->setApplicationName('WordPress Use-your-Drive '.USEYOURDRIVE_VERSION);
        $this->_client->setClientId($this->get_app_key());
        $this->_client->setClientSecret($this->get_app_secret());

        $this->_client->setRedirectUri($this->get_redirect_uri());
        $this->_client->setApprovalPrompt('force');
        $this->_client->setAccessType('offline');

        if (!empty($account)) {
            $this->_client->setLoginHint($account->get_email());
        }

        $this->_client->setScopes([
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
        ]);

        if ($this->get_processor()->is_network_authorized()) {
            $state = network_admin_url('admin.php?page=UseyourDrive_network_settings&action=useyourdrive_authorization&network=1');
        } else {
            $state = admin_url('admin.php?page=UseyourDrive_settings&action=useyourdrive_authorization');
        }

        $this->_client->setState(strtr(base64_encode($state), '+/=', '-_~'));

        $this->set_logger();

        if (null === $account) {
            return $this->_client;
        }

        $authorization = $account->get_authorization();

        if (false === $authorization->has_access_token()) {
            return $this->_client;
        }

        $access_token = $authorization->get_access_token();

        if (empty($access_token)) {
            return $this->_client;
        }

        $this->_client->setAccessToken($access_token);

        // Token function for new Google SDK
        // $this->client->setTokenCallback(array(&$this, 'storeToken'));

        // Check if the AccessToken is still valid
        if (false === $this->_client->isAccessTokenExpired()) {
            return $this->_client;
        }

        // If we end up here, we have to refresh the token
        return $this->refresh_token($account);
    }

    public function refresh_token(Account $account = null)
    {
        $authorization = $account->get_authorization();
        $access_token = $authorization->get_access_token();

        if (!flock($authorization->get_token_file_handle(), LOCK_EX | LOCK_NB)) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Wait till another process has renewed the Authorization Token'));

            /*
             * If the file cannot be unlocked and the last time
             * it was modified was 1 minute, assume that
             * the previous process died and unlock the file manually
             */
            $requires_unlock = ((filemtime($authorization->get_token_location()) + 60) < (time()));

            // Temporarily workaround when flock is disabled. Can cause problems when plugin is used in multiple processes
            if (false !== strpos(ini_get('disable_functions'), 'flock')) {
                $requires_unlock = false;
            }

            if ($requires_unlock) {
                $authorization->unlock_token_file();
            }

            if (flock($authorization->get_token_file_handle(), LOCK_SH)) {
                clearstatcache();
                rewind($authorization->get_token_file_handle());
                $access_token = fread($authorization->get_token_file_handle(), filesize($authorization->get_token_location()));
                error_log('[WP Cloud Plugin message]: '.sprintf('New Authorization Token has been received by another process'));
                $this->_client->setAccessToken($access_token);
                $authorization->unlock_token_file();

                return $this->_client;
            }
        }

        // Stop if we need to get a new AccessToken but somehow ended up without a refreshtoken
        $refresh_token = $this->_client->getRefreshToken();

        if (empty($refresh_token)) {
            error_log('[WP Cloud Plugin message]: '.sprintf('No Refresh Token found during the renewing of the current token. We will stop the authorization completely.'));
            $authorization->set_is_valid(false);
            $authorization->unlock_token_file();
            $this->revoke_token($account);

            return false;
        }

        // Refresh token
        try {
            $this->_client->refreshToken($refresh_token);

            // Store the new token
            $new_accestoken = $this->_client->getAccessToken();
            $authorization->set_access_token($new_accestoken);

            $authorization->unlock_token_file();

            if (false !== ($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]))) {
                wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]);
            }
        } catch (\Exception $ex) {
            $authorization->set_is_valid(false);
            $authorization->unlock_token_file();
            error_log('[WP Cloud Plugin message]: '.sprintf('Cannot refresh Authorization Token'));
            error_log($ex->getMessage());

            if (!wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()])) {
                wp_schedule_event(time(), 'daily', 'useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]);
            }

            $this->get_processor()->reset_complete_cache();

            throw $ex;
        }

        return $this->_client;
    }

    public function set_logger()
    {
        if ('Yes' === $this->get_processor()->get_setting('api_log')) {
            // Logger
            $this->get_client()->setClassConfig('UYDGoogle_Logger_File', [
                'file' => USEYOURDRIVE_CACHEDIR.'/api.log',
                'mode' => 0640,
                'lock' => true, ]);

            $this->get_client()->setClassConfig('UYDGoogle_Logger_Abstract', [
                'level' => 'debug', //'warning' or 'debug'
                'log_format' => "[%datetime%] %level%: %message% %context%\n",
                'date_format' => 'd/M/Y:H:i:s O',
                'allow_newlines' => true, ]);

            /* Uncomment the following line to log communcations.
             * The log is located in /cache/log
             */

            $this->get_client()->setLogger(new \UYDGoogle_Logger_File($this->get_client()));
        }

        /* Logger for new Google SDK
         *
         * $logger = new Monolog\Logger('google-api-php-client');
         * $logger->pushHandler(new Monolog\Handler\StreamHandler(USEYOURDRIVE_CACHEDIR . '/api.log', Monolog\Logger::DEBUG));
         * $this->client->setLogger($logger);
         */
    }

    public function create_access_token()
    {
        try {
            $code = $_REQUEST['code'];
            $state = $_REQUEST['state'];

            //Fetch the Access Token
            $access_token = $this->get_client()->authenticate($code);

            // Get & Update User Information
            $account_data = $this->get_user()->userinfo->get();
            $account = new Account($account_data->getId(), $account_data->getName(), $account_data->getEmail(), $account_data->getPicture());
            $account->get_authorization()->set_access_token($access_token);
            $account->get_authorization()->unlock_token_file();
            $this->get_accounts()->add_account($account);

            delete_transient('useyourdrive_'.$account->get_id().'_is_authorized');
        } catch (\Exception $ex) {
            error_log('[WP Cloud Plugin message]: '.sprintf('Cannot generate Access Token: %s', $ex->getMessage()));

            return new \WP_Error('broke', __('Error communicating with API:', 'wpcloudplugins').$ex->getMessage());
        }

        return true;
    }

    public function revoke_token(Account $account)
    {
        error_log('[WP Cloud Plugin message]: '.'Lost authorization');

        // Reset Private Folders Back-End if the account it is pointing to is deleted
        $private_folders_data = $this->get_processor()->get_setting('userfolder_backend_auto_root', []);
        if (is_array($private_folders_data) && isset($private_folders_data['account']) && $private_folders_data['account'] === $account->get_id()) {
            $this->get_processor()->set_setting('userfolder_backend_auto_root', null);
        }

        $this->get_processor()->reset_complete_cache();

        if (false !== ($timestamp = wp_next_scheduled('useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]))) {
            wp_unschedule_event($timestamp, 'useyourdrive_lost_authorisation_notification', ['account_id' => $account->get_id()]);
        }

        $this->get_processor()->get_main()->send_lost_authorisation_notification($account->get_id());

        try {
            $this->get_client()->revokeToken();
        } catch (\Exception $ex) {
            error_log('[WP Cloud Plugin message]: '.$ex->getMessage());
        }

        $this->get_accounts()->remove_account($account->get_id());

        delete_transient('useyourdrive_'.$account->get_id().'_is_authorized');

        return true;
    }

    // Token function for new Google SDK

    public function get_app_key()
    {
        return $this->_app_key;
    }

    public function get_app_secret()
    {
        return $this->_app_secret;
    }

    public function set_app_key($_app_key)
    {
        $this->_app_key = $_app_key;
    }

    public function set_app_secret($_app_secret)
    {
        $this->_app_secret = $_app_secret;
    }

    /**
     * @return \TheLion\UseyourDrive\Processor
     */
    public function get_processor()
    {
        return $this->_processor;
    }

    /**
     * @return \UYDGoogle_Client
     */
    public function get_client()
    {
        if (empty($this->_client)) {
            $this->_client = $this->start_client();
        }

        return $this->_client;
    }

    /**
     * @return \TheLion\UseyourDrive\Accounts
     */
    public function get_accounts()
    {
        return $this->get_processor()->get_main()->get_accounts();
    }

    /**
     * @return \UYDGoogle_Service_Oauth2
     */
    public function get_user()
    {
        if (empty($this->_user_info_service)) {
            $client = $this->get_client();
            $this->_user_info_service = new \UYDGoogle_Service_Oauth2($client);
        }

        return $this->_user_info_service;
    }

    /**
     * @return \UYDGoogle_Service_Drive
     */
    public function get_drive()
    {
        if (empty($this->_google_drive_service)) {
            $client = $this->get_client();
            $this->_google_drive_service = new \UYDGoogle_Service_Drive($client);
        }

        return $this->_google_drive_service;
    }

    public function get_redirect_uri()
    {
        return $this->_redirect_uri;
    }

    public function set_redirect_uri()
    {
        // Only change it if you are using own app
        if ($this->has_plugin_own_app()) {
            $this->_redirect_uri = USEYOURDRIVE_ROOTPATH.'/includes/_authorizeApp.php';
        }
    }
}

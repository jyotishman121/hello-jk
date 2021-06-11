<?php

namespace TheLion\UseyourDrive;

class Accounts
{
    /**
     * $_accounts contains all the accounts that are linked with the plugin.
     *
     * @var \TheLion\UseyourDrive\Account[]
     */
    private $_accounts = [];

    /**
     * @var \TheLion\UseyourDrive\Main
     */
    private $_main;

    /**
     * Are the accounts managed on Network level or per blog.
     *
     * @var bool
     */
    private $_use_network_accounts = false;

    public function __construct(Main $main)
    {
        $this->_main = $main;
        $this->_use_network_accounts = $this->get_processor()->is_network_authorized();

        $this->_init_accounts();
    }

    /**
     * @return bool
     */
    public function has_accounts()
    {
        return count($this->_accounts) > 0;
    }

    /**
     * @return \TheLion\UseyourDrive\Account[]
     */
    public function list_accounts()
    {
        return $this->_accounts;
    }

    /**
     * @return null|\TheLion\UseyourDrive\Account
     */
    public function get_primary_account()
    {
        if (0 === count($this->_accounts)) {
            return null;
        }

        return reset($this->_accounts);
    }

    /**
     * @param string $id
     *
     * @return null|\TheLion\UseyourDrive\Account
     */
    public function get_account_by_id($id)
    {
        if (false === isset($this->_accounts[(string) $id])) {
            return null;
        }

        return $this->_accounts[(string) $id];
    }

    /**
     * @param string $id
     * @param mixed  $email
     *
     * @return null|\TheLion\UseyourDrive\Account
     */
    public function get_account_by_email($email)
    {
        foreach ($this->_accounts as $account) {
            if ($account->get_email() === $email) {
                return $account;
            }
        }

        return null;
    }

    /**
     * @param \TheLion\UseyourDrive\Account $account
     *
     * @return $this
     */
    public function add_account(Account $account)
    {
        $this->_accounts[$account->get_id()] = $account;

        $this->save();

        return $this;
    }

    /**
     * @param string $account_id
     *
     * @return $this
     */
    public function remove_account($account_id)
    {
        $account = $this->get_account_by_id($account_id);

        if (null === $account) {
            return;
        }

        $account->get_authorization()->remove_token();

        unset($this->_accounts[$account_id]);

        $this->save();

        return $this;
    }

    /**
     * Function run once when upgrading from versions not supporting multiple accounts.
     */
    public function upgrade_from_single()
    {
        //require_once("wp-load.php");
        require_once ABSPATH.'wp-includes/pluggable.php';

        // Update Events database, add account_id column
        $this->get_main()->get_events()->install_database();

        // Process per blog
        $blog_id = get_current_blog_id();
        if ($this->_use_network_accounts) {
            $token_path = USEYOURDRIVE_CACHEDIR.'/network.access_token';
            $token_name = 'network.access_token';
        } else {
            $token_path = USEYOURDRIVE_CACHEDIR."/{$blog_id}.access_token";
            $token_name = "{$blog_id}.access_token";
        }

        if (false === file_exists($token_path)) {
            //Blog doesn't have an active authorization
            return;
        }

        // Create account with temporarily data
        $account = new Account($blog_id, '', $blog_id);
        $account->get_authorization()->set_token_name($token_name);
        $this->get_processor()->set_current_account($account);

        // Load Client for this account
        try {
            $client = $this->get_main()->get_app()->start_client($account);
        } catch (\Exception $ex) {
            @unlink($token_path);

            return;
        }

        // Get & Update User Information
        try {
            $account_data = $this->get_main()->get_processor()->get_client()->get_account_info();
        } catch (\Exception $ex) {
            @unlink($token_path);

            return;
        }

        $account->set_id($account_data->getId());
        $account->set_name($account_data->getName());
        $account->set_email($account_data->getEmail());
        $account->set_image($account_data->getPicture());

        // Create new token file
        $authorization = $account->get_authorization();
        $access_token = $authorization->get_access_token();
        $authorization->set_account_id($account->get_id());
        $authorization->set_token_name(Helpers::filter_filename($account->get_email().'_'.$account->get_id(), false).'.access_token');
        $authorization->set_access_token($access_token);
        $authorization->unlock_token_file();

        // Remove old token file
        @unlink($token_path);

        // Add Account to DB
        $this->add_account($account);

        // Update all Manually linked folders
        $users = get_users(['fields' => ['ID'], 'blog_id' => $blog_id]);

        // Manually linked folders for users
        foreach ($users as $user) {
            $manually_linked_data = get_user_option('use_your_drive_linkedto', $user->ID);

            if (false === $manually_linked_data) {
                continue;
            }

            $manually_linked_data['accountid'] = $account->get_id();
            update_user_option($user->ID, 'use_your_drive_linkedto', $manually_linked_data, false);
        }

        // Manually linked folder for guests (currently stored on network level)
        $manually_linked_guests_data = get_site_option('use_your_drive_guestlinkedto');
        if (false !== $manually_linked_guests_data) {
            $manually_linked_guests_data['accountid'] = $account->get_id();
            update_site_option('use_your_drive_guestlinkedto', $manually_linked_guests_data);
        }

        $this->get_processor()->clear_current_account();
        $this->get_processor()->reset_complete_cache();
    }

    public function save()
    {
        if ($this->_use_network_accounts) {
            $this->get_processor()->set_network_setting('accounts', $this->_accounts);
        } else {
            $this->get_processor()->set_setting('accounts', $this->_accounts);
        }
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
        return $this->get_main()->get_processor();
    }

    private function _init_accounts()
    {
        if ($this->_use_network_accounts) {
            $this->_accounts = $this->get_processor()->get_network_setting('accounts', []);
        } else {
            $this->_accounts = $this->get_processor()->get_setting('accounts', []);
        }
    }
}

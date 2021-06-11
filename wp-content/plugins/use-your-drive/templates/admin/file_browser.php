<div class="useyourdrive admin-settings">

  <div class="useyourdrive-header">
<div class="useyourdrive-logo"><a href="https://www.wpcloudplugins.com" target="_blank"><img src="<?php echo USEYOURDRIVE_ROOTPATH; ?>/css/images/wpcp-logo-dark.svg" height="64" width="64"/></a></div>
    <div class="useyourdrive-title"><?php _e('File Browser', 'wpcloudplugins'); ?></div>
  </div>

  <div class="useyourdrive-panel useyourdrive-panel-full">
    <?php
    $processor = $this->get_processor();
    $params = ['singleaccount' => '0',
        'mode' => 'files',
        'dir' => 'drive',
        'viewrole' => 'all',
        'downloadrole' => 'all',
        'uploadrole' => 'all',
        'upload' => '1',
        'rename' => '1',
        'delete' => '1',
        'addfolder' => '1',
        'createdocument' => '1',
        'edit' => '1',
        'move' => '1',
        'copy' => '1',
        'candownloadzip' => '1',
        'showsharelink' => '1',
        'deeplink' => '1',
        'searchcontents' => '1',
        'editdescription' => '1', ];

    $user_folder_backend = apply_filters('useyourdrive_use_user_folder_backend', $processor->get_setting('userfolder_backend'));

    if ('No' !== $user_folder_backend) {
        $params['userfolders'] = $user_folder_backend;

        $private_root_folder = $processor->get_setting('userfolder_backend_auto_root');
        if ('auto' === $user_folder_backend && !empty($private_root_folder) && isset($private_root_folder['id'])) {
            if (!isset($private_root_folder['account']) || empty($private_root_folder['account'])) {
                $main_account = $this->get_processor()->get_accounts()->get_primary_account();
                $params['account'] = $main_account->get_id();
            } else {
                $params['account'] = $private_root_folder['account'];
            }

            $params['dir'] = $private_root_folder['id'];

            if (!isset($private_root_folder['view_roles']) || empty($private_root_folder['view_roles'])) {
                $private_root_folder['view_roles'] = ['none'];
            }
            $params['viewuserfoldersrole'] = implode('|', $private_root_folder['view_roles']);
        }
    }

    $params = apply_filters('useyourdrive_set_shortcode_filebrowser_backend', $params);

    echo $processor->create_from_shortcode($params);
    ?>
  </div>
</div>
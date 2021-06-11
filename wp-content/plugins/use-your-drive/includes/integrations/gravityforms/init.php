<?php

class GFUseyourDriveAddOn extends GFAddOn
{
    protected $_version = '1.0';
    protected $_min_gravityforms_version = '1.9';
    protected $_slug = 'useyourdriveaddon';
    protected $_path = 'use-your-drive/includes/integrations/gravityforms.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Gravity Forms Use-your-Drive Add-On';
    protected $_short_title = 'Use-your-Drive Add-On';

    public function init()
    {
        parent::init();

        if (isset($this->_min_gravityforms_version) && !$this->is_gravityforms_supported($this->_min_gravityforms_version)) {
            return;
        }

        // Add a Use-your-Drive button to the advanced to the field editor
        add_filter('gform_add_field_buttons', [$this, 'useyourdrive_field']);
        add_filter('admin_enqueue_scripts', [$this, 'useyourdrive_extra_scripts']);

        // Now we execute some javascript technicalitites for the field to load correctly
        add_action('gform_editor_js', [$this, 'gform_editor_js']);
        add_filter('gform_field_input', [$this, 'useyourdrive_input'], 10, 5);

        // Add a custom setting to the field
        add_action('gform_field_standard_settings', [$this, 'useyourdrive_settings'], 10, 2);

        // Adds title to the custom field
        add_filter('gform_field_type_title', [$this, 'useyourdrive_title'], 10, 2);

        // Filter to add the tooltip for the field
        add_filter('gform_tooltips', [$this, 'add_useyourdrive_tooltips']);

        // Save some data for this field
        add_filter('gform_field_validation', [$this, 'useyourdrive_validation'], 10, 4);

        // Display values in a proper way
        add_filter('gform_entry_field_value', [$this, 'useyourdrive_entry_field_value'], 10, 4);
        add_filter('gform_entries_field_value', [$this, 'useyourdrive_entries_field_value'], 10, 4);
        add_filter('gform_merge_tag_filter', [$this, 'useyourdrive_merge_tag_filter'], 10, 5);

        // Add support for wpDataTables <> Gravity Form integration
        if (class_exists('WPDataTable')) {
            add_action('wpdatatables_before_get_table_metadata', [$this, 'render_wpdatatables_field'], 10, 1);
        }

        // Custom Private Folder names
        add_filter('useyourdrive_private_folder_name', [&$this, 'new_private_folder_name'], 10, 2);
        add_filter('useyourdrive_private_folder_name_guests', [&$this, 'rename_private_folder_names_for_guests'], 10, 2);
    }

    public function useyourdrive_extra_scripts()
    {
        if (GFForms::is_gravity_page()) {
            add_thickbox();

            wp_enqueue_style('WPCP-GravityForms', plugins_url('style.css', __FILE__));
        }
    }

    public function useyourdrive_field($field_groups)
    {
        foreach ($field_groups as &$group) {
            if ('advanced_fields' == $group['name']) {
                $group['fields'][] = [
                    'class' => 'button',
                    'value' => 'Use-your-Drive',
                    'date-type' => 'useyourdrive',
                    'onclick' => "StartAddField('useyourdrive');",
                ];

                break;
            }
        }

        return $field_groups;
    }

    public function gform_editor_js()
    {
        ?>
        <script type='text/javascript'>
            jQuery(document).ready(function ($) {
              /* Which settings field should be visible for our custom field*/
              fieldSettings["useyourdrive"] = ".label_setting, .description_setting, .admin_label_setting, .error_message_setting, .css_class_setting, .visibility_setting, .rules_setting, .label_placement_setting, .useyourdrive_setting, .conditional_logic_field_setting, .conditional_logic_page_setting, .conditional_logic_nextbutton_setting"; //this will show all the fields of the Paragraph Text field minus a couple that I didn't want to appear.

              /* binding to the load field settings event to initialize */
              $(document).on("gform_load_field_settings", function (event, field, form) {
                if (field["UseyourdriveShortcode"] !== undefined && field["UseyourdriveShortcode"] !== '') {
                  jQuery("#field_useyourdrive").val(field["UseyourdriveShortcode"]);
                } else {
                  /* Default value */
                  var defaultvalue = '[useyourdrive class="gf_upload_box" mode="upload" upload="1" uploadrole="all" upload_auto_start="0" userfolders="auto" viewuserfoldersrole="none"]';
                  jQuery("#field_useyourdrive").val(defaultvalue);
                }
              });

              /* Shortcode Generator Popup */
              $('.UseyourDrive-GF-shortcodegenerator').click(function () {
                var shortcode = jQuery("#field_useyourdrive").val();
                shortcode = shortcode.replace('[useyourdrive ', '').replace('"]', '');
                var query = encodeURIComponent(shortcode).split('%3D%22').join('=').split('%22%20').join('&');
                tb_show("Build Shortcode for Form", ajaxurl + '?action=useyourdrive-getpopup&' + query + '&type=shortcodebuilder&asuploadbox=1&callback=wpcp_uyd_gf_add_content&TB_iframe=true&height=600&width=800');
              });

                /* Callback function to add shortcode to GF field */
                if (typeof window.wpcp_uyd_gf_add_content === 'undefined') {
                    window.wpcp_uyd_gf_add_content = function (data) {
                        $('#field_useyourdrive').val(data);
                        SetFieldProperty('UseyourdriveShortcode', data);

                        tb_remove();
                    }
                }
            });

            function SetDefaultValues_useyourdrive(field) {
              field.label = '<?php _e('Upload your Files', 'wpcloudplugins'); ?>';
            }
        </script>
        <?php
    }

    public function useyourdrive_input($input, $field, $value, $lead_id, $form_id)
    {
        if ('useyourdrive' == $field->type) {
            if (!$this->is_form_editor()) {
                $return = do_shortcode($field->UseyourdriveShortcode);
                $return .= "<input type='hidden' name='input_".$field->id."' id='input_".$form_id.'_'.$field->id."'  class='fileupload-filelist fileupload-input-filelist' value='".(isset($_REQUEST['input_'.$field->id]) ? stripslashes($_REQUEST['input_'.$field->id]) : '')."'>";

                return $return;
            }

            return '<div class="wpcp-wpforms-placeholder"></div>';
        }

        return $input;
    }

    public function useyourdrive_settings($position, $form_id)
    {
        if (1430 == $position) {
            ?>
            <li class="useyourdrive_setting field_setting">
              <label for="field_useyourdrive">Use-your-Drive Shortcode <?php echo gform_tooltip('form_field_useyourdrive'); ?></label>
              <a href="#" class='button-primary UseyourDrive-GF-shortcodegenerator '><?php _e('Build your shortcode', 'wpcloudplugins'); ?></a>
              <textarea id="field_useyourdrive" class="fieldwidth-3 fieldheight-2" onchange="SetFieldProperty('UseyourdriveShortcode', this.value)"></textarea>
              <br/><small>Missing a Use-your-Drive Gravity Form feature? Please let me <a href="https://florisdeleeuwnl.zendesk.com/hc/en-us/requests/new" target="_blank">know</a>!</small>
            </li>
            <?php
        }
    }

    public function useyourdrive_title($title, $field_type)
    {
        if ('useyourdrive' === $field_type) {
            return 'Use-your-Drive'.__('Upload', 'wpcloudplugins');
        }

        return $title;
    }

    public function add_useyourdrive_tooltips($tooltips)
    {
        $tooltips['form_field_useyourdrive'] = '<h6>Use-your-Drive Shortcode</h6>'.__('Build your shortcode here', 'wpcloudplugins');

        return $tooltips;
    }

    public function useyourdrive_validation($result, $value, $form, $field)
    {
        if ('useyourdrive' !== $field->type) {
            return $result;
        }

        if (false === $field->isRequired) {
            return $result;
        }

        // Get information uploaded files from hidden input
        $filesinput = rgpost('input_'.$field->id);
        $uploadedfiles = json_decode($filesinput);

        if (empty($uploadedfiles)) {
            $result['is_valid'] = false;
            $result['message'] = __('This field is required. Please upload your files.', 'gravityforms');
        } else {
            $result['is_valid'] = true;
            $result['message'] = '';
        }

        return $result;
    }

    public function useyourdrive_entry_field_value($value, $field, $lead, $form)
    {
        if ('useyourdrive' !== $field->type) {
            return $value;
        }

        return $this->renderUploadedFiles(html_entity_decode($value));
    }

    public function render_wpdatatables_field($tableId)
    {
        add_filter('gform_get_input_value', [$this, 'useyourdrive_get_input_value'], 10, 4);
    }

    public function useyourdrive_get_input_value($value, $entry, $field, $input_id)
    {
        if ('useyourdrive' !== $field->type) {
            return $value;
        }

        return $this->renderUploadedFiles(html_entity_decode($value));
    }

    public function useyourdrive_entries_field_value($value, $form_id, $field_id, $entry)
    {
        $form = GFFormsModel::get_form_meta($form_id);

        if (is_array($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if ('useyourdrive' === $field->type && $field_id == $field->id) {
                    if (!empty($value)) {
                        return $this->renderUploadedFiles(html_entity_decode($value));
                    }
                }
            }
        }

        return $value;
    }

    public function useyourdrive_set_export_values($value, $form_id, $field_id, $lead)
    {
        $form = GFFormsModel::get_form_meta($form_id);

        if (is_array($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if ('useyourdrive' === $field->type && $field_id == $field->id) {
                    return $this->renderUploadedFiles(html_entity_decode($value), false);
                }
            }
        }

        return $value;
    }

    public function useyourdrive_merge_tag_filter($value, $merge_tag, $modifier, $field, $rawvalue)
    {
        if ('useyourdrive' == $field->type) {
            return $this->renderUploadedFiles(html_entity_decode($value));
        }

        return $value;
    }

    public function renderUploadedFiles($data, $ashtml = true)
    {
        return apply_filters('useyourdrive_render_formfield_data', $data, $ashtml, $this);
    }

    /**
     * Function to change the Private Folder Name.
     *
     * @param string                          $private_folder_name
     * @param \TheLion\UseyourDrive\Processor $processor
     *
     * @return string
     */
    public function new_private_folder_name($private_folder_name, $processor)
    {
        if (!isset($_COOKIE['WPCP-FORM-NAME-'.$processor->get_listtoken()])) {
            return $private_folder_name;
        }

        if ('gf_upload_box' !== $processor->get_shortcode_option('class')) {
            return $private_folder_name;
        }

        $raw_name = sanitize_text_field($_COOKIE['WPCP-FORM-NAME-'.$processor->get_listtoken()]);
        $name = str_replace(['|', '/'], ' ', $raw_name);
        $filtered_name = \TheLion\UseyourDrive\Helpers::filter_filename(stripslashes($name), false);

        return trim($filtered_name);
    }

    /**
     * Function to change the Private Folder Name for Guest users.
     *
     * @param string                          $private_folder_name_guest
     * @param \TheLion\UseyourDrive\Processor $processor
     *
     * @return string
     */
    public function rename_private_folder_names_for_guests($private_folder_name_guest, $processor)
    {
        if ('gf_upload_box' !== $processor->get_shortcode_option('class')) {
            return $private_folder_name_guest;
        }

        return str_replace(__('Guests', 'wpcloudplugins').' - ', '', $private_folder_name_guest);
    }
}

GFForms::include_addon_framework();

$GFUseyourDriveAddOn = new GFUseyourDriveAddOn();
// This filter isn't fired if inside class
add_filter('gform_export_field_value', [$GFUseyourDriveAddOn, 'useyourdrive_set_export_values'], 10, 4);

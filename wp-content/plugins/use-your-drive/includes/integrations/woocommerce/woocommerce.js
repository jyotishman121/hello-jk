jQuery(function ($) {
  var wc_useyourdrive = {
    // hold a reference to the last selected Google Drive button
    lastSelectedButton: false,

    init: function () {
      // add button for simple product
      this.addButtons();
      this.addButtonEventHandler();
      // add buttons when variable product added
      $('#variable_product_options').on('woocommerce_variations_added', function () {
        wc_useyourdrive.addButtons();
        wc_useyourdrive.addButtonEventHandler();
      });
      // add buttons when variable products loaded
      $('#woocommerce-product-data').on('woocommerce_variations_loaded', function () {
        wc_useyourdrive.addButtons();
        wc_useyourdrive.addButtonEventHandler();
      });

      return this;
    },

    addButtons: function () {
      var self = this;

      var button = $('<a href="#TB_inline?height=100%&amp;width=800&amp;inlineId=uyd-embedded" class="button insert-googledrive thickbox">' + useyourdrive_woocommerce_translation.choose_from + '</a>');
      $('.downloadable_files').each(function (index) {

        // we want our button to appear next to the insert button
        var insertButton = $(this).find('a.button.insert');
        // check if button already exists on element, bail if so
        if ($(this).find('a.button.insert-googledrive').length > 0) {
          return;
        }

        // finally clone the button to the right place
        insertButton.after(button.clone());

      });

      /* START Support for WooCommerce Product Documents */

      $('.wc-product-documents .button.wc-product-documents-set-file').each(function (index) {

        // check if button already exists on element, bail if so
        if ($(this).parent().find('a.button.insert-googledrive').length > 0) {
          return;
        }

        // finally clone the button to the right place
        $(this).after(button.clone());

      });


      $('#wc-product-documents-data').on('click', '.wc-product-documents-add-document', function () {
        self.addButtons();
      });
      /* END Support for WooCommerce Product Documents */

    },
    /**
     * Adds the click event to the buttons
     * and opens the Google Drive chooser
     */
    addButtonEventHandler: function () {
      $('#woocommerce-product-data').on('click', 'a.button.insert-googledrive', function (e) {
        e.preventDefault();

        // save a reference to clicked button
        wc_useyourdrive.lastSelectedButton = $(this);

      });
    },
    /**
     * Handle selected files
     */
    afterFileSelected: function (id, name, account_id) {

      if ($(wc_useyourdrive.lastSelectedButton).closest('.downloadable_files').length > 0) {

        var table = $(wc_useyourdrive.lastSelectedButton).closest('.downloadable_files').find('tbody');
        var template = $(wc_useyourdrive.lastSelectedButton).parent().find('.button.insert:first').data("row");
        var fileRow = $(template);

        fileRow.find('.file_name > input:first').val(name).change();
        fileRow.find('.file_url > input:first').val(useyourdrive_woocommerce_translation.download_url + id + '&account_id=' + account_id);
        table.append(fileRow);

        // trigger change event so we can save variation
        $(table).find('input').last().change();

      }

      /* START Support for WooCommerce Product Documents */
      if ($(wc_useyourdrive.lastSelectedButton).closest('.wc-product-document').length > 0) {


        var row = $(wc_useyourdrive.lastSelectedButton).closest('.wc-product-document');

        row.find('.wc-product-document-label input:first').val(name).change();
        row.find('.wc-product-document-file-location input:first').val(useyourdrive_woocommerce_translation.wcpd_url + id + '&account_id=' + account_id);
      }
      /* END Support for WooCommerce Product Documents */


    }

  };
  window.wc_useyourdrive = wc_useyourdrive.init();

  /* Callback function to add shortcode to WC field */
  if (typeof window.wpcp_uyd_wc_add_content === 'undefined') {
    window.wpcp_uyd_wc_add_content = function (data) {
      $('#useyourdrive_upload_box_shortcode').val(data);
      tb_remove();
    }
  }
});
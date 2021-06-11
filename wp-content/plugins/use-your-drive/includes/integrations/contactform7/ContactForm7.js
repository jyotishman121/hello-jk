jQuery(document).ready(function ($) {
  'use strict';

  $("body").on("change", ".useyourdrive-shortcode-value", function () {
    var decoded_shortcode = decodeURIComponent(escape(window.atob($(this).val())));
    $('#useyourdrive-shortcode-decoded-value').val(decoded_shortcode).css('display', 'block');
  });

  $("body").on("keyup", "#useyourdrive-shortcode-decoded-value", function () {
    var encoded_data = window.btoa(unescape(encodeURIComponent($(this).val())));
    $(".useyourdrive-shortcode-value", "body").val(encoded_data)
    $('.useyourdrive-shortcode-value').trigger('change');
  });

  var default_value = '[useyourdrive class="cf7_upload_box" mode="upload" upload="1" uploadrole="all" viewrole="all" upload_auto_start="0" userfolders="auto" viewuserfoldersrole="none"]';
  var encoded_data = window.btoa(unescape(encodeURIComponent(default_value)));
  $(".useyourdrive-shortcode-value", "body").val(encoded_data).trigger('change');

  // Callback function to add shortcode to CF7 input field
  if (typeof window.wpcp_uyd_cf7_add_content === 'undefined') {
    window.wpcp_uyd_cf7_add_content = function (data) {
      var encoded_data = window.btoa(unescape(encodeURIComponent(data)));

      $('.useyourdrive-shortcode-value').val(encoded_data);
      $('.useyourdrive-shortcode-value').trigger('change');

      if (data.indexOf('userfolders="auto"') > -1) {
        $('.use-your-drive-upload-folder').fadeIn();
      } else {
        $('.use-your-drive-upload-folder').fadeOut();
      }

      window.modal_action.close();
    }
  }

  // Modal opening Shortcode Builder
  $("body").on("click", ".UseyourDrive-CF-shortcodegenerator", function () {

    if ($('#useyourdrive-modal-action').length > 0) {
      window.modal_action.open();
      return true;
    }

    /* Build the Insert Dialog */
    var modalbuttons = '';
    var modalheader = $('<a tabindex="0" class="close-button" title="" onclick="modal_action.close();"><i class="fas fa-times fa-lg" aria-hidden="true"></i></a></div>');
    var modalbody = $('<div class="useyourdrive-modal-body" tabindex="0" style="display:none"></div>');
    var modalfooter = $('<div class="useyourdrive-modal-footer" style="display:none"><div class="useyourdrive-modal-buttons">' + '' + '</div></div>');
    var modaldialog = $('<div id="useyourdrive-modal-action" class="UseyourDrive useyourdrive-modal useyourdrive-modal80 light"><div class="modal-dialog"><div class="modal-content"><div class="loading"><div class="loader-beat"></div></div></div></div></div>');

    $('body').append(modaldialog);

    var $iframe_template = $('#useyourdrive-shortcode-iframe');
    var $iframe = $iframe_template.clone().appendTo(modalbody).show();

    $('#useyourdrive-modal-action .modal-content').append(modalheader, modalbody, modalfooter);

    var shortcode = $('#useyourdrive-shortcode-decoded-value', 'body').val()
    var shortcode_attr = shortcode.replace('</p>', '').replace('<p>', '').replace('[useyourdrive ', '').replace('"]', '');
    var query = encodeURIComponent(shortcode_attr).split('%3D%22').join('=').split('%22%20').join('&');

    $iframe.attr('src', $iframe_template.attr('data-src') + '&' + query);

    $iframe.on('load', function () {
      $('.useyourdrive-modal-body').fadeIn();
      $('.useyourdrive-modal-footer').fadeIn();
      $('.modal-content .loading:first').fadeOut();
    });

    /* Open the Dialog and load the images inside it */
    var modal_action = new RModal(document.getElementById('useyourdrive-modal-action'), {
      bodyClass: 'rmodal-open',
      dialogOpenClass: 'animated slideInDown',
      dialogCloseClass: 'animated slideOutUp',
      escapeClose: true
    });
    document.addEventListener('keydown', function (ev) {
      modal_action.keydown(ev);
    }, false);
    modal_action.open();
    window.modal_action = modal_action;

  });
});
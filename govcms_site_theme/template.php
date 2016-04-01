<?php
/**
 * @file
 * Contains the theme's functions to manipulate Drupal's default markup.
 *
 * Complete documentation for this file is available online.
 * @see https://drupal.org/node/1728096
 */

function govcms_site_theme_form_alter(&$form, &$form_state, $form_id) {
  // Check if we are dealing with Easy Bake webform
  $is_node = array_key_exists('#node', $form);
  $is_webform = $is_node && $form['#node']->type == "webform";
  $is_easybake_form = $is_webform && $form['#node']->machine_name == "EasyBake";
  if ($is_easybake_form) {
    // In case of AJAX call we need to add values Drupal.settings
    _push_ezbake_settings_to_js($form);

    // displays a drupal error if there is a GET param for error
    // and fill in form with values
    // @see https://govcms.atlassian.net/wiki/display/EZB/Baker+API for response types
    $query_params = drupal_get_query_parameters();
    if (!empty($query_params['error'])) {
      $error_msg = "Error: " . $query_params['error'];

      // read details (input as dot notation, e.g, details.message
      // but . is replaced by _ in PHP)
      if (!empty($query_params['details_message'])) {
        $error_msg .= "<br>Details: " . $query_params['details_message'];
      }

      // fill in the form fields
      $fields = array('contact_name', 'contact_email', 'contact_phone', 'site_name', 'agency_name', 'website_purpose');
      foreach ($fields as $field) {
        $query_field_name = "details_form_values_" . $field;
        if (!empty($query_params[$query_field_name])) {
          $form['submitted'][$field]['#default_value'] = $query_params[$query_field_name];
        }
      }

      if (!empty($query_params['details_field'])) {
        form_set_error($query_params['details_field'], $error_msg);
      }
      else {
        drupal_set_message($error_msg, 'error');
      }
    }

    $form['#attributes']['name'] = 'easybake-order-form';
    $form['submitted']['#tree'] = False;
    $form['#action'] = variable_get('ezbake_baker_url') . '/order/submit?redirect=true';
  }
}

/*
 * Helper function to construct settings array to be passed to Drupal.settings
 * in order to execute and AJAX call
 *
 * @form
 * EasyBake webform
 */
function _push_ezbake_settings_to_js(&$form) {
  // Initialise JS settings array
  $ezbake_settings = array();

  // Initialise flag to disable submit button if needed
  $disable_submit = FALSE;

  // Get the Baker URL
  $baker_url = variable_get('ezbake_baker_url');
  if (!$baker_url) {
    if (user_access('administer site configuration')) {
      $msg = "The Baker URL variable (ezbake_baker_url) is not set for this form. Form submission will not work.";
    }
    else {
      $msg = "This form cannot be submitted at the moment. Please contact site administrator for more information.";
    }
    drupal_set_message($msg, 'error');
    $disable_submit = TRUE;
  }
  else {
    $ezbake_settings['bakerURL'] = $baker_url;
  }

  // Get the confirmation page URL
  $confirmation_page_url = variable_get('ezbake_confirm_url');
  if (!$confirmation_page_url) {
    if (user_access('administer site configuration')) {
      $msg = "The confirmation page URL (ezbake_confirm_url) has not been set. Responses from the baker might not work properly.";
      drupal_set_message($msg, 'warning');
    }
  }
  else {
    $ezbake_settings['confirmPageURL'] = $confirmation_page_url;
  }

  // Get the error page URL
  $error_page_url = variable_get('ezbake_error_url');
  if (!$error_page_url) {
    if (user_access('administer site configuration')) {
      $msg = "The error page URL (ezbake_error_url) has not been set. Responses from the baker might not work properly.";
      drupal_set_message($msg, 'warning');
    }
  }
  else {
    $ezbake_settings['errorPageURL'] = $error_page_url;
  }

  if ($disable_submit) {
    $form['actions']['submit']['#disabled'] = TRUE;
  }

  if (!empty($ezbake_settings)) {
    // Push settings to JS
    drupal_add_js(array('ezBake' => $ezbake_settings), 'setting');
  }
}

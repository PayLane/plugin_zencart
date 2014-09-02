<?php
/**
 * paylane.php payment module class for PayLane Secure Form method
 *
 * @package paymentMethod
 * @copyright Copyright 2003-2011 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: paylane.php 19363 2011-08-26 14:52:30Z drbyte $
 */

class paylane extends base {

    const PAYLANE_SECURE_FORM_URL = 'https://secure.paylane.com/order/cart.html';
  /**
   * string representing the payment method
   *
   * @var string
   */
  var $code;
  /**
   * $title is the displayed name for this payment method
   *
   * @var string
    */
  var $title;
  /**
   * $description is a soft name for this payment method
   *
   * @var string
    */
  var $description;
  /**
   * $enabled determines whether this module shows or not... in catalog.
   *
   * @var boolean
    */
  var $enabled;
  /**
    * constructor
    *
    * @return paylane
    */
  function paylane() {
      global $order;

      $this->code = 'paylane';
      $this->title = MODULE_PAYMENT_PAYLANE_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_PAYLANE_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_PAYLANE_SORT_ORDER;

      $this->enabled = ((MODULE_PAYMENT_PAYLANE_STATUS == 'True') ? true : false);

      if ((int)MODULE_PAYMENT_PAYLANE_ORDER_STATUS_ID > 0)
      {
          $this->order_status = MODULE_PAYMENT_PAYLANE_ORDER_STATUS_ID;
      }

      $this->form_action_url = self::PAYLANE_SECURE_FORM_URL;

      if (is_object($order)) $this->update_status();
  }
  /**
   * calculate zone matches and flag settings to determine whether this module should display to customers or not
    *
    */
  function update_status() {
      global $order, $db;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_PAYLANE_ZONE > 0) )
      {
          $check_flag = false;
          $check_query = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYLANE_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");

          foreach ($check_query as $check)
          {
              if ($check['zone_id'] < 1)
              {
                  $check_flag = true;
                  break;
              }
              elseif ($check['zone_id'] == $order->delivery['zone_id'])
              {
                  $check_flag = true;
                  break;
              }
          }

          if ($check_flag == false)
          {
              $this->enabled = false;
          }
      }
  }
  /**
   * JS validation which does error-checking of data-entry if this module is selected for use
   * (Number, Owner, and CVV Lengths)
   *
   * @return string
    */
  function javascript_validation() {
    return false;
  }
  /**
   * Displays payment method name along with Credit Card Information Submission Fields (if any) on the Checkout Payment Page
   *
   * @return array
    */
  function selection() {
    return array('id' => $this->code,
                 'module' => MODULE_PAYMENT_PAYLANE_PUBLIC_TITLE
                 );
  }
  /**
   * Normally evaluates the Credit Card Type for acceptance and the validity of the Credit Card Number & Expiration Date
   * Since paylane module is not collecting info, it simply skips this step.
   *
   * @return boolean
   */
  function pre_confirmation_check() {
    return false;
  }
  /**
   * Display Credit Card Information on the Checkout Confirmation Page
   * Since none is collected for paylane before forwarding to paylane site, this is skipped
   *
   * @return boolean
    */
  function confirmation() {
    return false;
  }
  /**
   * Build the data and actions to process when the "Submit" button is pressed on the order-confirmation screen.
   * This sends the data to the payment gateway for processing.
   * (These are hidden fields on the checkout confirmation page)
   *
   * @return string
    */
  function process_button()
  {
      global $order, $currency, $language;

      $transaction_description = MODULE_PAYMENT_PAYLANE_SHOPPED_ITEMS . ": ";
      $process_button_string = "";

      foreach ($order->products as $k => $v)
      {
          $transaction_description .= $v['name'] . ", ";
      }

      $transaction_description = substr($transaction_description, 0, -2);

      $parameters = array(
          'merchant_id' => MODULE_PAYMENT_PAYLANE_MERCHANTID,
          'merchant_transaction_id' => "ZenCart",
          'amount' => $order->info['total'],
          'currency_code' => $_SESSION['currency'],
          'transaction_type' => 'S',
          'back_url' => zen_href_link('paylane_redirect.php', '', 'SSL', false, true, true),
          'transaction_description' => $transaction_description,
          'language' => ('polish' == $language) ? "pl" : "en",
          'customer_name' => $order->customer['firstname'] . " " . $order->customer['lastname'],
          'customer_email' => $order->customer['email_address'],
          'customer_address' => $order->customer['street_address'],
          'customer_zip' => $order->customer['postcode'],
          'customer_city' => $order->customer['city'],
          'customer_state' => $order->customer['state'],
          'customer_country' => $order->customer['country']['iso_code_2'],
      );

      $hash = MODULE_PAYMENT_PAYLANE_HASH;

      if ( !empty($hash))
      {
          $parameters['hash'] = SHA1(
              MODULE_PAYMENT_PAYLANE_HASH . "|" .
                  $parameters['merchant_transaction_id'] . "|" .
                  $parameters['amount'] . "|" .
                  $parameters['currency_code'] . "|" .
                  $parameters['transaction_type']
          );
      }

      reset($parameters);
      while (list($key, $value) = each($parameters))
      {
          $process_button_string .= zen_draw_hidden_field($key, $value);
      }

      return $process_button_string;
  }
  /**
   * Store transaction info to the order and process any results that come back from the payment gateway
   */
  function before_process()
  {
      global $messageStack;

      if ('POST' == MODULE_PAYMENT_PAYLANE_REDIRECT)
      {
          $request = $_POST;
      }
      else
      {
          $request = $_GET;
      }

      if (1 != $request['correct'])
      {
          if ( !empty($request['id_error']) and 0 < $request['id_error'])
          {
              $messageStack->add_session('header', MODULE_PAYMENT_PAYLANE_ERROR_FROM_PROCESSOR . " " . $request['error_code'] . "-" . $request['error_text'], 'error');
              zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'NONSSL', true, false));
          }
          else
          {
              $messageStack->add_session('header', MODULE_PAYMENT_PAYLANE_ERROR_INCORRECT, 'error');
              zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'NONSSL', true, false));
          }
      }

      $hash = MODULE_PAYMENT_PAYLANE_HASH;
      $hash_transaction = SHA1(MODULE_PAYMENT_PAYLANE_HASH . "|" . $request['correct'] . "|" . $request['merchant_transaction_id'] . "|" . $request['amount'] . "|" . $request['currency_code']);

      if ( !empty($hash) and $hash_transaction != $request['paylane_hash'])
      {
          zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode(MODULE_PAYMENT_PAYLANE_ERROR_HASH), 'NONSSL', true, false));
      }

      return true;
  }
  /**
    * Checks referrer
    *
    * @param string $zf_domain
    * @return boolean
    */
  function check_referrer($zf_domain) {
    return true;
  }
  /**
   * Post-processing activities
   *
   * @return boolean
    */
  function after_process()
  {
      global $db, $insert_id;

      if ('POST' == MODULE_PAYMENT_PAYLANE_REDIRECT)
      {
          $request = $_POST;
      }
      else
      {
          $request = $_GET;
      }

      if ($_SESSION['order_number_created'] > 0)
      {
          $sql_data_array= array(array('fieldName'=>'orders_id', 'value'=>$insert_id, 'type'=>'integer'),
              array('fieldName'=>'orders_status_id', 'value'=>$this->order_status, 'type'=>'integer'),
              array('fieldName'=>'date_added', 'value'=>'now()', 'type'=>'noquotestring'),
              array('fieldName'=>'customer_notified', 'value'=>0, 'type'=>'integer'),
              array('fieldName'=>'comments', 'value'=>'Paylane id sale = ' . $request['id_sale'] , 'type'=>'string'));
          $db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
      }

      return true;
  }
  /**
   * Used to display error message details
   *
   * @return boolean
    */
  function output_error() {
    return false;
  }
  /**
   * Check to see whether module is installed
   *
   * @return boolean
    */
  function check() {
    global $db;
    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYLANE_STATUS'");
      $this->_check = $check_query->RecordCount();
    }
    return $this->_check;
  }
  /**
   * Install the payment module and its configuration settings
    *
    */
  function install() {
    global $db, $messageStack;
    if (defined('MODULE_PAYMENT_PAYLANE_STATUS')) {
      $messageStack->add_session('PayLane SecureForm integration module already installed.', 'error');
      zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=paylane', 'NONSSL'));
      return 'failed';
    }
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable PayLane Secure Form', 'MODULE_PAYMENT_PAYLANE_STATUS', 'False', 'Do you want to process payments via PayLane Secure Form?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Type of Secure Form redirect', 'MODULE_PAYMENT_PAYLANE_REDIRECT', 'POST', 'How you want to handle redirects from PayLane?', '6', '1', 'zen_cfg_select_option(array(\'POST\', \'GET\'), ', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('PayLane Merchant ID', 'MODULE_PAYMENT_PAYLANE_MERCHANTID', 'merchant_id', 'Your PayLane Merchant ID', '6', '2', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Hash salt', 'MODULE_PAYMENT_PAYLANE_HASH', '0', 'Your HASH from Paylane Merchant Panel', '6', '2', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sorting order', 'MODULE_PAYMENT_PAYLANE_SORT_ORDER', '0', 'Default sorting order. Most recent are first in default.', '6', '0', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Public title', 'MODULE_PAYMENT_PAYLANE_PUBLIC_TITLE', 'PayLane Payment', 'Name of payment service visible on checkout payment page.', '6', '0', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment region', 'MODULE_PAYMENT_PAYLANE_ZONE', '0', 'If any is choosen, then this form of payments will be only available in that zone', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Default order status', 'MODULE_PAYMENT_PAYLANE_ORDER_STATUS_ID', '0', 'All orders processed by this form pament will be checked as setted up option', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

    $this->notify('NOTIFY_PAYMENT_PAYLANE_INSTALLED');
  }
  /**
   * Remove the module and all its settings
    *
    */
  function remove() {
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key LIKE 'MODULE\_PAYMENT\_PAYLANE\_%'");
    $this->notify('NOTIFY_PAYMENT_PAYLANE_UNINSTALLED');
  }
  /**
   * Internal list of configuration keys used for configuration of the module
   *
   * @return array
    */
  function keys()
  {
      return array('MODULE_PAYMENT_PAYLANE_STATUS', 'MODULE_PAYMENT_PAYLANE_REDIRECT', 'MODULE_PAYMENT_PAYLANE_MERCHANTID', 'MODULE_PAYMENT_PAYLANE_HASH', 'MODULE_PAYMENT_PAYLANE_ZONE', 'MODULE_PAYMENT_PAYLANE_ORDER_STATUS_ID','MODULE_PAYMENT_PAYLANE_SORT_ORDER', 'MODULE_PAYMENT_PAYLANE_PUBLIC_TITLE');
  }

}

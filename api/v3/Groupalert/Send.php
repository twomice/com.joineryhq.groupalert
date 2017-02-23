<?php

/**
 * Groupalert.Send API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_groupalert_Send_spec(&$spec) {
  $spec['group_id'] = array(
    'title' => 'Group ID',
    'description' => 'System ID of group to alert',
    'api.required' => 1,
    'type' => CRM_Utils_Type::T_INT,
  );
  $spec['noisy'] = array(
    'title' => 'Noisy',
    'description' => 'Send even if group is empty',
    'api.required' => 0,
    'api.default' => 0,
    'type' => CRM_Utils_Type::T_INT,
  );
  $spec['list_contacts'] = array(
    'title' => 'List contacts',
    'description' => 'Include a list of group contacts in the alert?',
    'api.required' => 0,
    'api.default' => 0,
    'type' => CRM_Utils_Type::T_INT,
  );
  $spec['list_contacts_limit'] = array(
    'title' => 'List contacts limit',
    'description' => 'Maximum number of group contacts to include in the alert',
    'api.required' => 0,
    'api.default' => 25,
    'type' => CRM_Utils_Type::T_INT,
  );
  $spec['recipients'] = array(
    'api.required' => 1,
    'title' => 'Recipients',
    'description' => 'Email address(es) to receive the alert, comma-separated',
    'type' => CRM_Utils_Type::T_STRING,
  );
}

/**
 * Groupalert.Send API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_groupalert_Send($params) {
  $sent_count = 0;
  $group_id = (int)CRM_Utils_Array::value('group_id', $params, 0);
  $noisy = CRM_Utils_Array::value('noisy', $params, 0);
  $list_contacts_limit = CRM_Utils_Array::value('list_contacts_limit', $params, 25);

  $recipient_addresses = _civicrm_api3_groupalert_Send_getrecipients($params);
  if (empty($recipient_addresses)) {
    // If there are no recipients
    return;
  }

  $result = civicrm_api3('Contact', 'get', array(
    'is_deleted' => 0,
    'group' => array($group_id => 1),
    'return' => array(
      'sort_name'
    ),
    'options' => array(
      'limit' => $list_contacts_limit,
      'sort' => 'sort_name',
    ),
  ));

  if ($noisy || $result['count']) {
    $contacts = $result['values'];

    $group_title = '';
    $result = civicrm_api3('Group', 'get', array(
      'id' => $group_id,
      'sequential' => 1,
      'return' => array(
        'title'
      ),
    ));
    if (!empty($result['values'][0])) {
      $group_title = CRM_Utils_Array::value('title', $result['values'][0]);
    }

    $body = _civicrm_api3_groupalert_Send_getbody($group_title, $contacts, $params);

    $sent_count = _civicrm_api3_groupalert_Send_sendmail($recipient_addresses, $body, $group_title);

  }
  return civicrm_api3_create_success("Sent $sent_count email(s).", $params, 'Groupalert', 'send');
}

function _civicrm_api3_groupalert_Send_sendmail($recipient_addresses, $body, $subject) {
  $sent = 0;

  // I'd use the optionvalue api here, but it doesn't return intelligible results for this value.
  $from = array_shift(CRM_Core_OptionGroup::values('from_email_address', NULL, NULL, NULL, ' AND is_default = 1'));
  if (!$from) {
    throw new API_Exception('Cannot send mail. Organization default email address is not set.');
  }

  foreach ($recipient_addresses as $recipient_address) {
    $params = array(
      'from' => $from,
      'toEmail' => $recipient_address,
      'subject' => $subject,
      'text' => $body,
    );
    $sent += (int)CRM_Utils_Mail::send($params);
  }
  return $sent;
}

function _civicrm_api3_groupalert_Send_getrecipients($params) {
  $recipients = CRM_Utils_Array::value('recipients', $params, '');
  array_walk($recipient_addresses = explode(',', $recipients), 'trim');

  $valid_addresses = $invalid_addresses = array();

  // Validate all recipient addresses. No action will be taken if any recipient
  // address has a bad format.
  foreach ($recipient_addresses as $recipient_address) {
    if (CRM_Utils_Rule::email($recipient_address)) {
      $valid_addresses[] = $recipient_address;
    }
    else {
      $invalid_addresses[] = $recipient_address;
    }
  }

  if (!empty($invalid_addresses)) {
    throw new API_Exception('Incorrectly formatted recipient emails: '. implode(', ', $invalid_addresses) .'; no action taken.');
    return array();
  }

  return $valid_addresses;
}

function _civicrm_api3_groupalert_Send_getbody($group_title, $contacts, $params) {
  $group_id = (int)CRM_Utils_Array::value('group_id', $params, 0);
  $list_contacts = CRM_Utils_Array::value('list_contacts', $params, 0);

  $url = CRM_Utils_System::url('/civicrm/group/search', 'force=1&context=smog&gid=1', TRUE);

  $contact_count = count($contacts);
  $body = "There are $contact_count contact(s) in the group: $group_title\n\n";

  if ($contact_count) {
    $body .= "View group contacts here:\n$url\n";

    if ($list_contacts) {
      $body .= "\n";
      $body .= "Group contacts:\n\n";
      foreach ($contacts as $contact_id => $contact) {
        $body .= "${contact['sort_name']} (cid: {$contact_id})\n";
      }
    }
  }
  dsm("====\n$body");
  return $body;
}
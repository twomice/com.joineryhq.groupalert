<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'Cron:Groupalert.send',
    'entity' => 'Job',
    'params' => 
    array (
      'version' => 3,
      'name' => 'Call Groupalert.send',
      'description' => 'Call Groupalert.send API',
      'run_frequency' => 'Daily',
      'api_entity' => 'Groupalert',
      'api_action' => 'send',
      'parameters' => 'group_id=[integer] (System ID of group to alert)
noisy=[1 or 0] (Send even if group is empty)
list_contacts=[1 or 0] (Include a list of group contacts in the alert?)
list_contacts_limit=[integer] (Maximum number of group contacts to include in the alert; default=25)
recipients=[string] (Email address(es) to receive the alert, comma-separated)
',
    ),
  ),
);

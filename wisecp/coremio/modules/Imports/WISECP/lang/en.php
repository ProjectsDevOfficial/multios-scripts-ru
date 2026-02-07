<?php 
return [
    'name'           => 'Import from WISECP',
    'page-title'     => 'Import from WISECP',
    'notice-message' => "<p style=\"text-align:center;\"><strong>You can easily import data from another WISECP'system into your own WISECP system.</strong></p><ul>\n<li>Due to insufficient server resources, the process may be interrupted. Keep a backup of your existing WISECP database to re-import it into the clean database.</li>\n<li>Make sure your hosting \"memory_size, max_execution_time, max_input_time, max_input_vars and etc\" settings are set high before you start this process. These settings are usually found in php.ini configuration file.</li>\n<li>This module does not perform any action on the other WISECP database.</li>\n<li>The processing time varies depending on the size of your database.</li>\n</ul>",
    'encryption-key' => 'User Encryption Hash',
    'encryption-key-info' => 'Enter the KEY information for the \'user\' value in the <b>coremio/configuration/crypt.php</b> file belonging to WISECP.',
    'encryption-key2' => 'System Encryption Hash',
    'encryption-key2-info' => 'Enter the KEY information for the \'system\' value in the <b>coremio/configuration/crypt.php</b> file belonging to WISECP.',
    'same-database-error' => "Importing to the same database on the same server is not possible.",
];

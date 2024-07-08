<?php

// This is a PLUGIN TEMPLATE.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Uncomment and edit this line to override:

$plugin['name'] = 'rvm_utf8_to_utf8mb4';
$plugin['version'] = '0.2';
$plugin['author'] = 'Ruud van Melick';
$plugin['author_uri'] = 'http://vanmelick.com/';
$plugin['description'] = 'Alter pre-MySQL 5.5.3 tables to use full UTF-8 charset (utf8mb4 instead of utf8)';

// Plugin types:
// 0 = regular plugin; loaded on the public web side only
// 1 = admin plugin; loaded on both the public and admin side
// 2 = library; loaded only when include_plugin() or require_plugin() is called
$plugin['type'] = 1;


@include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---

h1. utf8 to utf8mb4

*WARNING: carefully read this documentation and BACKUP YOUR DATABASE before activating/using this plugin.*

Textpattern always uses 'utf8' to store data in the database, but depending on which MySQL version you used when installing Textpattern, the character set of the tables and columns differs:

# MySQL versions older than 4.1: the character set of the tables created by Textpattern is typically 'latin1'.
# MySQL versions 4.1 and higher: the tables are set to use the 'utf8' character set, which matches the actual character set of the data stored in those tables, but doesn't allow 4-byte UTF-8 characters.
# MySQL versions 5.5.3 and higher + TXP 4.6 and higher: the tables are set to use the 'utf8mb4' character set, which allows the full range of UTF-8 characters to be used, including emojis.

Having the tables/columns themselves use the same character set as the data stored in them has some advantages when performing searches or sorting in Textpattern, especially if you use characters outside US-ASCII.

If you upgrade your Textpattern version to 4.6 or higher + MySQL version from 5.5.3 or higher, the table/column character sets are not automatically converted to 'utf8mb4', so they stay 'latin1' or 'utf8' while the data stored in those tables is still 'utf8'.

To switch from 'latin1' to 'utf8', use the rvm_latin1_to_utf8 plugin before using this one.
To switch from 'utf8' to 'utf8mb4', this plugin can be used.

This plugin assumes the current charset for tables and columns is set to 'utf8' and changes that to 'utf8mb4'. This can be done in TXP 4.6 or higher, because the relevant indexes have already been changed to allow for the extra byte space per character as required by 'utf8mb4'. Since the old 'utf8' columns couldn't contain 4-byte UTF-8 characters, no data is lost when changing to utf8mb4.

Afterwards an attempt is made to update the 'dbcharset' setting in the @textpattern/config.php@ file to 'utf8mb4'. Should this fail due to lack of permissions, you will be asked to make this change yourself. Do not skip this step, otherwise you will experience irreparable data corruption so severe that you will cry like a baby (you made backups, right?), much to the amusement of onlookers.

How to use this plugin:

# *MAKE BACKUPS FIRST!* (not just the TXP tables, but the entire database)
# Make sure you have MySQL version 5.5.3 or higher installed.
# Upgrade to at least Textpattern 4.6 or higher.
# Activate the plugin.
# "Click here":?event=rvm_utf8_to_utf8mb4 and follow the instructions on the screen.

# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---

if (txpinterface == 'admin' and gps('event') == 'rvm_utf8_to_utf8mb4')
{
  add_privs('rvm_utf8_to_utf8mb4', '1');
  register_tab('extensions', 'rvm_utf8_to_utf8mb4', 'utf8_to_utf8mb4');
  register_callback('rvm_utf8_to_utf8mb4', 'rvm_utf8_to_utf8mb4');
}

function rvm_utf8_to_utf8mb4()
{
  global $txpcfg, $DB, $dbversion;

  $debug = FALSE;
  $error = FALSE;

  require_privs('rvm_utf8_to_utf8mb4');
  pagetop('');

  echo '<div style="margin-left: 50px">';

  if (mysqli_get_server_version($DB->link) < 50503) {
    $error = 'MySQL version is lower than 5.5.3';
  } else {
    if (false !== strpos(mysqli_get_client_info(), 'mysqlnd')) {
      // mysqlnd 5.0.9+ required
      if (mysqli_get_client_version() < 50009) {
        $error = 'mysqlnd client version is below 5.0.9';
      }
    } else {
      // libmysqlclient 5.5.3+ required
      if (mysqli_get_client_version() < 50503) {
        $error = 'libmysqlclient version is below 5.5.3';
      }
    }
  }

  if (version_compare($dbversion, '4.6.0', '<')) {
    $error = 'Textpattern database version is below 4.6.0. Upgrade Textpattern first';
  }

  if ($txpcfg['dbcharset'] == 'latin1') {
    $error = 'dbcharset is latin1, so you have to use the rvm_latin1_to_utf8 plugin first';
  }

  if ($error)
  {
    echo graf('The rvm_utf8_to_utf8mb4 plugin has been removed, because your '.$error);
    safe_delete('txp_plugin', "name = 'rvm_utf8_to_utf8mb4'");
    rvm_utf8_to_utf8mb4_end();
  }

  # standard TXP tables
  $txptables = array(
    'textpattern',
    'txp_category',
    'txp_css',
    'txp_discuss',
    'txp_discuss_ipban',
    'txp_discuss_nonce',
    'txp_file',
    'txp_form',
    'txp_image',
    'txp_lang',
    'txp_link',
    'txp_log',
    'txp_page',
    'txp_plugin',
    'txp_prefs',
    'txp_section',
    'txp_skin',
    'txp_token',
    'txp_users'
  );

  # find tables that match the TXP table prefix, mark standard and selected ones.
  $mytables = ps('mytables', array()) ? ps('mytables') : array();
  $unknown  = FALSE;

  if ($rs = safe_query("SHOW TABLES LIKE '".addcslashes(doSlash(PFX), '%_')."%'"))
  {
    while ($row = mysqli_fetch_row($rs))
    {
      $table = substr($row[0], strlen(PFX));

      if (in_array($table, $txptables) or in_array($table, $mytables))
      {
        $tables[] = $table;
      }
      else
      {
        $unknown = TRUE;
      }

      $inputs[] =
        '<label>'.
          '<input type="checkbox" name="mytables[]" value="'.htmlspecialchars($table).'" '.
            (in_array($table, $txptables) ? 'disabled="disabled" checked="checked"' : '').' />'.
          htmlspecialchars(PFX.$table).
        '</label>';
    }
  }
  else
  {
    echo graf('Hmmm... strange, I cannot find any tables that match your TXP table prefix');
  }

  # ask user what to do with tables we're not sure about.
  if ($unknown and !ps('continue'))
  {
    echo
      graf('Below, a list of tables is shown that may be part of your Textpattern install.').
      graf('Some of these tables may have been created by plugins you have installed.<br />
        In that case, check the tables that these plugins have added.<br />
        Be careful not to check tables that are not related to this Textpattern install!').
      graf('The standard Textpattern tables are already checked.').
      form(
        join('<br />', $inputs).
        graf(
          eInput('rvm_utf8_to_utf8mb4').'<br />'.
          fInput('submit', 'continue', 'Continue', 'publish')
        )
      );

    rvm_utf8_to_utf8mb4_end();
  }

  echo graf('Updating Textpattern tables...');

  # loop through tables
  foreach ($tables as $table)
  {
    $utf8mb4 = array();

    # prepare alter statements for columns
    $columns = getRows('SHOW COLUMNS FROM '.safe_pfx($table));

    if ($columns) foreach ($columns as $column)
    {
      extract($column);

      if (preg_match('/^(char|varchar|tinytext|text|mediumtext|longtext|enum|set)\b/', $Type))
      {
        $utf8mb4[] = 'MODIFY `'.doSlash($Field).'` '.$Type.'
          CHARACTER SET utf8mb4
          COLLATE utf8mb4_unicode_ci'.
          ($Null == 'YES' ? ' NULL' : ' NOT NULL').
          ($Default == NULL
            ? ((preg_match('/text/', $Type) || $Null == 'NO') ? '' : ' DEFAULT NULL')
            : " DEFAULT '".doSlash($Default)."'"
          );
      }
    }

    $success = TRUE;

    # alter the table
    $success *= safe_alter($table, join(', ', $utf8mb4).', DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $debug);
  }

  # while we're here, might as well optimize the tables
  safe_query("OPTIMIZE TABLE ".join(', ', array_map('safe_pfx', $tables)), $debug);

  if ($success)
  {
    echo graf('Successfully completed.');

    $file   = txpath.'/config.php';
    $config = file_get_contents($file);
    $config = str_replace('$txpcfg[\'dbcharset\'] = \'utf8\''.n, '$txpcfg[\'dbcharset\'] = \'utf8\';'.n, $config); # fix missing ; on line added by in rvm_latin1_to_utf8
    $config = str_replace('?>', n.'# rvm_utf8_to_utf8mb4 plugin added the following line to ensure a correct dbcharset'.n.'$txpcfg[\'dbcharset\'] = \'utf8mb4\';'.n.'?>', $config);

    if ($txpcfg['dbcharset'] == 'utf8mb4'
      or is_writable($file)
      and $handle = fopen($file, 'w')
      and fwrite($handle, $config) !== FALSE
      and fclose($handle))
    {
      echo graf('The rvm_utf8_to_utf8mb4 plugin has been automatically de-installed.');
      safe_delete('txp_plugin', "name = 'rvm_utf8_to_utf8mb4'");
    }
    else
    {
      echo graf('<b>WARNING: textpattern/config.php could not be updated automatically. Please update your textpattern/config.php file to contain <code>$txpcfg[\'dbcharset\'] = \'utf8mb4\';</code></b> and deinstall the rvm_utf8_to_utf8mb4 plugin manually');
    }
  }
  else
  {
    echo graf('Due to one or more errors the tables could not be updated correctly. You may have to restore a backup.');
  }

  rvm_utf8_to_utf8mb4_end();
}

function rvm_utf8_to_utf8mb4_end()
{
  echo '</div>';
  end_page();
  exit();
}

# --- END PLUGIN CODE ---

?>

# rvm_utf8_to_utf8mb4

_WARNING: carefully read this documentation and **BACKUP YOUR DATABASE** before activating/using this plugin._

Textpattern always uses ‘utf8’ to store data in the database, but depending on which MySQL version you used when installing Textpattern, the character set of the tables and columns differs:

- MySQL versions older than 4.1: the character set of the tables created by Textpattern is typically `latin1`.
- MySQL versions 4.1 and higher: the tables are set to use the `utf8` character set, which matches the actual character set of the data stored in those tables, but doesn’t allow 4-byte UTF-8 characters.
- MySQL versions 5.5.3 and higher + TXP 4.6 and higher: the tables are set to use the `utf8mb4` character set, which allows the full range of UTF-8 characters to be used, including emojis.

Having the tables/columns themselves use the same character set as the data stored in them has some advantages when performing searches or sorting in Textpattern, especially if you use characters outside US-ASCII.

If you upgrade your Textpattern version to 4.6 or higher + MySQL version from 5.5.3 or higher, the table/column character sets are not automatically converted to ‘utf8mb4’, so they stay ‘latin1’ or ‘utf8’ while the data stored in those tables is still ‘utf8’.

## Operation

- To switch from ‘latin1’ to ‘utf8’, use the [rvm_latin1_to_utf8](https://vanmelick.com/txp/) plugin before using this one.
- To switch from ‘utf8’ to ‘utf8mb4’, this plugin can be used.

This plugin assumes the current charset for tables and columns is set to ‘utf8’ and changes that to ‘utf8mb4’. This can be done in TXP 4.6 or higher, because the relevant indexes have already been changed to allow for the extra byte space per character as required by ‘utf8mb4’. Since the old ‘utf8’ columns couldn’t contain 4-byte UTF-8 characters, no data is lost when changing to utf8mb4.

Afterwards an attempt is made to update the ‘dbcharset’ setting in the textpattern/config.php file to ‘utf8mb4’. Should this fail due to lack of permissions, you will be asked to make this change yourself. _Do not skip this step, otherwise you may experience irreparable data corruption._

## How to use this plugin

- MAKE BACKUPS FIRST! (not just the TXP tables, but the entire database)
- Make sure you have MySQL version 5.5.3 or higher installed.
- Upgrade to at least Textpattern 4.6 or higher.
- Activate the plugin.
- Open the plugin help and follow the instructions on the screen.
- The plugin will deinstall itself after completion.

### Credits

This is an update of [rvm_utf8_to_utf8mb4](https://vanmelick.com/txp/) by Ruud van Melick to bring it inline with more recent versions of Textpattern and PHP.

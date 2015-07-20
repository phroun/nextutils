# Welcome to my Utilities Repository

## baseutils.php

This file contains a lot of basic utility functions for use with PHP web development.  Some of these functions are in flux and will be improved upon in the future.

#### First Class Functions

``setTimeZoneByOffset("+00:00")`` Sets the PHP default timezone to a match with the same offset specified (useful for back end of ajax auto detection)

``tzOffset("America/Los_Angeles")`` Get the current offset for the named timezone in "+00:00" format.

``luminanceOfHex('99CCFF')`` Returns the optically weighted luminance score for a 3 or 6 digit hexadecimal color string.

``selected($expr)`` if true, returns ' selected="selected"', otherwise returns a blank string

``checked($expr)`` if true, returns ' checked="checked"', otherwise returns a blank string

``dtLocal("0000-00-00 00:00:00")`` takes a MySQL style UTC time string and returns the same format of string converted to the current PHP local timezone.

``dtUTC("0000-00-00 00:00:00")``  takes a MySQL style local time string and returns the same format of string converted to UTC.

``formatDuration($seconds, $usedays = true)``  returns a duration in "mm:ss" or "hh:mm:ss" or "x days" format as appropriate.

``boolToInt($expr)`` if true returns -1 otherwise returns 0

``intToBool($i)`` if nonzero returns true otherwise returns false

``hts($s)`` alias for htmlspecialchars, used to output properly escaped textual data into an html document.

``generateRandomString($length)`` returns a mixed case alphanumeric random string of the specified length.

``xmlentities($s)`` convert named character entities not defined in the XML serialization into XHTML5 compatible numeric entities.

#### Functions with Improvements Planned

``logline($s)`` Write a line of text to the system message log. (set $MESSAGELOGFN to override, this will be changed later)

``hexDigitToDec($d)`` Useless less functional duplicate of PHP's hexdec() function, will be removed once phased out of projects.

``size_readable(15000, "K", "si")`` Converts a size in raw bytes into a human readable size with either si or bi (binary) units. Second parameter is largest unit specifier to use.

``getBaseURL()`` detects and returns the relative base URL based on the request URI.  Intended for user before css and javascript inclusions, but since it pulls the current request, I'm not sure how this is different from "./" at the moment I'm writing this documentation.

## dbiutils.php

This is a database safety and convenience layer built upon the mysqli extension.

``$link dbutils_connect($hostAndPort, $user, $pass, $base = '', $graceful = false)``  Connect to the database server and use the specified database.  On success, returns the database connection link object.  If there is a failure, die with an error message (or if $graceful == true, return the error message.)

``setDataLink($conn, $readonly = false)`` Set the active data link to the object returned from a previous call to dbutils_connect().  The last dbutils_connect link is automatically set as the active data link, so this only needs to be called to switch back to a previous connection.

``updateorinsert($table, $keyvalues, $values, [$insertonlyvalues])``  Looks in table for a row matching keyvalues, if found, updates with $values, otherwise inserts a new row with $newvalues and $insertonlyvalues merged.

``insertorupdate($table, $keyvalues, $values, [$insertonlyvalues])``  Alias for updateorinsert().

``updateorinsert_inserted()``  Returns true if the previous updateorinsert performed an insert rather than an update.

``update($table, $keyvalues, $values)``  Looks in table for a row matching keyvalues.  If found, updates values.  If not found, does nothing.  Returns false only if there was a database error.  **Should this be improved to return the record number instead?**

``insert($table, $values)``  Inserts a new record in table with values and returns the new record id, or 0 if a failure occurred.

``deleteFrom($table, $keyvalues, [$limit])``  Delete matching records from table.

``select($table, $fields, $keyvalues, $clauses)``  Performs the query, in the basic form of: SELECT fields FROM table WHERE keyvalues clauses;  keyvalues is an associative array, and clauses is a qsafe-compatible array (see qsafe)  The return value is an associative array of the first row of the result set.  The result set remains loaded for use with selectRow() and selectClose()

``selecta($table, $fields, $keyvalues, $causes)``  Performs the query, in the basic from of: SELECT fields FROM table WHERE keyvalues clauses;  keyvalues is an associative array, and clauses is a qsafe-compatible array (see qsafe)  The return value is an associative array of the first row of the result set, the result set is freed immediately.

``selectRow()``  Returns the first (or next) row in the resultset retrieved by calling select()  Note that even though the first row was already made available as the result of the call to select(), it is returned again on the first call to selectRow() to make looping and conditionals more flexible.

``txBegin()``  Start a database transaction.  These calls are stackable so if a function requires transactional behavior and is already in a transaction, only the outermost transaction will actually be committed or the whole thing will be rolled back as a whole.

``txCommit()``  End (and commit) a database transaction.  If nested, does nothing until caled on the outermost transaction level.

``txCancel()``  Roll back a database transaction.  The current implementation calls die() if this is called from within an interior transaction since InnoDB and mysqli don't support nested transactions.

``qsafe(...)``  For any query too sophisticated (or convoluted) to be written using the standard functions (selecta, select, updateorinsert, update, insert, or deleteFrom), you must run the query text through this in a special parameterized form in order to ensure safety against injection attacks.  (form will be described here)

``sq(&$queryStringOrObject)``  Returns the first result (if a string was passed) and converts the string variable into a queryObject (class Q).  If a queryObject is passed (i.e., the second pass through a while loop), it will return the next result.  If no more results are available, returns false and automatically frees the queryObject.  To demonstrate the general utility of this, you would store the output of a call to qsafe into a variable, then run a loop of this form: while ($result = sq($qs)) { /* process $result */ } in order to process each of the results.

``qf($queryObject)``  Frees a queryObject.

``sqf($queryString)``  Returns the first (or only) result of a query and immediately frees the result set.

``sqx($qs)``  Executes an SQL statement (or an array of statements) that does/do not return [a] result set.

#### Used internally:

``getSelectFrom($table, $fields, $keyvalues, [$clauses])``  Constructs a query string and returns it, in the basic form of: SELECT fields FROM table WHERE keyvalues clauses;  keyvalues is an associative array, and clauses is a qsafe-compatible array (see qsafe)

``arraytosafe($a, $useand = false)`` Turns an associative array of key values pairs (or literal query fragments with an (auto)numbered key) into either `field` = "value", `field` = "value" notation or the same thing with AND instead of a comma separating each pair.

``mes($s)`` Calls mysqli_real_escape_string with the currently active link.

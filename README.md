# Welcome to my Utilities Repository

## baseutils.php

This file contains a lot of basic utility functions for use with PHP web development.  Some of these functions are in flux and will be improved upon in the future.  The baseutils library is fully Unicode compliant and requires the mbstring PHP extension to be enabled.

#### Base Utility Functions

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

``size_readable(15000, "K", "si")`` Converts a size in raw bytes into a human readable size with either si or bi (binary) units. Second parameter is largest unit specifier to use. (BADLY NAMED, suggest rename to humanReadableSize)

``getBaseURL()`` detects and returns the relative base URL based on the request URI.  Intended for user before css and javascript inclusions, but since it pulls the current request, I'm not sure how this is different from "./" at the moment I'm writing this documentation.

## dbiutils.php

This is a database safety and convenience layer built upon the mysqli extension.  dbiutils is pretty brazen in its assumptions about what it can put in the namespace.  The assumption is that if you're using it, you're writing a serious database application, and things like update() or insert() are common enough operations to warrant being named as such.  The dbiutils library is fully Unicode compliant and requires the mbstring PHP extension to be enabled.

``$link dbutils_connect($hostAndPort, $user, $pass, $base = '', $graceful = false)``  Connect to the database server and use the specified database.  On success, returns the database connection link object.  If there is a failure, die with an error message (or if $graceful == true, return the error message.)

``setDataLink($conn, $readonly = false)`` Set the active data link to the object returned from a previous call to dbutils_connect().  The last dbutils_connect link is automatically set as the active data link, so this only needs to be called to switch back to a previous connection.

``updateorinsert($table, $keyvalues, $values, [$insertonlyvalues])``  Looks in table for a row matching keyvalues, if found, updates with $values, otherwise inserts a new row with $newvalues and $insertonlyvalues merged.  If id is specified as a keyvalue and is zero, it will be omitted during an insert so MySQL can auto-increment the value of this field instead.  If this function is successful, The record id of the updated or inserted row is returned.

``insertorupdate($table, $keyvalues, $values, [$insertonlyvalues])``  Alias for updateorinsert().

``updateorinsert_inserted()``  Returns true if the previous updateorinsert performed an insert rather than an update.

``update($table, $keyvalues, $values)``  Looks in table for a row matching keyvalues.  If found, updates values.  If not found, does nothing.  Returns false only if there was a database error.  **Should this be improved to return the record number instead?**

``insert($table, $values)``  Inserts a new record in table with values and returns the new record id, or 0 if a failure occurred.  If id is specified and is zero, it will be omitted so MySQL can auto-increment the value of this field instead.

``deleteFrom($table, $keyvalues, [$limit])``  Delete matching records from table.

``select($table, [$fields], $keyvalues, [$clauses])``  Performs the query, in the basic form of: SELECT fields FROM table WHERE keyvalues clauses;  keyvalues is an associative array, and clauses is a qsafe-compatible array (see qsafe)  The return value is an associative array of the first row of the result set.  The result set remains loaded for use with selectRow() and selectClose()

``selecta($table, [$fields], $keyvalues, [$clauses])``  Performs the query, in the basic from of: SELECT fields FROM table WHERE keyvalues clauses;  keyvalues is an associative array, and clauses is a qsafe-compatible array (see qsafe)  The return value is an associative array of the first row of the result set, the result set is freed immediately.  The short form ``selecta($table, [$fields], $rowid)`` is also supported.  If $fields is omitted in either form, "*" is assumed.

``selectRow()``  Returns the first (or next) row in the resultset retrieved by calling select()  Note that even though the first row was already made available as the result of the call to select(), it is returned again on the first call to selectRow() to make looping and conditionals more flexible.  Once you've called selectRow() on a result set, another call to select() will push the previous result set onto a stack internally, and calling selectClose() will close the inner query and pop the original query back into active status.  This stacking behavior allows you to nest an entire "select / while-selectRow / selectClose" structure inside of the loop body of another one.

``txBegin()``  Start a database transaction.  These calls are stackable so if a function requires transactional behavior and is already in a transaction, only the outermost transaction will actually be committed or the whole thing will be rolled back as a whole.

``txCommit()``  End (and commit) a database transaction.  If nested, does nothing until caled on the outermost transaction level.

``txCancel()``  Roll back a database transaction.  The current implementation calls die() if this is called from within an interior transaction since InnoDB and mysqli don't support nested transactions.

``qsafe(...)``  For any query too sophisticated (or convoluted) to be written using the standard functions (selecta, select, updateorinsert, update, insert, or deleteFrom), you must run the query text through this in a special parameterized form in order to ensure safety against injection attacks.  See section below, Using qsafe().

``sq(&$queryStringOrObject)``  Returns the first result (if a string was passed) and converts the string variable into a queryObject (class Q).  If a queryObject is passed (i.e., the second pass through a while loop), it will return the next result.  If no more results are available, returns false and automatically frees the queryObject.  To demonstrate the general utility of this, you would store the output of a call to qsafe into a variable, then run a loop of this form: while ($result = sq($qs)) { /* process $result */ } in order to process each of the results.

``qf($queryObject)``  Frees a queryObject.

``sqf($queryString)``  Returns the first (or only) result of a query and immediately frees the result set.

``sqx($qs)``  Executes an SQL statement (or an array of statements) that does/do not return [a] result set.

#### Using qsafe()

qsafe takes a variable number of parameters.  The first parameter is a string, which is either a whole query or a fragment of a query.  So, in the simplest form:

``$qs = qsafe('SELECT `version` FROM `global`');``

qsafe prevents numbers or quoted string literals from being used directly in a query.  So, the following will FAIL:

``$qs = qsafe('SELECT * FROM `user` WHERE email = "sample@domain.tld"');``

This is by design, because the most common type of injection is when a programmer does something like this:

``$qs = 'SELECT * FROM `user` WHERE email = "' . $_REQUEST['email'] . '"'; /* BAD */``

So instead, we handle all the quoting and escaping and concatenation, and you must do this:

``$qs = qsafe('SELECT * FROM `user` WHERE email = $', $_REQUEST['email']);``

Notice the $ terminating the first argument.  This tells qsafe that the next argument is a string which will need to be properly escaped and quoted when the final query is composed.

If a fragment ends with the special symbol #, the following argument will be treated as a numeric argument.  It will be confirmed to be a number and placed into the final query without quotes.  If it fails to be processed as a number, the number zero will be assumed, so if you want other behavior, you need to check your inputs and perform appropriate actions before passing them to qsafe.

The only place qsafe gets a little awkward is if you are trying to pass literals to a function.  This, for example, will not work:

``$qs = qsafe('SELECT SUBSTRING("Hello", 3, 2)'); /* BAD */``

With qsafe, you'd have to do it like this:

``$qs = qsafe('SELECT SUBSTRING($', "Hello", ', #', 3, ', #', 2, ')');``

What's going on here?  Lets take out every other argument and merge those strings together to get a better visual:

``$qs = qsafe('SELECT SUBSTRING($, #, #)'); /* This won't work as is, it is just to demonstrate. */``

All we are doing is breaking this apart, and placing th literal values in adjacent parameters immediately following the $ and # tokens.

#### Functions used internally:

``getSelectFrom($table, $fields, $keyvalues, [$clauses])``  Constructs a query string and returns it, in the basic form of: SELECT fields FROM table WHERE keyvalues clauses;  keyvalues is an associative array, and clauses is a qsafe-compatible array (see qsafe)

``arraytosafe($a, $useand = false)`` Turns an associative array of key values pairs (or literal query fragments with an (auto)numbered key) into either `field` = "value", `field` = "value" notation or the same thing with AND instead of a comma separating each pair.  (BADLY NAMED)

``mes($s)`` Calls mysqli_real_escape_string with the currently active link.  This is used internally by qsafe, and was used directly by a lot of projects before we added qsafe.  For new projects, qsafe should be used instead.

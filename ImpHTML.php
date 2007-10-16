<?php
	require_once('ImpUtils/Utilities.php');

	class ImpHTML {
		var $original;

		function __construct($source) {
			if (is_array($source)) {
				$this->original = implode("\n", $source);
			} else {
				$this->original = $source;
			}

			$this->hasBlockFormatting = preg_match('/(?=<P|<BR|<LI|<TABLE|<DIV|<DD|<HR)/i', $this->original);
			$this->hasDisplayFormatting = preg_match('/(?=<U|<B|<I|<IMG|<FONT|&amp;|&quote;|&#153;|<SPAN)/i', $this->original);
			$this->hasLinks = preg_match('/(?=</A[ \/>]+)/i', $this->original);
		}

		function dbSafe($convert_links = true) {
			/*
			 * FIXME: this function's name is misleading
			 * returns a string containing the source in a form safe to be stored in the database.
			 * plain text has paragraph formatting preserved and [optionally] will have http: and ftp: URLs and
			 * email addresses converted into hyperlinks
			 */

			$output = stripslashes($this->original);

			if (!$this->hasBlockFormatting && !$this->hasDisplayFormatting && !$this->hasLinks) {
				$output = html_encode($output);
			}

			if (!$this->hasBlockFormatting) {
				$output = nl2br($output);
			}

			if (!$this->hasLinks && !$this->hasDisplayFormatting && !$this->hasBlockFormatting && $convert_links) {
				$output = preg_replace('/(\"[^\\\"]+\"|[^\\ \(\)\<\>@,;:\"]+@[a-z0-9-]+\.+[a-z][a-z][a-z]?)/i', '<A HREF="mailto:\1">\1</A>', $output);
				$output = preg_replace('/(http:[^ ]+)/i', '<a href="\1" target="_new">\1</a>', $output);
				$output = preg_replace('/(ftp:[^ ]+)/i', '<a href="\1" target="_new">\1</a>', $output);
			}

			return $output;
		}

		function convertToText() {
			return strip_tags($this->original);
		}

		function convertToHTML() {
			if (!preg_match('/(?=<P|<BR|<LI|<TABLE|<DIV|<HR)/i', $this->original)) {
				return nl2br($this->original);
			} else {
				return $this->original;
			}
		}

		// Static methods for one-line convenience:
		public static function makeText($Source) {
			$t = new ImpHTML($Source);
			return $t->convertToText();
		}

		public static function makeHTML($Source) {
			$t = new ImpHTML($Source);
			return $t->convertToHTML();
		}

		public static function convertTextToHTML($input) {
			$output = preg_replace('/(?<=\s)(\w+@[a-z0-9-]+\.+[a-z][a-z][a-z]?)/i', '<a href="mailto:\1">\1</a>', $input);
			$output = preg_replace('/(http:[^\s]+)/i', '<a href="\1">\1</A>', $output);
			$output = preg_replace('/(ftp:[^\s]+)/i', '<a href="\1">\1</A>', $output);
			return nl2br($output);
		}

		public static function generateQueryStringFromArray($A = false) {
			// TODO: Remove all legaccy uses of this and replace them with the new http_build_query function
			$res = array();

			if (!is_array($A)) {
				$A = $_REQUEST;
			}

			foreach ($A as $k => $v) {
				if (!is_array($v)) {
					$res[] =	urlencode($k) . '=' . urlencode($v);
				} else {
					$k = urlencode($k);

					foreach ($v as $i => $j) {
						$res[] = "{$k}[" . urlencode($i) . ']=' . urlencode($j);
					}
				}
			}

			return implode("&", $res);
		}

		public static function generateSelectFromQuery(ImpDB $DB, $query, $select_name, $selected_value = false, $multiple = false) {
			/*
			 * returns a string containing an HTML SELECT seeded with the results of $query.
			 * $query should be a SQL SELECT statement where the first column will be some sort of identifier
			 * which will be used internally to keep track of the record while the second is displayed to the user
			 * as the text of the option.
			 * If supplied, any ID value (col 1) matching $selected_value will be SELECTED.
			 */

			$html = "<select name=\"$select_name\"" . ($multiple ? " multiple" : "") . ">\n";

			$query_results = $DB->query($query);

			if (!$multiple) {
				$html .= "<option value=\"\">Select...</option>\n";
			}

			if (!is_array($query_results)) {
				trigger_error("Could not load data from '$query':\nError: " + mysql_errno() . ":\n" . mysql_error(), E_USER_ERROR);
			} else {
				if (is_array($selected_value)) {
					foreach ($query_results as $row) {
						$html .= "<option value=\"{$row['ID']}\"" . (in_array($row['ID'], $selected_value) ? " SELECTED" : "") . ">" . html_encode($row['Value']) . "</option>\n";
					}
				} else {
					foreach ($query_results as $row) {
						$html .= "<option value=\"{$row['ID']}\"" . ($row['ID'] == $selected_value ? " SELECTED" : "") . ">" . html_encode($row['Value']) . "</option>\n";
					}
				}
			}

			$html .=	"</select>\n";
			return $html;
		}

		public static function generateSelectForEnum(ImpDB $DB, $table, $column, $selected_value = false) {
			/*
			 * Similar to generateSelectFromQuery but this simplifies the result of creating
			 * a select for all of the valid values of a MySQL ENUM column.
			 */

			$html = "<select name=\"$column\">\n";

			$query_results = $DB->query("DESCRIBE $table $column");

			$html .= "<option value=\"\">Select...</option>\n";

			if (!$query_results) {
				throw new RuntimeException("Could not load definition for ENUM $column in $table");
			} else {
				/*
						The results will be a single row like this:

						mysql> describe Transactions Status;
						+--------+------------------------------------------------------+------+-----+---------+-------+
						| Field | Type																								  | Null | Key | Default | Extra |
						+--------+------------------------------------------------------+------+-----+---------+-------+
						| Status | enum('Pending','Approved','Declined','System Error') |	  	 |		 | Pending |			 |
						+--------+------------------------------------------------------+------+-----+---------+-------+

						We're interested in the Type column. We need to strip the enum('') wrapper off
						and split the values into an array.
				 */
				$Type = $query_results[0]['Type'];

				// Sanity checks:
				assert(substr($Type, 0, 6) == "enum('");
				assert(substr($Type, -2) == "')");
				$Type = substr($Type, 6, -2);

				$Options = explode("','", $Type);

				foreach ($Options as $o) {
					$html .= "<option value=\"{$o}\"" . ($o == $selected_value ? " SELECTED" : "") . ">{$o}</option>\n";
				}
			}

			$html .=	"</select>\n";
			return $html;
		}

		public static function generateCheckboxesForSet(ImpDB $DB, $table, $column, array $selected_values = array()) {
			// Similar to generateSelectForEnum but this generates a checkbox for each possible value of a SET
			$query_results = $DB->query("DESCRIBE $table $column");
			if (empty($query_results)) {
				throw new RuntimeException("Could not load definition for ENUM $column in $table");
			}

			$Type                      = $query_results[0]['Type'];
			assert(substr($Type, 0, 5) == "set('");
			assert(substr($Type, -2)   == "')");
			$Type                      = substr($Type, 5, -2);

			$Options = explode("','", $Type);

			$html = '';

			foreach ($Options as $o) {
				$o_val = html_encode($o);
				$o_id = ImpHTML::makeSafeCSSName($column . $o);
				$html .= '<input type="checkbox" id="' . $o_id .'" name="' . html_encode($column) . '[' . $o_val . ']" ' . (in_array($o, $selected_values) ? " CHECKED" : "") . '><label for="' . $o_id .'">' . $o_val . '</label><br/>';
			}

			return $html;
		}

		public static function filterCheckboxes($values) {
			if (!is_array($values)) return array();

			foreach ($values as $k => $v) {
				$values[$k] = ($v == 'on');
			}

			return $values;
		}

		public static function generateSelectFromArray(array $the_array, $select_name, $selected_value = false, $use_value_as_key = false) {
			/*
			 * returns a string containing an HTML SELECT seeded with the contents of $the_array.
			 * If supplied, any ID value (col 1) matching $selected_value will be SELECTED.
			 */
			$html = "";
			$html .= "<select name=\"$select_name\">\n";

			$html .= "<option value=\"\">Select...</option>\n";

			foreach ($the_array as $ID => $Value) {
				if ($use_value_as_key) {
					$ID = $Value;
				}
				$html .= "<option value=\"$ID\"" . ($ID == $selected_value ? " SELECTED" : "") . ">$Value</option>\n";
			}

			$html .=	"</select>\n";
			return $html;
		}

		public static function generateDateSelect($name, $current_value = false) {
			return ImpHTML::generateSelectFromTime($name, $current_value, true);
		}

		public static function getTimeFromSelectArray(array $Fields) {
			/**
			 * Returns a unix timestamp from the array generated by form submission from
			 * either generateDateSelect() or generateSelectFromTime()
			 *
			 */

			// Defaults: not all of these need to be specified:
			$Year		= 0;
			$Month	= 0;
			$Day		= 0;
			$Hour		= 0;
			$Minute = 0;
			$Second = 0;

			extract($Fields);

			return mktime($Hour, $Minute, $Second, $Month, $Day, $Year);
		}

		public static function generateSelectFromTime($name, $current_value = false, $date_only = false, $show_seconds = false) {
			/*
			 * Returns a string containing HTML SELECTs for the full date and time. The SELECT will have the NAME attribute populated with $name
			 * if $current_value is provided, the appropriate values in each SELECT will be SELECTED to match the unix timestamp $current_value.
			 *
			 */

			if (!$current_value) $current_value = time();

			$CurYear	= date("Y", $current_value);
			$CurMonth = date("m", $current_value);
			$CurDay		= date("d", $current_value);
			$CurHour	= date("H", $current_value);
			$CurMin		= date("i", $current_value);
			$CurSec		= date("s", $current_value);

			$Months		= array(
				'invalid month',
				'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
			);

			$html = '';

			$html .= "<select name=\"{$name}[Year]\">\n";
			for ($i = 1999; $i <= 2020; $i++)			{ $html .= "\t<option value=\"$i\"" . ($i == $CurYear ? " selected" : "") . ">$i</option>\n"; }
			$html .= "</select>\n";

			$html .= "<select name=\"{$name}[Month]\">\n";
			for ($i = 1; $i <= 12; $i++)				{ $html .= "\t<option value=\"$i\"" . ($i == $CurMonth ? " selected" : "") . ">" . $Months[$i] . "</option>\n"; }
			$html .= "</select>\n";

			$html .= "<select name=\"{$name}[Day]\">\n";
			for ($i = 1; $i <= 31; $i++)				{ $html .= "\t<option value=\"$i\"" . ($i == $CurDay ? " selected" : "") . ">" . sprintf("%02d", $i) . "</option>\n"; }
			$html .= "</select>\n";

			if (!$date_only) {
				$html .= "&nbsp;&nbsp;<select name=\"{$name}[Hour]\">\n";
				for ($i = 0; $i <= 23; $i++)				{ $html .= "\t<option value=\"$i\"" . ($i == $CurHour ? " selected" : "") . ">" . sprintf("%02d", $i) . "</option>\n"; }
				$html .= "</select>\n";

				$html .= ":<select name=\"{$name}[Minute]\">\n";
				for ($i = 0; $i <= 59; $i++)				{ $html .= "\t<option value=\"$i\"" . ($i == $CurMin ? " selected" : "") . ">" . sprintf("%02d", $i) . "</option>\n"; }
				$html .= "</select>\n";

				if ($show_seconds) {
					$html .= ":<select name=\"{$name}[Second]\">\n";
					for ($i = 0; $i <= 59; $i++)				{ $html .= "\t<option value=\"$i\"" . ($i == $CurSec ? " selected" : "") . ">" . sprintf("%02d", $i) . "</option>\n"; }
					$html .= "</select>\n";
				}
			}

			return $html;
		}

		public static function generateHiddenInputs(array $source) {
			// Generates hidden inputs for the key=>value pairs in the passed array:

			foreach ($source as $k => $v) {
				print '<input type="hidden" name="' . html_encode($k) . '" value="' . html_encode($v) . '">';
			}
		}

		public static function generateBreadcrumbs($separator = ' &gt; ') {
			// Trim the trailing filename (if any) from the URL, reverse URL encoding (e.g. '%20' => ' ')
			// and finally split it into the component directories:
			$parts = explode('/', urldecode(preg_replace('|/[^/]*$|', '', $_SERVER['REQUEST_URI'])));

			array_shift($parts); // Get rid of empty leading element caused by the leading /

			$path = '';
			$crumbs = array();

			foreach ($parts as $part) {
				$prefix .= "/$part";
				array_push($crumbs, '<a href="' . html_encode($prefix) . '">' . html_encode($part) . '</a>');
			}

			// Implode avoids us needing to check for and suppress the separator after the last element:
			print implode($separator, $crumbs);
		}

		public static function generatePopupLink($Title, $URL, $Target = '_new', $JSOptions = false) {
			return "<a href=\"$URL\" target=\"$Target\" OnClick=\"window.open('$URL', '$Target', '$JSOptions'); return false;\">$Title</a>";
		}

		public static function makeSafeLink($URL, $Title) {
			$Title	= html_encode($Title);
			$URL		= html_encode(trim($URL));
			$uParts = @parse_url($URL);

			if (
				!empty($uParts['scheme'])
				and ($uParts['scheme'] == 'http' or $uParts['scheme'] == 'https' or $uParts['scheme'] == 'ftp')
				and !empty($uParts['host'])
			) {
				return "<a href=\"$URL\">$Title</a>";
				}
		}

		public static function makeSafeCSSName($s) {
			return preg_replace('/[^\w]+/', '_', $s);
		}

		public static function makeSafeJavaScriptName($s) {
			return preg_replace('/[^\w]+/', '_', $s);
		}

		public static function displayErrors(array $Errors) {
			if (!empty($Errors)) {
				echo '<ul class="Errors"><li>', implode('</li><li>', $Errors), '</li></ul>', "\n";
			}
		}

		public static function attributeImplode(array $a) {
			 // Takes an input array and prints the key=value as HTML attributes:
			 $r = array();

			 foreach ($a as $k => $v) {
				 $r[] = $k . '="' . $v . '"';
			 }

			 return implode(' ', $r);
		 }

	}
?>

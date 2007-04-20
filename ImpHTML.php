<?php
	class ImpHTML {
		var $original;

		// Static methods for one-line convenience:
		function makeText($Source) {
			$t = new ImpHTML($Source);
			return $t->convertToText();
		}

		function makeHTML($Source) {
			$t = new ImpHTML($Source);
			return $t->convertToHTML();
		}

		function ImpHTML($source) {
			if (is_array($source)) {
				$this->original = implode("\n", $source);
			} else {
				$this->original = $source;
			}

			$this->hasBlockFormatting = preg_match('/(?=<P|<BR|<LI|<TABLE|<DIV|<DD|<HR)/i', $this->original);
			$this->hasDisplayFormatting = preg_match('/(?=<U|<B|<I|<IMG|<FONT|&amp;|&quote;|&#153;|<SPAN)/i', $this->original);
			$this->hasLinks = preg_match('/(?=</A[ \/>]+)/i', $this->original);
		}

		function convertTextToHTML($input) {
			$output = preg_replace('/(?<=\s)(\w+@[a-z0-9-]+\.+[a-z][a-z][a-z]?)/i', '<a href="mailto:\1">\1</a>', $input);
			$output = preg_replace('/(http:[^\s]+)/i', '<a href="\1">\1</A>', $output);
			$output = preg_replace('/(ftp:[^\s]+)/i', '<a href="\1">\1</A>', $output);
			return nl2br($output);
		}

		function dbSafe($convert_links = true) {
			/*
			 * returns a string containing the source in a form safe to be stored in the database.
			 * plain text has paragraph formatting preserved and [optionally] will have http: and ftp: URLs and
			 * email addresses converted into hyperlinks
			 */

			$output = stripslashes($this->original);

			if (!$this->hasBlockFormatting && !$this->hasDisplayFormatting && !$this->hasLinks) {
				$output = htmlspecialchars($output);
			}

			if (!$this->hasBlockFormatting) {
				$output = nl2br($output);
			}

			if (!$this->hasLinks && !$this->hasDisplayFormatting && !$this->hasBlockFormatting && $convert_links) {
				$output = preg_replace('/(\"[^\\\"]+\"|[^\\	\(\)\<\>@,;:\"]+@[a-z0-9-]+\.+[a-z][a-z][a-z]?)/i', '<A HREF="mailto:\1">\1</A>', $output);
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

		function generateQueryStringFromArray($A = false) {
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

		function generateSelectFromQuery(&$DB, $query, $select_name, $selected_value = false, $multiple = false) {
			/*
			 * returns a string containing an HTML SELECT seeded with the results of $query.
			 * $query should be a SQL SELECT statement where the first column will be some sort of identifier
			 * which will be used internally to keep track of the record while the second is displayed to the user
			 * as the text of the option.
			 * If supplied, any ID value (col 1) matching $selected_value will be SELECTED.
			 */
			assert(is_object($DB));

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
						$html .= "<option value=\"{$row['ID']}\"" . (in_array($row['ID'], $selected_value) ? " SELECTED" : "") . ">" . htmlspecialchars($row['Value']) . "</option>\n";
					}
				} else {
					foreach ($query_results as $row) {
						$html .= "<option value=\"{$row['ID']}\"" . ($row['ID'] == $selected_value ? " SELECTED" : "") . ">" . htmlspecialchars($row['Value']) . "</option>\n";
					}
				}
			}

			$html .=	"</select>\n";
			return $html;
		}

		function generateSelectForEnum(&$DB, $table, $column, $selected_value = false) {
			/*
			 * Similar to generateSelectFromQuery but this simplifies the result of creating
			 * a <SELECT> for all of the valid values of a MySQL ENUM column.
			 */
			assert(is_object($DB));

			$html = "<select name=\"$column\">\n";

			$query_results = $DB->query("DESCRIBE $table $column");

			$html .= "<option value=\"\">Select...</option>\n";

			if (!$query_results) {
				trigger_error("Could not load definition for ENUM $column in $table:\nError: " + mysql_errno() . ":\n" . mysql_error(), E_USER_ERROR);
			} else {
				/*
						The results will be a single row like this:

						mysql> describe Transactions Status;
						+--------+------------------------------------------------------+------+-----+---------+-------+
						| Field	| Type																								 | Null | Key | Default | Extra |
						+--------+------------------------------------------------------+------+-----+---------+-------+
						| Status | enum('Pending','Approved','Declined','System Error') |			|		 | Pending |			 |
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

		function generateSelectFromArray($the_array, $select_name, $selected_value = false, $use_value_as_key = false) {
			/*
			 * returns a string containing an HTML SELECT seeded with the contents of $the_array.
			 * If supplied, any ID value (col 1) matching $selected_value will be SELECTED.
			 */
			$html = "";
			$html .= "<select name=\"$select_name\">\n";

			$html .= "<option value=\"\">Select...</option>\n";

			if (!is_array($the_array)) {
				trigger_error("Error in generate_select_from_array", E_USER_ERROR);
			} else {
				foreach ($the_array as $ID => $Value) {
					if ($use_value_as_key) {
						$ID = $Value;
					}
					$html .= "<option value=\"$ID\"" . ($ID == $selected_value ? " SELECTED" : "") . ">$Value</option>\n";
				}
			}

			$html .=	"</select>\n";
			return $html;
		}

		// TODO: clean up the generation code:
		function generateDateSelect($name, $current_value = false) {
			return ImpHTML::generateSelectFromTime($name, $current_value, true);
		}

		function getTimeFromSelectArray($Fields) {
			/**
			 * Returns a unix timestamp from the array generated by form submission from
			 * either generateDateSelect() or generateSelectFromTime()
			 *
			 */

			if (!is_array($Fields)) {
				return intval($Fields); // better be something meaningful...
			}

			// Defaults: not all of these need to be specified:
			$Year   = 0;
			$Month  = 0;
			$Day    = 0;
			$Hour   = 0;
			$Minute = 0;
			$Second = 0;

			extract($Fields);

			return mktime($Hour, $Minute, $Second, $Month, $Day, $Year);
		}

		function generateSelectFromTime($name, $current_value = false, $date_only = false, $show_seconds = false) {
			/*
			 * Returns a string containing HTML SELECTs for the full date and time. The SELECT will have the NAME attribute populated with $name
			 * if $current_value is provided, the appropriate values in each SELECT will be SELECTED to match the unix timestamp $current_value.
			 *
			 */

			if (!$current_value) $current_value = time();

			$CurYear  = date("Y", $current_value);
			$CurMonth = date("m", $current_value);
			$CurDay   = date("d", $current_value);
			$CurHour  = date("H", $current_value);
			$CurMin   = date("i", $current_value);
			$CurSec   = date("s", $current_value);

			$Months   = array(
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

		function generateHiddenInputs($source) {
			// Generates hidden inputs for the key=>value pairs in the passed array:
			assert(is_array($source));

			foreach ($source as $k => $v) {
				print '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
			}
		}

		function generateBreadcrumbs($separator = ' &gt; ') {
			// Trim the trailing filename (if any) from the URL, reverse URL encoding (e.g. '%20' => ' ')
			// and finally split it into the component directories:
			$parts = explode('/', urldecode(preg_replace('|/[^/]*$|', '', $_SERVER['REQUEST_URI'])));

			array_shift($parts); // Get rid of empty leading element caused by the leading /

			$path = '';
			$crumbs = array();

			foreach ($parts as $part) {
				$prefix .= "/$part";
				array_push($crumbs, '<a href="' . htmlspecialchars($prefix) . '">' . htmlspecialchars($part) . '</a>');
			}

			// Implode avoids us needing to check for and suppress the separator after the last element:
			print implode($separator, $crumbs);
		}

		function generatePopupLink($Title, $URL, $Target = '_new', $JSOptions = false) {
			return "<a href=\"$URL\" target=\"$Target\" OnClick=\"window.open('$URL', '$Target', '$JSOptions'); return false;\">$Title</a>";
		}

		function makeSafeLink($URL, $Title) {
			$Title  = htmlspecialchars($Title);
			$URL    = htmlspecialchars(trim($URL));
			$uParts = @parse_url($URL);

			if (
				!empty($uParts['scheme'])
				and ($uParts['scheme'] == 'http' or $uParts['scheme'] == 'https' or $uParts['scheme'] == 'ftp')
				and !empty($uParts['host'])
			) {
				return "<a href=\"$URL\">$Title</a>";
				}
		}

		function makeSafeCSSName($s) {
			return htmlspecialchars(str_replace(" ", "_", $s));
		}

		function makeSafeJavaScriptName($s) {
			return htmlspecialchars(str_replace(" ", "_", $s));
		}

	}
?>

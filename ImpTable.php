<?php
	class ImpTable {
		var $Data							 = array();
		var $CSS							 = array("Class" => "ImpTable");

		var $generateHeaders	 = true;
		var $generateSortLinks = true;

		var $DefaultSortOrder	 = "Ascending";
		var $_SortKey;
		var $_SortOrder;

		function ImpTable($Data = false) {
			if (!empty($Data)) {
				if (is_array($Data)) {
					$this->Data = $Data;
				} else {
					trigger_error("ImpHTML::ImpTable() called with non-array data!", E_USER_ERROR);
				}
			}
		}

		function _compare($k1, $k2) {
			return strnatcasecmp($k1[$this->_SortKey], $k2[$this->_SortKey]);
		}

		function _reverse_compare($k1, $k2) {
			return -1 * strnatcasecmp($k1[$this->_SortKey], $k2[$this->_SortKey]);
		}

		function AutoSort($Key = false, $Order = false) {
			assert(is_array($this->Data));

			if (empty($Key)) {
				if (!empty($_REQUEST['SortKey'])) {
					$this->_SortKey = $_REQUEST['SortKey'];
				} elseif (!empty($this->DefaultSortKey)) {
					$this->_SortKey = $this->DefaultSortKey;
				} else {
					return;
				}
			}

			if (empty($Order)) {
				if (!empty($_REQUEST['SortOrder'])) {
					$this->_SortOrder = $_REQUEST['SortOrder'];
				} else {
					return;
				}
			}

			if (empty($this->_SortOrder)) {
				$this->_SortOrder = $this->DefaultSortOrder;
			}
			$this->_SortOrder = $this->_SortOrder == "Descending" ? "Descending" : "Ascending";

			reset($this->Data);
			if (!empty($this->_SortKey) and array_key_exists($this->_SortKey, current(($this->Data)))) {
				usort($this->Data, array($this, ($this->_SortOrder == "Ascending" ? "_compare" : "_reverse_compare")));
			}
		}

		function generate() {
			assert(is_array($this->Data));
			if (empty($this->Data)) {
				return;
			}

			reset($this->Data);
			assert(is_array(current($this->Data)));

			print "<table";
			foreach ($this->CSS as $k => $v) {
				print ' ' . strtolower($k) . "=\"$v\"";
			}
			print ">\n";

			if (!empty($this->Caption)) {
				print "\t<caption>" . $this->Caption . "</caption>\n";
			}

			if ($this->generateHeaders) {
				print "\t<tr>\n";

				if (!empty($this->ColumnHeaders)) {
					$Headers = $this->ColumnHeaders;
				} else {
					reset($this->Data);
					$Headers = array_keys(current($this->Data));
				}

				foreach ($Headers as $h) {
					print "\t\t<th>";

					if ($this->generateSortLinks) {
						if (!empty($_REQUEST['SortKey']) and !empty($_REQUEST['SortOrder']) and $_REQUEST['SortKey'] == $h) {
							$_REQUEST['SortOrder'] = $_REQUEST['SortOrder'] == "Ascending" ? "Descending" : "Ascending";
						} else {
							$_REQUEST['SortOrder'] = $this->DefaultSortOrder;
						}
						$_REQUEST['SortKey'] = $h;

						print "<a href=\"{$_SERVER['PHP_SELF']}?" . ImpHTML::generateQueryStringFromArray($_REQUEST) . "\">" . html_encode($h) . "</a>";
					} else {
						print html_encode($h);
					}

					print "</th>\n";
				}

				print "\t</tr>\n";
			}


			foreach ($this->Data as $id => $D) {
				print "\t<tr id=\"$id\">\n";
				foreach ($D as $k => $v) {
					print "\t\t<td class=\"" . ImpHTML::makeSafeCSSName($k) . "\">" . html_encode($v) . "</td>\n";
				}
				print "\t</tr>\n";
			}

			print "</table>\n";
		}
	};
?>

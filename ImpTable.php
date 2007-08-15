<?php
	/*
		Simple HTML table generation - this currently is implemented using YUI DataTable.

		Basic Usage:
			$IT = new ImpTable($data_array);
			$IT->generate();

		Customization:
			$IT->Caption = "My ImpTable!";
			$IT->Attributes['id'] = 'My_Custom_CSS_ID';

			$QueryTable->AutoSort('aColumn', 'Descending');

			$QueryTable->ColumnHeaders = array(
				'aColumn' => array('text' => 'Column Name', 'type'=> 'number', 'sortable' => true, 'formatter' => 'myCustomYUIFormatter'),
			);

	*/


	class ImpTable {
		var $Data              = array();
		var $Attributes        = array("class" => "ImpTable");
		var $DefaultSortOrder  = "Ascending";
		var $DefaultSortKey;
		protected $_SortKey;
		protected $_SortOrder;

		protected static $JS_Initialized = false;

		function ImpTable(array $Data = array()) {
			$this->Data = $Data;
		}

		function AutoSort($Key = false, $Order = false) {
			assert(is_array($this->Data));
			if (empty($this->Data)) return;

			if (!empty($this->_autoSorted)) return;
			$this->_autoSorted = true;

			if (empty($Key) and !empty($_REQUEST['SortKey'])) {
				$this->_SortKey = $_REQUEST['SortKey'];
			}

			if (empty($Order) and !empty($_REQUEST['SortOrder'])) {
				$this->_SortOrder = $_REQUEST['SortOrder'];
			}

			if (empty($this->_SortOrder)) {
				$this->_SortOrder = $this->DefaultSortOrder;
			}

			$this->_SortOrder = $this->_SortOrder == "Descending" ? "Descending" : "Ascending";

			if (empty($this->_SortKey)) {
				$this->_SortKey = !empty($this->DefaultSortKey) ? $this->DefaultSortKey : current(array_keys(reset($this->Data)));
			}

			assert(array_key_exists($this->_SortKey, reset($this->Data)));

			reset($this->Data);
			if (!empty($this->_SortKey) and array_key_exists($this->_SortKey, current(($this->Data)))) {
				usort($this->Data, array($this, ($this->_SortOrder == "Ascending" ? "_compare" : "_reverse_compare")));
			}
		}

		protected function _compare($k1, $k2) {
			return strnatcasecmp($k1[$this->_SortKey], $k2[$this->_SortKey]);
		}
		protected function _reverse_compare($k1, $k2) {
			return -1 * strnatcasecmp($k1[$this->_SortKey], $k2[$this->_SortKey]);
		}

		function __get($p) {
			switch($p) {
				case "ColumnHeaders":
					if (!empty($this->ColumnHeaders)) {
						return $this->ColumnHeaders;
					} else {
						return array_combine(array_keys(reset($this->Data)), array_keys(reset($this->Data)));
					}

				default:
					return $this->$p;
			}
		}

		function generate() {
			assert(is_array($this->Data));
			if (empty($this->Data)) {
				return;
			}

			reset($this->Data);
			if(!is_array(current($this->Data))) {
				throw new Exception(__CLASS__ . '->Data is not a two-dimensional array!');
			}

			// This is currently necessary because YUI DataSources assume associative arrays and will display '[Object object]' for the value of a numeric array:
			if (isset($this->Data[0][0])) {
				$this->makeDataYUISafe();
			}

			$this->AutoSort();

			if (empty($this->Attributes['id'])) {
				$this->Attributes['id'] = uniqid('ImpTable_');
			}

			$Headers = $this->ColumnHeaders;

			$JSName = $this->Attributes['id'];
			assert(preg_match('/^\w+$/', $JSName)); // The list of safe CSS and JavaScript names is extremely similar so we leave it up to the developer to pick a valid name

			if (!ImpTable::$JS_Initialized) {
				ImpTable::$JS_Initialized = true;
?>
		<script src="http://yui.yahooapis.com/2.3.0/build/yahoo/yahoo-min.js" type="text/javascript"></script>
		<script src="http://yui.yahooapis.com/2.3.0/build/yuiloader/yuiloader-beta-min.js" type="text/javascript"></script>
		<script type="text/javascript" charset="utf-8">
			ImpTable_Generators = new Array();

			if (window.YAHOO && !(YAHOO.util && YAHOO.util.YUILoader)) {
				alert("Existing YAHOO_config found; please add the YUILoader module!");
			} else {
				ImpTable_Loader = new YAHOO.util.YUILoader();
				ImpTable_Loader.require('datatable', 'datasource');
				ImpTable_Loader.insert(function() { while(f = ImpTable_Generators.pop()){ f(); } });
			}
		</script>
<? 
		} 

		echo '<div ';
		foreach ($this->Attributes as $k => $v) {
			echo $k, '="', htmlspecialchars($v), '" ';
		}
		echo '></div>';

?>

		<script type="text/javascript">
			function generate_<?=$JSName?>() {
				<?=$JSName?>_DataSource = new YAHOO.util.DataSource(<?=json_encode($this->Data)?>);
				<?=$JSName?>_DataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
				<?=$JSName?>_DataSource.responseSchema = { fields: ["<?=implode(array_keys($Headers), '","')?>"] };
				<?=$JSName?>_DataTable = new YAHOO.widget.DataTable(document.getElementById('<?=$JSName?>'), <?=json_encode($this->getYUIColumnDefinitions())?>, <?=$JSName?>_DataSource, <?=json_encode($this->getYUIDataTableOptions())?>);
			}

			window.ImpTable_Generators.push(generate_<?=$JSName?>);
		</script>
<?
		}

		protected function makeDataYUISafe() {
			$formatKey = create_function('$s', 'return "Column " . ($s + 1);');
			foreach ($this->Data as $k => $v) {
				$this->Data[$k] = array_combine(array_map($formatKey, array_keys($v)), array_values($v));
			}
		}

		protected function getYUIColumnDefinitions() {
			$Columns = array();

			foreach($this->ColumnHeaders as $name => $display) {
				$def = array('key' => $name, 'sortable' => true);
				if (is_array($display)) {
					$def = array_merge($def, $display);
				}
				$Columns[] = $def;
			}

			return $Columns;
		}

		protected function getYUIDataTableOptions() {
			$opts = array();

			if (!empty($this->Caption)) {
				$opts['caption'] = $this->Caption;
			}

			if (!empty($this->DefaultSortKey)) {
				$opts['sortedBy'] = array('colKey' => $this->DefaultSortKey, 'dir' => $this->DefaultSortOrder == 'Ascending' ? 'asc' : 'desc');
			}

			return $opts;
		}
	};
?>

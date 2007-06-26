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

		function ImpTable(array $Data = array()) {
			$this->Data = $Data;
		}

		function AutoSort($Key = false, $Order = false) {
			assert(is_array($this->Data));

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
				$this->_SortKey = !empty($this->DefaultSortKey) ? $this->DefaultSortKey : array_keys(array_first($this->Data));
			}

			assert(array_key_exists($this->_SortKey, array_first($this->Data)));

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
						reset($this->Data);
						return array_combine(array_keys(current($this->Data)), array_keys(current($this->Data)));
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
			assert(is_array(current($this->Data)));

			$this->AutoSort();

			if (empty($this->Attributes['id'])) {
				$this->Attributes['id'] = uniqid('ImpTable_');
			}

			$Headers = $this->ColumnHeaders;

			$JSName = ImpHTML::makeSafeJavaScriptName($this->Attributes['id']);
?>
			<script>
				{
					// TODO: generalize this into a JavaScript equivalent to require_once()
					document.write('<link type="text/css" rel="stylesheet" href="http://yui.yahooapis.com/2.2.2/build/datatable/assets/datatable.css" />');
				}

				document.write('<script type="text/javascript" src="http://yui.yahooapis.com/2.2.2/build/yahoo-dom-event/yahoo-dom-event.js"><' + '/script>');
				document.write('<script type="text/javascript" src="http://yui.yahooapis.com/2.2.2/build/datasource/datasource-beta-min.js"><' + '/script>');
				document.write('<script type="text/javascript" src="http://yui.yahooapis.com/2.2.2/build/datatable/datatable-beta-min.js"><' + '/script>');
			</script>

			<div <?=ImpHTML::attributeImplode($this->Attributes)?>></div>

			<script type="text/javascript">
				var <?=$JSName?>_DataSource = new YAHOO.util.DataSource(<?=json_encode($this->Data)?>);
				<?=$JSName?>_DataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
				<?=$JSName?>_DataSource.responseSchema = { fields: ["<?=implode(array_keys($Headers), '","')?>"] };

				var <?=$JSName?>_ColumnSet = new YAHOO.widget.ColumnSet([<?
					foreach($Headers as $name => $display) {
						if (is_array($display)) {
							echo json_encode(array_merge(array('key' => $name, 'text' => $name, 'sortable' => true), $display)), ",\n";
						}	else {
							echo '{key:"', $name, '",text:"', $display, '", sortable:true},';
						}
					}
				?>]);

				<?
					//FIXME: Ugly hack around the fact that we're using json_encode for all of the ColumnSet options but need to pass function references to some of them and those need to be barewords rather than quoted strings
					foreach ($Headers as $name => $display) {
						if (!empty($display['sortOptions']['ascFunction'])) {
							echo 'for (var i in ', $JSName, '_ColumnSet.flat) { if (', $JSName, '_ColumnSet.flat[i].key != "', $name ,'") continue;';
							echo $JSName, '_ColumnSet.flat[i].ascFunction=', $display['sortOptions']['ascFunction'], ";\n";
							echo $JSName, '_ColumnSet.flat[i].descFunction=', $display['sortOptions']['descFunction'], ";\n";
							echo $JSName, '_ColumnSet.flat[i].sortOptions.ascFunction=', $display['sortOptions']['ascFunction'], ";\n";
							echo $JSName, '_ColumnSet.flat[i].sortOptions.descFunction=', $display['sortOptions']['descFunction'], ";\n";
							echo "};\n";
						}

						if(!empty($display['formatter'])) {
							echo 'for (var i in ', $JSName, '_ColumnSet.flat) { if (', $JSName, '_ColumnSet.flat[i].key != "', $name ,'") continue;';
							echo $JSName, '_ColumnSet.flat[i].formatter=', $display['formatter'], ";\n";
							echo "};\n";
						}
					}

				?>

				var <?=$JSName?>_DataTable = new YAHOO.widget.DataTable(document.getElementById('<?=$JSName?>'), <?=$JSName?>_ColumnSet, <?=$JSName?>_DataSource, <?=json_encode($this->_getDataTableOptions())?>);
			</script>
<?
		}

		protected function _getDataTableOptions() {
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

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
		public $Data                     = array();
		public $Attributes               = array('class' => 'ImpTable');
		public $DefaultSortOrder         = 'Ascending';
		public $DefaultSortKey;
		public $JSRenderQueue            = 'ImpTable_Generators'; // This is the name of the JavaScript array which we will add the renderer for the table to
		private $_SortKey;
		private $_SortOrder;
		protected static $JS_Initialized = false;

		function ImpTable(array $Data = array()) {
			$this->Data = $Data;
		}

		function AutoSort($Key = false, $Order = false) {
			assert(is_array($this->Data));
			if (empty($this->Data)) return;

			if (!empty($this->_autoSorted)) return;
			$this->_autoSorted = true;

			$this->_SortOrder = $this->DefaultSortOrder == 'Descending' ? 'Descending' : 'Ascending';
			$this->_SortKey = !empty($this->DefaultSortKey) ? $this->DefaultSortKey : current(array_keys(reset($this->Data)));

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

			if (empty($this->Attributes['id'])) {
				$this->Attributes['id'] = uniqid('ImpTable_');
			}

			$Headers = $this->ColumnHeaders;

			$JSName = $this->Attributes['id'];
			assert(preg_match('/^\w+$/', $JSName)); // The list of safe CSS and JavaScript names is extremely similar so we leave it up to the developer to pick a valid name

			if (!ImpTable::$JS_Initialized) {
				ImpTable::$JS_Initialized = true;
?>
		<script type="text/javascript" charset="utf-8">
			ImpTable_Generators	= new Array();
			ImpTable_Renderer 	= function() { while(f = ImpTable_Generators.pop()){ f(); }; };

			if (window.addEventListener) {
				window.addEventListener("load", ImpTable_Renderer, false);
			} else if (window.attachEvent) {
				window.attachEvent("onload", ImpTable_Renderer);
			}

			if (!(window.YAHOO && YAHOO.widget && YAHOO.widget.DataTable && YAHOO.util && YAHOO.util.DataSource)) {
				if (!window.YAHOO || !YAHOO.util || !YAHOO.util.YUILoader) {
					YAHOO_config = { load: { require: [ 'datatable', 'datasource' ] }, onLoadComplete:ImpTable_Renderer };
					document.write(unescape('%3Cscript%20src%3D%22http%3A%2F%2Fyui.yahooapis.com%2F2.3.1%2Fbuild%2Fyuiloader%2Fyuiloader-beta-min.js%22%20type%3D%22text%2Fjavascript%22%3E%3C%2Fscript%3E'));
				} else {
					ImpTable_Loader = new YAHOO.util.YUILoader();
					ImpTable_Loader.require('datatable', 'datasource');
					ImpTable_Loader.insert(ImpTable_Renderer);
				}
			}
		</script>
<?
		}

		echo '<div ', ImpHTML::attributeImplode($this->Attributes), '></div>';
?>
		<script type="text/javascript">
			<?=$this->JSRenderQueue?>.push(function () {
				var <?=$JSName?>_DataSource = new YAHOO.util.DataSource(<?=json_encode($this->Data)?>);
				<?=$JSName?>_DataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
				<?=$JSName?>_DataSource.responseSchema = { fields: ["<?=implode(array_keys($Headers), '","')?>"] };
				<?=$JSName?>_DataTable = new YAHOO.widget.DataTable(document.getElementById('<?=$JSName?>'), <?=json_encode($this->getYUIColumnDefinitions())?>, <?=$JSName?>_DataSource, <?=json_encode($this->getYUIDataTableOptions())?>);
			});

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

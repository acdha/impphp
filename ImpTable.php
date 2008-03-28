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

		Using External Data:
		


	*/

	class ImpTable {
		public $Data                = array();
		public $Attributes          = array('class' => 'ImpTable');
		public $DefaultSortOrder    = 'Ascending';
		public $DefaultSortKey;
		public $JSRenderQueue       = 'ImpTable_Generators'; // This is the name of the JavaScript array which we will add the renderer for the table to
		public $yuiDataTableOptions = array();
		private $_dataType          = 'internal';
		private $_dataSource;
		private $_dataSourceOptions;
		private $_SortKey;
		private $_SortOrder;

		/*
			This variable is used to control whether the necessary JavaScript
	    libraries have been included. If true ImpTable assumes that the
	    YUI DataTable and DataSource are ready to use.
		*/
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
			return strnatcasecmp($k2[$this->_SortKey], $k1[$this->_SortKey]);
		}

		function __get($p) {
			switch($p) {
				case "ColumnHeaders":
					if (!empty($this->ColumnHeaders)) {
						return $this->ColumnHeaders;
					} elseif ($this->_dataType == 'internal') {
						return array_combine(array_keys(reset($this->Data)), array_keys(reset($this->Data)));
					} elseif (!empty($this->_dataSourceOptions['fields'])) {
						return array_combine(array_values($this->_dataSourceOptions['fields']), array_values($this->_dataSourceOptions['fields']));
					}
					throw new RuntimeException("ColumnHeaders has not been defined and there is no data source to generate it from!");

				default:
					return $this->$p;
			}
		}

		function generate() {
			if (!ImpTable::$JS_Initialized) {
				$this->initializeJavaScriptEnvironment();
			}

			if (empty($this->Attributes['id'])) {
				$this->Attributes['id'] = uniqid('ImpTable_');
			}

			$this->JSName = $this->Attributes['id'];
			assert(preg_match('/^\w+$/', $this->JSName)); // The list of safe CSS and JavaScript names is extremely similar so we leave it up to the developer to pick a valid name

			$attrs = array();
			foreach ($this->Attributes as $k => $v) {
				$attrs[] = "$k=\"$v\"";
			}

			echo '<div ', implode(" ", $attrs), '></div>';

			$this->generateDataSource();
?>
			<script type="text/javascript">
				<?=$this->JSRenderQueue?>.push(function () {
					<?=$this->JSName?>_DataTable = new YAHOO.widget.DataTable(
						document.getElementById('<?=$this->JSName?>'), 
						<?=json_encode($this->getYUIColumnDefinitions())?>, 
						<?=$this->JSDataSourceName?>, 
						<?=json_encode($this->getYUIDataTableOptions())?>
					);
				});
			</script>
<?
			flush();
		}

		protected function generateDataSource() {
			if (empty($this->JSDataSourceName)) {
				$this->JSDataSourceName = $this->JSName . "_DataSource";
			}
			
			switch ($this->_dataType) {
				case 'datasource':
					break;

				case 'internal':
					$this->generateInternalDataSource();
					break;
					
				case 'xml':
				case 'json':
				case 'text':
					$this->generateExternalDataSource();
					break;

				default:
					throw new InvalidArgumentException("useData() called with unknown data type: {$this->_dataType}");
			}			
			
			flush(); // Large data sources may take time to process and we want remote ones to start loading as quickly as possible 
		}
		
		private function generateInternalDataSource() {
			assert(is_array($this->Data));
			if (empty($this->Data)) {
				return;
			}

			reset($this->Data);
			if (!is_array(current($this->Data))) {
				throw new InvalidArgumentException(__CLASS__ . '->Data is not a two-dimensional array!');
			}

			// This is currently necessary because YUI DataSources assume associative arrays and will display '[Object object]' for the value of a numeric array:
			if (isset($this->Data[0][0])) {
				$this->makeDataYUISafe();
			}			
			
		?>
			<script type="text/javascript">
				<?=$this->JSRenderQueue?>.push(function () {
					<?=$this->JSDataSourceName?> = new YAHOO.util.DataSource(<?=json_encode($this->Data)?>);
					<?=$this->JSDataSourceName?>.responseType   = YAHOO.util.DataSource.TYPE_JSARRAY;
					<?=$this->JSDataSourceName?>.responseSchema = { fields: ["<?=implode(array_keys($this->ColumnHeaders), '","')?>"] };
				});
			</script>
		<?php	
		}

		private function generateExternalDataSource() {
			assert(!empty($this->_dataType));
			assert(!empty($this->_dataSource));
			assert(!empty($this->_dataSourceOptions));
			
			switch ($this->_dataType) {
				case 'xml': 	$dsType = 'TYPE_XML'; break;
				case 'json':	$dsType = 'TYPE_JSON'; break;
				case 'text':	$dsType = 'TYPE_TEXT'; break;
				default: throw new InvalidArgumentException("generateExternalDataSource() called with unknown data type: {$this->_dataType}");				 
			}
			
			$ds = $this->_dataSource;
			if (strpos('?', $ds) === FALSE) $ds .= '?'; // We'll avoid messing with existing query strings but help the user out if they forgot to begin one
		?>
			<script type="text/javascript">
				<?=$this->JSRenderQueue?>.push(function () {
					<?=$this->JSDataSourceName?> = new YAHOO.util.DataSource(<?=json_encode($ds)?>);
					<?=$this->JSDataSourceName?>.responseType   = YAHOO.util.DataSource.<?=$dsType?>;
					<?=$this->JSDataSourceName?>.responseSchema = <?=json_encode($this->_dataSourceOptions)?>;
				});
			</script>
		<?php	
		}

		public function useData($type, $source, $args = array()) {
			$this->_dataType          = strtolower($type);
			$this->_dataSource        = $source;
			$this->_dataSourceOptions = $args;
			
			if ($this->_dataType == "datasource") {
				$this->JSDataSourceName = $source;
			}
		}

		protected function makeDataYUISafe() {
			$formatKey = create_function('$s', 'return "Column " . ($s + 1);');
			foreach ($this->Data as $k => $v) {
				$this->Data[$k] = array_combine(array_map($formatKey, array_keys($v)), array_values($v));
			}
		}

		protected function getYUIColumnDefinitions() {
			$Columns = array();

			foreach ($this->ColumnHeaders as $name => $display) {
				$def = array('key' => $name, 'sortable' => true);
				if (is_array($display)) {
					$def = array_merge($def, $display);
				}
				$Columns[] = $def;
			}

			return $Columns;
		}

		protected function getYUIDataTableOptions() {
			if (!empty($this->Caption) and empty($this->yuiDataTableOptions['caption'])) {
				$this->yuiDataTableOptions['caption'] = $this->Caption;
			}

			if (!empty($this->DefaultSortKey) and empty($this->yuiDataTableOptions['sortedBy'])) {
				$this->yuiDataTableOptions['sortedBy'] = array('colKey' => $this->DefaultSortKey, 'dir' => $this->DefaultSortOrder == 'Ascending' ? 'asc' : 'desc');
			}

			return $this->yuiDataTableOptions;
		}
		
		public function initializeJavaScriptEnvironment() {
			ImpTable::$JS_Initialized = true;
			?>
				<script type="text/javascript" charset="utf-8">
					if (!window.YAHOO || !YAHOO.util || !YAHOO.util.YUILoader) {
						document.write(unescape('%3Cscript%20type%3D%22text%2Fjavascript%22%20src%3D%22http%3A%2F%2Fyui.yahooapis.com%2F2.5.1%2Fbuild%2Fyuiloader%2Fyuiloader-beta-min.js%22%3E%3C%2Fscript%3E'));
					}
				</script>

				<script type="text/javascript" charset="utf-8">
					<?=$this->JSRenderQueue?>	= new Array();
					ImpTable_Renderer = function() { while(f = <?=$this->JSRenderQueue?>.shift()){ f(); }; };

					if (!(window.YAHOO && YAHOO.widget && YAHOO.widget.DataTable && YAHOO.util && YAHOO.util.DataSource)) {
						ImpTable_Loader = new YAHOO.util.YUILoader({
							require: ['datatable', 'datasource'],
							optional:true,
							onSuccess:ImpTable_Renderer
						});
						ImpTable_Loader.insert();
					} else {
						YAHOO.util.Event.onDOMReady(ImpTable_Renderer);
					}
				</script>
			<?
				flush();
		}
	};
?>

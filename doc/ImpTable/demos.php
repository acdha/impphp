<?
  require_once('ImpPHP/ImpTable.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>
      ImpTable Demos
    </title>
    <link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.5.1/build/reset-fonts-grids/reset-fonts-grids.css" />
    <link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.5.1/build/base/base-min.css" />
    <link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.5.1/build/datatable/assets/skins/sam/datatable.css" />
    <script type="text/javascript" src="http://yui.yahooapis.com/2.5.1/build/yahoo-dom-event/yahoo-dom-event.js" xml:space="preserve">
</script>
    <script type="text/javascript" src="http://yui.yahooapis.com/2.5.1/build/connection/connection-min.js" xml:space="preserve">
</script>
    <script type="text/javascript" src="http://yui.yahooapis.com/2.5.1/build/element/element-beta-min.js" xml:space="preserve">
</script>
    <script type="text/javascript" src="http://yui.yahooapis.com/2.5.1/build/datasource/datasource-beta-min.js" xml:space="preserve">
</script>
    <script type="text/javascript" src="http://yui.yahooapis.com/2.5.1/build/datatable/datatable-beta-min.js" xml:space="preserve">
</script>
    <link rel="stylesheet" type="text/css" media="screen" href="../docs.css" />
  </head>
  <body>
    <div>
      <h1>
        Basic Usage: PHP arrays
      </h1>
      <p>
        This is the most basic case where you have a PHP array containing data and want to easily create an HTML table, with various handy features such as sorting, captions, custom value formats, etc.
      </p>
      <pre xml:space="preserve">
$SimpleTable = new ImpTable( 
  array( 
    array("foo", "bar"), 
    array("baaz", "quux") 
  )
); 
$SimpleTable-&gt;Caption = 'Metasyntactic'; 
$SimpleTable-&gt;generate();
</pre>

      <?php
        $SimpleTable = new ImpTable( array( 1=>array("foo", "bar"), 2=>array("baaz", "quux") ) );
        $SimpleTable->Caption = 'Metasyntactic';
        $SimpleTable->generate();
      ?>
      <h1>
        Styling and Scripting
      </h1>
      <p>
        ImpTable exposes its structure through CSS and you can assign arbitrary attributes using the <code>ImpTable-&gt;Attributes</code> array:
      </p>
      <pre xml:space="preserve">
$QueryTable-&gt;Attributes['id'] = "ReportTable5";
$QueryTable-&gt;Attributes['class'] = "ReportTable";
</pre>
      <p>
        For a full example of how this can be used, you can see the optional database profiling report in ImpDB-&gt;printQueryLog(): <a href="http://svn.improbable.org/ImpPHP/trunk/ImpDB.php">ImpDB.php</a>
      </p>
      <p>
        The same approach can be used to add custom formatting callbacks, sort functions, etc. in your javascript before calling <code>ImpTable-&gt;generate()</code>. This works exactly as it does in normal DataTables - if it doesn't, please <a href="mailto:chris@improbable.org">let me know!</a>
      </p>
      <h1>
        Remote Data
      </h1>
      <p>
        Frequently you want to load data which is generated externally and doesn't slow your initial page rendering with all of that data. The assumption in ImpTable is that it's most useful to help keep the simple stuff simple. The examples below illustrate various was to load external data for simple needs. The last example shows how you can create an external <a href="http://developer.yahoo.com/yui/datasource/" title="Yahoo! UI Library: DataSource">DataSource</a> and use that when your requirements become more complicated.
      </p>
      <h2>
        XML using xmlHttpRequest
      </h2>
      <pre xml:space="preserve">
$xhrTable = new ImpTable();
$xhrOptions = array(
  'resultNode' =&gt; 'row',
  'fields'     =&gt; array('col1','col2')
);
$xhrTable-&gt;useData('XML', 'data.xml', $xhrOptions);
// In a real project this would specify the initial search parameters:
$xhrTable-&gt;yuiDataTableOptions['initialRequest'] = "dummy=…"; 
$xhrTable-&gt;Caption = 'XML loaded using XHR';
$xhrTable-&gt;generate();       
      
</pre><?php
                            $xhrTable = new ImpTable();
                            $xhrOptions = array(
                              'resultNode' => 'row',
                              'fields'     => array('col1','col2')
                            );
                            $xhrTable->useData('XML', 'data.xml', $xhrOptions);
                            // In a real project this would specify the initial search parameters:          
                            $xhrTable->yuiDataTableOptions['initialRequest'] = "dummy=…"; 
                            $xhrTable->Caption = 'XML loaded using XHR';
                            $xhrTable->generate();
                        ?>
      <h2>
        JSON using xmlHttpRequest
      </h2>
      <pre xml:space="preserve">
$jsonTable = new ImpTable();
$jsonOptions = array(
  'resultsList' =&gt; "metavars", 
  'fields'      =&gt; array("col1","col2") 
);
$jsonTable-&gt;useData('JSON', 'data.json', $jsonOptions);
$jsonTable-&gt;Caption = 'JSON loaded using XHR';
$jsonTable-&gt;generate();
</pre><?php
                            $jsonTable = new ImpTable();
                            $jsonOptions = array(
                              'resultsList' => "metavars", 
                              'fields'      => array("col1","col2") 
                            );
                            $jsonTable->useData('JSON', 'data.json', $jsonOptions);
                            $jsonTable->Caption = 'JSON loaded using XHR';
                            $jsonTable->generate();
                        ?>
      <h2>
        Text using xmlHttpRequest
      </h2>
      <pre xml:space="preserve">
$TextTable = new ImpTable();
$TextTable-&gt;Attributes['id'] = "TextTable";
$txtSchema = array(
  'recordDelim' =&gt; "\n", 
  'fieldDelim'  =&gt; "\t", 
  'fields'      =&gt; array('Column 1', 'Column 2')
);
$TextTable-&gt;useData('Text', 'data.txt', $txtSchema);
$TextTable-&gt;Caption = 'Text loaded using XHR';
$TextTable-&gt;generate();  
</pre><?php
                            $TextTable = new ImpTable();
                            $TextTable->Attributes['id'] = "TextTable";
                            $txtSchema = array(
                              'recordDelim' => "\n", 
                              'fieldDelim'  => "\t", 
                              'fields'      => array('Column 1', 'Column 2')
                            );
                            $TextTable->useData('Text', 'data.txt', $txtSchema);
                            $TextTable->Caption = 'Text loaded using XHR';
                            $TextTable->generate();
                        ?>
      <h2>
        Using an existing YUI DataSource
      </h2><script type="text/javascript" charset="utf-8" xml:space="preserve">
//<![CDATA[
        my_preloaded_datasource                = new YAHOO.util.DataSource("data.xml?");
        my_preloaded_datasource.responseType   = YAHOO.util.DataSource.TYPE_XML;
        my_preloaded_datasource.responseSchema = {
          resultNode:'row',
          fields: ["col1", "col2"]
        };
      //]]>
      </script>
      <pre xml:space="preserve">
&lt;script type="text/javascript" charset="utf-8"&gt;
  my_preloaded_datasource                = new YAHOO.util.DataSource("data.xml?");
  my_preloaded_datasource.responseType   = YAHOO.util.DataSource.TYPE_XML;
  my_preloaded_datasource.responseSchema = {
    resultNode:'row',
    fields: ["col1", "col2"]
  };
&lt;/script&gt;

&lt;?php
$externalDSTable = new ImpTable();
$externalDSTable-&gt;ColumnHeaders = array(
  array('key'=&gt;"col1", "label" =&gt; "Column 1", "sortable" =&gt; false), 
  array("key" =&gt; "col2", "label" =&gt; "Column 2", "sortable" =&gt; true)
);
$externalDSTable-&gt;useData('DataSource', 'my_preloaded_datasource');
$externalDSTable-&gt;Caption = 'XML loaded from an existing YUI DataSource';
$externalDSTable-&gt;generate();  
?&gt;
</pre><?php
                            $externalDSTable = new ImpTable();
                            $externalDSTable->ColumnHeaders = array(
                              array('key'=>"col1", "label" => "Column 1", "sortable" => false), array("key" => "col2", "label" => "Column 2", "sortable" => true)
                            );
                            $externalDSTable->useData('DataSource', 'my_preloaded_datasource');
                            $externalDSTable->Caption = 'XML loaded from an existing YUI DataSource';
                            $externalDSTable->generate();
                        ?>
    </div>
  </body>
</html>

<?php
	/*
		XMLRPCHandler

		$Id$
		$ProjectHeader: $

		This class wraps the messy bits of dealing with XML-RPC requests.

		All we currently support the MetaWeblog API:

		Links:
			http://www.xmlrpc.com/metaWeblogApi
			http://www.keithdevens.com/software/xmlrpc
			http://backend.userland.com/rss
			http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php

		TODO:
			- Chain error handlers so we can return XMLRPC errors here and HTML errors everywhere else
			- Add support for blogger.deletePost
			- Add support for blogger.* api
	 */

	require_once("com.keithdevens.software.xmlrpc.php");

	class XMLRPCHandler {
		var $ValidMethods = array(
			'metaWeblog.newPost'				=> 'metaWeblog_newPost',
			'metaWeblog.editPost'				=> 'metaWeblog_editPost',
			'metaWeblog.getPost'				=> 'metaWeblog_getPost',
			'metaWeblog.getRecentPosts' => 'metaWeblog_getRecentPosts'
			);

			function XMLRPCHandler(&$CMS) {
			  if (class_exists("htmlpurifier")) {
  			  $this->HTMLPurifier = $purifier = new HTMLPurifier();
  			}

				$this->CMS = $CMS;
			}

			function processRequest($xml) {
  			$this->Request = XMLRPC_parse($GLOBALS['HTTP_RAW_POST_DATA']);
  			$this->Parameters = XMLRPC_getParams($this->Request);

  			$method = XMLRPC_getMethodName($this->Request);

  			if (count($this->Parameters) < 3) {
  				return $this->_returnError("Malformed request: invalid number of parameters");
  			}

  			$this->ParentDocumentID = array_shift($this->Parameters);						// We consider the "BlogID" the ID of the parent document
  			$this->Username = array_shift($this->Parameters);
  			$this->Password = array_shift($this->Parameters);

  			if (IMP_DEBUG) {
  				error_log("$method request for blog id {$this->ParentDocumentID} username={$this->Username} password={$this->Password}");
  			}

  			$this->CMS->enableAdminAccess($this->Username, $this->Password) or $this->_ReturnError("Login failed for {$this->Username} - please check your password");

  			$this->ParentDocument = $this->CMS->getDocument($this->ParentDocumentID);
  			if (empty($this->ParentDocument)) {
  				return $this->_ReturnError("Unable to load document!", E_USER_ERROR);
  			}

  			if (!empty($this->ValidMethods[$method]) and method_exists($this, $this->ValidMethods[$method])) {
  				// Since XML-RPC is brain-dead and uses position instead of keys, there's no reason
  				// for us not to treat its parameters like normal PHP parameters:
  				return call_user_func_array(array(&$this, $this->ValidMethods[$method]), $this->Parameters);
  			} else {
  				return $this->_methodNotFound($method);
  			}
			}

			function _methodNotFound($method) {
  			error_log("XML-RPC request for invalid method '$method' from " . $_SERVER['REMOTE_ADDR'] . " (User-Agent: " . $_SERVER['HTTP_USER_AGENT'] . ")");
  			XMLRPC_Error("2", "Method '$method' does not exist" , IMPCMS_USERAGENT);
			}

			function _ReturnError($message) {
				error_log(get_class($this) . " error: $message (username={$this->Username} password={$this->Password}");
  			XMLRPC_Error(-32602, "Malformed request: " . $message, IMPCMS_USERAGENT);
			}

		function metaWeblog_newPost($struct, $publish = false) {
			// SPEC: metaWeblog.newPost (blogid, username, password, struct, publish) returns string

			$Document = $this->CMS->newDocument();

			$this->_getPropertiesFromStruct($Document, $struct);

			$Document->Parent = $this->ParentDocument;
			$Document->Visible = $publish;
			$Document->Save();

			XMLRPC_response(XMLRPC_prepare($Document->ID), IMPCMS_USERAGENT);
		}

		function metaWeblog_editPost($struct, $publish = false) {
			// SPEC: metaWeblog.editPost (postid, username, password, struct, publish) returns true

			$this->_getPropertiesFromStruct($this->ParentDocument, $struct);

			$this->ParentDocument->Visible = $publish;
			$this->ParentDocument->Modified = time();
			$this->ParentDocument->Save();

			XMLRPC_response(XMLRPC_prepare($this->ParentDocument->ID), IMPCMS_USERAGENT);
		}
		function metaWeblog_getPost($id = false) {
			// SPEC: metaWeblog.getPost (postid, username, password) returns struct

			if ($id === false) {
				$id = $this->ParentDocument->ID;
			}

			$d = $this->CMS->getDocument($id);

			$post = $this->_convertDocumentToStruct($d);

			XMLRPC_response(XMLRPC_prepare($post), IMPCMS_USERAGENT);
		}

		function metaWeblog_getRecentPosts($count) {
			// SPEC: metaWeblog.getRecentPosts (postid, username, password[, count]) returns struct

			$posts = array();
			foreach ($this->ParentDocument->getChildren($count) as $d) {
				$posts[] = $this->_convertDocumentToStruct($d);
			}

			XMLRPC_response(XMLRPC_prepare($posts), IMPCMS_USERAGENT);
		}

		function _convertDocumentToStruct($d) {
			// Valid item elements per http://backend.userland.com/rss#hrelementsOfLtitemgt

			// BUG: get rid of hard coded URLs!
			$tmp = array(
				'postid'			=> $d->ID,
				'guid'				=> 'http://improbable.org/chris/index.php?ID=' . $d->ID,
				'link'				=> 'http://improbable.org/chris/index.php?ID=' . $d->ID,
				'title'				=> $d->Title,
				'description'	=> $d->Body,
				'dateCreated'	=> XMLRPC_convert_timestamp_to_iso8601($d->Created),
				'pubDate'			=> XMLRPC_convert_timestamp_to_iso8601($d->Modified)
			);

			return $tmp;
		}

		function _getPropertiesFromStruct(&$d, $struct) {
			foreach ($struct as $key => $val) {
				switch ($key) {
					case "description":
						$d->Body = isset($this->HTMLPurifier) ? $this->HTMLPurifier->purify($val) : $val;
						break;

					case "title":
						$d->Title = $val;
						break;

					default:
						error_log(get_class($this) . "->_getPropertiesFromStruct() ignoring unknown attribute $key ('$val')");
				}
			}
		}
	 }
?>

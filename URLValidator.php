<?php
	class URLValidator {
		var $Message = false;

		var $validSchemes = array('http', 'https', 'ftp');
		var $allowAuthenticatedURLs = false;

		function URLValidator($url) {
			assert(!empty($url));

			$this->originalURL = $url;

			$c = curl_init($url);
			curl_setopt($c, CURLOPT_HEADER, 				true);
			curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);

			$this->CURL = $c;
		}

		function check() {
			$this->_valid = false; // If everything goes well this will be changed to true

			$url_parts = parse_url($this->originalURL);
			if (empty($url_parts['scheme']) or !in_array($url_parts['scheme'], $this->validSchemes)) {
				$this->Message = "URLs must start with: " . implode(", ", $this->validSchemes);
				return false;
			}

			if (empty($url_parts['host']) or ($url_parts['host'] == 'localhost') or ($url_parts['host'] == '127.0.0.1')) {
				$this->Message = 'Invalid hostname';
				return false;
			}

			if (!$this->allowAuthenticatedURLs and (!empty($url_parts['user']) or !empty($url_parts['user']))) {
				$this->Message = 'URLs which include login information are not allowed';
				return false;
			}

			curl_exec($this->CURL);

			if (curl_errno($this->CURL) != 0) {
				$this->Message = curl_error($this->CURL);
				return false;
			}

			$eu = curl_getinfo($this->CURL, CURLINFO_EFFECTIVE_URL);

			if ($eu != $this->originalURL) {
				$this->effectiveURL = $eu;
			}

			$this->HTTPStatus = curl_getinfo($this->CURL, CURLINFO_HTTP_CODE);
			$this->CURLInfo   = curl_getinfo($this->CURL);

			switch ($this->HTTPStatus) {
				case 200:
				case 302:
					$this->_valid = true;
					break;

				case 301:
					$this->_valid = true;
					$this->Message = "The webserver for {$this->originalURL} informs us that it has been replaced by {$this->effectiveURL}";
					break;

				default:
					$this->Message = "Unknown response {$this->HTTPStatus}";
			}

			curl_close($this->CURL);

			return $this->_valid;
		}

		function isValid() {
			if (empty($this->_valid)) {
				$this->check();
			}

			return $this->_valid;
		}

		function getURL() {
			if (!$this->isValid()) {
				return false;
			}

			return (empty($this->effectiveURL) ? $this->originalURL : $this->effectiveURL);
		}

	}
?>

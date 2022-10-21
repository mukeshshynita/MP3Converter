<?php
	/**
	* All video/audio site "extractor" classes MUST extend this base class.
	*/
	namespace MP3Converter\lib\extractors;

	use MP3Converter\lib\Config;
	use MP3Converter\lib\VideoConverter;

	// Extraction Base Class
	abstract class Extractor
	{
		// Common Fields
		protected $_converter;
		protected $_isCurlError = false;

		#region Common Public Methods
		/**
		* Instantiate class and initialize class variables.
		*
		* @param VideoConverter $converter Instance of VideoConverter class
		* @return void
		*/
		function __construct(VideoConverter $converter)
		{
			$this->_converter = $converter;
		}
		#endregion

		#region Common Protected Methods
		/**
		* Retrieve source code of remote video page.
		*
		* @param string $url Video page URL
		* @return string Video page source code
		*/
		protected function FileGetContents($url)
		{
			$converter = $this->GetConverter();
			$file_contents = '';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_USERAGENT, Config::_REQUEST_USER_AGENT);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			$file_contents = curl_exec($ch);
			$this->_isCurlError = curl_errno($ch) != 0;
			$curlInfo = curl_getinfo($ch);
			if (curl_errno($ch) == 0)
			{
				if ($converter->GetCurrentVidHost() == "YouTube" && ($curlInfo['http_code'] == '302' || $curlInfo['http_code'] == '301'))
				{
					if (isset($curlInfo['redirect_url']) && !empty($curlInfo['redirect_url']))
					{
						$file_contents = $this->FileGetContents($curlInfo['redirect_url']);
					}
				}
			}
			curl_close($ch);
			return $file_contents;
		}
		#endregion

		#region Force child classes to define these methods
		/**
		* Retrieve info about a video.
		*
		* @param string $vidUrl Video page URL
		* @return array Info about the video
		*/
		abstract public function RetrieveVidInfo($vidUrl);

		/**
		* Extract all available source URLs for requested video.
		*
		* @return array Video source URLs
		*/
		abstract public function ExtractVidSourceUrls();
		#endregion

		#region Common Properties
		/**
		* Getter method that retrieves VideoConverter instance.
		*/
		protected function GetConverter()
		{
			return $this->_converter;
		}

		/**
		* Getter method that retrieves extractor configuration parameters.
		*/
		public function GetParams()
		{
			return $this->_params;
		}
		#endregion
	}
?>
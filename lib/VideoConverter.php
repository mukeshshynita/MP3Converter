<?php
	/**
	* This file and class contains all logic pertaining to the actual download and MP3 conversion of videos.
	*/
	namespace MP3Converter\lib;

	// Conversion Class
	class VideoConverter extends Config
	{
		// Private Fields
		private $_convertedFileQuality = '';
		private $_songFileName = '';
		private $_flvUrls = array();
		private $_tempVidFileName;
		private $_uniqueID = '';
		private $_percentVidDownloaded = 0;
		private $_curlResource;
		private $_currentVidHost = '';
		private $_vidInfo = array(
			'title' => '?????',
			'thumb_preview' => 'http://img.youtube.com/vi/oops/1.jpg'
		);
		private $_extractor = null;
		private $_videoHosts = array();
		private $_skipConversion = false;

		#region Public Methods
		/**
		* Instantiate class, set session token, register available extractors, and initialize class variables.
		*
		* @param string $videoPageUrl The user-supplied video page URL
		* @param string $mp3Quality The bitrate (quality) of MP3 chosen by user
		* @return void
		*/
		function __construct($videoPageUrl, $mp3Quality)
		{
			if (isset($_SESSION))
			{
				$this->_uniqueID = (!isset($_SESSION[parent::_SITENAME])) ? time() . "_" . uniqid('', true) : $_SESSION[parent::_SITENAME];
				$_SESSION[parent::_SITENAME] = (!isset($_SESSION[parent::_SITENAME])) ? $this->_uniqueID : $_SESSION[parent::_SITENAME];
				$this->RegisterExtractors();
				if (!empty($videoPageUrl) && !empty($mp3Quality))
				{
					$this->SetCurrentVidHost($videoPageUrl);
					$this->SetConvertedFileQuality($mp3Quality);
					$this->SetExtractor($this->GetCurrentVidHost());
					$extractor = $this->GetExtractor();
					if (!is_null($extractor))
					{
						$this->SetVidInfo($extractor->RetrieveVidInfo($videoPageUrl));
					}
				}
			}
			else
			{
				die('Error!: Session must be started in the calling file to use this class.');
			}
		}

		/**
		* Prepare and initiate the download of video.
		*
		* @return bool Download success or failure
		*/
		function DownloadVideo()
		{
			$extractor = $this->GetExtractor();
			if (!is_null($extractor))
			{
				$this->SetConvertedFileName();
				$this->SetVidSourceUrls();
				if ($this->GetConvertedFileName() != '' && count($this->GetVidSourceUrls()) > 0)
				{
					return $this->SaveVideo($this->GetVidSourceUrls());
				}
			}
			return false;
		}

		/**
		* Generate the FFmpeg command and send it to exec_ffmpeg.php to be executed.
		*
		* @return void
		*/
		function GenerateMP3()
		{
			$audioQuality = $this->GetConvertedFileQuality();
			$qualities = $this->GetAudioQualities();
			$quality = (in_array($audioQuality, $qualities)) ? $audioQuality : $qualities['medium'];
			$exec_string = parent::_FFMPEG.' -i '.$this->GetTempVidFileName().' -vol '.parent::_VOLUME.' -y -acodec libmp3lame -ab '.$quality.'k '.$this->GetConvertedFileName() . ' 2> logs/' . $this->GetUniqueID() . '.txt';
			$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
			$ffmpegExecUrl = preg_replace('/(([^\/]+?)(\.php))$/', "exec_ffmpeg.php", $protocol.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);
			$postData = "cmd=".urlencode($exec_string)."&token=".urlencode($this->_uniqueID);
			$strCookie = 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . '; path=/';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $ffmpegExecUrl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_COOKIE, $strCookie);
			curl_exec($ch);
			curl_close($ch);
		}

		/**
		* Prepare MP3 file name and prompt user for MP3 download.
		*
		* @param string $file MP3 file name
		* @return void
		*/
		function DownloadMP3($file)
		{
			$filepath = parent::_SONGFILEDIR . urldecode($file);
			$filename = urldecode($file);
			if (parent::_ENABLE_CONCURRENCY_CONTROL)
			{
				$filename = preg_replace('/((_uuid-)(\w{13})(\.mp3))$/', "$4", $filename);
			}
			if (is_file($filepath))
			{
				header('Content-Type: audio/mpeg3');
				header('Content-Length: ' . filesize($filepath));
				header('Content-Disposition: attachment; filename="'.$filename.'"');
				ob_clean();
				flush();
				readfile($filepath);
				die();
			}
			else
			{
				$redirect = explode("?", $_SERVER['REQUEST_URI']);
				header('Location: ' . $redirect[0]);
			}
		}

		/**
		* Extract video ID.
		*
		* @param string $vidUrl Video URL
		* @return string Video ID
		*/
		function ExtractVideoId($vidUrl)
		{
			$id = '';
			$url = trim($vidUrl);
			$urlQueryStr = parse_url($url, PHP_URL_QUERY);
			if ($urlQueryStr !== false && !empty($urlQueryStr))
			{
				$v = '';
				parse_str($urlQueryStr);
				if (!empty($v))
				{
					$id = $v;
				}
				else
				{
					$url = preg_replace('/(\?' . preg_quote($urlQueryStr, '/') . ')$/', "", $url);
					$id = trim(strrchr(trim($url, '/'), '/'), '/');
				}
			}
			else
			{
				$id = trim(strrchr(trim($url, '/'), '/'), '/');
			}
			return $id;
		}

		/**
		* Flush output buffer at various points during download/conversion process.
		*
		* @return void
		*/
		function FlushBuffer()
		{
			if (ob_get_length() > 0) ob_end_flush();
			if (ob_get_length() > 0) ob_flush();
			flush();
		}
		#endregion

		#region Private "Helper" Methods
		/**
		* Find and load all available video/audio site extractors.
		*
		* @return void
		*/
		private function RegisterExtractors()
		{
			$hosts = array();
			$iterator = new \DirectoryIterator(__DIR__ . DIRECTORY_SEPARATOR . 'extractors' . DIRECTORY_SEPARATOR);
			while ($iterator->valid())
			{
				$fname = $iterator->getFilename();
				if ($fname != '.' && $fname != '..' && $fname != "Extractor.php")
				{
					$extractorName = __NAMESPACE__ . '\\extractors\\' . current(explode(".", $fname));
					if (class_exists($extractorName))
					{
						$extractor = new $extractorName($this);
						$hosts[$extractorName] = $extractor->GetParams();
					}
				}
				$iterator->next();
			}
			if (!empty($hosts))
			{
				ksort($hosts);
				$count = 0;
				foreach ($hosts as $host)
				{
					$this->_videoHosts[++$count] = $host;
				}
			}
			//die(print_r($this->_videoHosts));
		}

		/**
		* cURL callback function that updates download progress bar.
		*
		* @param resource $curlResource cURL resource handle
		* @param int $downloadSize Total number of bytes expected to be downloaded
		* @param int $downloaded Number of bytes downloaded so far
		* @param int $uploadSize Total number of bytes expected to be uploaded
		* @param int $uploaded Number of bytes uploaded so far
		* @return void
		*/
		private function UpdateVideoDownloadProgress($curlResource, $downloadSize, $downloaded, $uploadSize, $uploaded)
		{
			$httpCode = curl_getinfo($curlResource, CURLINFO_HTTP_CODE);
			if ($httpCode == "200" && $downloadSize > 0)
			{
				$percent = round($downloaded / $downloadSize, 2) * 100;
				if ($percent > $this->_percentVidDownloaded)
				{
					$this->_percentVidDownloaded++;
					echo '<script type="text/javascript">updateVideoDownloadProgress("'. $percent .'");</script>';
					$this->FlushBuffer();
				}
			}
		}

		/**
		* cURL callback function that updates download progress bar for PHP 5.4 and below.
		* Deprecated - May be removed in future versions!
		*
		* @param int $downloadSize Total number of bytes expected to be downloaded
		* @param int $downloaded Number of bytes downloaded so far
		* @param int $uploadSize Total number of bytes expected to be uploaded
		* @param int $uploaded Number of bytes uploaded so far
		* @return void
		*/
		private function LegacyUpdateVideoDownloadProgress($downloadSize, $downloaded, $uploadSize, $uploaded)
		{
			$this->UpdateVideoDownloadProgress($this->_curlResource, $downloadSize, $downloaded, $uploadSize, $uploaded);
		}

		/**
		* Save video to "videos" directory.
		*
		* @param array $urls Direct links to available videos on remote server
		* @return bool Download success or failure
		*/
		private function SaveVideo(array $urls)
		{
			//die(print_r($urls));
			$extractor = $this->GetExtractor();
			$vidInfo = $this->GetVidInfo();
			$this->_skipConversion = $skipConversion = $this->GetCurrentVidHost() == 'SoundCloud' && !$vidInfo['downloadable'] && $this->GetConvertedFileQuality() == '128';
			if (!$skipConversion) $this->SetTempVidFileName();
			$filename = (!$skipConversion) ? $this->GetTempVidFileName() : $this->GetConvertedFileName();
			$success = false;
			$vidCount = -1;
			while (!$success && ++$vidCount < count($urls))
			{
				$this->_percentVidDownloaded = 0;
				$file = fopen($filename, 'w');
				$progressFunction = (parent::_PHP_VERSION >= 5.5) ? 'UpdateVideoDownloadProgress' : 'LegacyUpdateVideoDownloadProgress';
				$this->_curlResource = $ch = curl_init();
				curl_setopt($ch, CURLOPT_FILE, $file);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_URL, $urls[$vidCount]);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($ch, CURLOPT_NOPROGRESS, false);
				curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array($this, $progressFunction));
				curl_setopt($ch, CURLOPT_BUFFERSIZE, 4096000);
				curl_setopt($ch, CURLOPT_USERAGENT, parent::_REQUEST_USER_AGENT);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_exec($ch);
				if (curl_errno($ch) == 0)
				{
					$curlInfo = curl_getinfo($ch);
					if (($this->GetCurrentVidHost() == "Dailymotion" || $this->GetCurrentVidHost() == "SoundCloud" || $this->GetCurrentVidHost() == "YouTube") && $curlInfo['http_code'] == '302')
					{
						if (isset($curlInfo['redirect_url']) && !empty($curlInfo['redirect_url']))
						{
							$urls[$vidCount] = $curlInfo['redirect_url'];
							$vidCount--;
						}
					}
					if (method_exists($extractor, 'GetCypherUsed') && $extractor->GetCypherUsed() && $curlInfo['http_code'] == '403')
					{
						$extractor->FixDecryption();
					}
				}
				curl_close($ch);
				fclose($file);
				if (is_file($filename))
				{
					if (!filesize($filename) || filesize($filename) < 10000)
					{
						unlink($filename);
					}
					else
					{
						$success = true;
					}
				}
			}
			return $success;
		}
		#endregion

		#region Properties
		/**
		* Getter and setter methods for MP3 file name.
		*/
		public function GetConvertedFileName()
		{
			return $this->_songFileName;
		}
		private function SetConvertedFileName()
		{
			$videoInfo = $this->GetVidInfo();
			$trackName = $videoInfo['title'];
			if (!empty($trackName))
			{
				$fname = parent::_SONGFILEDIR . preg_replace('/_{2,}/','_',preg_replace('/ /','_',preg_replace('/[^A-Za-z0-9 _-]/','',$trackName)));
				$fname .= (parent::_ENABLE_CONCURRENCY_CONTROL) ? uniqid('_uuid-') : '';
				$this->_songFileName = $fname . '.mp3';
			}
		}

		/**
		* Getter and setter methods for available video links (for a given video).
		*/
		public function GetVidSourceUrls()
		{
			return $this->_vidSourceUrls;
		}
		private function SetVidSourceUrls()
		{
			$extractor = $this->GetExtractor();
			$this->_vidSourceUrls = $extractor->ExtractVidSourceUrls();
		}

		/**
		* Getter and setter methods for local (temporary) video file name.
		*/
		private function GetTempVidFileName()
		{
			return $this->_tempVidFileName;
		}
		private function SetTempVidFileName()
		{
			$this->_tempVidFileName = parent::_TEMPVIDDIR . $this->GetUniqueID() .'.flv';
		}

		/**
		* Getter and setter methods for current video/audio site name.
		*/
		public function GetCurrentVidHost()
		{
			return $this->_currentVidHost;
		}
		public function SetCurrentVidHost($videoUrl)
		{
			$vidHosts = $this->GetVideoHosts();
			foreach ($vidHosts as $host)
			{
				foreach ($host['url_root'] as $urlRoot)
				{
					$rootUrlPattern = preg_replace('/#wildcard#/', "[^\\\\/]+", preg_quote($urlRoot, '/'));
					$rootUrlPattern = ($host['allow_https_urls']) ? preg_replace('/^(http)/', "https?", $rootUrlPattern) : $rootUrlPattern;
					if (preg_match('/^('.$rootUrlPattern.')/i', $videoUrl) == 1)
					{
						$this->_currentVidHost = $host['name'];
						break 2;
					}
				}
			}
		}

		/**
		* Getter and setter methods for general info related to the requested video.
		*/
		public function GetVidInfo()
		{
			return $this->_vidInfo;
		}
		private function SetVidInfo(array $vidInfo)
		{
			$this->_vidInfo = (!empty($vidInfo)) ? $vidInfo : $this->_vidInfo;
		}

		/**
		* Getter and setter methods for current site "extractor".
		*/
		public function GetExtractor()
		{
			return $this->_extractor;
		}
		private function SetExtractor($vidHostName)
		{
			$className = __NAMESPACE__ . '\\extractors\\' . $vidHostName;
			$this->_extractor = (class_exists($className)) ? new $className($this) : null;
		}

		/**
		* Getter and setter methods for converted file (MP3) quality.
		*/
		public function GetConvertedFileQuality()
		{
			return $this->_convertedFileQuality;
		}
		private function SetConvertedFileQuality($quality)
		{
			$this->_convertedFileQuality = $quality;
		}

		/**
		* Getter method that retrieves all available audio (MP3) qualities in Config class.
		*/
		public function GetAudioQualities()
		{
			return $this->_audioQualities;
		}

		/**
		* Getter method that retrieves the unique ID used for temporary and log file names.
		*/
		public function GetUniqueID()
		{
			return $this->_uniqueID;
		}

		/**
		* Getter method that retrieves all supported video/audio sites and their corresponding extractor configurations.
		*/
		public function GetVideoHosts()
		{
			return $this->_videoHosts;
		}

		/**
		* Getter method that retrieves whether or not FFmpeg conversion is required (i.e., if the MP3 is directly available from the video/audio site.
		*/
		public function GetSkipConversion()
		{
			return $this->_skipConversion;
		}
		#endregion
	}

?>

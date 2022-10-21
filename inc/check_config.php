<?php
	use MP3Converter\lib\Config;

	$appRoot = pathinfo($_SERVER['PHP_SELF'], PATHINFO_DIRNAME);
	$appRoot .= ($appRoot != "/") ? "/" : "";
	$tests = array();
	$success = '<span style="color:green"><i class="fa fa-check"></i></span>';
	$failed = '<span style="color:red"><i class="fa fa-times"></i></span>';

	// Check authorized domains
	array_walk(Config::$_authorizedDomains, function(&$domain) {$domain = strtolower($domain);});
	$tests['domains'] = in_array(strtolower($_SERVER['HTTP_HOST']), Config::$_authorizedDomains);
	//$tests['domains'] = false;

	// Folder and file permissions
	$appRootPerms = substr(decoct(fileperms(dirname(dirname(__FILE__)))), -4);
	$tmpFile = dirname(dirname(__FILE__)) . "/tmp.txt";
	$fp = @fopen($tmpFile, "w");
	$isWritable = $fp !== false;
	if ($isWritable)
	{
		fclose($fp);
		unlink($tmpFile);
	}
	$tests['appRootPerms'] = $isWritable;
	//$tests['appRootPerms'] = false;
	$videosPerms = substr(decoct(fileperms(dirname(dirname(__FILE__)) . "/videos")), -4);
	$tests['videosPerms'] = $videosPerms == "0777";
	//$tests['videosPerms'] = false;
	$logsPerms = substr(decoct(fileperms(dirname(dirname(__FILE__)) . "/logs")), -4);
	$tests['logsPerms'] = $logsPerms == "0777";
	//$tests['logsPerms'] = false;
	$outputPerms = substr(decoct(fileperms(dirname(dirname(__FILE__)) . "/mp3")), -4);
	$tests['outputPerms'] = $outputPerms == "0777";
	//$tests['outputPerms'] = false;

	// Check PHP version
	$phpVersion = explode(".", PHP_VERSION);
	$tests['php_version'] = version_compare(PHP_VERSION, '5.3.0') >= 0 && Config::_PHP_VERSION == $phpVersion[0] . "." . $phpVersion[1];
	//$tests['php_version'] = false;

	// Check for PHP handler
	$phpMode = php_sapi_name();
	$tests['php_handler'] = preg_match('/apache/i', $phpMode) == 1;
	//$tests['php_handler'] = false;

	// Check for PHP open_basedir restriction
	$phpOpenBaseDir = ini_get('open_basedir');
	$noObdRestriction = empty($phpOpenBaseDir) || $phpOpenBaseDir == "no value";
	if (!empty($phpOpenBaseDir) && $phpOpenBaseDir != "no value")
	{
		$absAppDir = dirname(dirname(__FILE__)) . "/";
		$obDirs = explode(":", $phpOpenBaseDir);
		$dirPattern = '/^(';
		foreach ($obDirs as $dir)
		{
			$dirPattern .= '(' . preg_quote($dir, "/") . ')';
			$dirPattern .= ($dir != end($obDirs)) ? '|' : '';
		}
		$dirPattern .= ')/';
		$noObdRestriction = preg_match($dirPattern, $absAppDir) == 1;
	}
	$tests['phpOpenBaseDir'] = $noObdRestriction;
	//$tests['phpOpenBaseDir'] = false;

	// Check if PHP exec() is enabled and working
	$phpExecRuns = function_exists('exec');
	if ($phpExecRuns)
	{
		$ffmpegData = array();
		@exec(Config::_FFMPEG . ' -version', $ffmpegData);
		//die(print_r($ffmpegData));
		$phpExecRuns = isset($ffmpegData[0]) && !empty($ffmpegData[0]);
	}
	$tests['phpExec'] = $phpExecRuns;
	//$tests['phpExec'] = false;

	// Check for cURL
	$curlExists = array();
	@exec('type curl', $curlExists);
	//die(print_r($curlExists));
	$tests['curlExists'] = !empty($curlExists) && preg_match('/^(curl is )/i', $curlExists[0]) == 1;
	//$tests['curlExists'] = false;

	if ($tests['curlExists'])
	{
		// Get cURL version
		$curlVersion = array();
		@exec('curl -V', $curlVersion);
		//die(print_r($curlVersion));
		if (!empty($curlVersion)) preg_match('/\d+\.\d+.\d+/', $curlVersion[0], $curlVersionNo);

		// Check for PHP cURL extension
		$tests['phpCurl'] = extension_loaded("curl");
		//$tests['phpCurl'] = false;

		if ($tests['phpCurl'])
		{
			$curlVersionInfo = curl_version();
			//die(print_r($curlVersionInfo));
			$curlVersionNo = array($curlVersionInfo['version']);

			// Check for DNS error resolving site domain name
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "http://" . $_SERVER['HTTP_HOST'] . $appRoot . "exec_ffmpeg.php");
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_NOBODY, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			$result = curl_exec($ch);
			//echo 'Curl error: (' . curl_errno($ch) . ') ' . curl_error($ch) . '<br>';
			//die(print_r(curl_getinfo($ch)));
			$tests['dns'] = curl_errno($ch) == 0;
			curl_close($ch);
			//$tests['dns'] = false;

			if ($tests['dns'])
			{
				// Check for SSL/TLS
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, "https://" . $_SERVER['HTTP_HOST'] . $appRoot . "contact.php");
				curl_setopt($ch, CURLOPT_HEADER, 1);
				curl_setopt($ch, CURLOPT_NOBODY, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 15);
				$result = curl_exec($ch);
				$tests['ssl'] = curl_errno($ch) == 0;
				curl_close($ch);
				//$tests['ssl'] = false;
			}
		}
	}

	// Check for FFmpeg install
	$ffmpegLocation = array();
	@exec('type ffmpeg', $ffmpegLocation);
	//die(print_r($ffmpegLocation));
	$tests['FFmpeg'] = !empty($ffmpegLocation) && preg_match('/^((ffmpeg is )(.+))/i', $ffmpegLocation[0], $ffmpegPath) == 1 && Config::_FFMPEG == trim($ffmpegPath[3]);
	//$tests['FFmpeg'] = false;

	if ($tests['FFmpeg'])
	{
		// Check for FFmpeg version
		$ffmpegInfo = array();
		@exec(Config::_FFMPEG . ' -version', $ffmpegInfo);
		//die(print_r($ffmpegInfo));
		$tests['FFmpegVersion'] = isset($ffmpegInfo[0]) && !empty($ffmpegInfo[0]);
		//$tests['FFmpegVersion'] = false;

		// Check for codecs
		$libmp3lame = array();
		@exec(Config::_FFMPEG . ' -codecs | grep -E "(\s|[[:space:]])libmp3lame(\s|[[:space:]])"', $libmp3lame);
		//die(print_r($libmp3lame));
		$tests['libmp3lame'] = isset($libmp3lame[0]) && !empty($libmp3lame[0]) && preg_match('/E/', current(preg_split('/\s/', $libmp3lame[0], -1, PREG_SPLIT_NO_EMPTY))) == 1;
		//$tests['libmp3lame'] = false;
	}

	// Check for at least one site module installed
	$modCount = 0;
	$iterator = new DirectoryIterator(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'extractors' . DIRECTORY_SEPARATOR);
	while ($iterator->valid())
	{
		$fname = $iterator->getFilename();
		if ($fname != '.' && $fname != '..' && $fname != "Extractor.php") $modCount++;
		$iterator->next();
	}
	$tests['modInstalled'] = $modCount > 0;
	//$tests['modInstalled'] = false;

	// Get Config constant and variable line numbers
	$configVars = array('_PHP_VERSION', '_FFMPEG', '_TEMPVIDDIR', '_LOGSDIR', '_SONGFILEDIR', '\$_authorizedDomains');
	$configLines = file(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . "Config.php");
	$linesPattern = '/^((\s*)((const )|(public static ))((' . implode(")|(", $configVars) . ')))/';
	$linesArr = preg_grep($linesPattern, $configLines);
	$lineNumsArr = array();
	foreach ($linesArr as $num => $line)
	{
		$lineNumsArr[trim(preg_replace('/^((\s*)((const )|(public static ))(\S+)(.*))$/', "$6", $line))] = $num + 1;
	}
	//die(print_r($lineNumsArr));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Check Configuration</title>
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.min.css" />
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css" />
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css" />
  <style type="text/css">
	@import url(https://fonts.googleapis.com/css?family=Architects+Daughter);
	body {background-color:#ccc;font-family:Verdana,Arial;font-size:13px;line-height:16px;}
	h3 {font-size:20px;font-weight:bold;margin:15px 0 25px 0;text-align:center;}
	h4, h5 {font-size:22px;margin:25px 0 15px 0;font-family:"Architects Daughter",Verdana;color:#f9f9f9;padding:10px 12px;background:#111;}
	h5 {font-size:4px;padding:0;}
	ul {margin-left:11px;}
	ul li, p {margin:15px 0;}
	ul ul {margin-left:9px;}
	ul ul li {padding-left:9px;text-indent:-9px;}
	ul ul ul {margin-left:0;}
	#container {width:720px;margin:20px auto;padding:20px;background-color:#f9f9f9;}
	.response span {text-indent:2px;font-weight:bold;font-style:italic;font-size:18px;}
	.orange {color:#FB9904;font-size:15px;}
	.italic {font-style:italic;}
	.bold {font-weight:bold;}
	.buttons {text-align:center;margin:25px auto 5px auto;}
  </style>
  <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
  <script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
  <script type="text/javascript">
  	$(document).ready(function(){
		$(".rerun").click(function(){
			location.href = location.href;
		});

		$(".printpage").click(function(){
			window.print();
		});

		$(".popup").click(function(){
			var tests = {
				phpExec: <?php echo ($tests['phpExec']) ? "true" : "false"; ?>,
				appRootPerms: <?php echo ($tests['appRootPerms']) ? "true" : "false"; ?>,
				domains: <?php echo ($tests['domains']) ? "true" : "false"; ?>
			};
			var testsPassed = true;
			for (var test in tests)
			{
				if (!tests[test])
				{
					$("#fix-" + test).css("display", "inline");
					var offset = $("#fix-" + test).offset();
					offset.top -= 100;
					$("html, body").animate({
					    scrollTop: offset.top
					}, 400, function(){
						$("#fix-" + test).tooltip('show');
					});
					testsPassed = false;
					break;
				}
			}
			if (testsPassed) $('#exitModal').modal();
		});

		$("#leave").click(function(){
			location.href += "?config=complete";
		});
	});
  </script>
</head>
<body>
	<div id="container">
		<h3>Check Your Server/Software Configuration. . .</h3>
		<p>This page will check your server and software installations for errors. Please ensure that you read through the results thoroughly and do not proceed until all tests have passed.</p>
		<?php if (!$tests['php_version'] || !$tests['phpExec'] || !$tests['phpOpenBaseDir'] || !$tests['curlExists'] || !$tests['phpCurl'] || !$tests['dns'] || !$tests['FFmpeg'] || !$tests['FFmpegVersion'] || !$tests['libmp3lame'] || !$tests['appRootPerms'] || !$tests['videosPerms'] || !$tests['logsPerms'] || !$tests['outputPerms'] || !$tests['domains'] || !$tests['modInstalled']) { ?>
			<div class="alert alert-danger alert-dismissible fade in" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<b><i class="fa fa-exclamation-triangle"></i> Warning!</b> &nbsp;You should <b>at least</b> confirm that all <b>"Required"</b> settings are OK!
			</div>
		<?php } ?>
		<div class="alert alert-info alert-dismissible fade in" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b><i class="fa fa-question-circle"></i> Questions?:</b> &nbsp;Get <b>help troubleshooting common issues</b> using "<a href="docs/faq.html" onclick="window.open(this.href); return false;" class="alert-link">The Official FAQ</a>".
		</div>
		<div class="alert alert-warning alert-dismissible fade in" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b><i class="fa fa-info-circle"></i> Support:</b> &nbsp;Find the <b>full array of support options</b> in the <a href="docs/" onclick="window.open(this.href); return false;" class="alert-link">Software Documentation</a>.
		</div>
		<div class="buttons">
			<button class="btn btn-primary rerun"><i class="fa fa-refresh"></i> Run the tests again.</button> <button class="btn btn-success printpage"><i class="fa fa-print"></i> Print this page.</button> <button class="btn btn-danger popup"><i class="fa fa-sign-out"></i> Get me out of here!</button>
		</div>

		<h4><u>Required</u> settings. . .</h4>
		<ul>
			<li><span class="italic bold">Software Dependencies</span>
				<ul>
					<li>PHP version: &nbsp;&nbsp;&nbsp;<?php echo PHP_VERSION; ?><span class="response"><span> <?php echo ($tests['php_version']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['php_version'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that your PHP version is at least 5.3 or above and that the _PHP_VERSION constant value in "lib/Config.php" (line ' . $lineNumsArr['_PHP_VERSION'] . ') is set correctly.</span></li></ul>';
						}
					?>
					</li>
					<li>PHP exec() enabled and working?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['phpExec']) ? 'Yes' : 'No'; ?><span class="response"><span> <?php echo ($tests['phpExec']) ? $success : $failed; ?></span></span><span id="fix-phpExec" style="display:none" data-toggle="tooltip" data-placement="right" data-trigger="manual" data-html="true" title="&nbsp;&nbsp;You must fix this before leaving.">&nbsp;&nbsp;</span>
					<?php
						if (!$tests['phpExec'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the PHP exec() function is enabled and that exec() can run FFmpeg commands.</span></li></ul>';
						}
					?>
					</li>
					<li>PHP "open_basedir": &nbsp;&nbsp;&nbsp;<?php echo (!empty($phpOpenBaseDir)) ? $phpOpenBaseDir : "no value"; ?><span class="response"><span> <?php echo ($tests['phpOpenBaseDir']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['phpOpenBaseDir'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the PHP "open_basedir" directive is empty, set to no value, or includes the app root folder in the specified directory-tree.</span></li></ul>';
						}
					?>
					</li>
					<li>cURL version: &nbsp;&nbsp;&nbsp;<?php echo ($tests['curlExists'] && isset($curlVersionNo) && !empty($curlVersionNo)) ? $curlVersionNo[0] : 'Unknown'; ?><span class="response"><span> <?php echo ($tests['curlExists']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['curlExists'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that cURL is installed.</span></li></ul>';
						}
					?>
					</li>
					<?php if ($tests['curlExists']) { ?>
						<li>PHP cURL installed?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['phpCurl']) ? 'Yes' : 'Not found'; ?><span class="response"><span> <?php echo ($tests['phpCurl']) ? $success : $failed; ?></span></span>
						<?php
							if (!$tests['phpCurl'])
							{
								echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the PHP cURL extension is installed.</span></li></ul>';
							}
						?>
						</li>
						<?php if ($tests['phpCurl']) { ?>
							<li>DNS is OK?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['dns']) ? 'Yes' : 'No'; ?><span class="response"><span> <?php echo ($tests['dns']) ? $success : $failed; ?></span></span>
							<?php
								if (!$tests['dns'])
								{
									echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> An HTTP request made by your server to "' . $_SERVER['HTTP_HOST'] . '" failed -- indicating a faulty DNS configuration. Please try another DNS provider, changing nameservers, and/or having a professional configure your DNS. &nbsp;<a href="http://' . $_SERVER['HTTP_HOST'] . $appRoot . 'docs/faq.html#nine" onclick="window.open(this.href); return false;"><b>Read More &nbsp;&nbsp;<i class="fa fa-angle-double-right"></i></b></a></span></li></ul>';
								}
							?>
							</li>
						<?php } ?>
					<?php } ?>
					<li>FFmpeg location: &nbsp;&nbsp;&nbsp;<?php echo (isset($ffmpegPath[3])) ? trim($ffmpegPath[3]) : 'Not found'; ?><span class="response"><span> <?php echo ($tests['FFmpeg']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['FFmpeg'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that FFmpeg is installed and that the _FFMPEG constant value in "lib/Config.php" (line ' . $lineNumsArr['_FFMPEG'] . ') is set correctly.</span></li></ul>';
						}
					?>
					</li>
					<?php if ($tests['FFmpeg']) { ?>
						<li>FFmpeg version: &nbsp;&nbsp;&nbsp;<?php echo ($tests['FFmpegVersion']) ? $ffmpegInfo[0] : 'Not found'; ?><span class="response"><span> <?php echo ($tests['FFmpegVersion']) ? $success : $failed; ?></span></span>
						<?php
							if (!$tests['FFmpegVersion'])
							{
								echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Something is wrong with your FFmpeg installation. Consider reinstalling FFmpeg.</span></li></ul>';
							}
						?>
						</li>
						<li>libmp3lame installed?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['libmp3lame']) ? 'Yes' : 'Not found'; ?><span class="response"><span> <?php echo ($tests['libmp3lame']) ? $success : $failed; ?></span></span>
						<?php
							if (!$tests['libmp3lame'])
							{
								echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the libmp3lame codec is installed and compiled with FFmpeg.</span></li></ul>';
							}
						?>
						</li>
					<?php } ?>
					<li>Site module(s) installed?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['modInstalled']) ? 'Yes' : 'Not found'; ?><span class="response"><span> <?php echo ($tests['modInstalled']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['modInstalled'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please use your Envato/CodeCanyon purchase code to <a href="https://secure.rajwebconsulting.com/Mp3ConverterStore" onclick="window.open(this.href); return false;">download one FREE site module</a> (your choice), and then upload the corresponding PHP file to the "lib/extractors/" directory.</span></li></ul>';
						}
					?>
					</li>
				</ul>
			</li>
			<li><span class="italic bold">Folder/File Permissions</span>
				<ul>
					<li>App Root directory permissions: &nbsp;&nbsp;&nbsp;<?php echo $appRootPerms; ?><span class="response"><span> <?php echo ($tests['appRootPerms']) ? $success : $failed; ?></span></span><span id="fix-appRootPerms" style="display:none" data-toggle="tooltip" data-placement="right" data-trigger="manual" data-html="true" title="&nbsp;&nbsp;You must fix this before leaving.">&nbsp;&nbsp;</span>
					<?php
						if (!$tests['appRootPerms'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the application root directory (i.e., ';
							echo ($appRoot == '/') ? 'the web root' : '"'.$appRoot.'"';
							echo ') is "chmod" to 0777 permissions. If that\'s not possible or practical, then at least ensure that permissions enable writing to this folder.</span></li></ul>';
						}
					?>
					</li>
					<li>"videos" folder permissions: &nbsp;&nbsp;&nbsp;<?php echo $videosPerms; ?><span class="response"><span> <?php echo ($tests['videosPerms']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['videosPerms'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the "videos" directory (represented by the _TEMPVIDDIR constant value in "lib/Config.php" on line ' . $lineNumsArr['_TEMPVIDDIR'] . ') is "chmod" to 0777 permissions.</span></li></ul>';
						}
					?>
					</li>
					<li>"logs" folder permissions: &nbsp;&nbsp;&nbsp;<?php echo $logsPerms; ?><span class="response"><span> <?php echo ($tests['logsPerms']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['logsPerms'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the "logs" directory (represented by the _LOGSDIR constant value in "lib/Config.php" on line ' . $lineNumsArr['_LOGSDIR'] . ') is "chmod" to 0777 permissions.</span></li></ul>';
						}
					?>
					</li>
					<li>"mp3" folder permissions: &nbsp;&nbsp;&nbsp;<?php echo $outputPerms; ?><span class="response"><span> <?php echo ($tests['outputPerms']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['outputPerms'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the "mp3" directory (represented by the _SONGFILEDIR constant value in "lib/Config.php" on line ' . $lineNumsArr['_SONGFILEDIR'] . ') is "chmod" to 0777 permissions.</span></li></ul>';
						}
					?>
					</li>
				</ul>
			</li>
			<li><span class="italic bold">Software Settings</span>
				<ul>
					<li>Domain Name: &nbsp;&nbsp;&nbsp;<?php echo $_SERVER['HTTP_HOST']; ?><span class="response"><span> <?php echo ($tests['domains']) ? $success : $failed; ?></span></span><span id="fix-domains" style="display:none" data-toggle="tooltip" data-placement="right" data-trigger="manual" data-html="true" title="&nbsp;&nbsp;You must fix this before leaving.">&nbsp;&nbsp;</span>
					<?php
						if (!$tests['domains'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please add your domain name (and any subdomains) to the $_authorizedDomains array in "lib/Config.php" (line ' . $lineNumsArr['$_authorizedDomains'] . ').</span></li></ul>';
						}
					?>
					</li>
				</ul>
			</li>
		</ul>

		<h4><u>Recommended</u> settings. . .</h4>
		<ul>
			<li><span class="italic bold">Software Dependencies</span>
				<ul>
					<li>PHP mode: &nbsp;&nbsp;&nbsp;<?php echo $phpMode; ?><span class="response"><span> <?php echo ($tests['php_handler']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['php_handler'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> For best performance, please ensure that PHP is running as an Apache module.</span></li></ul>';
						}
					?>
					</li>
				</ul>
			</li>
			<?php if (isset($tests['ssl'])) { ?>
				<li><span class="italic bold">Miscellaneous</span>
					<ul>
						<li>SSL certificate?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['ssl']) ? 'Yes' : 'No'; ?><span class="response"><span> <?php echo ($tests['ssl']) ? $success : $failed; ?></span></span>
						<?php
							if (!$tests['ssl'])
							{
								echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that a valid SSL certificate is installed.</span></li></ul>';
							}
						?>
						</li>
					</ul>
				</li>
			<?php } ?>
		</ul>

		<h5>&nbsp;</h5>
		<div class="buttons">
			<button class="btn btn-primary rerun"><i class="fa fa-refresh"></i> Run the tests again.</button> <button class="btn btn-success printpage"><i class="fa fa-print"></i> Print this page.</button> <button class="btn btn-danger popup"><i class="fa fa-sign-out"></i> Get me out of here!</button>
		</div>
	</div>

	<!-- Exit Modal -->
	<div class="modal fade" id="exitModal" tabindex="-1" role="dialog" aria-labelledby="exitModalLabel">
	  <div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" id="exitModalLabel">Are you sure?</h4>
		  </div>
		  <div class="modal-body">
			<p>At the very least, <u>you should confirm that all "Required" settings are configured correctly</u>. Failure to do so will adversely affect software performance!</p>
			<p>Consider printing this page for future reference before you leave. After you leave, you will not see this page again.<span style="font-weight:bold">*</span></p>
			<div class="alert alert-danger" role="alert">
				<p style="margin-top:0;padding-left:14px;text-indent:-14px;"><b>*</b> <span class="italic">If you do ever want to return, then you can delete the "setup.log" file from your app root directory and navigate back to the software's index.php.</span></p>
			</div>
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal">No, take me back.</button>
			<button id="leave" type="button" class="btn btn-primary">Yes!</button>
		  </div>
		</div>
	  </div>
	</div>
</body>
</html>
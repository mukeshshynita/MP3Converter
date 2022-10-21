<?php
	use MP3Converter\lib\Config;
	use MP3Converter\lib\VideoConverter;

	// Prevent other domains from displaying software in an iframe
	header('X-Frame-Options: SAMEORIGIN');

	// Start session to enable saving of exec_ffmpeg.php security token
	session_start();

	// Execution settings
	ini_set('max_execution_time',0);
	ini_set('display_errors',0);

	// Autoload class files
	include 'inc/autoload.php';

	// Software config check logic
	if (!is_file('setup.log') && preg_match('/^(win)/i', PHP_OS) != 1)
	{
		if (isset($_GET['config']) && $_GET['config'] == "complete")
		{
			$fp = @fopen("setup.log", "w");
			if ($fp !== false)
			{
				fwrite($fp, 'Delete this file to run the config check again.');
				fclose($fp);
			}
		}
		else
		{
			include 'inc/check_config.php';
			die();
		}
	}

	// Verify HTTP_HOST is in the list of authorized domains
	if (!empty(Config::$_authorizedDomains))
	{
		array_walk(Config::$_authorizedDomains, function(&$domain) {$domain = strtolower($domain);});
		if (!in_array(strtolower($_SERVER['HTTP_HOST']), Config::$_authorizedDomains)) die("This domain is not authorized to access this software!<br /><br />(Did you forget to add your domains/subdomains to the \$_authorizedDomains array in the configuration class file?)");
	}

	// Instantiate converter class
	$videoPageUrl = (isset($_POST['submit'])) ? trim($_POST['videoURL']) : '';
	$mp3Quality = (isset($_POST['submit'])) ? trim($_POST['quality']) : '';
	$converter = new VideoConverter($videoPageUrl, $mp3Quality);

	// Initialize variables
	$vidHosts = $converter->GetVideoHosts();
	$qualities = $converter->GetAudioQualities();

	// On download of MP3
	if (isset($_GET['mp3']))
	{
		$converter->DownloadMP3($_GET['mp3']);
	}
?>
<?php include 'inc/page_header.php'; ?>
	<div class="container">
		<div class="row">
			<div class="col-lg-6 col-lg-offset-3 col-md-8 col-md-offset-2 col-sm-10 col-sm-offset-1">
				<div class="overlay">
					<div class="converter text-center">
						<h2><i class="fa fa-music"></i> MP3 Converter PHP Script <i class="fa fa-music"></i></h2>
						<p>Supported Sites: &nbsp;<span style="font-size:26px"><?php
							if (!empty($vidHosts))
							{
								foreach ($vidHosts as $host)
								{
									echo '<i class="'.$host['icon_style'].'" data-toggle="tooltip" data-placement="top" title="'.$host['name'].'"></i> ';
								}
							}
							else
							{
								echo '<a href="https://secure.rajwebconsulting.com/Mp3ConverterStore" target="_blank" style="font-size:14px">Download one free site module here!</a>';
							}
						?></span></p>
						<?php
							// On form submission...
							if (isset($_POST['submit']) && isset($_POST['formToken']) && $_POST['formToken'] == $converter->GetUniqueID())
							{
								// Print "please wait" message and preview image
								$vidInfo = $converter->GetVidInfo();
								echo '<div id="preview" style="display:block"><p>...Please wait while I try to convert:</p>';
								echo '<p><img src="'.$vidInfo['thumb_preview'].'" alt="preview image" style="width:160px" /></p>';
								echo '<p>'.$vidInfo['title'].'</p>';
								echo '<div id="progress-bar"><div id="progress">0%</div></div>';
								echo '<div id="conversion-status">Downloading video. . .</div></div>';
								$converter->FlushBuffer();

								// Main Program Execution
								if ($converter->DownloadVideo())
								{
									echo '<div id="conversionSuccess"></div>';
									$songFile = trim(strstr($converter->GetConvertedFileName(), '/'), '/');
									if ($converter->GetSkipConversion())
									{
										echo '<script type="text/javascript">$(window).load(function(){ showConversionResult("'.$songFile.'", 1); });</script>';
									}
									else
									{
										echo '<script type="text/javascript">var progressBar = document.getElementById("progress"); progressBar.style.width = progressBar.innerHTML = "0%"; updateConversionProgress("'.$songFile.'");</script>';
										$converter->FlushBuffer();
										$converter->GenerateMP3();
									}
								}
								else
								{
									echo '<p style="margin-top:10px;margin-bottom:5px">Error downloading video!</p>';
								}
							}
						?>
						<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="conversionForm" style="display:none">
							<p><input type="text" class="form-control input-lg" name="videoURL" placeholder="Enter a valid, supported site URL" /></p>
							<p><i>(i.e., "<span style="color:#337ab7"><?php
								if (!empty($vidHosts))
								{
									$currentVhost = end($vidHosts);
									echo $currentVhost['url_root'][0] . $currentVhost['url_example_suffix'];
								}
								else
								{
									echo "http://www.video-site.com/videoID";
								}
							?></span>")</i></p>
							<p style="margin-top:20px">Choose the audio file quality:</p>
							<p style="margin-bottom:25px"><?php
								foreach ($qualities as $label => $qual)
								{
									echo '<input type="radio" value="'.$qual.'" name="quality"';
									echo ($label == "medium") ? ' checked="checked"' : '';
									echo ' /> '.ucfirst($label).' &nbsp; ';
								}
							?></p>
							<p><input type="hidden" name="formToken" value="<?php echo $converter->GetUniqueID(); ?>" /><button type="submit" name="submit" class="btn btn-primary" value="Create MP3 File"><i class="fa fa-cogs"></i> Create MP3 File</button></p>
						</form>
					</div><!-- ./converter -->
				</div><!-- ./overlay -->
			</div><!-- ./col-lg-6 -->
		</div><!-- ./row -->
	</div><!-- ./container -->
<?php include 'inc/page_footer.php'; ?>
	<script type="text/javascript">
	//<![CDATA[
		var conversionLogLength = 0;

		function updateVideoDownloadProgress(percentage)
		{
			var progress = document.getElementById('progress');
			progress.style.width = progress.innerHTML = parseInt(percentage) + '%';
		}

		function updateConversionProgress(songFile)
		{
			var progress = document.getElementById('progress');
			document.getElementById('conversion-status').innerHTML = "Converting video. . .";
			$.ajax({
				type : "POST",
				url : "ffmpeg_progress.php",
				data : "uniqueId=<?php echo $converter->GetUniqueID(); ?>&logLength=" + conversionLogLength + "&mp3File=" + encodeURI(songFile),
				success : function(retVal, status, xhr) {
					var retVals = retVal.split('|');
					if (retVals[3] == 2)
					{
						progress.style.width = progress.innerHTML = parseInt(retVals[1]) + '%';
						if (parseInt(retVals[1]) < 100)
						{
							conversionLogLength = parseInt(retVals[0]);
							setTimeout(function(){updateConversionProgress(songFile);}, 10);
						}
						else
						{
							showConversionResult(songFile, retVals[2]);
						}
					}
					else
					{
						setTimeout(function(){updateConversionProgress(songFile);}, 1);
					}
				},
				error : function(xhr, status, ex) {
					setTimeout(function(){updateConversionProgress(songFile);}, 1);
				}
			});
		}

		function showConversionResult(songFile, success)
		{
			$("#preview").css("display", "none");
			var convertSuccessMsg = (success == 1) ? '<p class="alert alert-success">Success!</p><p><a class="btn btn-success" href="<?php echo $_SERVER['PHP_SELF']; ?>?mp3=' + encodeURI(songFile) + '"><i class="fa fa-download"></i> Download your MP3 file</a><br /> <br /><a class="btn btn-warning" href="<?php echo $_SERVER['PHP_SELF']; ?>"><i class="fa fa-reply"></i> Back to Homepage</a></p>' : '<p class="alert alert-danger">Error generating MP3 file!</p>';
			$("#conversionSuccess").html(convertSuccessMsg);
			//$("#conversionForm").css("display", "block");
		}

		$(document).ready(function(){
			if (!document.getElementById('preview'))
			{
				$("#conversionForm").css("display", "block");
			}

			$(function(){
			  $('[data-toggle="tooltip"]').tooltip();
			});
		});
	//]]>
	</script>
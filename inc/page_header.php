<?php
	use MP3Converter\lib\Config;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>MP3 Converter :: <?php echo Config::_SITENAME; ?></title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" />
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css" />
	<link rel="stylesheet" href="assets/css/media-icons.css" />
	<link rel="stylesheet" href="assets/css/style.css" />
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function(){
			<?php if (isset($pageName)) { ?>
				$("ul.navbar-nav li a").each(function(){
					if ($(this).text().toLowerCase() == " <?php echo strtolower($pageName); ?>")
					{
						$(this).parent().addClass("active");
					}
				});
			<?php } ?>
		});
	</script>
	<?php if (isset($converter)) include 'inc/converter_js.php'; ?>
</head>
<body>
	<nav class="navbar navbar-default navbar-aluminium">
	  <div class="container">
		<!-- Brand and toggle get grouped for better mobile display -->
		<div class="navbar-header">
		  <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
			<span class="sr-only">Toggle navigation</span>
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
			<span class="icon-bar"></span>
		  </button>
		  <a class="navbar-brand" href="index.php"><?php echo Config::_SITENAME; ?></a>
		</div>
		<!-- Collect the nav links, forms, and other content for toggling -->
		<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
		  <ul class="nav navbar-nav">
			<li<?php echo (isset($converter)) ? ' class="active"' : ''; ?>><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
			<li><a href="about.php"><i class="fa fa-user"></i> About</a></li>
			<li><a href="faq.php"><i class="fa fa-question"></i> FAQ</a></li>
			<li><a href="contact.php"><i class="fa fa-envelope-o"></i> Contact</a></li>
		  </ul>
		</div><!-- /.navbar-collapse -->
	  </div><!-- /.container-fluid -->
	</nav>
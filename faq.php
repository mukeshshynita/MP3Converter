<?php
	use MP3Converter\lib\Config;

	// Autoload class files
	include 'inc/autoload.php';

	// Page title
	$pageName = 'FAQ';
?>
<?php include 'inc/page_header.php'; ?>
	<div class="container">
		<div class="row">
			<div class="col-lg-8 col-lg-offset-2 col-md-8 col-md-offset-2 col-sm-10 col-sm-offset-1 ">
				<div class="overlay">
					<div class="content-page text-center">
						<h2><?php echo $pageName; ?></h2>
						<p>Your Content here.</p>
					</div><!-- ./converter -->
				</div><!-- ./overlay -->
			</div><!-- ./col-lg-6 -->
		</div><!-- ./row -->
	</div><!-- ./container -->
<?php include 'inc/page_footer.php'; ?>
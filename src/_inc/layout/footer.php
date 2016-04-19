	<footer role="contentinfo">
		<p>Thrown together by <a href="http://adamaveray.com.au/" target="_blank">Adam Averay</a></p>
		<p>Movie metadata from <a href="http://www.omdbapi.com" target="_blank" rel="nofollow">OMDb API</a></p>
		<p><a href="https://github.com/adamaveray/nyc-summer-movies/" target="_blank">Contribute</a></p>
	</footer>

	<?php /*
	<!--[if lt IE 9]>
		<?php if(!IS_DEV){ ?><script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script><?php } ?>
		<script>window.jQuery || document.write('<script src="/js/vendor/jquery-1.11.3.min.js"><\/script>')</script>
	<![endif]-->
	<!--[if gte IE 9]><!-->
		<?php if(!IS_DEV){ ?><script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script><?php } ?>
		<script>window.jQuery || document.write('<script src="/js/vendor/jquery-2.1.4.min.js"><\/script>')</script>
	<!--<![endif]-->
	*/ ?>

	<script src="/js/main.js"></script>
	<?=(isset($scripts) ? $scripts : '');?>
	<?=(isset($foot) ? $foot : '');?>
	<?php if(ANALYTICS_ID !== ''){ ?>
    <script>window.ga=function(){ga.q.push(arguments)};ga.q=[];ga.l=+new Date;ga('create','<?=e(ANALYTICS_ID);?>','auto');ga('send','pageview'<?php if(isset($analyticsURL)){ echo ',\''.e($analyticsURL).'\''; } ?>)</script>
	<?php } ?>
</body>
</html>

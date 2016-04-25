	<footer role="contentinfo">
		<p>Made by <a href="http://adamaveray.com.au/" target="_blank">Adam Averay</a></p>
		<p>Some data from <a href="http://www.omdbapi.com/" target="_blank" rel="nofollow">OMDb API</a> &amp; <a href="https://www.yahoo.com/news/weather/" target="_blank" rel="nofollow">Yahoo Weather</a></p>
		<script>document.write('<p><'+'a  h'+(0 ? 'nul' : 'ref')+'="m'+'o,t,l,i,a'.split(',').reverse().join('')+':'+"Nf99,Cy111'Si110-Vp116#Jq97.Hl99#Rn116.Fp64+Kk115*Lq117+Ge109.Zg109.Hz101%Cu114-Ee109(Bl111.Dc118'Zi105/Rt101'Fe115#Xk46%Vk110(Rs121(Wu99%".replace(/[^a-zA-Z0-9]/g,';').replace(/[A-Z]/g,'&').replace(/[a-z]/g,'#')+'">'+"Contact"+'<\/a><\/p>');</script>
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

	<script src="/js/vendor/Weather.js"></script>
	<script src="/js/main.js"></script>
	<?=(isset($scripts) ? $scripts : '');?>
	<?=(isset($foot) ? $foot : '');?>
	<?php if(ANALYTICS_ID !== ''){ ?>
    <script>window.ga=function(){ga.q.push(arguments)};ga.q=[];ga.l=+new Date;ga('create','<?=e(ANALYTICS_ID);?>','auto');ga('send','pageview')</script>
	<?php } ?>
</body>
</html>

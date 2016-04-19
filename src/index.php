<?php
$ROOT=__DIR__;
require_once($ROOT.'/_inc/tpl.php');

$pageID				= 'home';
$pageTitle			= null;
// $pageDescription	= 'The description of the page';

$scripts	= '<script src="https://maps.googleapis.com/maps/api/js?key='.e(MAPS_KEY).'&callback=googleMapsReady" async defer></script>';

include($ROOT.'/_inc/layout/header.php');

/** @var Movie[] $movies */
/** @var Venue[] $venues */
list($venues, $movies)	= loadData(2015);

$ratings	= [];
$boroughs	= [];
foreach($movies as $movie){
	if(isset($movie->rating)){
		$ratings[$movie->rating]	= true;
	}
	if(isset($movie->venue) && isset($movie->venue->borough)){
		$boroughs[$movie->venue->borough]	= ucwords(str_replace('-', ' ', $movie->venue->borough));
	}
}
$ratings	= array_keys($ratings);
?>

<header role="banner">
	<a class="site-name" href="/"><h1>Summer Movies NYC</h1></a>

	<a class="calendar-feed" href="webcal://localhost:8080/calendar.ics">Subscribe in Calendar</a>
</header>

<div id="map">
	<p class="map-loading">
		<span class="map-loading__label">Loading Map</span>
	</p>
</div>

<main id="main">
	<header class="search-filters">
		<ul class="search-filters__items">
			<li class="search-filter search-filter--filter search-filter--checkbox search-filter--free">
				<input type="checkbox" name="filter-free" id="search-filter-free" value="1" />
				<label for="search-filter-free">Free Only</label>
			</li>
			<?php
			function buildSelectOptions($items, $associative = true, $blank = null){
				?>
				<?php if(isset($blank)){ ?>
				<option value="" selected><?=($blank === true) ? '' : $blank;?></option>
				<?php } ?>
				<?php foreach($items as $key => $value){
					if(!$associative){
						$key	= $value;
					}
					?>
					<option value="<?=e($key);?>"><?=e($value);?></option>
				<?php } ?>
				<?php
			}
			?>

			<?php if($ratings){ ?>
			<li class="search-filter search-filter--filter search-filter--select search-filter--rating">
				<label for="search-filter-rating">Rating</label>
				<select name="filter-rating" id="search-filter-rating">
					<?php buildSelectOptions($ratings, false, 'All Ratings');?>
				</select>
			</li>
			<?php } ?>

			<?php if($boroughs){ ?>
			<li class="search-filter search-filter--filter search-filter--select search-filter--borough">
				<label for="search-filter-borough">Borough</label>
				<select name="filter-venue.borough" id="search-filter-borough">
					<?php buildSelectOptions($boroughs, true, 'All Boroughs');?>
				</select>
			</li>
			<?php } ?>

			<?php /*
			<li class="search-filter search-filter--select search-filter--sort">
				<label for="search-filter-sort">Sort</label>
				<select name="sort" id="search-filter-sort">
					<option value="date">Date</option>
					<option value="title">Title</option>
					<option value="venue">Venue</option>
				</select>
			</li>
			*/ ?>
			<li class="search-filter search-filter--radio search-filter--display">
				<input type="radio" name="size-toggle" id="search-filter-size-toggle-full" value="full" checked="checked" />
				<label for="search-filter-size-toggle-full">Full View</label>
				<input type="radio" name="size-toggle" id="search-filter-size-toggle-compact" value="compact" />
				<label for="search-filter-size-toggle-compact">Compact View</label>
			</li>
		</ul>
	</header>

	<?php $currentMonth = null; ?>
	<?php foreach($movies as $movie){ ?>
		<?php
		$newMonth 	= $movie->date->format('F');
		$movieDate	= $movie->date->format('D M j');
		$movieDateExtra	= $movie->date->format('g:ia');
		if($movieDateExtra !== '12:00am'){
			// Has time
			$movieDate	.= ' '.$movieDateExtra;
		}

		$movieClasses	= [];
		if(!isset($movie->poster)){
			$movieClasses[]	= 'movie--no-poster';
		}
		if(!isset($movie->year)){
			$movieClasses[]	= 'movie--no-year';
		}
		if(!isset($movie->rating)){
			$movieClasses[]	= 'movie--no-rating';
		}
		if(!isset($movie->synopsis)){
			$movieClasses[]	= 'movie--no-synopsis';
		}
		?>
		<?php if(!isset($currentMonth) || $currentMonth !== $newMonth){?>
			<?php if(isset($currentMonth)){?>
				</ol>
			<?php } ?>
			<?php $currentMonth = $newMonth;?>
			<h2 class="movies-month" data-month="<?=e($movie->date->format('n'));?>"><?=e($currentMonth);?></h2>
			<ol class="movies">
		<?php } ?>
			<li class="movie <?=implode(' ', $movieClasses);?>" id="<?=e($movie->id);?>"
				data-free="<?=e(isset($movie->cost) ? '1' : '0');?>"
				>
				<?php if(isset($movie->poster)){ ?>
					<figure class="movie__poster">
						<!--
						<img src="<?=e($movie->poster);?>" alt="<?=e($movie->title);?>" />
						-->
					</figure>
				<?php } ?>
				<div class="movie__content">
					<h3 class="movie__title">
						<strong class="movie__title__name"><?=e($movie->title)?></strong>

						<?php if(isset($movie->year)){?>
						<span class="separator">–</span>

						<span class="movie__title__year"><?=e($movie->year);?></span>
						<?php } ?>

						<?php if(isset($movie->rating)){ ?>
						<span class="movie__title__rating">
							<span class="separator">(</span><?=e($movie->rating);?><span class="separator">)</span>
						</span>
						<?php } ?>
					</h3>
					<?php if(isset($movie->synopsis)){ ?>
						<p class="movie__synopsis"><?=e($movie->synopsis);?></p>
					<?php } ?>
					<ul class="movie__details">
						<li class="movie__detail movie__detail--date">
							<strong>Date:</strong>
								<time datetime="<?=e($movie->date->format('c'));?>"><?=e($movieDate);?></time>
						</li>
						<li class="movie__detail movie__detail--venue"
							data-id="<?=e($movie->venue->id);?>"
							data-borough="<?=e($movie->venue->borough);?>"
							data-location="<?=e($movie->venue->location);?>"
							data-coords="<?=e($movie->venue->coords);?>"
							data-image="<?=$movie->venue->image ? 1 : '';?>"
							data-website="<?=e($movie->venue->website);?>"
							data-facebook="<?=e($movie->venue->facebook);?>"
							data-twitter="<?=e($movie->venue->twitter);?>"
							data-foursquare="<?=e($movie->venue->foursquare);?>"
							>
							<strong>Venue:</strong>
								<?=e($movie->venue->name);?>
						</li>
					</ul>
				</div>
			</li>
	<?php } ?>
	<?php if(isset($movie)){ ?>
		</ol>
	<?php } ?>

	<div class="search-signup">
		<h2>Sign Up for Updates</h2>
		<p>Get notified when new screenings are announced. No spam, just a brief email when new venues or screenings are announced.</p>
		<form action="//adamaveray.us13.list-manage.com/subscribe/post?u=7368a199a4d5b1c59420e8af7&amp;id=aef6504333" method="post" target="_blank">
			<span class="input input--hinted">
				<label for="input-subscribe-email">Email Address</label>
				<input name="EMAIL" id="input-subscribe-email" type="email" />
			</span>
			<!-- .h.o.n.e.y.p.o.t. -->
			<div class="hp" aria-hidden="true"><input type="text" name="b_7368a199a4d5b1c59420e8af7_aef6504333" tabindex="-1" value=""></div>
			<input type="hidden" name="subscribe" value="Subscribe" />
			<button type="submit">Sign Up</button>
		</form>
	</div>
</main>

<script type="text/x-data" id="pin-images"><?=file_get_contents($ROOT.'/img/map/pin.svg').'|'.file_get_contents($ROOT.'/img/map/pin-active.svg');?></script>
<template id="template-venue-details">
	<div class="map-details">
		<header class="map-details__image">
			<img />
		</header>

		<h2 class="map-details__title"></h2>
		<p class="map-details__location"></p>
		<p class="map-details__description"></p>

		<ul class="map-details__actions">
			<li class="map-details__action map-details__action--directions">
				<a class="map-details__link" target="_blank" rel="nofollow" href="https://maps.google.com?saddr=Current+Location&daddr=">Get Directions</a>
			</li>
			<li class="map-details__action map-details__action--website">
				<a class="map-details__link" target="_blank" rel="nofollow">View Website</a>
			</li>
			<?php $platforms = [
				'Facebook'		=> 'https://www.facebook.com/',
				'Twitter'		=> 'https://twitter.com/',
				'Foursquare'	=> 'https://4sq.com/',
			];?>
			<?php foreach($platforms as $platform => $url){ ?>
			<li class="map-details__action map-details__action--social map-details__action--social--<?=e(slugify($platform));?>">
				<a class="map-details__link" target="_blank" rel="nofollow" href="<?=e($url);?>"><?=e($platform);?></a>
			</li>
			<?php } ?>
		</ul>
	</div>
</template>
<template id="template-no-results">
	<div class="search-feedback search-feedback--no-results">
		<h2>No Movies Found</h2>
		<p>No movies match the current filters.</p>
		<button class="search-feedback__reset cta">Reset Filters</button>
	</div>
</template>

<?php
include($ROOT.'/_inc/layout/footer.php');
?>

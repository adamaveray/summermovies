<?php
$ROOT=__DIR__.'/../src';
require_once($ROOT.'/_inc/lib.php');

$year	= @date('Y');
if(IS_CLI){
	$args	= array_slice($argv, 1);
	for($i = 0, $max = count($args); $i < $max; $i += 2){
		$key	= substr($args[$i], 2);
		if($key === 'year'){
			$year	= $args[$i+1];
		}
	}
}

$pageDescription	= 'Summertime means outdoor movies time. See what movies are screening this summer across New York.';

$scripts	= '<script src="https://maps.googleapis.com/maps/api/js?key='.e(MAPS_KEY).'&callback=googleMapsReady" async defer></script>';

include($ROOT.'/_inc/layout/header.php');

/** @var Movie[] $movies */
/** @var Venue[] $venues */
try {
	list($venues, $movies)	= loadData($year);
	$hasMovies	= count($movies) > 0;
} catch(\OutOfBoundsException $e){
	// Unknown year
	if(IS_CLI){
		// Output in CLI
		throw $e;
	}

	$movies	= [];
	$venues	= [];
	$hasMovies	= false;
}

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

// Remove past movies
$now	= new \DateTime('now');
$movies	= array_filter($movies, function(Movie $movie) use($now){
	return ($movie->date > $now);
});
?>

<header role="banner">
	<a class="site-name" href="/"><h1>Summer Movies NYC</h1></a>

	<a class="calendar-feed" href="webcal://summermovies.nyc/calendar.ics">Subscribe in Calendar</a>
</header>

<div id="map">
	<p class="map-loading">
		<span class="map-loading__label">Loading Map</span>
	</p>
</div>

<main id="main">
	<?php if($movies){ ?>
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
	<?php } else { ?>
		<header class="no-results no-results--<?=($hasMovies ? 'past' : 'future');?>">
			<h2 class="no-results__title">That's A Wrap!</h2>
			<p class="no-results__message">
				<?php if($hasMovies){ ?>
					All screenings for the year are over.
				<?php } else { ?>
					This year's movies haven't been announced yet.
				<?php } ?>
			</p>
		</header>
	<?php } ?>

	<?php
	$currentMonth	= null;
	$activeVenues	= [];
	?>
	<?php foreach($movies as $movie){ ?>
		<?php
		$activeVenues[$movie->venue->id]	= $movie->venue;

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
		if($movie->isPending){
			$movieClasses[]	= 'movie--pending';
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
				data-free="<?=e((isset($movie->cost) && $movie->cost != '0') ? '0' : '1');?>"
				>
				<?php if(isset($movie->poster)){ ?>
					<figure class="movie__poster">
						<img src="<?=e($movie->poster);?>" alt="<?=e($movie->title);?>" />
					</figure>
				<?php } else { ?>
					<span class="movie__poster">No poster available</span>
				<?php } ?>
				<div class="movie__content">
					<h3 class="movie__title">
						<?php if(isset($movie->url)){ ?>
							<a class="movie__title__name" href="<?=e($movie->url);?>" target="_blank" rel="nofollow"><?=e($movie->title)?></a>
						<?php } else { ?>
							<strong class="movie__title__name"><?=e($movie->title)?></strong>
						<?php } ?>

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
						<li class="movie__detail movie__detail--venue">
							<strong>Venue:</strong>
								<a href="#venue-<?=e($movie->venue->id);?>"><?=e($movie->venue->name);?></a>
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

	<ul class="venues">
		<?php foreach($activeVenues as $venue){ ?>
			<li class="venue-details"
					id="venue-<?=e($venue->id);?>"
					data-coords="<?=e($venue->coords);?>"
					data-borough="<?=e($venue->borough);?>"
					<?php if($venue->image){ ?>
						data-image="<?=e('/img/venues/'.$venue->id.'.jpg');?>"
					<?php } ?>
				>
				<h2 class="venue-details__name"><?=e($venue->name);?></h2>
				<p class="venue-details__location"><?=e($venue->location);?></p>
				<?php /*
				<p class="venue__description"><?=e($venue->description);?></p>
				*/ ?>

				<ul class="venue-details__actions">
					<?php if(isset($venue->coords)){ ?>
					<li class="venue-details__action venue-details__action--directions">
						<a class="venue-details__link" target="_blank" rel="nofollow" href="https://maps.google.com?saddr=Current+Location&daddr=<?=e($venue->coords);?>">Get Directions</a>
					</li>
					<?php } ?>
					<?php if(isset($venue->website)){ ?>
					<li class="venue-details__action venue-details__action--website">
						<a class="venue-details__link" href="<?=e($venue->website);?>" target="_blank" rel="nofollow">View Website</a>
					</li>
					<?php } ?>
					<?php $platforms = [
						'Facebook'		=> $venue->facebook		? 'https://www.facebook.com/'.$venue->facebook	: null,
						'Twitter'		=> $venue->twitter		? 'https://twitter.com/'.$venue->twitter		: null,
						'Foursquare'	=> $venue->foursquare	? 'https://4sq.com/'.$venue->foursquare			: null,
					];?>
					<?php foreach($platforms as $platform => $url){ if(!isset($url)){ continue; } ?>
					<li class="venue-details__action venue-details__action--social venue-details__action--social--<?=e(slugify($platform));?>">
						<a class="venue-details__link" target="_blank" rel="nofollow" href="<?=e($url);?>"><?=e($platform);?></a>
					</li>
					<?php } ?>
				</ul>
			</li>
		<?php } ?>
	</ul>
</main>

<script type="text/x-data" id="pin-images"><?=file_get_contents($ROOT.'/img/map/pin.svg').'|'.file_get_contents($ROOT.'/img/map/pin-active.svg');?></script>
<template id="template-venue-image">
	<header class="venue-details__image">
		<img />
	</header>
</template>
<template id="template-venue-dismiss">
	<button class="venue-details-dismiss">Close Venue</button>
</template>
<template id="template-no-results">
	<div class="search-feedback search-feedback--no-results">
		<h2>No Movies Found</h2>
		<p>No movies match the current filters.</p>
		<button class="search-feedback__reset cta">Reset Filters</button>
	</div>
</template>
<template id="template-weather">
	<p class="forecast">
		<a class="forecast__link" target="_blank">
			<span class="forecast__conditions"></span>
			<span class="forecast__temperature"></span>
		</a>
	</p>
</template>

<?php
include($ROOT.'/_inc/layout/footer.php');
?>

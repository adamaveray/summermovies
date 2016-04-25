(function(){
	"use strict";

	// Utilities
	function trim(text){
		return String(text).replace(/^\s*(.*?)\s*$/m, '$1')
	}
	function oneNode(node){
		if(node instanceof NodeList || node instanceof HTMLCollection){
			// Use first element in list
			node	= ((node.length > 0) ? node[0] : null);
		}

		return node;
	}
	function getText(node, trimText){
		node	= oneNode(node);

		var content	= (node ? (node.innerText || node.textContent) : null);
		if(content == null){
			return null;
		}

		if(trimText || trimText === undefined){
			content	= trim(content);
		}
		return content;
	}
	function setText(node, text){
		node	= oneNode(node);
		if(!node){
			return;
		}

		if('textContent' in node){
			node.textContent	= text;
		} else {
			node.innerText		= text;
		}
	}
	function find(parent, steps){
		var level	= oneNode(parent);
		for(var i = 0; i < steps.length && level != null; i++){
			var step	= steps[i];
			parent	= oneNode(level);
			level	= null;
			switch(typeof step){
				case 'number':
					if(parent && parent.childNodes && parent.childNodes.length >= step){
						level	= parent.childNodes[step];
					}
					break;
				case 'string':
					if(step.substr(0, 1) === '.'){
						level	= parent.getElementsByClassName(step.substr(1));
						if(!level.length){
							level	= null;
						}
					} else {
						step	= step.toUpperCase();
						for(var j = 0; j < parent.childNodes.length; j++){
							var childNode	= parent.childNodes[j];
							if(childNode.tagName != null && childNode.tagName === step){
								level	= childNode;
								break;
							}
						}
					}
					break;
				default:
					throw new Error('Unknown step "'+step+'"');
					break;
			}
		}

		return level;
	}
	function findOne(){
		return oneNode(find.apply(this, arguments));
	}
	var __classRegExpPatterns	= {};
	function classRegExp(className){
		if(!(className instanceof RegExp)){
			if(!__classRegExpPatterns[className]){
				// Cache instance
				__classRegExpPatterns[className]	= new RegExp('(^|\\s)'+className+'(\\s|$)', 'm');
			}
			className	= __classRegExpPatterns[className];
		}
		return className;
	}
	function elementHasClass(element, className){
		var pattern	= classRegExp(className);
		return !!element.className.match(pattern)
	}
	function toggleClass(element, className, state){
		var hasClass	= elementHasClass(element, className);

		if(state){
			// Add class
			if(!hasClass){
				element.className	+= ' '+className;
			}
		} else {
			// Remove class
			if(hasClass){
				var pattern	= classRegExp(className);
				element.className	= element.className.replace(pattern, '$1');
			}
		}
	}
	function removeNode(node){
		return node.parentNode.removeChild(node);
	}
	function loadTemplate(template, singleNode){
		var generator	= document.createElement('div');
		generator.innerHTML	= trim(template);

		var result	= generator;
		if(singleNode || singleNode === undefined){
			result	= oneNode(result);
		}
		return result;
	}
	function insertBefore(element, target){
		return target.parentNode.insertBefore(element, target);
	}
	var transitionEndEvent;
	function onTransitionEnd(element, handler){
		if(!element){
			return;
		}

		// Determine supported event
		if(transitionEndEvent === undefined){
			transitionEndEvent	= null;	// Prevent repeat testing for unsupported browsers (undefined vs null)
			var transitions	= {
				'transition':       'transitionend',
				'OTransition':      'oTransitionEnd',
				'MozTransition':    'transitionend',
				'WebkitTransition': 'webkitTransitionEnd'
			};
			for(var t in transitions){ if(!transitions.hasOwnProperty(t)){ continue; }
				if(element.style[t] !== undefined){
					transitionEndEvent	= transitions[t];
					break;
				}
			}
		}

		if(transitionEndEvent){
			element.addEventListener(transitionEndEvent, handler);
		} else {
			// No support - trigger immediately
			window.setTimeout(function(){
				handler.call(element);
			}, 0);
		}

		window.setTimeout(function(){
			toggleClass(element, '__active', false);
		}, 1500);
	}

	var moviesContainer	= document.getElementById('main'),
		movieElements	= document.getElementsByClassName('movie'),
		activeFeedback;

	function showFeedback(type){
		clearFeedback();

		var template	= document.getElementById('template-'+type),
			feedback	= loadTemplate(template.innerHTML);

		var resetButton	= findOne(feedback, ['.search-feedback__reset']);
		if(resetButton){
			resetButton.addEventListener('click', function(){
				resetFilters();
			});
		}

		activeFeedback	= feedback;
		moviesContainer.appendChild(activeFeedback);
	}
	function clearFeedback(){
		if(!activeFeedback){
			return;
		}
		moviesContainer.removeChild(activeFeedback);
		activeFeedback	= null;
	}

	// Input placeholders
	(function(){
		var inputs	= document.getElementsByClassName('input--hinted'),
			activeClass	= 'input--active';
		for(var i = 0; i < inputs.length; i++){
			var input	= inputs[i],
				field	= findOne(input, ['input']);
			toggleClass(input, 'input--setup', true);
			field.addEventListener('focus', function(){
				toggleClass(this.parentNode, activeClass, true);
			});
			field.addEventListener('blur', function(){
				var field	= this;
				window.setTimeout(function(){
					var isActive = (field.value !== field.defaultValue);
					toggleClass(field.parentNode, activeClass, isActive);
				}());
			});
		}
	}());

	// Size toggle
	(function(){
		var elements	= document.getElementsByClassName('movies'),
			classes		= (elements.length ? elements[0].className : '');
		var toggle	= function(e){
			var state	= e.target.value;

			for(var i = 0; i < elements.length; i++){
				elements[i].className	= classes+' movies--'+state;
			}
		};
		document.getElementById('search-filter-size-toggle-full').addEventListener('change', toggle);
		document.getElementById('search-filter-size-toggle-compact').addEventListener('change', toggle);
	}());

	// Filtering logic
	var applyFilters,
		moviesData,
		venues;
	var filterListeners	= [];
	var filterValues	= {};
	var dates		= {},
		hasDates	= false;

	(function(){
		// Load venues data
		var venueElements	= document.getElementsByClassName('venue-details');
		venues	= {};

		var getVenueText		= function(element){
			if(!element){
				return null;
			}
			var text	= getText(element);
			return text === '' ? null : text;
		};
		var getVenueAttribute	= function(element, attr){
			if(!element){
				return;
			}
			var text	= element.getAttribute(attr);
			return (text == null || text === '') ? null : text;
		};

		for(var i = 0; i < venueElements.length; i++){
			var element	= venueElements[i];

			var venueID	= element.id.substr(6); // Remove 'venue-' prefix

			// Parse coordinates
			var coords	= element.getAttribute('data-coords');
			if(coords){
				coords	= coords.split(',');
				if(coords.length === 2){
					coords	= {lat: parseFloat(coords[0]), lng: parseFloat(coords[1])};
				} else {
					coords	= null;
				}
			}

			venues[venueID]	= {
				element:		element,
				id:				venueID,
				title:			getVenueText(findOne(element, ['.venue-details__name'])),
				borough:		getVenueAttribute(element, 'data-borough'),
				location:		getVenueText(findOne(element, ['.venue-details__location'])),
				description:	getVenueText(findOne(element, ['.venue-details__name'])),
				website:		getVenueAttribute(findOne(element, ['.venue-details__action--website', 'a']), 'href'),
				facebook:		getVenueAttribute(findOne(element, ['.venue-details__action--social--facebook', 'a']), 'href'),
				twitter:		getVenueAttribute(findOne(element, ['.venue-details__action--social--twitter', 'a']), 'href'),
				foursquare:		getVenueAttribute(findOne(element, ['.venue-details__action--social--foursquare', 'a']), 'href'),
				image:			getVenueAttribute(element, 'data-image'),
				coords:			coords,
				movies:			[],
			};
		}
	}());

	(function(){
		// Load movie data
		moviesData	= {};
		for(var i = 0; i < movieElements.length; i++){
			var element	= movieElements[i];

			// Extract data
			var titleBlock	= findOne(element, ['.movie__title']),
				title		= getText(findOne(titleBlock, ['.movie__title__name'])),
				year		= getText(findOne(titleBlock, ['.movie__title__year'])),
				rating		= getText(findOne(titleBlock, ['.movie__title__rating', 2]));
			var detailsBlock	= findOne(element, ['.movie__details']),
				venueElement	= findOne(detailsBlock, ['.movie__detail--venue', 'a']),
				dateElement		= findOne(detailsBlock, ['.movie__detail--date', 3]);

			var dateString	= dateElement ? dateElement.getAttribute('datetime') : null,
				date		= dateElement ? new Date(dateString) : null;

			// Link venue details
			var venue	= null;
			if(venueElement){
				var venueID	= venueElement.getAttribute('href').substr(7); // Remove '#venue-'
				venue	= venues[venueID];
			}

			var movieData	= {
				element:	element,
				id:		element.id,
				title:	title,
				year:	year,
				rating:	rating,
				date:	date,
				venue:	venue,
				free:	(element.getAttribute('data-free') === '1'),
			};

			moviesData[element.id]	= movieData;
			if(venue){
				venue.movies.push(movieData)
			}
			if(!dates[date]){
				dates[date]	= {
					date:		date,
					elements:	[],
				};
				hasDates	= true;
			}
			dates[date].elements.push(dateElement);
		}

		function filterData(filters){
			var subset	= {};
			for(var id in moviesData){ if(!moviesData.hasOwnProperty(id)){ continue; }
				var movie	= moviesData[id];

				var match	= true;
				for(var filter in filters){ if(!filters.hasOwnProperty(filter)){ continue; }
					var value	= filters[filter],
						steps	= filter.split('.'),
						level	= movie;
					for(var i = 0; i < steps.length && level != null; i++){
						var step	= steps[i];
						level		= level[step];
					}
					if(!level || !match){
						match	= false;
						break;
					}

					if(level !== value){
						match	= false;
						break;
					}
				}

				if(match){
					subset[id]	= movie;
				}
			}
			return subset;
		}

		applyFilters	= function(filters){
			var newData			= filterData(filters),
				invalidClass	= '__filtered-out',
				currentMonth		= null,
				currentMonthCount	= 0;

			var handleMonth	= function(month, count){
				if(!month){
					return;
				}
				toggleClass(month.previousSibling.previousSibling, invalidClass, (count === 0));
			};

			// Check each element for validity
			var validItemsCount	= 0;
			for(var i = 0; i < movieElements.length; i++){
				var element	= movieElements[i];

				// Check for switching months
				var thisMonth	= element.parentNode;
				if(currentMonth !== thisMonth){
					// Changed months
					handleMonth(currentMonth, currentMonthCount);
					currentMonth		= thisMonth;
					currentMonthCount	= 0;
				}

				// Update element visibility
				var isValid	= newData[element.id];
				toggleClass(element, invalidClass, !isValid);
				if(isValid){
					validItemsCount++;
					currentMonthCount++;
				}
			}

			if(validItemsCount > 0){
				clearFeedback();
			} else {
				showFeedback('no-results');
			}

			handleMonth(currentMonth, currentMonthCount);

			for(i = 0; i < filterListeners.length; i++){
				filterListeners[i](newData, filters);
			}
		};
	}());
	function setFilters(newValues, apply){
		for(var filter in newValues){ if(!newValues.hasOwnProperty(filter)){ continue; }
			var value	= newValues[filter];

			if(value == null || value === ''){
				delete filterValues[filter];
			} else {
				filterValues[filter]	= value;
			}
		}
		if(apply || apply === undefined){
			applyFilters(filterValues);
		}
	}
	function setFilter(filter, value, apply){
		var values	= {};
		values[filter]	= value;
		setFilters(values, apply);
	}
	function addFilterListener(fn){
		filterListeners.push(fn);
	}

	// Filtering interactivity
	var resetFilters;
	(function(){
		var handler	= function(e){
			var filter	= this.getAttribute('name').replace(/^filter-/, ''),
				value	= this.value;

			if(this.getAttribute('type') === 'checkbox'){
				var toggleOn	= value,
					toggleOff	= null;
				if(value === '1'){
					toggleOn	= true;
				}
				value	= this.checked ? toggleOn : toggleOff;
			}

			var apply	= !!e;
			setFilter(filter, value, apply);
		};
		var filterElements	= find(document, ['.search-filter--filter']),
			filterInputs	= [];
		for(var i = 0; i < filterElements.length; i++){
			var filterContainer	= filterElements[i],
				filter			= findOne(filterContainer, ['input']) || findOne(filterContainer, ['select']);

			filter.addEventListener('change', handler);
			filterInputs.push(filter);
		}

		var manualEvent;
		resetFilters	= function(){
			manualEvent || (manualEvent = new MouseEvent('click', {
				'view':       window,
				'bubbles':    true,
				'cancelable': true
			}));
			for(var i = 0; i < filterInputs.length; i++){
				var filter	= filterInputs[i];

				switch(filter.nodeName){
					case 'SELECT':
						for(var j = 0; j < filter.childNodes.length; j++){
							var option	= filter.childNodes[j];
							option.selected	= option.defaultSelected;
						}
						break;

					case 'INPUT':
						var setValue	= true;
						switch(filter.getAttribute('type')){
							case 'checkbox':
							case 'radio':
								filter.checked	= filter.defaultChecked;
								setValue	= false;
								break;
						}
						if(!setValue){
							break;
						}
						// Fallthrough

					default:
						filter.value	= filter.defaultValue;
						break;
				}

				// Update filter value
				handler.call(filter);
			}

			// Apply updated filters
			applyFilters(filterValues);
		};
	}());

	// Weather forecasts
	var weatherLocationID	= 2459115;	// New York, NY
	hasDates && window.Weather(weatherLocationID, 'f', function(weather){
		var formatDate	= function(date){
			return date.getFullYear()+'-'+(date.getMonth()+1)+'-'+date.getDate();
		};

		var forecasts	= {},
			forecast;
		for(var i = 0; i < weather.forecast.length; i++){
			forecast	= weather.forecast[i];
			var forecastDate	= new Date(forecast.date);

			forecasts[formatDate(forecastDate)]	= forecast;
		}

		var template;
		var outputElements	= [];
		for(var _ in dates){ if(!dates.hasOwnProperty(_)){ continue; }
			var data	= dates[_],
				date	= formatDate(data.date);
			if(!forecasts[date]){
				continue;
			}

			forecast	= forecasts[date];

			// Show forecast
			if(!template){
				template	= document.getElementById('template-weather').innerHTML;
			}

			for(i = 0; i < data.elements.length; i++){
				var element	= data.elements[i],
					output	= loadTemplate(template, true).firstChild;
				output.setAttribute('data-conditions', forecast.code);
				setText(findOne(output, ['.forecast__temperature']), forecast.high+'º');
				setText(findOne(output, ['.forecast__conditions']), forecast.text);
				findOne(output, ['.forecast__link']).setAttribute('href', weather.link);

				element.parentNode.appendChild(output);

				toggleClass(output, '__transitioning', true);
				outputElements.push(output);
			}
		}

		if(!outputElements.length){
			return;
		}

		// Trigger entrance animations
		var entranceIndex		= 0,
			entranceInterval	= window.setInterval(function(){
				toggleClass(outputElements[entranceIndex], '__transitioning', false);

				entranceIndex++;
				if(entranceIndex >= outputElements.length){
					window.clearInterval(entranceInterval);
				}
			}, 250);
	});

	// Map
	var mapStyles	= (function(){
		var on		={visibility:'on'},
			off		={visibility:'off'},
			onlyOff	=[off];
		return [
			{
				"featureType": "all",
				"elementType": "labels",
				"stylers": onlyOff
			},
			{
				"featureType": "administrative",
				"elementType": "all",
				"stylers": [
					off,
					{
						"color": "#efebe2"
					}
				]
			},
			{
				"featureType": "administrative.country",
				"elementType": "geometry",
				"stylers": [
					off,
					{
						"color": "#ff0000"
					}
				]
			},
			{
				"featureType": "administrative.country",
				"elementType": "geometry.fill",
				"stylers": [
					{
						"color": "#893b00"
					},
					on
				]
			},
			{
				"featureType": "administrative.country",
				"elementType": "geometry.stroke",
				"stylers": [
					on,
					{
						"color": "#ddd4cb"
					}
				]
			},
			{
				"featureType": "administrative.country",
				"elementType": "labels.text",
				"stylers": [
					on,
					{
						"color": "#343434"
					}
				]
			},
			{
				"featureType": "administrative.country",
				"elementType": "labels.text.stroke",
				"stylers": onlyOff
			},
			{
				"featureType": "administrative.country",
				"elementType": "labels.icon",
				"stylers": onlyOff
			},
			{
				"featureType": "landscape",
				"elementType": "all",
				"stylers": [
					{
						"color": "#efebe2"
					}
				]
			},
			{
				"featureType": "poi",
				"elementType": "all",
				"stylers": [
					{
						"color": "#efebe2"
					}
				]
			},
			{
				"featureType": "poi.attraction",
				"elementType": "all",
				"stylers": [
					{
						"color": "#efebe2"
					}
				]
			},
			{
				"featureType": "poi.business",
				"elementType": "all",
				"stylers": [
					{
						"color": "#efebe2"
					}
				]
			},
			{
				"featureType": "poi.government",
				"elementType": "all",
				"stylers": [
					{
						"color": "#dfdcd5"
					}
				]
			},
			{
				"featureType": "poi.medical",
				"elementType": "all",
				"stylers": [
					{
						"color": "#dfdcd5"
					}
				]
			},
			{
				"featureType": "poi.park",
				"elementType": "all",
				"stylers": [
					{
						"color": "#bad294"
					}
				]
			},
			{
				"featureType": "poi.place_of_worship",
				"elementType": "all",
				"stylers": [
					{
						"color": "#efebe2"
					}
				]
			},
			{
				"featureType": "poi.school",
				"elementType": "all",
				"stylers": [
					{
						"color": "#efebe2"
					}
				]
			},
			{
				"featureType": "poi.sports_complex",
				"elementType": "all",
				"stylers": [
					{
						"color": "#efebe2"
					}
				]
			},
			{
				"featureType": "road",
				"elementType": "labels.text",
				"stylers": [
					on,
					{
						"lightness": "50"
					},
					{
						"saturation": "-100"
					}
				]
			},
			{
				"featureType": "road.highway",
				"elementType": "geometry.fill",
				"stylers": [
					{
						"color": "#ffffff"
					}
				]
			},
			{
				"featureType": "road.highway",
				"elementType": "geometry.stroke",
				"stylers": onlyOff
			},
			{
				"featureType": "road.arterial",
				"elementType": "geometry.fill",
				"stylers": [
					{
						"color": "#ffffff"
					}
				]
			},
			{
				"featureType": "road.arterial",
				"elementType": "geometry.stroke",
				"stylers": onlyOff
			},
			{
				"featureType": "road.local",
				"elementType": "geometry.fill",
				"stylers": [
					{
						"color": "#fbfbfb"
					}
				]
			},
			{
				"featureType": "road.local",
				"elementType": "geometry.stroke",
				"stylers": onlyOff
			},
			{
				"featureType": "transit",
				"elementType": "all",
				"stylers": onlyOff
			},
			{
				"featureType": "water",
				"elementType": "all",
				"stylers": [
					{
						"color": "#a5d7e0"
					}
				]
			}
		];
	}());

	function Map(maps, element){
		this.Maps		= maps;
		this.element	= element;
		this.map		= null;
		this.activeVenue	= null;

		this.markers	= {};

		this.moviesInteractive	= false;
		this.maxZoom			= 15;
	}

	Map.prototype.show				= function(center){
		var options	= {
			styles:				mapStyles,
			mapTypeControl:		false,
			scaleControl:		false,
			streetViewControl:	false,
			rotateControl:		false,
			fullscreenControl:	false,
			zoomControl:		true,
			zoomControlOptions:	{
				position:	this.Maps.ControlPosition.LEFT_BOTTOM
			}
		};
		if(center){
			options.center	= center;
			if(center.zoom){
				options.zoom	= center.zoom;
			}
		}

		// Prevent loading webfont
		var head			= document.getElementsByTagName('head')[0],
			insertBefore	= head.insertBefore;
		head.insertBefore	= function(newElement, referenceElement){
			if(!newElement.href || newElement.href.indexOf('https://fonts.googleapis.com/css?family=Roboto') !== 0){
				insertBefore.call(head, newElement, referenceElement);
			}
		};

		this.map	= new this.Maps.Map(this.element, options);
	};
	Map.prototype.loadVenues		= function(venues){
		var _this	= this,
			handler	= function(event){
				var venue	= this.dataVenue,
					filter	= (event.filter === undefined || event.filter);
				if(_this.activeVenue === venue && !event.manual){
					_this.deactivateVenue(venue);
					filter && setFilters({
						venue:	null
					});
					_this.moviesInteractive	= true;
				} else {
					_this.activateVenue(venue, true, !filter);
					filter && setFilters({
						venue:	this.dataVenue,
					});
					_this.moviesInteractive	= false;
				}
			};

		this.map.addListener('click', function(){
			if(_this.activeVenue && !_this.moviesInteractive){
				_this.deactivateVenue(_this.activeVenue);
				setFilters({
					venue:	null
				});
				_this.moviesInteractive	= true;
			}
		});

		for(var id in venues){ if(!venues.hasOwnProperty(id)){ continue; }
			var venue	= venues[id];
			if(!venue.coords){
				// Cannot show venue on map
				continue;
			}

			var marker	= this.buildMarker(venue.coords, handler, venue);
			this.markerForVenue(venue, marker);
		}

		this.fitMap();

		this.monitorMovies(venues);

		// Reposition once ready
		this.Maps.event.addListenerOnce(this.map, 'idle', function(){
			_this.fitMap();
		});
	};
	Map.prototype.buildMarker		= function(coords, handler, venue){
		var marker	= new this.Maps.Marker({
			map:		this.map,
			position:	coords,
		});
		marker.addListener('click', handler);
		marker.dataVenue	= venue;

		this.unhighlightMarker(marker);

		return marker;
	};
	Map.prototype.activateVenue		= function(venue, showInfo, focus){
		var showLabel	= true;
		var marker		= this.markerForVenue(venue);

		if(this.activeVenue){
			if(this.activeVenue === venue){
				if(!showInfo || marker.detailsPanel){
					// Activating already-active venue - ignore
					return;
				} else {
					// Need to show panel for active label
					showLabel	= false;
				}
			} else {
				// Clear currently-active venue
				this.deactivateVenue(this.activeVenue);
			}
		}

		if(showLabel){
			this.activeVenue	= venue;

			// Highlight marker
			this.highlightMarker(marker);

			// Show label
			marker.label	= this.buildLabel(venue);
			marker.label.open(this.map, marker);
		}

		if(showInfo){
			// Show full details
			var element	= this.getVenuePanel(venue),
				overlay	= loadTemplate(document.getElementById('template-venue-dismiss').innerHTML).firstChild;

			toggleClass(element, '__active', true);
			toggleClass(element, '__transitioning', true);
			toggleClass(overlay, '__transitioning', true);
			element.style.display	= 'block';
			window.setTimeout(function(){
				toggleClass(element, '__transitioning', false);
				toggleClass(overlay, '__transitioning', false);
			}, 0);

			marker.detailsPanel	= element;

			// Setup overlay
			var _this	= this;
			element.__private_overlay__	= overlay;
			insertBefore(overlay, element.parentNode.nextSibling);
			overlay.addEventListener('click', function(){
				_this.deactivateVenue(venue);
			});
		}

		if(focus){
			this.map.setCenter(marker.getPosition());
			this.map.setZoom(this.maxZoom);
		}
	};
	Map.prototype.deactivateVenue	= function(venue){
		if(this.activeVenue === venue){
			// Clear active venue
			this.activeVenue	= null;
		}

		var marker	= this.markerForVenue(venue);
		this.unhighlightMarker(marker);

		if(marker.label){
			marker.label.close();
			marker.label	= null;
		}

		if(marker.detailsPanel){
			var element	= marker.detailsPanel,
				overlay	= element.__private_overlay__;
			marker.detailsPanel	= null;

			toggleClass(element, '__transitioning', true);
			if(overlay){
				toggleClass(overlay, '__transitioning', true);
			}
			onTransitionEnd(element, function(){
				toggleClass(element, '__active', false);
				if(overlay){
					removeNode(overlay);
				}
			});
		}
	};
	Map.prototype.highlightMarker	= function(marker){
		marker.setIcon(this.pinIcons.on);
		marker.setZIndex(this.Maps.Marker.MAX_ZINDEX + 1);
	};
	Map.prototype.unhighlightMarker	= function(marker){
		marker.setIcon(this.pinIcons.off);
		marker.setZIndex(1);
	};
	Map.prototype.markerForVenue	= function(venue, marker){
		if(marker){
			this.markers[venue.id]	= marker;
		}
		return this.markers[venue.id];
	};
	Map.prototype.fitMap			= function(bounds){
		if(!bounds || bounds.isEmpty()){
			// No bounds - show all
			bounds = new this.Maps.LatLngBounds();

			for(var venueID in this.markers){	if(!this.markers.hasOwnProperty(venueID)){ continue; }
				bounds.extend(this.markers[venueID].getPosition());
			}
		}

		if(bounds.isEmpty()){
			// Nothing to show
			return;
		}

		this.map.fitBounds(bounds);
		if(this.map.getZoom() > this.maxZoom){
			this.map.setZoom(this.maxZoom);
		}
	};

	Map.prototype.buildLabel		= function(venue){
		var label	= new this.Maps.InfoWindow({
			title:		venue.title,
			content:	venue.title,
		});

		// Hide stock infowindow UI elements
		this.Maps.event.addListenerOnce(label, 'domready', function(){
			var container	= document.getElementsByClassName('gm-style-iw')[0].parentNode;
			container.className	+= ' map-infowindow';
		});
		return label;
	};
	Map.prototype.getVenuePanel		= function(venue){
		var element	= venue.element;

		// Load image
		if(venue.image && !venue.imageLoaded){
			var imageContainer	= loadTemplate(document.getElementById('template-venue-image').innerHTML, true).firstChild,
				image			= findOne(imageContainer, ['img']);

			image.setAttribute('alt', venue.title);
			image.setAttribute('src', venue.image);

			insertBefore(imageContainer, element.firstChild);

			// Prevent reloading image
			venue.imageLoaded	= true;
		}

		return element;
	};

	Map.prototype.monitorMovies		= function(){
		var _this	= this;

		var callbackOver	= function(){
			if(!_this.moviesInteractive){
				// Interactivity disabled
				return;
			}

			if(this.isOver){
				// Suppress multiple over's
				return;
			}

			// Mark element as over
			this.isOver	= true;

			_this.activateVenue(this.__private_venue__);
		};
		var callbackOut		= function(e){
			if(!_this.moviesInteractive){
				// Interactivity disabled
				return;
			}

			// Only handle leaving the parent element
			if(e.target !== this){
				return;
			}

			// Mark element as exited
			this.isOver	= false;

			_this.deactivateVenue(this.__private_venue__);
		};
		var callbackVenueClick	= function(e){
			e.preventDefault();
			var venue	= this.__private_venue__,
				marker	= _this.markerForVenue(venue);

			_this.Maps.event.trigger(marker, 'click', {manual: true, filter: false});
		};

		this.moviesInteractive	= true;
		for(var venueID in venues){ if(!venues.hasOwnProperty(venueID)){ continue; }
			var venue	= venues[venueID];
			for(var i = 0; i < venue.movies.length; i++){
				var element	= venue.movies[i].element;
				element.__private_venue__	= venue;
				element.addEventListener('mouseover', callbackOver, true);
				element.addEventListener('mouseleave', callbackOut, true);

				// Show venue on venue name click
				var venueElement	= findOne(element, ['.movie__detail--venue', 'a']);
				venueElement.__private_venue__	= venue;
				venueElement.addEventListener('click', callbackVenueClick, true);
			}
		}
	};

	Map.prototype.filterVenues		= function(venuesSubset, fitMap){
		fitMap	= fitMap || fitMap === undefined;
		var bounds = new this.Maps.LatLngBounds();
		for(var venueID in this.markers){ if(!this.markers.hasOwnProperty(venueID)){ continue; }
			var isVisible	= !!venuesSubset[venueID];
			var marker	= this.markers[venueID];

			marker.setOpacity(isVisible ? 1 : 0.333);
			marker.setClickable(isVisible);

			if(isVisible && fitMap){
				bounds.extend(marker.getPosition());
			}
		}

		if(fitMap){
			this.fitMap(bounds);
		}
	};

	// Setup map on load
	var map;
	window.googleMapsReady	= function(){
		var element	= document.getElementById('map'),
			Maps	= window.google.maps;

		map	= new Map(Maps, element, moviesData);
		map.show({lat: 40.771133, lng: -73.974187, zoom: 11});

		// Set icons
		var pinImages	= document.getElementById('pin-images').innerHTML.split('|'),
			scale		= Math.min(1, (element.clientWidth+200) / 900);	// Scale pin width according to map width
		map.pinIcons	= {
			off:	{
				anchor:		new Maps.Point(18 * scale, 48 * scale),
				url:	'data:image/svg+xml,'+encodeURIComponent(pinImages[0]),
				scaledSize:	new Maps.Size(36 * scale, 48 * scale)
			},
			on:		{
				anchor:		new Maps.Point(26 * scale, 60 * scale),
				url:	'data:image/svg+xml,'+encodeURIComponent(pinImages[1]),
				scaledSize:	new Maps.Size(52 * scale, 70 * scale)
			}
		};
		map.loadVenues(venues);

		// Update on filter changes
		addFilterListener(function(movies){
			// Get remaining venues
			var venues	= {};
			for(var id in movies){ if(!movies.hasOwnProperty(id)){ continue; }
				var venue	= movies[id].venue;
				if(venue){
					venues[venue.id]	= venue;
				}
			}

			// Update map
			map.filterVenues(venues);
		});
	};
	// Initialise if already loaded
	window.google && window.googleMapsReady();
}());

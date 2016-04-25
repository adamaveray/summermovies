/*! simpleWeather v3.1.0 - http://simpleweatherjs.com */
window.Weather	= (function(){
	'use strict';

	function getAltTemp(unit, temp){
		if(unit === 'c') {
			// C -> F
			temp	= (9.0/5.0) * temp + 32.0;
		} else {
			// F -> C
			temp	= (5.0/9.0) * (temp - 32.0);
		}
		return Math.round(temp);
	}

	function getImage(code, image){
		return 'https://s.yimg.com/'
			+ (code === '3200'
				// 404 image
				? 'os/mit/media/m/weather/images/icons/l/44d-100567.png'
				// Valid
				: 'zz/combo?a/i/us/nws/weather/gr/'+code+image+'.png'
			);
	}

	function doRequest(method, url, callback){
		var request = new XMLHttpRequest();
		request.open(method, url, true);
		request.onreadystatechange	= function(){
			if(this.readyState !== 4){
				return;
			}

			callback(this);
		};
		request.send();
		request	= null;
	}

	function processResponse(data, unit){
		if(!data || !data.query || !data.query.results || !data.query.results.channel || !data.query.results.channel.description === 'Yahoo! Weather Error'){
			// Error with response
			return null;
		}

		var result		= data.query.results.channel,
			compass		= ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW', 'N'];

		var weather = {
			unit:			unit,
			title:			result.item.title,
			temp:			result.item.condition.temp,
			code:			result.item.condition.code,
			todayCode:		result.item.forecast[0].code,
			currently:		result.item.condition.text,
			heatIndex:		result.item.condition.temp,
			high:			result.item.forecast[0].high,
			low:			result.item.forecast[0].low,
			text:			result.item.forecast[0].text,
			humidity:		result.atmosphere.humidity,
			pressure:		result.atmosphere.pressure,
			rising:			result.atmosphere.rising,
			visibility:		result.atmosphere.visibility,
			sunrise:		result.astronomy.sunrise,
			sunset:			result.astronomy.sunset,
			description:	result.item.description,
			city:			result.location.city,
			country:		result.location.country,
			region:			result.location.region,
			updated:		result.item.pubDate,
			link:			result.item.link,
			image:			getImage(result.item.condition.code, 'd'),
			thumbnail:		getImage(result.item.condition.code, 'ds'),
			forecast:		[],
			units:	{
				temp:		result.units.temperature,
				distance:	result.units.distance,
				pressure:	result.units.pressure,
				speed:		result.units.speed
			},
			wind:	{
				chill:		result.wind.chill,
				direction:	compass[Math.round(result.wind.direction / 22.5)],
				speed:		result.wind.speed
			}
		};

		// Calculate alternate unit temperatures
		weather.alt = {
			unit:	(unit === 'c') ? 'f' : 'c',
			temp:	getAltTemp(unit, result.item.condition.temp),
			high:	getAltTemp(unit, result.item.forecast[0].high),
			low:	getAltTemp(unit, result.item.forecast[0].low)
		};

		// Calculate special heat index
		if(result.item.condition.temp < 80 && result.atmosphere.humidity < 40){
			weather.heatindex	=
				-42.379 + 2.04901523
				* result.item.condition.temp + 10.14333127
				* result.atmosphere.humidity - 0.22475541
				* result.item.condition.temp
				* result.atmosphere.humidity - 6.83783
				* Math.pow(10, -3)
				* Math.pow(result.item.condition.temp, 2) - 5.481717
				* Math.pow(10, -2)
				* Math.pow(result.atmosphere.humidity, 2) + 1.22874
				* Math.pow(10, -3)
				* Math.pow(result.item.condition.temp, 2)
				* result.atmosphere.humidity + 8.5282
				* Math.pow(10, -4)
				* result.item.condition.temp
				* Math.pow(result.atmosphere.humidity, 2) - 1.99
				* Math.pow(10, -6)
				* Math.pow(result.item.condition.temp, 2)
				* Math.pow(result.atmosphere.humidity, 2);
		}

		// Load additional data for forecasted days
		for(var i = 0; i < result.item.forecast.length; i++){
			var forecast	= result.item.forecast[i];

			forecast.alt = {
				high:	getAltTemp(unit, result.item.forecast[i].high),
				low:	getAltTemp(unit, result.item.forecast[i].low)
			};
			forecast.image		= getImage(forecast.code, 'd');
			forecast.thumbnail	= getImage(forecast.code, 'ds');

			weather.forecast.push(forecast);
		}

		return weather;
	}

	return function(location, unit, callback){
		var condition;
		switch(typeof location){
			case 'number':
				// WOEID
				condition	= 'woeid='+location;
				break;
			case 'string':
				// Location name - leave as is
				break;
			case 'object':
				// Check for lat-lng
				if(location.lat != null && location.lng != null){
					location	= '('+location.lat+','+location.lng+')';
					break;
				}
				// Fallthrough
			default:
				throw new Error('Invalid location format');
		}

		if(!condition){
			// Text-based location
			condition	= 'woeid in (select woeid from geo.places(1) where text="'+location+'")';
		}

		// Build request
		var now		= new Date(),
			query	= 'select * from weather.forecast where '+condition+' and u="'+unit+'"',
			url		= 'https://query.yahooapis.com/v1/public/yql?format=json&rnd='+encodeURIComponent(now.getFullYear()+now.getMonth()+now.getDay()+now.getHours())+'&diagnostics=true&q='+encodeURIComponent(query);

		doRequest('GET', url, function(response){
			if(response.status < 200 || response.status >= 400){
				// Request failed
				callback(response);
				return;
			}

			try {
				var responseData	= JSON.parse(response.responseText);
			} catch(e){
				callback();
				return;
			}

			var weather	= processResponse(responseData, unit);
			callback(weather);
		});
	};
}());
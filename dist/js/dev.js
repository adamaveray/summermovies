(function($){
	var $head	= $('head'),
		$body	= $('body');

	$head.append('<link rel="stylesheet" href="/css/index.php/dev.css" />');

	// Display notice banner
	if(!window.location.hostname.match(/^dev\d+\.walkercorp\.com\.au$/)){
		$body.append('<div class="dev__indicator">Development Site</div>');
	}

	var notices		= {},
		hasNotices	= false;
	function addNotice(type, message, $element){
		hasNotices	= true;
		if(!notices[type]){
			notices[type]	= {
				message:	message,
				$elements:	$()
			};
		}

		notices[type].$elements	= notices[type].$elements.add($element);

		$element.addClass('dev__invalid');
	}

	// Validate links
	var $links	= $('a');
	$links.each(function(){
		var $link	= $(this),
			url		= $link.attr('href');
		if(url === undefined || url === '' || url === '#' || url.indexOf('javascript:') === 0){
			addNotice('invalid-link', 'Invalid link `href`', $link);
		}
	});

	// Validate images
	var $images	= $('img');
	$images.each(function(){
		var $image	= $(this),
			alt		= $image.attr('alt');

		if(!this.complete || (typeof this.naturalWidth != "undefined" && this.naturalWidth == 0) || $image.attr('src').match(/\?placeholder=/)){
			addNotice('invalid-image[src]', 'Invalid image `src`', $image);
		}

		if(alt === undefined || alt === ''){
			addNotice('invalid-image[alt]', 'Invalid image `alt`', $image);
		}
	});

	if(!hasNotices){
		return;
	}

	// Display list of notices
	var $notices		= $(document.createElement('div')).addClass('dev__notices'),
		$noticesList	= $(document.createElement('ul')).addClass('dev__notices-list');

	for(var type in notices){
		if(!notices.hasOwnProperty(type)){ continue; }
		var notice	= notices[type];

		var $notice	= $(document.createElement('li')).addClass('dev__notice');

		var content	= notice.message.replace(/`(.*?)`/g, '<code>$1</code>');
		$notice.html(content)
			   .data('notice', notice)
			   .append(' <span class="dev__notice__count">'+notice.$elements.length+'</span>');

		$notice.appendTo($noticesList);
	}

	$noticesList.children().hover(function(){
		var $notice	= $(this),
			notice	= $notice.data('notice');
		notice.$elements.addClass('dev__invalid--active');
	}, function(){
		var $notice	= $(this),
			notice	= $notice.data('notice');
		notice.$elements.removeClass('dev__invalid--active');
	});

	$noticesList.appendTo($notices);
	$notices.appendTo($body);
}(jQuery));

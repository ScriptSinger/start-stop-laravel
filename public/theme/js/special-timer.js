(function($){
	const specTimer = {
		init:function(options, el) {
            var base = this;
			
			base.options = $.extend({}, $.fn.uniTimer.options, options);
			base.days = 24*60*60, base.hours = 60*60, base.minutes = 60;
			
			var date_arr = base.options.date.split('-'),
				year = parseFloat(date_arr[0]), 
				month = parseFloat(date_arr[1])-1, 
				day = parseFloat(date_arr[2]);
			
			base.$date = (new Date(year, month, day)).getTime();
			base.$elem = $(el);
			
			if(base.$date > (new Date()).getTime())	{
				html = '<div class="uni-timer">';
			
				for(i in base.options.texts){
					html += '<div class="uni-timer__group g-'+i+'">';
					html += '<div class="uni-timer__digit"></div>';
					html += '<div class="uni-timer__text">'+base.options.texts[i]+'</div>';
					html += '</div>';
				}
			
				html += '</div>';
			
				base.$elem.append(html);
				base.digits = base.$elem.find('.uni-timer__digit');
				base.count();
			}
        },
		count:function() {
			var base = this, left, d, h, m, s;
			
			left = Math.floor((base.$date - (new Date()).getTime())/1000);
			
			left = left > 0 ? left : 0;
			
			d = Math.floor(left / base.days);
			left -= d*base.days;
			h = Math.floor(left / base.hours);
			left -= h*base.hours;
			m = Math.floor(left / base.minutes);
			left -= m*base.minutes;
			s = left;
			
			base.switchDigit(base.digits.eq(0), d);
			base.switchDigit(base.digits.eq(1), h);
			base.switchDigit(base.digits.eq(2), m);
			base.switchDigit(base.digits.eq(3), s);
			
			setTimeout(() => { 
				base.count();
			}, 1000);
		},
		switchDigit:function(position, number) {
			if(position.data('digit') != number){
				position.data('digit', number).text(number);
			}
		},
	}
	
	$.fn.uniTimer = function(options) {		
		return this.each(function() {
            if ($(this).data("uni-timer-init") === true) {
                return false;
            }
			
            $(this).data("uni-timer-init", true);
			
            var timer = Object.create(specTimer);
            timer.init(options, this);
        });
	};
	
	$.fn.uniTimer.options = {
		date	:'0000-00-00',
		texts	:['Дней','Часов','Минут','Секунд']
	};
})(jQuery);
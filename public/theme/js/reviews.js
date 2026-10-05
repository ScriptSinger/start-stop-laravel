$(function() {
	$(document).on('click', '.review-list__icon', function() {
		let $this = $(this), id = $this.data('id'), vote = $this.data('vote'), total = parseInt($this.next().text() != '' ? $this.next().text() : 0);
						
		$.ajax({
			url: 'index.php?route=product/uni_reviews/setReviewsVotes',
			type: 'post',
			dataType: 'json',
			data: 'id='+id+'&vote='+vote,
			success: function(json) {
				if (json['error']) {
					uniFlyAlert('warning', json['error']);
				}
				if (json['success']) {
					uniFlyAlert('success', json['success']);
					$this.next().text(total+1);
				}
			}
		});
	});
	
	$('.review-form__add-photo').on('click', function() {
		const $this = $(this),
			  item = '.review-form input[type="file"]',
			  items = $(item).length,
			  max = 5,
			  input = '<input type="file" name="images[]" accept=".jpg, .jpeg, .png" />';
		
		if(items < max) {
			$('.review-form__images-inputs').append(input);
			$(item).last().click();
			
			$(item).last().on('change', (event) => {
				$this.before('<div class="review-form__images-preview-item"><img src="'+URL.createObjectURL(event.target.files[0])+'" class="review-form__images-preview-img"><i class="review-form__images-preview-del far fa-trash-can"></i></div>');
			});
		} else {
			uniFlyAlert('danger', uniJsVars.reviews.error_upload_limit);
		}
		
		
	});
	
	$(document).on('click', '.review-form__images-preview-del', function() {
		const indx = $(this).parent().index();
		
		$(this).parent().remove();
		$('.review-form input[type="file"]:eq('+indx+')').remove()
	});
	
	$('#button-review').on('click', () => {
		
		const form = $('#form-review'), formData = new FormData(form[0]), product_id = $('.product-page__cart input[name="product_id"]').val();
		
		$.ajax({
			url: 'index.php?route=product/product/write&product_id='+product_id,
			type: 'post',
			dataType: 'json',
			data: formData,
			processData: false,
            contentType: false,
			cache: false,
			beforeSend: function() {
				$('#button-review').button('loading');
			},
			complete: function() {
				$('#button-review').button('reset');
			},
			success: function(json) {
				if (json['error']) {
					uniFlyAlert('danger', json['error']);
				}

				if (json['success']) {
					$('#form-review').before('<div class="alert alert-success"><i class="fa fa-check-circle"></i> '+json['success']+' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
					$('#form-review input, #form-review textarea').val('');
					$('.review_star input').attr('checked', false).prop('checked', false);
					$('#form-review').slideToggle();
					
					uniScrollTo('.alert.alert-success');
				}
			}
		});
	});
	
	$('.review-form__input').on('mouseenter', function () {
		$('.review-form__star:lt('+$(this).val()+')').removeClass('far').addClass('fa');
		
		$(this).on('mouseleave', () => {
			$('.review-form__star').removeClass('fa').addClass('far');
		
			$('.review-form__input').each(function() {
				if($(this).prop('checked')) {
					$('.review-form__star:lt('+$(this).val()+')').removeClass('far').addClass('fa');
				}
			});
		});
	});

	$('.review-form__input').on('click', function(){
		$('.review-form__star:not(:lt('+$(this).val()+'))').removeClass('fa').addClass('far');
	});
	
	const popupBlock = '.review-list__popup',
		  titleBlock = '.review-list__popup-title',
		  fullBlock = '.review-list__popup-full',
		  thumbBlock = '.review-list__popup-thumbs',
		  revImgBlock = '.review-list__images',
		  allRevImgBlock = '.review-list__all-review-images',
		  navText = ['<i class="fa fa-chevron-left"></i>', '<i class="fa fa-chevron-right"></i>'];
				
	const createPopup = function(data) {
		$(popupBlock).addClass('fixed');
			$('body').addClass('scroll-disabled2');
				
			$('.review-list__popup-title').append(data.title)

			let popup = '', thumb = '';
	
			images = data.images;

			for (i in images) {
				popup += '<img src="'+images[i].popup+'" />';
				thumb += '<img src="'+images[i].thumb+'" class="img-responsive" width="80" height="80" />';
			};
				
			$(fullBlock).html(popup)
			$(thumbBlock).html(thumb)

			$(fullBlock).addClass('owl-carousel').owlCarousel({
				items: 1,
				dots: false,
				nav: true,
				navText: navText,
				onTranslated: function (event) {
					changeSelected(event.item.index);
				}
			});
					
			$(thumbBlock).addClass('owl-carousel').owlCarousel({
				responsive: {0:{items:5}, 450:{items:6}, 650:{items:8}, 1050:{items:15}, 1600:{items:20}},
				dots: false,
				nav: true,
				navText: navText,
			});
	
			$('body').on('click mouseover', thumbBlock+' .owl-item', function() {
				$(fullBlock).trigger('to.owl.carousel', [parseInt($(this).index()), 250]);
	
				changeSelected($(this).index());
			});
					
			const changeSelected = function(indx) {
				$(thumbBlock+' .owl-item img').removeClass('selected');
				$(thumbBlock+' .owl-item:eq('+parseInt(indx)+') img').addClass('selected');
				$(thumbBlock).trigger('to.owl.carousel', [parseInt(indx), 250]);
			}
				
			$(fullBlock).trigger('to.owl.carousel', [data.id, 0]);
			$(thumbBlock).trigger('to.owl.carousel', [data.id, 0]);
			changeSelected(data.id)
	};
				
	$(document).on('click', allRevImgBlock+' .owl-item', function() {
		createPopup({images: JSON.parse(uniAllReviewImages), id: $(this).index(), title: uniJsVars.reviews.text_all_review_images});
	});
				
	$(document).on('click', revImgBlock+' img', function() {
		let images = [];
					
		$(this).parent().find('img').each(function() {
			images.push({popup: $(this).data('popup'), thumb: $(this).data('thumb')});
		});
					
		const pBlock = $(this).closest('.review-list__info-text').prev(), name = pBlock.find('.review-list__name').text(), rating = pBlock.find('.review-list__rating').html();
					
		createPopup({images: images, id: $(this).index(), title: '<span class="rating">'+rating+'</span> '+uniJsVars.reviews.text_from+' '+name});
	});
			
	$(document).on('click', '.review-list__popup-close', function() {
		$(popupBlock).removeClass('fixed');
		$(fullBlock).trigger('destroy.owl.carousel').html('');
		$(thumbBlock).trigger('destroy.owl.carousel').html('');
		$(titleBlock+', '+thumbBlock).html('');
		$('body').removeClass('scroll-disabled2');
	});
});
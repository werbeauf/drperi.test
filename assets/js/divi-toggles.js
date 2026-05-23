// assets/js/divi-toggles.js

(function($) {

    document.addEventListener('click', function(e) {
        
        var target = e.target;
        var toggleEl = target.closest('.et_pb_toggle');

        if (!toggleEl) return;

        if (target.closest('.et_pb_toggle_content')) {
            e.stopPropagation(); 
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        var $toggle    = $(toggleEl);
        var $content   = $toggle.find('.et_pb_toggle_content');
        var $accordion = $toggle.closest('.et_pb_accordion');

        $content.css({ 'transition': 'none', 'animation': 'none' });
        
        if ($toggle.hasClass('et_pb_toggle_close')) {
            
            $accordion.addClass('et_pb_accordion_toggling');
            $toggle.removeClass('et_pb_toggle_close').addClass('et_pb_toggle_open');
            
            $content.stop(true, true).slideDown(200, function() {
                $accordion.removeClass('et_pb_accordion_toggling');
                $(this).css('height', 'auto');
            });

        } else {
            
            $accordion.addClass('et_pb_accordion_toggling');
            $toggle.removeClass('et_pb_toggle_open').addClass('et_pb_toggle_close');
            
            $content.stop(true, true).slideUp(200, function() {
                $accordion.removeClass('et_pb_accordion_toggling');
            });
        }

    }, true); 

    $(document).ready(function() {
        
        var $allToggles = $('.et_pb_toggle');
        
        $allToggles.removeClass('et_pb_toggle_open').addClass('et_pb_toggle_close');
        $allToggles.find('.et_pb_toggle_content').css({
            'transition': 'none', 
            'animation': 'none',
            'display': 'none'
        });
        
        $('head').append('<style>.et_pb_toggle { cursor: pointer; } .et_pb_toggle_content { cursor: default; }</style>');

        $('a[href^="#to-term:"]').click(function(e){
            let href = $(this).attr('href');
            let tag_id = href.replace('#to-term:','');
            let selector = '[data-tag-id="'+tag_id+'"]';
            
            if($(selector).length) {
                $(selector).click();
                
                $('html, body').animate({
                    scrollTop: $(selector).offset().top - 100 
                }, 1000);
            }
        });
    });

})(jQuery);
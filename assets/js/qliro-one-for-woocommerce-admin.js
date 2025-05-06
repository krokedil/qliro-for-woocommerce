jQuery(function ($) {
    const qocAdmin = {
        /**
         * Toggles the Qliro settings sections.
         */
        toggleSettingsSection: function () {
            $('.qliro-settings-header').on('click', function () {
                qocAdmin.toggleSectionContent($(this));
            });
        },

        /**
         * Moves the submit button to a new placement.
         */
        moveSubmitButton: function () {
            let $submitBtn = $('.krokedil_settings__gateway_page p.submit');
            let $newPlacement = $('.krokedil_settings__sidebar');

            if ($submitBtn.length && $newPlacement.length) {
                $newPlacement.append($submitBtn);
            }
        },

        /**
         * Smooth scrolls to anchor links.
         */
        smoothScroll: function () {
            $(document).on('click', 'a[href^="#"]', function (event) {
            event.preventDefault();
            let section = $('#qliro-header-' + (this).attr('href').replace('#', ''));

            if(!section.length) {
                return;
            }

            if (!section.next('.form-table').hasClass('active')) {
                qocAdmin.toggleSectionContent(section);
            }
            

            $('html, body').animate({
                scrollTop: $($.attr(this, 'href')).offset().top
            }, 500);
            });
        },
    
        /**
         * Toggles the content of the settings section.
         */
        toggleSectionContent: function ($section) {
            $section.find('.qliro-settings-toggle')
                    .toggleClass('dashicons-arrow-up-alt2')
                    .toggleClass('dashicons-arrow-down-alt2');

            let $sectionContent = $section.closest('.qliro-settings-section-wrapper').find('.form-table');
            $sectionContent.toggleClass('active');
        },

        /**
         * Initializes the events for this file.
         */
        init: function () {
            $(document)
                .ready(this.toggleSettingsSection)
                .ready(this.moveSubmitButton)
                .ready(this.smoothScroll);
        }
    };

    qocAdmin.init();
});

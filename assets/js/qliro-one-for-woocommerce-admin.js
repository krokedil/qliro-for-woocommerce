jQuery(document).ready(function($) {
    $('.qliro-settings-header').on('click', function() {
        $(this).find('.qliro-settings-toggle').toggleClass('dashicons-arrow-up-alt2').toggleClass('dashicons-arrow-down-alt2');

        let $sectionContent = $(this).closest('.qliro-settings-section-wrapper').find('.form-table');
        $sectionContent.toggle();
    });

    let $submitBtn = $('.krokedil_settings__gateway_page p.submit');
    let $newPlacement = $('.krokedil_settings__sidebar');

    if ($submitBtn.length && $newPlacement.length) {
        $newPlacement.append($submitBtn);
    }


});

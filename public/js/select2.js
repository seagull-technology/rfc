  // Always re-init Select2 for selects in tab-pane when tab is shown (fix for offcanvas > tab > select)
  $('.offcanvas .nav-link[data-bs-toggle="pill"], .offcanvas .nav-link[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
    var $tabPane = $($(e.target).attr('data-bs-target'));
    $tabPane.find('.select2-basic-multiple, .select2-basic-single').each(function() {
      if ($(this).data('select2')) {
        $(this).select2('destroy');
      }
      $(this).select2({
        width: '100%',
        dropdownParent: $(this).closest('.offcanvas')
      });
    });
  });
  // Defensive: re-init Select2 for all .select2-basic-multiple and .select2-basic-single in any tab-pane inside offcanvas when tab is shown
  $('.offcanvas .tab-pane').each(function() {
    var $tabPane = $(this);
    $tabPane.on('shown.bs.tab', function() {
      $tabPane.find('.select2-basic-multiple, .select2-basic-single').each(function() {
        if (!$(this).data('select2')) {
          $(this).select2({
            width: '100%',
            dropdownParent: $(this).closest('.offcanvas')
          });
        }
      });
    });
  });
(function(){
  "use strict";
  //single
  $(".select2-basic-single").select2({
    width: '100%'
  });

  $('.select2-container').addClass('wide');

  // tags
  $(".select2-basic-single-tag").select2({
      tags: true
  });

  //multiple
  $(".select2-basic-multiple").select2({
    width: '100%'
  });


  //disable
  var $disabledResults = $(".select2-disabled ");
  $disabledResults.select2();

  //placeholder
  $('.select2-placeholder').select2({
    placeholder: "Select a State",
    allowClear: true
  });
  //maximum input number

  $(".select2-multiple-limit").select2({
    maximumSelectionLength: 3
  });

  //theme
  $(".select2-theme-single").select2({
    theme: "classic"
  });


  $(".select2-option-creation").select2({
    tags: true
  });

  $(".select2-automatic-tokenizer").select2({
    tags: true,
    tokenSeparators: [',', ' ']
  })
  // ['#ott-select', '#parent-genre', '#related-product'].forEach(selector => {
  //   jQuery(selector).select2({ width: '100%', dropdownParent: jQuery('#season-offcanvas') });
  // });  
  // jQuery('#ott-select').select2({ width: '100%', dropdownParent: jQuery('#season-offcanvas') });
  // jQuery('#parent-genre').select2({ width: '100%', dropdownParent: jQuery('#season-offcanvas') });
  // jQuery('#pmp_levels').select2({ width: '100%', dropdownParent: jQuery('#season-offcanvas') });
  // jQuery('#choice_tags').select2({ width: '100%', dropdownParent: jQuery('#season-offcanvas') });
  // jQuery('#select-product').select2({ width: '100%', dropdownParent: jQuery('#season-offcanvas') });
  // jQuery('#categoryVideos_tab1').select2({ width: '100%', dropdownParent: jQuery('#season-offcanvas-edit') });
  // jQuery('#related-product').select2({ width: '100%', dropdownParent: jQuery('#season-offcanvas') });
  // parent-genre
  // Fix: Ensure select2 dropdowns display inside Bootstrap offcanvas
  // Also re-initialize select2-basic-multiple outside offcanvas (fallback for pages like movie-list)
  $('.select2-basic-multiple').each(function() {
    if (!$(this).data('select2')) {
      $(this).select2({ width: '100%' });
    }
  });

  $('.offcanvas .select2-basic-multiple, .offcanvas .select2-basic-single').each(function() {
    $(this).select2({
      width: '100%',
      dropdownParent: $(this).closest('.offcanvas')
    });
  });

  // Re-initialize Select2 when tab is shown inside offcanvas (for hidden tabs)
  $('.offcanvas .nav-link[data-bs-toggle="pill"], .offcanvas .nav-link[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
    var $tabPane = $($(e.target).attr('data-bs-target'));
    $tabPane.find('.select2-basic-multiple, .select2-basic-single').each(function() {
      if (!$(this).data('select2')) {
        $(this).select2({
          width: '100%',
          dropdownParent: $(this).closest('.offcanvas')
        });
      }
    });
  });
  
})();

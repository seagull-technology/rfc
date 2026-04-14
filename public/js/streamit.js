/*
 * Version: 5.4.0
 * Template:Streamit - Responsive Bootstrap 5.3.8 Admin Dashboard Template
 * Author: iqonic.design
 * Design and Developed by: iqonic.design
 * NOTE: This file contains the script for initialize & listener Template.
 */
/*----------------------------------------------
Index Of Script
------------------------------------------------
------- Plugin Init --------
:: Tooltip
:: Popover
:: Progress Bar
:: CopyToClipboard
:: Vanila Datepicker
:: SliderTab
:: Data Tables
:: Active Class for Pricing Table
------ Functions --------
:: Loader Init
:: Resize Plugins
:: Back To Top
------- Listners ---------
:: DOMContentLoaded
:: Window Resize
:: FullScreen
:: Font size change script
:: header toggle
:: Pro Sidebar Left Active Border
:: Reset Settings
:: Copy Json
:: Logo Change Functionality Preview purpose only based on user selected file
------------------------------------------------
Index Of Script
----------------------------------------------*/

(function () {
  "use strict";
  
  /*------------LoaderInit----------------*/
  const loaderInit = () => {
    const loader = document.querySelector(".loader");
    if (loader !== null) {
      loader.classList.add("animate__animated", "animate__fadeOut");
      setTimeout(() => {
        loader.classList.add("d-none");
      }, 1500);
    }
  };
  /*----------Sticky-Nav-----------*/
  window.addEventListener("scroll", function () {
    let yOffset = document.documentElement.scrollTop;
    let navbar = document.querySelector(".navs-sticky");
    if (navbar !== null) {
      if (yOffset >= 100) {
        navbar.classList.add("menu-sticky");
      } else {
        navbar.classList.remove("menu-sticky");
      }
    }
  });
  /*------------Popover--------------*/
  const popoverTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="popover"]')
  );
  if (typeof bootstrap !== typeof undefined) {
    popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl);
    });
  }
  /*-------------Tooltip--------------------*/
  if (typeof bootstrap !== typeof undefined) {
    const tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const sidebarTooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-sidebar-toggle="tooltip"]')
    );
    sidebarTooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  }
  /*-------------Progress Bar------------------*/
  const progressBarInit = (elem) => {
    const currentValue = elem.getAttribute("aria-valuenow");
    elem.style.width = "0%";
    elem.style.transition = "width 2s";
    if (typeof Waypoint !== typeof undefined) {
      new Waypoint({
        element: elem,
        handler: function () {
          setTimeout(() => {
            elem.style.width = currentValue + "%";
          }, 100);
        },
        offset: "bottom-in-view",
      });
    }
  };
  const customProgressBar = document.querySelectorAll(
    '[data-toggle="progress-bar"]'
  );
  Array.from(customProgressBar, (elem) => {
    progressBarInit(elem);
  });
  /*------------Copy To Clipboard---------------*/
  const copy = document.querySelectorAll('[data-toggle="copy"]');
  if (typeof copy !== typeof undefined) {
    Array.from(copy, (elem) => {
      elem.addEventListener("click", (e) => {
        const target = elem.getAttribute("data-copy-target");
        let value = elem.getAttribute("data-copy-value");
        const container = document.querySelector(target);
        if (container !== undefined && container !== null) {
          if (container.value !== undefined && container.value !== null) {
            value = container.value;
          } else {
            value = container.innerHTML;
          }
        }
        if (value !== null) {
          const elem = document.createElement("textarea");
          document.querySelector("body").appendChild(elem);
          elem.value = value;
          elem.select();
          document.execCommand("copy");
          elem.remove();
        }
        elem.setAttribute("data-bs-original-title", "Copied!");
        let btn_tooltip = bootstrap.Tooltip.getInstance(elem);
        btn_tooltip.show();
        // reset the tooltip title
        elem.setAttribute("data-bs-original-title", "Copy");
        setTimeout(() => {
          btn_tooltip.hide();
        }, 500);
      });
    });
  }
  /*------------Minus-plus--------------*/
  const plusBtns = document.querySelectorAll(".iq-quantity-plus");
  const minusBtns = document.querySelectorAll(".iq-quantity-minus");
  const updateQtyBtn = (elem, value) => {
    const oldValue = elem
      .closest('[data-qty="btn"]')
      .querySelector('[data-qty="input"]').value;
    const newValue = Number(oldValue) + Number(value);
    if (newValue >= 1) {
      elem
        .closest('[data-qty="btn"]')
        .querySelector('[data-qty="input"]').value = newValue;
    }
  };
  Array.from(plusBtns, (elem) => {
    elem.addEventListener("click", (e) => {
      updateQtyBtn(elem, 1);
    });
  });
  Array.from(minusBtns, (elem) => {
    elem.addEventListener("click", (e) => {
      updateQtyBtn(elem, -1);
    });
  });
  /*------------Flatpickr--------------*/
  const date_flatpickr = document.querySelectorAll(".date_flatpicker");
  Array.from(date_flatpickr, (elem) => {
    if (typeof flatpickr !== typeof undefined) {
      flatpickr(elem, {
        minDate: "today",
        dateFormat: "Y-m-d",
      });
    }
  });
  /*----------Range Flatpickr--------------*/
  const range_flatpicker = document.querySelectorAll(".range_flatpicker");
  Array.from(range_flatpicker, (elem) => {
    if (typeof flatpickr !== typeof undefined) {
      flatpickr(elem, {
        mode: "range",
        minDate: "today",
        dateFormat: "Y-m-d",
      });
    }
  });
  /*------------Wrap Flatpickr---------------*/
  const wrap_flatpicker = document.querySelectorAll(".wrap_flatpicker");
  Array.from(wrap_flatpicker, (elem) => {
    if (typeof flatpickr !== typeof undefined) {
      flatpickr(elem, {
        wrap: true,
        minDate: "today",
        dateFormat: "Y-m-d",
      });
    }
  });
  /*-------------Time Flatpickr---------------*/
  const time_flatpickr = document.querySelectorAll(".time_flatpicker");
  Array.from(time_flatpickr, (elem) => {
    if (typeof flatpickr !== typeof undefined) {
      flatpickr(elem, {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
      });
    }
  });
  /*-------------Inline Flatpickr-----------------*/
  const inline_flatpickr = document.querySelectorAll(".inline_flatpickr");
  Array.from(inline_flatpickr, (elem) => {
    if (typeof flatpickr !== typeof undefined) {
      flatpickr(elem, {
        inline: true,
        minDate: "today",
        dateFormat: "Y-m-d",
      });
    }
  });

  /*-------------CounterUp 2--------------*/
  if (window.counterUp !== undefined) {
    const counterUp = window.counterUp["default"];
    const counterUp2 = document.querySelectorAll(".counter");
    Array.from(counterUp2, (el) => {
      if (typeof Waypoint !== typeof undefined) {
        const waypoint = new Waypoint({
          element: el,
          handler: function () {
            counterUp(el, {
              duration: 1000,
              delay: 10,
            });
            this.destroy();
          },
          offset: "bottom-in-view",
        });
      }
    });
  }

  /*----------------SliderTab------------------*/
  Array.from(
    document.querySelectorAll('[data-toggle="slider-tab"]'),
    (elem) => {
      if (typeof SliderTab !== typeof undefined) {
        window.SliderTab.init(elem);
      }
    }
  );
  let Scrollbar;
  if (typeof Scrollbar !== typeof null) {
    if (document.querySelectorAll(".data-scrollbar").length) {
      Scrollbar = window.Scrollbar;
      Scrollbar.init(document.querySelector(".data-scrollbar"), {
        continuousScrolling: false,
      });
    }
  }
  /*-------------Data tables---------------*/
  if ($.fn.DataTable) {
    const canInitializeDataTable = function ($table) {
      return $table.find("tbody td[colspan], tbody th[colspan], tbody td[rowspan], tbody th[rowspan]").length === 0;
    };

    const initializeDataTables = function (selector, options) {
      const instances = [];

      $(selector).each(function () {
        const $table = $(this);

        if (!canInitializeDataTable($table)) {
          $table.attr("data-datatable-skipped", "true");
          return;
        }

        instances.push($table.DataTable(options));
      });

      return instances;
    };

    // Bootstrap DataTable
    if ($('[data-toggle="data-table"]').length) {
      initializeDataTables('[data-toggle="data-table"]', {
        autoWidth: false,
        dom: '<"row align-items-center"<"col-md-6" l><"col-md-6" f>><"table-responsive my-3" rt><"row align-items-center" <"col-md-6" i><"col-md-6" p>><"clear">',
      });
    }

    $(document).ready(function () {
      if ($('[data-toggle="data-table1"]').length) {
        const tableInstances = initializeDataTables('[data-toggle="data-table1"]', {
          "autoWidth": false,
          "dom": '<"row align-items-center gy-2"<f>><"table-responsive my-3" rt><"row align-items-center"<"col-md-6 sum"i><"col-md-6"p>><"clear">',
          "pagingType": "full_numbers",
          "language": {
            "paginate": {
              "first": "«",
              "last": "»",
              "next": "›",
              "previous": "‹"
            }
          },
          buttons: [],
        });

        tableInstances.forEach(function (table, index) {
          const $container = $(table.table().container());
          const searchId = `customSearch-${index}`;
          const filterId = `seasonTable_filter_${index}`;

          $container.find('.dataTables_length').hide();
          $container.find('.dataTables_filter label').contents().filter(function () {
            return this.nodeType === 3;
          }).remove();

          var searchWrapper = $(
            `<div class="row align-items-center gy-2">
              <div id="${filterId}">
                <div class="d-flex gap-2">
                  <input type="text" id="${searchId}" placeholder="اكتب هنا ...." class="form-control ms-0 custom-search-table w-100">
                  <button type="button" class="btn btn-danger">بحث</button>
                </div>
              </div>
            </div>`
          );

          $container.find('.dataTables_filter').empty().append(searchWrapper);
          $('.select2-basic-multiple').select2({width: '100%'});

          // Bind search input to DataTables search
          $container.find(`#${searchId}`).on('keyup', function () {
            table.search(this.value).draw();
          });

          // Function to show pagination controls
          function showPagination() {
            $container.find('.dataTables_paginate ul.pagination').css({
              'opacity': '1',
              'position': 'relative'
            });
          }

          // Call function initially and on every draw
          showPagination();
          table.on('draw', function () {
            showPagination();
          });
        });
      }

      // Initialize second table if present
      if ($('[data-toggle="data-table2"]').length) {
        const tableInstances = initializeDataTables('[data-toggle="data-table2"]', {
          "autoWidth": false,
          "dom": '<"row align-items-center gy-2"<"px-0"f>><"table-responsive my-3" rt><"row align-items-center"<"col-md-6"i><"col-md-6"p>><"clear">',
          "searching": false,
          "pagingType": "full_numbers",
          "language": {
            "paginate": {
              "first": "«",
              "last": "»",
              "next": "›",
              "previous": "‹"
            }
          },
          buttons: []
        });

        tableInstances.forEach(function (table) {
          const $container = $(table.table().container());

          // Function to show pagination controls
          function showPagination() {
            $container.find('.dataTables_paginate ul.pagination').css({
              'opacity': '1',
              'position': 'relative'
            });
          }

          // Call function initially and on every draw
          showPagination();
          table.on('draw', function () {
            showPagination();
          });
        });
      }
    });


    // Column hidden datatable
    if ($('[data-toggle="data-table-column-hidden"]').length) {
      const hiddenTableInstances = initializeDataTables('[data-toggle="data-table-column-hidden"]', {});
      var hiddentable = hiddenTableInstances[0];

      if (hiddentable) {
        $("a.toggle-vis").on("click", function (e) {
          e.preventDefault();
          const column = hiddentable.column($(this).attr("data-column"));
          column.visible(!column.visible());
        });
      }
    }
    // Column filter datatable
    if ($('[data-toggle="data-table-column-filter"]').length) {
      $('[data-toggle="data-table-column-filter"] tfoot th').each(function () {
        const title = $(this).attr("title");
        $(this).html(
          `<td><input type="text" class="form-control" placeholder="${title}" /></td>`
        );
      });
      initializeDataTables('[data-toggle="data-table-column-filter"]', {
        initComplete: function () {
          this.api()
            .columns()
            .every(function () {
              var that = this;

              $("input", this.footer()).on("keyup change clear", function () {
                if (that.search() !== this.value) {
                  that.search(this.value).draw();
                }
              });
            });
        },
      });
    }
    // Multilanguage datatable
    if ($('[data-toggle="data-table-multi-language"]').length) {
      function languageSelect() {
        return Array.from(document.querySelector("#langSelector").options)
          .filter((option) => option.selected)
          .map((option) => option.getAttribute("data-path"));
      }
      function dataTableInit() {
        initializeDataTables('[data-toggle="data-table-multi-language"]', {
          language: {
            url: languageSelect(),
          },
        });
      }
      dataTableInit();
      document
        .querySelector("#langSelector")
        .addEventListener("change", (e) => {
          $('[data-toggle="data-table-multi-language"]')
            .dataTable()
            .fnDestroy();
          dataTableInit();
        });
    }
  }

  /*--------------Active Class for Pricing Table------------------------*/
  const tableTh = document.querySelectorAll("#my-table tr th");
  const tableTd = document.querySelectorAll("#my-table td");
  if (tableTh !== null) {
    Array.from(tableTh, (elem) => {
      elem.addEventListener("click", (e) => {
        Array.from(tableTh, (th) => {
          if (th.children.length) {
            th.children[0].classList.remove("active");
          }
        });
        elem.children[0].classList.add("active");
        Array.from(tableTd, (td) => td.classList.remove("active"));
        const col = Array.prototype.indexOf.call(
          document.querySelector("#my-table tr").children,
          elem
        );
        const tdIcons = document.querySelectorAll(
          "#my-table tr td:nth-child(" + parseInt(col + 1) + ")"
        );
        Array.from(tdIcons, (td) => td.classList.add("active"));
      });
    });
  }
  /*------------------Pricing--------------------*/

  $("#contcheckbox").change(function () {
  if (this.checked) {
    $(".montlypricing").hide();
    $(".yearlypricing").show();
  } else {
    $(".montlypricing").show();
    $(".yearlypricing").hide();
  }
});
  /*------------Resize Plugins--------------*/
  const resizePlugins = () => {
    // For sidebar-mini & responsive
    const tabs = document.querySelectorAll(".nav");
    if (window.innerWidth < 1025) {
      Array.from(tabs, (elem) => {
        if (
          !elem.classList.contains("flex-column") &&
          elem.classList.contains("nav-tabs") &&
          elem.classList.contains("nav-pills")
        ) {
          elem.classList.add("flex-column", "on-resize");
        }
      });
    } else {
      Array.from(tabs, (elem) => {
        if (elem.classList.contains("on-resize")) {
          elem.classList.remove("flex-column", "on-resize");
        }
      });
    }
  };
  /*----------------Back To Top--------------------*/
  const backToTop = document.getElementById("back-to-top");
  if (backToTop !== null && backToTop !== undefined) {
    document
      .getElementById("back-to-top")
      .classList.add("animate__animated", "animate__fadeOut");
    window.addEventListener("scroll", (e) => {
      if (document.documentElement.scrollTop > 250) {
        document
          .getElementById("back-to-top")
          .classList.remove("animate__fadeOut");
        document.getElementById("back-to-top").classList.add("animate__fadeIn");
      } else {
        document
          .getElementById("back-to-top")
          .classList.remove("animate__fadeIn");
        document
          .getElementById("back-to-top")
          .classList.add("animate__fadeOut");
      }
    });
    // scroll body to 0px on click
    document.querySelector("#top").addEventListener("click", (e) => {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }
  /*------------DOMContentLoaded--------------*/
  document.addEventListener("DOMContentLoaded", (event) => {
    resizePlugins();
    loaderInit();
  });
  /*------------Window Resize------------------*/
  window.addEventListener("resize", function (event) {
    resizePlugins();
  });
  /*--------DropDown--------*/

  function darken_screen(yesno) {
    if (yesno == true) {
      if (document.querySelector(".screen-darken") !== null) {
        document.querySelector(".screen-darken").classList.add("active");
      }
    } else if (yesno == false) {
      if (document.querySelector(".screen-darken") !== null) {
        document.querySelector(".screen-darken").classList.remove("active");
      }
    }
  }
  function close_offcanvas() {
    darken_screen(false);
    if (document.querySelector(".mobile-offcanvas.show") !== null) {
      document.querySelector(".mobile-offcanvas.show").classList.remove("show");
      document.body.classList.remove("offcanvas-active");
    }
  }
  function show_offcanvas(offcanvas_id) {
    darken_screen(true);
    if (document.getElementById(offcanvas_id) !== null) {
      document.getElementById(offcanvas_id).classList.add("show");
      document.body.classList.add("offcanvas-active");
    }
  }
  document.addEventListener("DOMContentLoaded", function () {
    document
      .querySelectorAll("[data-trigger]")
      .forEach(function (everyelement) {
        let offcanvas_id = everyelement.getAttribute("data-trigger");
        everyelement.addEventListener("click", function (e) {
          e.preventDefault();
          show_offcanvas(offcanvas_id);
        });
      });
    if (document.querySelectorAll(".btn-close")) {
      document.querySelectorAll(".btn-close").forEach(function (everybutton) {
        everybutton.addEventListener("click", function (e) {
          close_offcanvas();
        });
      });
    }
    if (document.querySelector(".screen-darken")) {
      document
        .querySelector(".screen-darken")
        .addEventListener("click", function (event) {
          close_offcanvas();
        });
    }
  });
  if (document.querySelector("#navbarSideCollapse")) {
    document
      .querySelector("#navbarSideCollapse")
      .addEventListener("click", function () {
        document.querySelector(".offcanvas-collapse").classList.toggle("open");
      });
  }

  /*---------------------------------------------------------------------
              FullScreen
  -----------------------------------------------------------------------*/
  jQuery(document).ready(function () {
    jQuery(".search-full").click(function () {
      jQuery(".search-mini").toggleClass("active");
    });
  });

  jQuery(document).on("click", ".iq-full-screen", function () {
    if (
      !document.fullscreenElement &&
      !document.mozFullScreenElement && // Mozilla
      !document.webkitFullscreenElement && // Webkit-Browser
      !document.msFullscreenElement
    ) {
      // MS IE ab version 11

      if (document.documentElement.requestFullscreen) {
        document.documentElement.requestFullscreen();
      } else if (document.documentElement.mozRequestFullScreen) {
        document.documentElement.mozRequestFullScreen();
      } else if (document.documentElement.webkitRequestFullscreen) {
        document.documentElement.webkitRequestFullscreen(
          Element.ALLOW_KEYBOARD_INPUT
        );
      } else if (document.documentElement.msRequestFullscreen) {
        document.documentElement.msRequestFullscreen(
          Element.ALLOW_KEYBOARD_INPUT
        );
      }
      document
        .querySelector(".iq-full-screen")
        .querySelector(".normal-screen")
        .classList.add("d-none");
      document
        .querySelector(".iq-full-screen")
        .querySelector(".full-normal-screen")
        .classList.remove("d-none");
    } else {
      if (document.cancelFullScreen) {
        document.cancelFullScreen();
      } else if (document.mozCancelFullScreen) {
        document.mozCancelFullScreen();
      } else if (document.webkitCancelFullScreen) {
        document.webkitCancelFullScreen();
      } else if (document.msExitFullscreen) {
        document.msExitFullscreen();
      }
      document
        .querySelector(".iq-full-screen")
        .querySelector(".full-normal-screen")
        .classList.add("d-none");
      document
        .querySelector(".iq-full-screen")
        .querySelector(".normal-screen")
        .classList.remove("d-none");
    }
  });

  /*---------------------------------------------------------------------
            Font size change script
  -----------------------------------------------------------------------*/

  const sizes = document.querySelectorAll('[data-change="fs"]');
  sizes.forEach((size) =>
    size.addEventListener("click", () => changeSize(size))
  );
  function changeSize(params) {
    const size = params.dataset.size;
    sizes.forEach((params) => params.classList.remove("btn-primary"));
    if (document.querySelector("html").style.fontSize !== size) {
      document.querySelector("html").style.fontSize = size;
      params.classList.add("btn-primary");
    } else {
      document.querySelector("html").style.removeProperty("font-size");
    }
    window.dispatchEvent(new Event("resize"));
    hideTooltip();
  }

  function hideTooltip() {
    const tooltipElms = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipElms.forEach((tooltipElm) => {
      const tooltip = bootstrap.Tooltip.getInstance(tooltipElm);
      tooltip.hide();
    });
  }

  /*---------------------------------------------------------------------
            header toggle
  -----------------------------------------------------------------------*/
  const toggleelem = document.getElementById("navbarSupportedContent");
  const offcanvasheader = document.getElementById("offcanvasBottom");
  if (offcanvasheader !== null && offcanvasheader !== undefined) {
    const bsOffcanvas = new bootstrap.Offcanvas(offcanvasheader);
    toggleelem.addEventListener("show.bs.collapse", function () {
      bsOffcanvas.show();
      document
        .querySelector(".offcanvas-backdrop")
        .addEventListener("click", function () {
          const toggleInstace = bootstrap.Collapse.getInstance(toggleelem);
          toggleInstace.hide();
        });
    });
    toggleelem.addEventListener("hide.bs.collapse", function () {
      bsOffcanvas.hide();
    });
  }

  const toggleelem1 = document.getElementById("navbarSupportedContent1");
  const offcanvas = document.getElementById("offcanvasBottom1");
  if (offcanvas !== null && toggleelem1 !== null) {
    const offcanvas = new bootstrap.Offcanvas();
    toggleelem1.addEventListener("show.bs.collapse", function () {
      offcanvas.show();
      document
        .querySelector(".offcanvas-backdrop")
        .addEventListener("click", function () {
          const toggleInstace = bootstrap.Collapse.getInstance(toggleelem1);
          toggleInstace.hide();
        });
    });
    toggleelem1.addEventListener("hide.bs.collapse", function () {
      offcanvas.hide();
    });
  }

  /*---------------------------------------------------------------------
            Pro Sidebar Left Active Border
  -----------------------------------------------------------------------*/
  window.addEventListener("load", () => {
    const leftSidebar = document.querySelector('[data-toggle="main-sidebar"]');
    if (leftSidebar !== null) {
      const collapseElementList = [].slice.call(
        leftSidebar.querySelectorAll(".collapse")
      );
      const collapseList = collapseElementList.map(function (collapseEl) {
        collapseEl.addEventListener("show.bs.collapse", function (elem) {
          collapseEl.closest("li").classList.add("active");
        });
        collapseEl.addEventListener("hidden.bs.collapse", function (elem) {
          collapseEl.closest("li").classList.remove("active");
        });
      });

      const active = leftSidebar.querySelector(".active");
      if (active !== null) {
        active.closest("li").classList.add("active");
      }
    }
  });

  /*---------------------------------------------------------------------
            Reset Settings
  -----------------------------------------------------------------------*/
  const resetSettings = document.querySelector('[data-reset="settings"]');
  if (resetSettings !== null) {
    resetSettings.addEventListener("click", (e) => {
      e.preventDefault();
      const confirm = window.confirm(
        "Are you sure you want to reset your settings?"
      );
      if (confirm) {
        window.IQSetting.reInit();
      }
    });
  }

  /*---------------------------------------------------------------------
            Copy Json
  -----------------------------------------------------------------------*/
  const copySettings = document.querySelector('[data-copy="settings"]');
  if (copySettings !== null) {
    copySettings.addEventListener("click", (e) => {
      e.preventDefault();
      let settingJson = window.IQSetting.getSettingJson();
      const elem = document.createElement("textarea");
      document.querySelector("body").appendChild(elem);
      elem.value = settingJson;
      elem.select();
      document.execCommand("copy");
      elem.remove();
      copySettings.setAttribute("data-bs-original-title", "Copied!");
      let btn_tooltip = bootstrap.Tooltip.getInstance(copySettings);
      btn_tooltip.show();
      // reset the tooltip title
      copySettings.setAttribute("data-bs-original-title", "Copy to clipboard");
      setTimeout(() => {
        btn_tooltip.hide();
      }, 500);
    });
  }

  $(".iq-status").on("change", function () {
    const status = $(this).is(":checked");
    if (status) {
      $(".iq-reset-status").text("Online");
    } else {
      $(".iq-reset-status").text("Offline");
    }
  });

  $(".delete-btn").on("click", function () {
    const __this = $(this);
    Swal.fire({
      title: "Are you sure?",
      text: "You want to delete this item",
      icon: "error",
      showCancelButton: true,
      backdrop: `rgba(60,60,60,0.8)`,
      confirmButtonText: "Yes, delete it!",
      confirmButtonColor: "#c03221",
    }).then((result) => {
      if (result.isConfirmed) {
        $(__this).closest('[data-item="list"]').remove();
        Swal.fire("Deleted!", "Your item has been deleted.", "success");
      }
    });
  });

  $(".restore-btn").on("click", function () {
    const __this = $(this);
    Swal.fire({
      title: "Are you sure?",
      text: "You want to restore this item",
      icon: "info",
      showCancelButton: true,
      backdrop: `rgba(60,60,60,0.8)`,
      confirmButtonText: "Yes",
      confirmButtonColor: "#c03221",
    }).then((result) => {
      if (result.isConfirmed) {
        $(__this).closest('[data-item="list"]').remove();
        Swal.fire("Restore!", "Your item has been restore.", "success");
      }
    });
  });

  $(".wishlist-btn").on("click", function () {
    Swal.fire("Added!", "Your item has been Added to the wishlist.", "success");
  });

  $(".cart-btn").on("click", function () {
    Swal.fire("Added!", "Your item has been Added to the cart.", "success");
  });

  /*---------------Form Validation--------------------*/
  // Example starter JavaScript for disabling form submissions if there are invalid fields
  window.addEventListener(
    "load",
    function () {
      // Fetch all the forms we want to apply custom Bootstrap validation styles to
      var forms = document.getElementsByClassName("needs-validation");
      // Loop over them and prevent submission
      var validation = Array.prototype.filter.call(forms, function (form) {
        form.addEventListener(
          "submit",
          function (event) {
            if (form.checkValidity() === false) {
              event.preventDefault();
              event.stopPropagation();
            }
            form.classList.add("was-validated");
          },
          false
        );
      });
    },
    false
  );
})();

document.addEventListener("DOMContentLoaded", function () {
  const checkbox = document.getElementById("contcheckbox");

  // Update the displayed prices
  function updatePrices() {
    const prices = document.querySelectorAll("[data-monthly][data-yearly]");
    prices.forEach((price) => {
      price.textContent = checkbox.checked
        ? price.getAttribute("data-yearly")
        : price.getAttribute("data-monthly");
    });
  }

  // Initialize on page load
  updatePrices();

  // Add event listener for checkbox toggle
  if (checkbox) {
    checkbox.addEventListener("change", updatePrices);
  }
});


function thumbnil_img() {
  // Initialize all upload widgets on the page so multiple instances work independently
  document.querySelectorAll('.streamit-upload').forEach(function(root) {
    const trigger = root.querySelector('.streamit_upload_video_button');
    const fileUpload = root.querySelector('.file_upload');
    const removeButton = root.querySelector('.remove_tvshow_genre_thumbnail_button, .remove_tvshow_genre_thumbnail_buttons');

    if (trigger && fileUpload) {
      trigger.addEventListener('click', function(e) {
        e.preventDefault();
        fileUpload.click();
      });
    }

    if (fileUpload) {
      fileUpload.addEventListener('change', function (event) {
        const file = event.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function (evt) {
            // scoped elements within this widget instance
            const existingImage = root.querySelector('.tvshow_genre_thumbnail_preview, .tvshow_genre_thumbnail_previews');
            if (existingImage) existingImage.remove();

            const img = document.createElement('img');
            img.className = 'tvshow_genre_thumbnail_preview';
            img.src = evt.target.result;
            img.style.maxWidth = '100px';
            img.style.maxHeight = '100px';
            img.style.objectFit = 'cover';

            if (trigger) trigger.appendChild(img);

            if (removeButton) removeButton.style.display = 'block';
            const span = trigger ? trigger.querySelector('span') : null;
            if (span) span.classList.add('d-none');
            const icon = trigger ? trigger.querySelector('.img-icon') : null;
            if (icon) icon.classList.add('d-none');

            toggleRemoveButton(removeButton, trigger, root);
          };
          reader.readAsDataURL(file);
        }
      });
    }

    if (removeButton) {
      removeButton.addEventListener('click', function(e) {
        e.preventDefault();
        const img = root.querySelector('.tvshow_genre_thumbnail_preview');
        if (img) img.remove();
        if (trigger) trigger.innerHTML = template;
        removeButton.style.display = 'none';
        const file = root.querySelector('.file_upload');
        if (file) file.value = null;
      });
    }
  });
}

document.addEventListener("DOMContentLoaded", function () {
  // Open file input dialog when clicking the 'Choose Media to Upload' button
  thumbnil_img()
});

const template = `  <!-- Default SVG icon (visible before image is selected) -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="img-icon d-block">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <!-- Default text (visible before image is selected) -->
                                <span class="d-block">Choose Media to Upload</span>
                            `;

function toggleRemoveButton(removeButton, buttonContainer, root) {
  if (!removeButton) return;
  removeButton.classList.remove('d-none');
  removeButton.addEventListener('click', function(e) {
    e.preventDefault();
    const img = root ? root.querySelector('.tvshow_genre_thumbnail_preview') : document.querySelector('.tvshow_genre_thumbnail_preview');
    if (img) img.remove();
    if (buttonContainer) buttonContainer.innerHTML = template;
    removeButton.classList.add('d-none');
    const fileInput = root ? root.querySelector('.file_upload') : document.querySelector('.file_upload');
    if (fileInput) fileInput.value = null;
  });
}


// Removed global remove handler because widgets are now per-instance


function getchange(event) {
  const selectedValue = event.target.value;
  const dropdownId = event.target.id;

  // Define the mapping of dropdown IDs to the related fields
  const fieldMapping = {
    'parent-genre1': ['link-field', 'upload-field'],
    'parent-genre2': ['link-field2', 'upload-field2']
  };

  // Get the field ids for the specific dropdown
  const [linkFieldId, uploadFieldId] = fieldMapping[dropdownId] || [];

  // If the dropdown has valid field mapping, update the fields visibility
  if (linkFieldId && uploadFieldId) {
    document.getElementById(linkFieldId).classList.toggle('d-none', selectedValue !== 'Link');
    document.getElementById(uploadFieldId).classList.toggle('d-none', selectedValue !== 'Upload');
  }
}

// Add new moview source
let sourceCount = 0; // counter to keep track of sources
let addMovieSource = document.querySelector('#add-movie-source');
if (addMovieSource) {
  addMovieSource.addEventListener('click', function () {
    event.preventDefault();
    sourceCount++; // increment the source count
  
    // Create a new source element
    const source = document.createElement('div');
    source.classList.add('streamit_source');
  
    // Set the content of the new source
    source.innerHTML = `
    <div id=${sourceCount} class="d-flex justify-content-between align-items-center flex-wrap rounded-3">
      <h3 class="mb-0 fw-semibold">Source ${sourceCount}</h3>
      <div class="d-flex flex-shrink-0 gap-2 align-items-center custom-source-font">
        <a id=${sourceCount} href="#" class="" onclick="removeSource(this)">Remove</a>
        <div id=${sourceCount} class="movie_source_toggle handlediv" onclick="toggleSource(this)" title="Click to toggle">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
            <path d="M137.4 374.6c12.5 12.5 32.8 12.5 45.3 0l128-128c9.2-9.2 11.9-22.9 6.9-34.9s-16.6-19.8-29.6-19.8L32 192c-12.9 0-24.6 7.8-29.6 19.8s-2.2 25.7 6.9 34.9l128 128z"></path>
          </svg> 
        </div>
        <strong class="source_name"></strong>
      </div>
      </div>
      <div id="source${sourceCount}"></div>
    `;
  
    // Append the new source to the container
    document.querySelector('#source-container').appendChild(source);
  });
}

let sourceCount1 = 0; // counter to keep track of sources
let addMovieSources = document.querySelector('#add-movie-source1');
if (addMovieSources) {
  addMovieSources.addEventListener('click', function () {
  event.preventDefault();
  sourceCount1++; // increment the source count

  // Create a new source element
  const source = document.createElement('div');
  source.classList.add('streamit_source');

  // Set the content of the new source
  source.innerHTML = `
  <div id=${sourceCount1} class="d-flex justify-content-between align-items-center flex-wrap rounded-3">
    <h3 class="mb-0 fw-semibold">Source ${sourceCount1}</h3>
    <div class="d-flex flex-shrink-0 gap-2 align-items-center custom-source-font">
      <a id=${sourceCount1} href="#" class="" onclick="removeSource(this)">Remove</a>
      <div id=${sourceCount1} class="movie_source_toggle handlediv" onclick="toggleSource1(this)" title="Click to toggle">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
          <path d="M137.4 374.6c12.5 12.5 32.8 12.5 45.3 0l128-128c9.2-9.2 11.9-22.9 6.9-34.9s-16.6-19.8-29.6-19.8L32 192c-12.9 0-24.6 7.8-29.6 19.8s-2.2 25.7 6.9 34.9l128 128z"></path>
        </svg> 
      </div>
      <strong class="source_name"></strong>
    </div>
    </div>
    <div id="source${sourceCount1}"></div>
  `;

  // Append the new source to the container
  document.querySelector('#source-container1').appendChild(source);
});
}
// Function to remove a source
function removeSource(element) {
  const sourceElement = element.closest('.streamit_source'); // find the parent source element
  sourceElement.remove(); // removwe it from the DOM
  document.querySelector(`#source${element.id}`).innerHTML = ""
  // sourceCount = 0

}

function toggleSource(element) {
  event.preventDefault();

  // Get the ID of the clicked source's container (e.g., source_1)
  const sourceId = element.id; // e.g., "source_1", "source_2"

  // Clone the hidden form template
  const formTemplate = document.getElementById('source-data-template').innerHTML;

  // Create a new div to hold the form
  const formContainer = document.createElement('div');
  formContainer.classList.add('form-container');

  // Set the form content dynamically based on the sourceId
  formContainer.innerHTML = formTemplate;

  // Dynamically update the form fields based on the sourceId
  const form = formContainer.querySelector('#source-data-container');

  // Ensure the form container exists before proceeding
  if (form) {
    const inputs = form.querySelectorAll('input, select, textarea');

    // Update form fields dynamically based on the sourceId (e.g., input_1, input_2)
    inputs.forEach(input => {
      input.id = `${input.name}_${sourceId}`;
      input.name = `${input.name}_${sourceId}`;
    });

    // Show the form
    form.classList.remove('d-none');

    // Append the form to the clicked source's container
    const sourceElement = document.getElementById(`source${sourceId}`);
    const existingForm = sourceElement.querySelector('.form-container');
    if (existingForm) {
      // If form already exists, toggle the 'd-none' class to hide/show it
      existingForm.classList.toggle('d-none');
    } else {
      // If no form exists, append the new form
      form.classList.remove('d-none');
      sourceElement.appendChild(formContainer);
    }
  } else {
    console.error('Form container is not found.');
  }
}

function toggleSource1(element) {
  event.preventDefault();

  // Get the ID of the clicked source's container (e.g., source_1)
  const sourceId = element.id; // e.g., "source_1", "source_2"

  // Clone the hidden form template
  const formTemplate1 = document.getElementById('source-data-template1').innerHTML;

  // Create a new div to hold the form
  const formContainer = document.createElement('div');
  formContainer.classList.add('form-container');

  // Set the form content dynamically based on the sourceId
  formContainer.innerHTML = formTemplate1;

  // Dynamically update the form fields based on the sourceId
  const form = formContainer.querySelector('#source-data-container');

  // Ensure the form container exists before proceeding
  if (form) {
    const inputs = form.querySelectorAll('input, select, textarea');

    // Update form fields dynamically based on the sourceId (e.g., input_1, input_2)
    inputs.forEach(input => {
      input.id = `${input.name}_${sourceId}`;
      input.name = `${input.name}_${sourceId}`;
    });

    // Show the form
    form.classList.remove('d-none');

    // Append the form to the clicked source's container
    const sourceElement = document.getElementById(`source${sourceId}`);
    const existingForm = sourceElement.querySelector('.form-container');
    if (existingForm) {
      // If form already exists, toggle the 'd-none' class to hide/show it
      existingForm.classList.toggle('d-none');
    } else {
      // If no form exists, append the new form
      form.classList.remove('d-none');
      sourceElement.appendChild(formContainer);
    }
  } else {
    console.error('Form container is not found.');
  }
}

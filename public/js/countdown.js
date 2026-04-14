(function () {
  "use strict";

  function updateClock(clock, endtime) {
    
    const t = Date.parse(endtime) - Date.now();
    if (t <= 0) return clearInterval(clock._timer);

    const format = (num) => String(num).padStart(2, '0');
    if (clock) {
      clock.querySelector("[data-days]").textContent = Math.floor(t / 86400000);
      clock.querySelector("[data-hours]").textContent = format(Math.floor((t / 3600000) % 24));
      clock.querySelector("[data-minutes]").textContent = format(Math.floor((t / 60000) % 60));
      clock.querySelector("[data-seconds]").textContent = format(Math.floor((t / 1000) % 60));
    }
  }

  function startClock(selector, endtime) {
    const clock = document.querySelector(selector);
    updateClock(clock, endtime);
    if (clock) {
      clock._timer = setInterval(() => updateClock(clock, endtime), 1000);
    }
  }

  startClock(".countdown", new Date(document.querySelector(".countdown")?.getAttribute("data-date") || Date.now() + 15 * 86400000));
})();
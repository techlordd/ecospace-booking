(function () {
  function byId(id) {
    return document.getElementById(id);
  }

  function formatHour(hour) {
    var suffix = hour >= 12 ? "PM" : "AM";
    var normalized = hour % 12;
    if (normalized === 0) {
      normalized = 12;
    }
    return normalized + ":00 " + suffix;
  }

  function formatPrice(amount) {
    var data = window.ecoBookingData || {};
    var symbol = data.currencySymbol || "";
    return symbol + amount;
  }

  function parseDate(value) {
    if (!value) {
      return null;
    }
    var date = new Date(value + "T00:00:00");
    return isNaN(date.getTime()) ? null : date;
  }

  function addDays(date, days) {
    var clone = new Date(date.getTime());
    clone.setDate(clone.getDate() + days);
    return clone;
  }

  function addMonths(date, months) {
    var clone = new Date(date.getTime());
    clone.setMonth(clone.getMonth() + months);
    return clone;
  }

  function init() {
    var root = document.querySelector(".ecospace-booking-ui");
    if (!root || typeof flatpickr === "undefined") {
      return;
    }

    var data = window.ecoBookingData || {};
    var plan = byId("eco_plan");
    var startDate = byId("eco_start_date");
    var endDate = byId("eco_end_date");
    var endDateBlock = byId("eco_end_date_block");
    var preferredDays = byId("eco_preferred_days");
    var startTime = byId("eco_start_time");
    var hours = byId("eco_hours");
    var endTime = byId("eco_end_time");
    var price = byId("eco_price");

    var openHour = Number(data.openHour || 9);
    var closeHour = Number(data.closeHour || 20);
    var prices = {
      daily: Number(data.dailyPrice || 0),
      weekly3: Number(data.weekly3Price || 0),
      weekly5: Number(data.weekly5Price || 0),
      monthly3: Number(data.monthly3Price || 0),
      monthly5: Number(data.monthly5Price || 0),
    };

    var startDatePicker = flatpickr(startDate, {
      dateFormat: "Y-m-d",
      minDate: "today",
    });

    var endDatePicker = flatpickr(endDate, {
      dateFormat: "Y-m-d",
      clickOpens: false,
    });

    function clearPreferredInputs() {
      preferredDays.innerHTML = "";
    }

    function createPreferredInputs(count, labelPrefix) {
      clearPreferredInputs();
      var intro = document.createElement("p");
      intro.innerHTML = "<strong>Select Preferred Dates</strong>";
      preferredDays.appendChild(intro);

      var start = parseDate(startDate.value);
      var end = parseDate(endDate.value);

      for (var i = 0; i < count; i += 1) {
        var wrapper = document.createElement("p");
        if (labelPrefix) {
          var label = document.createElement("label");
          label.textContent = labelPrefix + " " + (i + 1);
          wrapper.appendChild(label);
        }

        var input = document.createElement("input");
        input.type = "text";
        input.className = "eco_calendar";
        input.name = "eco_preferred_days[]";
        input.autocomplete = "off";

        wrapper.appendChild(input);
        preferredDays.appendChild(wrapper);

        flatpickr(input, {
          dateFormat: "Y-m-d",
          minDate: start || "today",
          maxDate: end || null,
        });
      }
    }

    function createMonthlyInputs(perWeek) {
      clearPreferredInputs();
      var intro = document.createElement("p");
      intro.innerHTML = "<strong>Select Preferred Dates (Grouped Weekly)</strong>";
      preferredDays.appendChild(intro);

      var start = parseDate(startDate.value);
      var end = parseDate(endDate.value);

      for (var week = 1; week <= 4; week += 1) {
        var title = document.createElement("p");
        title.innerHTML = "<strong>Week " + week + "</strong>";
        preferredDays.appendChild(title);

        for (var i = 0; i < perWeek; i += 1) {
          var input = document.createElement("input");
          input.type = "text";
          input.name = "eco_preferred_days[]";
          input.className = "eco_calendar";
          input.autocomplete = "off";
          preferredDays.appendChild(input);

          flatpickr(input, {
            dateFormat: "Y-m-d",
            minDate: start || "today",
            maxDate: end || null,
          });
        }
      }
    }

    function updateEndDateFromPlan() {
      var selectedStart = parseDate(startDate.value);
      if (!selectedStart) {
        endDate.value = "";
        return;
      }

      var selectedPlan = plan.value;
      if (selectedPlan === "weekly3" || selectedPlan === "weekly5") {
        endDatePicker.setDate(addDays(selectedStart, 7), true, "Y-m-d");
      } else if (selectedPlan === "monthly3" || selectedPlan === "monthly5") {
        endDatePicker.setDate(addMonths(selectedStart, 1), true, "Y-m-d");
      } else {
        endDate.value = "";
      }
    }

    function updateHourlyPrice() {
      var selectedStartHour = Number(startTime.value || 0);
      var selectedHours = Number(hours.value || 0);
      var hourlyRate = Number(data.hourlyRate || 0);

      endTime.value = "";
      if (!selectedStartHour || !selectedHours) {
        price.textContent = formatPrice(0);
        return;
      }

      var endHour = selectedStartHour + selectedHours;
      if (endHour > closeHour) {
        alert(data.invalidHoursMessage || "Hours exceed closing time");
        hours.value = "";
        price.textContent = formatPrice(0);
        return;
      }

      endTime.value = formatHour(endHour);
      price.textContent = formatPrice(hourlyRate * selectedHours);
    }

    function applyPlanUI() {
      var selectedPlan = plan.value;
      clearPreferredInputs();
      endTime.value = "";

      if (selectedPlan === "hourly") {
        endDateBlock.style.display = "none";
        byId("eco_hourly_fields").style.display = "block";
        updateHourlyPrice();
        return;
      }

      byId("eco_hourly_fields").style.display = "none";

      if (selectedPlan === "daily") {
        endDateBlock.style.display = "none";
        endTime.value = formatHour(closeHour);
        price.textContent = formatPrice(prices.daily);
        return;
      }

      endDateBlock.style.display = "block";
      updateEndDateFromPlan();

      if (selectedPlan === "weekly3") {
        price.textContent = formatPrice(prices.weekly3);
        createPreferredInputs(3);
      } else if (selectedPlan === "weekly5") {
        price.textContent = formatPrice(prices.weekly5);
        createPreferredInputs(5);
      } else if (selectedPlan === "monthly3") {
        price.textContent = formatPrice(prices.monthly3);
        createMonthlyInputs(3);
      } else if (selectedPlan === "monthly5") {
        price.textContent = formatPrice(prices.monthly5);
        createMonthlyInputs(5);
      }
    }

    plan.addEventListener("change", applyPlanUI);
    startDate.addEventListener("change", function () {
      updateEndDateFromPlan();
      applyPlanUI();
    });
    startTime.addEventListener("change", updateHourlyPrice);
    hours.addEventListener("change", updateHourlyPrice);

    applyPlanUI();
  }

  document.addEventListener("DOMContentLoaded", init);
})();

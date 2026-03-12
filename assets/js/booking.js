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

  function createHourOption(hour) {
    var option = document.createElement("option");
    option.value = String(hour);
    option.textContent = formatHour(hour);
    return option;
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
    var preferredHint = byId("eco_preferred_hint");
    var startTime = byId("eco_start_time");
    var hours = byId("eco_hours");
    var endTime = byId("eco_end_time");
    var price = byId("eco_price");

    var openHour = Number(data.openHour || 9);
    var closeHour = Number(data.closeHour || 20);
    var recurringSessionHours = Number(data.recurringSessionHours || 8);
    var recurringStartMin = Number(data.recurringStartMinHour || openHour);
    var recurringStartMax = Number(data.recurringStartMaxHour || 12);
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
      if (preferredHint) {
        preferredHint.textContent = "";
      }
    }

    function createRecurringSlotRow(slotLabel, start, end) {
      var row = document.createElement("div");
      row.className = "eco-recurring-slot";

      if (slotLabel) {
        var rowLabel = document.createElement("p");
        rowLabel.className = "eco-recurring-slot-title";
        rowLabel.innerHTML = "<strong>" + slotLabel + "</strong>";
        row.appendChild(rowLabel);
      }

      var dateField = document.createElement("p");
      var dateLabel = document.createElement("label");
      dateLabel.textContent = "Preferred Date";
      var dateInput = document.createElement("input");
      dateInput.type = "text";
      dateInput.className = "eco_calendar";
      dateInput.name = "eco_preferred_days[]";
      dateInput.autocomplete = "off";
      dateField.appendChild(dateLabel);
      dateField.appendChild(dateInput);
      row.appendChild(dateField);

      var timeStartField = document.createElement("p");
      var timeStartLabel = document.createElement("label");
      timeStartLabel.textContent = "Preferred Start Time";
      var timeStartSelect = document.createElement("select");
      timeStartSelect.name = "eco_preferred_start_times[]";
      var defaultOption = document.createElement("option");
      defaultOption.value = "";
      defaultOption.textContent = "Select";
      timeStartSelect.appendChild(defaultOption);
      for (var hour = recurringStartMin; hour <= recurringStartMax; hour += 1) {
        timeStartSelect.appendChild(createHourOption(hour));
      }
      timeStartField.appendChild(timeStartLabel);
      timeStartField.appendChild(timeStartSelect);
      row.appendChild(timeStartField);

      var timeEndField = document.createElement("p");
      var timeEndLabel = document.createElement("label");
      timeEndLabel.textContent = "Preferred End Time";
      var timeEndInput = document.createElement("input");
      timeEndInput.type = "text";
      timeEndInput.readOnly = true;
      timeEndInput.className = "eco-recurring-end-time";
      timeEndField.appendChild(timeEndLabel);
      timeEndField.appendChild(timeEndInput);
      row.appendChild(timeEndField);

      timeStartSelect.addEventListener("change", function () {
        var selected = Number(timeStartSelect.value || 0);
        if (!selected) {
          timeEndInput.value = "";
          return;
        }
        timeEndInput.value = formatHour(selected + recurringSessionHours);
      });

      flatpickr(dateInput, {
        dateFormat: "Y-m-d",
        minDate: start || "today",
        maxDate: end || null,
      });

      preferredDays.appendChild(row);
    }

    function createPreferredInputs(count, labelPrefix) {
      clearPreferredInputs();
      var start = parseDate(startDate.value);
      var end = parseDate(endDate.value);

      for (var i = 0; i < count; i += 1) {
        var slotLabel = labelPrefix ? labelPrefix + " " + (i + 1) : "Session " + (i + 1);
        createRecurringSlotRow(slotLabel, start, end);
      }
    }

    function createMonthlyInputs(perWeek) {
      clearPreferredInputs();
      var start = parseDate(startDate.value);
      var end = parseDate(endDate.value);

      for (var week = 1; week <= 4; week += 1) {
        for (var i = 0; i < perWeek; i += 1) {
          createRecurringSlotRow("Week " + week + " - Session " + (i + 1), start, end);
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

      if (preferredHint) {
        preferredHint.textContent = "Each session is fixed to " + recurringSessionHours + " hours. Choose a start time from " + formatHour(recurringStartMin) + " to " + formatHour(recurringStartMax) + ".";
      }

      if (selectedPlan === "weekly3") {
        price.textContent = formatPrice(prices.weekly3);
        createPreferredInputs(3, "Session");
      } else if (selectedPlan === "weekly5") {
        price.textContent = formatPrice(prices.weekly5);
        createPreferredInputs(5, "Session");
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

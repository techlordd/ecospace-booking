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

  function createSelectPlaceholder(text) {
    var option = document.createElement("option");
    option.value = "";
    option.textContent = text;
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
    var preferredError = byId("eco_preferred_error");
    var startTime = byId("eco_start_time");
    var hours = byId("eco_hours");
    var endTime = byId("eco_end_time");
    var price = byId("eco_price");
    var form = root.closest("form");

    var openHour = Number(data.openHour || 9);
    var closeHour = Number(data.closeHour || 20);
    var recurringSessionHours = Number(data.recurringSessionHours || 8);
    var recurringStartMin = Number(data.recurringStartMinHour || openHour);
    var recurringStartMax = Number(data.recurringStartMaxHour || closeHour - 1);
    var recurringPlans = {
      weekly3: true,
      weekly5: true,
      monthly3: true,
      monthly5: true,
    };
    var productId = Number(data.productId || 0);
    var ajaxUrl = data.ajaxUrl || "";
    var availabilityNonce = data.availabilityNonce || "";
    var availabilityRefreshMs = Number(data.availabilityRefreshMs || 15000);
    var bookedRecurringSlots = data.bookedRecurringSlots || {};
    var availabilityRefreshPromise = null;
    var lastAvailabilityHash = JSON.stringify(bookedRecurringSlots || {});
    var allowImmediateSubmit = false;
    var pendingSubmitter = null;
    var slotIdCounter = 0;
    var recurringDatePickers = [];
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

    function isRecurringPlan(value) {
      return recurringPlans[value] === true;
    }

    function setPreferredError(message) {
      if (!preferredError) {
        return;
      }

      if (!message) {
        preferredError.textContent = "";
        preferredError.style.display = "none";
        return;
      }

      preferredError.textContent = message;
      preferredError.style.display = "block";
    }

    function applyAvailabilityPayload(nextBookedSlots) {
      bookedRecurringSlots = nextBookedSlots || {};
      data.bookedRecurringSlots = bookedRecurringSlots;
      lastAvailabilityHash = JSON.stringify(bookedRecurringSlots);
    }

    function clearPreferredInputs() {
      preferredDays.innerHTML = "";
      recurringDatePickers = [];
      if (preferredHint) {
        preferredHint.textContent = "";
      }
      setPreferredError("");
    }

    function syncRecurringDateAvailability() {
      var selectedDates = {};

      for (var i = 0; i < recurringDatePickers.length; i += 1) {
        var currentValue = recurringDatePickers[i].input.value;
        if (!currentValue) {
          continue;
        }

        selectedDates[currentValue] = true;
      }

      for (var j = 0; j < recurringDatePickers.length; j += 1) {
        var pickerEntry = recurringDatePickers[j];
        var selfDate = pickerEntry.input.value;
        var disableDates = [];

        for (var dateValue in selectedDates) {
          if (!Object.prototype.hasOwnProperty.call(selectedDates, dateValue)) {
            continue;
          }

          if (dateValue !== selfDate) {
            disableDates.push(dateValue);
          }
        }

        pickerEntry.picker.set("disable", disableDates);
      }
    }

    function refreshRenderedAvailability() {
      rebuildHourlyStartOptions();
      updateHourlyPrice();
      syncStartDateAvailability();

      if (!isRecurringPlan(plan.value)) {
        return;
      }

      var rows = preferredDays.querySelectorAll(".eco-recurring-slot");
      for (var i = 0; i < rows.length; i += 1) {
        populateRecurringEndOptions(rows[i], true);
      }

      syncRecurringDateAvailability();
      validateRecurringSlots();
    }

    function refreshAvailability(forceRefresh) {
      var shouldForceRefresh = forceRefresh === true;

      if (!productId || !ajaxUrl || !availabilityNonce) {
        return Promise.resolve(bookedRecurringSlots);
      }

      if (availabilityRefreshPromise && !shouldForceRefresh) {
        return availabilityRefreshPromise;
      }

      var requestBody = new URLSearchParams();
      requestBody.set("action", "eco_refresh_booking_availability");
      requestBody.set("product_id", String(productId));
      requestBody.set("nonce", availabilityNonce);

      availabilityRefreshPromise = fetch(ajaxUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        credentials: "same-origin",
        body: requestBody.toString(),
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("Availability request failed");
          }

          return response.json();
        })
        .then(function (payload) {
          if (!payload || payload.success !== true || !payload.data) {
            throw new Error("Invalid availability payload");
          }

          var nextBookedSlots = payload.data.bookedRecurringSlots || {};
          var nextHash = JSON.stringify(nextBookedSlots);
          if (shouldForceRefresh || nextHash !== lastAvailabilityHash) {
            applyAvailabilityPayload(nextBookedSlots);
            refreshRenderedAvailability();
          }

          return bookedRecurringSlots;
        })
        .catch(function () {
          return bookedRecurringSlots;
        })
        .finally(function () {
          availabilityRefreshPromise = null;
        });

      return availabilityRefreshPromise;
    }

    function getBookedSlotSetForDate(dateValue) {
      var set = {};
      if (!dateValue || !bookedRecurringSlots[dateValue] || !bookedRecurringSlots[dateValue].length) {
        return set;
      }

      for (var i = 0; i < bookedRecurringSlots[dateValue].length; i += 1) {
        set[bookedRecurringSlots[dateValue][i]] = true;
      }

      return set;
    }

    function getBookedRangesForDate(dateValue) {
      var ranges = [];
      if (!dateValue || !bookedRecurringSlots[dateValue] || !bookedRecurringSlots[dateValue].length) {
        return ranges;
      }

      for (var i = 0; i < bookedRecurringSlots[dateValue].length; i += 1) {
        var parts = String(bookedRecurringSlots[dateValue][i]).split("|");
        if (parts.length !== 2) {
          continue;
        }

        ranges.push({
          start: Number(parts[0] || 0),
          end: Number(parts[1] || 0),
        });
      }

      return ranges;
    }

    function doesRangeOverlap(dateValue, startHourValue, endHourValue) {
      if (!dateValue || !startHourValue || !endHourValue) {
        return false;
      }

      var startHourValueNumber = Number(startHourValue || 0);
      var endHourValueNumber = Number(endHourValue || 0);
      var ranges = getBookedRangesForDate(dateValue);

      for (var i = 0; i < ranges.length; i += 1) {
        if (startHourValueNumber < ranges[i].end && endHourValueNumber > ranges[i].start) {
          return true;
        }
      }

      return false;
    }

    function rebuildHourlyStartOptions() {
      if (!startTime) {
        return;
      }

      var selectedDate = startDate.value;
      var previousStartValue = startTime.value;

      startTime.innerHTML = "";
      startTime.appendChild(createSelectPlaceholder("Select"));

      for (var hour = openHour; hour <= closeHour - 1; hour += 1) {
        var hasAvailableRange = false;

        if (!selectedDate) {
          hasAvailableRange = true;
        } else {
          for (var endHour = hour + 1; endHour <= closeHour; endHour += 1) {
            if (!doesRangeOverlap(selectedDate, hour, endHour)) {
              hasAvailableRange = true;
              break;
            }
          }
        }

        if (hasAvailableRange) {
          startTime.appendChild(createHourOption(hour));
        }
      }

      if (previousStartValue && startTime.querySelector('option[value="' + previousStartValue + '"]')) {
        startTime.value = previousStartValue;
      }
    }

    function setHourlyConflictState(message) {
      if (!hours) {
        return;
      }

      if (typeof hours.setCustomValidity === "function") {
        hours.setCustomValidity(message || "");
      }
    }

    function validateCurrentPlanAvailability(showBrowserMessage) {
      var shouldShowBrowserMessage = showBrowserMessage === true;

      if (plan.value === "hourly") {
        var selectedStartHour = Number(startTime.value || 0);
        var selectedHours = Number(hours.value || 0);
        if (!startDate.value || !selectedStartHour || !selectedHours) {
          setHourlyConflictState("");
          return true;
        }

        var selectedEndHour = selectedStartHour + selectedHours;
        if (doesRangeOverlap(startDate.value, selectedStartHour, selectedEndHour)) {
          var hourlyMessage = data.bookedTimeRangeMessage || "This time range is already booked.";
          setHourlyConflictState(hourlyMessage);
          if (shouldShowBrowserMessage && typeof hours.reportValidity === "function") {
            hours.reportValidity();
          }
          return false;
        }

        setHourlyConflictState("");
        return true;
      }

      if (plan.value === "daily") {
        if (!startDate.value) {
          return true;
        }

        if (doesRangeOverlap(startDate.value, openHour, closeHour)) {
          if (shouldShowBrowserMessage) {
            alert(data.dailyUnavailableMessage || "This date is no longer available for a daily booking.");
          }
          return false;
        }
      }

      if (isRecurringPlan(plan.value)) {
        return validateRecurringSlots();
      }

      return true;
    }

    function attemptSubmitAfterRefresh(event) {
      if (allowImmediateSubmit) {
        allowImmediateSubmit = false;
        pendingSubmitter = null;
        return;
      }

      event.preventDefault();
      pendingSubmitter = event.submitter || form.querySelector('[name="add-to-cart"]') || null;

      refreshAvailability(true).then(function () {
        if (!validateCurrentPlanAvailability(true)) {
          pendingSubmitter = null;
          return;
        }

        allowImmediateSubmit = true;

        if (typeof form.requestSubmit === "function") {
          form.requestSubmit(pendingSubmitter || undefined);
          return;
        }

        if (pendingSubmitter && typeof pendingSubmitter.click === "function") {
          pendingSubmitter.click();
          return;
        }

        form.submit();
      });
    }

    function collectInFormSlotKeys(excludedRowId) {
      var set = {};
      var rows = preferredDays.querySelectorAll(".eco-recurring-slot");

      for (var i = 0; i < rows.length; i += 1) {
        var row = rows[i];
        if (excludedRowId && row.getAttribute("data-slot-id") === excludedRowId) {
          continue;
        }

        var rowDate = row.querySelector('input[name="eco_preferred_days[]"]');
        var rowStart = row.querySelector('select[name="eco_preferred_start_times[]"]');
        var rowEnd = row.querySelector('select[name="eco_preferred_end_times[]"]');

        if (!rowDate || !rowStart || !rowEnd || !rowDate.value || !rowStart.value || !rowEnd.value) {
          continue;
        }

        set[rowDate.value + "|" + rowStart.value + "|" + rowEnd.value] = true;
      }

      return set;
    }

    function populateRecurringEndOptions(row, keepExistingSelection) {
      var rowDate = row.querySelector('input[name="eco_preferred_days[]"]');
      var rowStart = row.querySelector('select[name="eco_preferred_start_times[]"]');
      var rowEnd = row.querySelector('select[name="eco_preferred_end_times[]"]');
      var selectedDate = rowDate ? rowDate.value : "";
      var selectedStart = rowStart ? Number(rowStart.value || 0) : 0;
      var previousEnd = keepExistingSelection && rowEnd ? rowEnd.value : "";
      var rowId = row.getAttribute("data-slot-id") || "";

      if (!rowEnd) {
        return;
      }

      rowEnd.innerHTML = "";
      rowEnd.appendChild(createSelectPlaceholder("Select"));

      if (!selectedStart) {
        return;
      }

      var maxEnd = Math.min(selectedStart + recurringSessionHours, closeHour);
      var inFormSet = collectInFormSlotKeys(rowId);

      for (var hour = selectedStart + 1; hour <= maxEnd; hour += 1) {
        var fullKey = selectedDate + "|" + selectedStart + "|" + hour;
        if (selectedDate && (doesRangeOverlap(selectedDate, selectedStart, hour) || inFormSet[fullKey])) {
          continue;
        }

        rowEnd.appendChild(createHourOption(hour));
      }

      if (previousEnd && rowEnd.querySelector('option[value="' + previousEnd + '"]')) {
        rowEnd.value = previousEnd;
      }
    }

    function validateRecurringSlots() {
      if (!isRecurringPlan(plan.value)) {
        setPreferredError("");
        return true;
      }

      var rows = preferredDays.querySelectorAll(".eco-recurring-slot");
      var seen = {};
      var seenDates = {};
      var hasError = false;
      var message = "";

      for (var i = 0; i < rows.length; i += 1) {
        rows[i].classList.remove("eco-recurring-slot-error");
      }

      for (var j = 0; j < rows.length; j += 1) {
        var row = rows[j];
        var rowDate = row.querySelector('input[name="eco_preferred_days[]"]');
        var rowStart = row.querySelector('select[name="eco_preferred_start_times[]"]');
        var rowEnd = row.querySelector('select[name="eco_preferred_end_times[]"]');

        if (rowDate && rowDate.value) {
          if (seenDates[rowDate.value]) {
            row.classList.add("eco-recurring-slot-error");
            seenDates[rowDate.value].classList.add("eco-recurring-slot-error");
            hasError = true;
            if (!message) {
              message = data.duplicateRecurringDateMessage || "Preferred dates must be unique";
            }
            continue;
          }

          seenDates[rowDate.value] = row;
        }

        if (!rowDate || !rowStart || !rowEnd || !rowDate.value || !rowStart.value || !rowEnd.value) {
          continue;
        }

        var key = rowDate.value + "|" + rowStart.value + "|" + rowEnd.value;
        if (seen[key]) {
          row.classList.add("eco-recurring-slot-error");
          seen[key].classList.add("eco-recurring-slot-error");
          hasError = true;
          if (!message) {
            message = data.duplicateRecurringSlotMessage || "Duplicate preferred slot selected";
          }
          continue;
        }

        if (doesRangeOverlap(rowDate.value, rowStart.value, rowEnd.value)) {
          row.classList.add("eco-recurring-slot-error");
          hasError = true;
          if (!message) {
            message = data.bookedRecurringSlotMessage || "Selected slot is already booked";
          }
          continue;
        }

        seen[key] = row;
      }

      setPreferredError(hasError ? message : "");
      return !hasError;
    }

    function createRecurringSlotRow(slotLabel, start, end) {
      var row = document.createElement("div");
      row.className = "eco-recurring-slot";
      slotIdCounter += 1;
      row.setAttribute("data-slot-id", "slot-" + slotIdCounter);

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
      timeStartSelect.appendChild(createSelectPlaceholder("Select"));
      for (var hour = recurringStartMin; hour <= recurringStartMax; hour += 1) {
        timeStartSelect.appendChild(createHourOption(hour));
      }
      timeStartField.appendChild(timeStartLabel);
      timeStartField.appendChild(timeStartSelect);
      row.appendChild(timeStartField);

      var timeEndField = document.createElement("p");
      var timeEndLabel = document.createElement("label");
      timeEndLabel.textContent = "Preferred End Time";
      var timeEndInput = document.createElement("select");
      timeEndInput.name = "eco_preferred_end_times[]";
      timeEndInput.className = "eco-recurring-end-time";
      timeEndInput.appendChild(createSelectPlaceholder("Select"));
      timeEndField.appendChild(timeEndLabel);
      timeEndField.appendChild(timeEndInput);
      row.appendChild(timeEndField);

      function onSlotChange() {
        refreshAvailability(false);
        syncRecurringDateAvailability();
        populateRecurringEndOptions(row, true);
        validateRecurringSlots();
      }

      timeStartSelect.addEventListener("change", onSlotChange);
      timeEndInput.addEventListener("change", validateRecurringSlots);
      dateInput.addEventListener("change", onSlotChange);

      var datePicker = flatpickr(dateInput, {
        dateFormat: "Y-m-d",
        minDate: start || "today",
        maxDate: end || null,
        onChange: function () {
          onSlotChange();
        },
      });

      recurringDatePickers.push({
        input: dateInput,
        picker: datePicker,
      });

      syncRecurringDateAvailability();

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

      setHourlyConflictState("");
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

      if (startDate.value && doesRangeOverlap(startDate.value, selectedStartHour, endHour)) {
        setHourlyConflictState(data.bookedTimeRangeMessage || "This time range is already booked.");
        endTime.value = "";
        price.textContent = formatPrice(0);
        return;
      }

      endTime.value = formatHour(endHour);
      price.textContent = formatPrice(hourlyRate * selectedHours);
    }

    function syncStartDateAvailability() {
      if (!startDatePicker || typeof startDatePicker.set !== "function") {
        return;
      }

      startDatePicker.set("disable", [function (dateObject) {
        if (plan.value !== "daily") {
          return false;
        }

        var year = dateObject.getFullYear();
        var month = String(dateObject.getMonth() + 1).padStart(2, "0");
        var day = String(dateObject.getDate()).padStart(2, "0");
        var dateKey = year + "-" + month + "-" + day;

        return getBookedRangesForDate(dateKey).length > 0;
      }]);
    }

    function applyPlanUI() {
      var selectedPlan = plan.value;
      clearPreferredInputs();
      endTime.value = "";

      if (selectedPlan === "hourly") {
        endDateBlock.style.display = "none";
        byId("eco_hourly_fields").style.display = "block";
        rebuildHourlyStartOptions();
        updateHourlyPrice();
        syncStartDateAvailability();
        return;
      }

      byId("eco_hourly_fields").style.display = "none";
      syncStartDateAvailability();

      if (selectedPlan === "daily") {
        endDateBlock.style.display = "none";
        endTime.value = formatHour(closeHour);
        price.textContent = formatPrice(prices.daily);
        return;
      }

      endDateBlock.style.display = "none";
      updateEndDateFromPlan();

      if (preferredHint) {
        preferredHint.textContent = "Select a preferred start and end time for each session. Maximum duration per session is " + recurringSessionHours + " hours.";
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

      validateRecurringSlots();
    }

    plan.addEventListener("change", function () {
      applyPlanUI();
      refreshAvailability(true);
    });
    startDate.addEventListener("change", function () {
      rebuildHourlyStartOptions();
      updateEndDateFromPlan();
      applyPlanUI();
      refreshAvailability(true);
    });
    startTime.addEventListener("change", updateHourlyPrice);
    hours.addEventListener("change", updateHourlyPrice);

    document.addEventListener("visibilitychange", function () {
      if (document.visibilityState === "visible") {
        refreshAvailability(true);
      }
    });

    if (availabilityRefreshMs > 0) {
      window.setInterval(function () {
        refreshAvailability(false);
      }, availabilityRefreshMs);
    }

    if (form) {
      form.addEventListener("submit", attemptSubmitAfterRefresh);
    }

    applyPlanUI();
    refreshAvailability(true);
  }

  document.addEventListener("DOMContentLoaded", init);
})();

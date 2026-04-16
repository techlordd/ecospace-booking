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
    var preferredHeading = byId("eco_preferred_heading");
    var preferredDays = byId("eco_preferred_days");
    var preferredHint = byId("eco_preferred_hint");
    var preferredError = byId("eco_preferred_error");
    var startTime = byId("eco_start_time");
    var hours = byId("eco_hours");
    var hoursField = byId("eco_hours_field");
    var endTime = byId("eco_end_time");
    var dailyHint = byId("eco_daily_hint");
    var price = byId("eco_price");
    var originalPrice = byId("eco_original_price");
    var discountBadge = byId("eco_discount_badge");
    var form = root.closest("form");

    var plans = data.plans || {};
    var defaultPlan = data.defaultPlan || "";
    var useAdvancedConfig = data.useAdvancedConfig === true;
    var openHour = Number(data.openHour || 9);
    var closeHour = Number(data.closeHour || 20);
    var recurringSessionHours = Number(data.recurringSessionHours || 8);
    var recurringStartMin = Number(data.recurringStartMinHour || openHour);
    var recurringStartMax = Number(data.recurringStartMaxHour || closeHour - 1);
    var recurringPlans = {};
    var productId = Number(data.productId || 0);
    var ajaxUrl = data.ajaxUrl || "";
    var availabilityNonce = data.availabilityNonce || "";
    var availabilityRefreshMs = Number(data.availabilityRefreshMs || 15000);
    var defaultPrice = Number(data.defaultPrice || 0);
    var bookedRecurringSlots = data.bookedRecurringSlots || {};
    var availabilityRefreshPromise = null;
    var lastAvailabilityHash = JSON.stringify(bookedRecurringSlots || {});
    var allowImmediateSubmit = false;
    var pendingSubmitter = null;
    var slotIdCounter = 0;
    var recurringDatePickers = [];

    for (var planKey in plans) {
      if (Object.prototype.hasOwnProperty.call(plans, planKey) && plans[planKey] && plans[planKey].type === "recurring") {
        recurringPlans[planKey] = true;
      }
    }

    function getPlanConfig(planKey) {
      return plans[planKey] || null;
    }

    function getCurrentPlanConfig() {
      return getPlanConfig(plan.value);
    }

    function getHourlyMinimumHours() {
      var hourlyPlan = getPlanConfig("hourly") || {};
      return Math.max(1, Number(hourlyPlan.min_hours || 1));
    }

    function getDailySessionHours() {
      var dailyPlan = getPlanConfig("daily") || {};
      return Math.max(1, Number(dailyPlan.session_hours || 8));
    }

    function getDailyStartMinHour() {
      var dailyPlan = getPlanConfig("daily") || {};
      return Number(dailyPlan.start_hour || openHour);
    }

    function getDailyEndMaxHour() {
      var dailyPlan = getPlanConfig("daily") || {};
      return Number(dailyPlan.end_hour || closeHour);
    }

    function getPlanPrice(planKey) {
      var planConfig = getPlanConfig(planKey);
      if (!planConfig) {
        return 0;
      }

      if (planConfig.type === "hourly") {
        return Number(data.hourlyRate || 0) * getHourlyMinimumHours();
      }

      return Number(planConfig.price || 0);
    }

    function getActiveDiscountPercent(planKey) {
      var disc = data.discount || {};
      if (!disc.enabled) { return 0; }
      var pct = Number(disc.percent || 0);
      if (pct <= 0) { return 0; }
      var today = disc.today || "";
      if (disc.start && today < disc.start) { return 0; }
      if (disc.end   && today > disc.end)   { return 0; }
      var plans = disc.plans || [];
      if (plans.length > 0 && plans.indexOf(planKey) === -1) { return 0; }
      return Math.min(100, pct);
    }

    function applyDiscount(basePrice, planKey) {
      var pct = getActiveDiscountPercent(planKey);
      if (pct <= 0) { return basePrice; }
      return Math.round(basePrice * (1 - pct / 100) * 100) / 100;
    }

    function showDiscountUI(basePrice, discountedPrice, planKey) {
      if (!originalPrice || !discountBadge) { return; }
      var pct = getActiveDiscountPercent(planKey);
      if (pct > 0 && discountedPrice < basePrice) {
        originalPrice.textContent = formatPrice(basePrice);
        originalPrice.style.display = "inline";
        discountBadge.textContent = "-" + pct + "%";
        discountBadge.style.display = "inline";
      } else {
        originalPrice.textContent = "";
        originalPrice.style.display = "none";
        discountBadge.textContent = "";
        discountBadge.style.display = "none";
      }
    }

    function getRecurringSessionsCount(planKey) {
      var planConfig = getPlanConfig(planKey);
      if (!planConfig) {
        return 0;
      }

      return Math.max(0, Number(planConfig.sessions || 0));
    }

    function syncHourlyFieldAttributes() {
      var minimumHours = getHourlyMinimumHours();
      var maximumHours = Math.max(1, closeHour - openHour);

      hours.min = String(minimumHours);
      hours.max = String(maximumHours);

      if (useAdvancedConfig && (!hours.value || Number(hours.value || 0) < minimumHours)) {
        hours.value = String(minimumHours);
      }
    }

    function setDisplayedPlanPrice(planKey) {
      var base = getPlanPrice(planKey);
      var discounted = applyDiscount(base, planKey);
      price.textContent = formatPrice(discounted);
      showDiscountUI(base, discounted, planKey);
    }

    // Sync WooCommerce product price display when booking plan price changes.
    // WC renders either a single <bdi> (no sale) or <del>…<ins>…</ins></del> (sale).
    // We keep references so the MutationObserver can update them live.
    var wcPriceWrap = document.querySelector('.price');
    var wcBdiSale    = wcPriceWrap && (wcPriceWrap.querySelector('ins .woocommerce-Price-amount bdi') || wcPriceWrap.querySelector('ins .woocommerce-Price-amount'));
    var wcBdiRegular = wcPriceWrap && (wcPriceWrap.querySelector('del .woocommerce-Price-amount bdi') || wcPriceWrap.querySelector('del .woocommerce-Price-amount'));
    var wcBdiSingle  = wcPriceWrap && (!wcBdiSale) && (wcPriceWrap.querySelector('.woocommerce-Price-amount bdi') || wcPriceWrap.querySelector('.woocommerce-Price-amount'));
    var wcBdiOrigHtml = wcBdiSingle ? wcBdiSingle.innerHTML : null;

    function buildPriceHtml(sym, rawText) {
      var amount = sym ? rawText.slice(sym.length) : rawText;
      return '<span class="woocommerce-Price-currencySymbol">' + sym + '</span>' + amount;
    }

    if ((wcBdiSale || wcBdiSingle) && price) {
      var wcPriceObserver = new MutationObserver(function () {
        var sym        = (window.ecoBookingData || {}).currencySymbol || '';
        var saleText   = (price.textContent || '').trim();
        var origText   = originalPrice ? (originalPrice.textContent || '').trim() : '';
        var hasDiscount = origText && origText !== '' && origText !== sym + '0';

        if (hasDiscount && wcBdiSale && wcBdiRegular) {
          // WC already rendered a sale markup (<del>/<ins>): update both sides
          wcBdiSale.innerHTML    = buildPriceHtml(sym, saleText);
          wcBdiRegular.innerHTML = buildPriceHtml(sym, origText);
        } else if (wcBdiSingle) {
          // Single price element: just sync current (discounted or plain) price
          if (saleText && saleText !== '0' && saleText !== sym + '0') {
            wcBdiSingle.innerHTML = buildPriceHtml(sym, saleText);
          } else if (wcBdiOrigHtml !== null) {
            wcBdiSingle.innerHTML = wcBdiOrigHtml;
          }
        }
      });
      wcPriceObserver.observe(price, { childList: true, characterData: true, subtree: true });
      if (originalPrice) {
        wcPriceObserver.observe(originalPrice, { childList: true, characterData: true, subtree: true });
      }
    }

    var startDatePicker = flatpickr(startDate, {
      dateFormat: "Y-m-d",
      minDate: "today",
    });

    var endDatePicker = flatpickr(endDate, {
      dateFormat: "Y-m-d",
      clickOpens: false,
    });

    if (defaultPlan && plan && plan.querySelector('option[value="' + defaultPlan + '"]')) {
      plan.value = defaultPlan;
    }

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

    function syncPlanFieldRequirements() {
      var selectedPlan = plan ? plan.value : "";
      var needsStartTime = selectedPlan === "hourly" || selectedPlan === "daily";
      var needsHours = selectedPlan === "hourly";

      if (startTime) {
        startTime.disabled = !needsStartTime;
        startTime.required = needsStartTime;
      }

      if (hours) {
        hours.disabled = !needsHours;
        hours.required = needsHours;
      }
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
        var disableDateMap = {};

        if (!useAdvancedConfig) {
          for (var bookedDateValue in bookedRecurringSlots) {
            if (!Object.prototype.hasOwnProperty.call(bookedRecurringSlots, bookedDateValue)) {
              continue;
            }

            if (!bookedRecurringSlots[bookedDateValue] || !bookedRecurringSlots[bookedDateValue].length) {
              continue;
            }

            disableDateMap[bookedDateValue] = true;
          }
        }

        for (var dateValue in selectedDates) {
          if (!Object.prototype.hasOwnProperty.call(selectedDates, dateValue)) {
            continue;
          }

          if (dateValue !== selfDate) {
            disableDateMap[dateValue] = true;
          }
        }

        var disableDates = Object.keys(disableDateMap);

        pickerEntry.picker.set("disable", disableDates);
      }
    }

    function refreshRenderedAvailability() {
      if (plan.value === "hourly") {
        rebuildHourlyStartOptions();
        updateHourlyPrice();
      } else {
        setDisplayedPlanPrice(plan.value);
      }

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
      var minimumHours = getHourlyMinimumHours();

      startTime.innerHTML = "";
      startTime.appendChild(createSelectPlaceholder("Select"));

      for (var hour = openHour; hour <= closeHour - 1; hour += 1) {
        var hasAvailableRange = false;

        if (!selectedDate) {
          hasAvailableRange = hour + minimumHours <= closeHour;
        } else {
          for (var endHour = hour + minimumHours; endHour <= closeHour; endHour += 1) {
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

    function rebuildDailyStartOptions() {
      if (!startTime) {
        return;
      }

      var selectedDate = startDate.value;
      var previousStartValue = startTime.value;
      var sessionHours = getDailySessionHours();
      var windowStart = getDailyStartMinHour();
      var windowEnd = getDailyEndMaxHour();
      var latestStart = windowEnd - sessionHours;

      startTime.innerHTML = "";
      startTime.appendChild(createSelectPlaceholder("Select"));

      for (var hour = windowStart; hour <= latestStart; hour += 1) {
        if (!selectedDate || !doesRangeOverlap(selectedDate, hour, hour + sessionHours)) {
          startTime.appendChild(createHourOption(hour));
        }
      }

      if (previousStartValue && startTime.querySelector('option[value="' + previousStartValue + '"]')) {
        startTime.value = previousStartValue;
      }
    }

    function updateDailyTimeWindow() {
      var selectedStartHour = Number(startTime.value || 0);
      var sessionHours = getDailySessionHours();
      var windowEnd = getDailyEndMaxHour();

      endTime.value = "";
      if (!selectedStartHour) {
        return;
      }

      if (selectedStartHour + sessionHours > windowEnd) {
        startTime.value = "";
        return;
      }

      endTime.value = formatHour(selectedStartHour + sessionHours);
      setDisplayedPlanPrice("daily");
    }

    function hasAvailableDailyWindow(dateValue) {
      var sessionHours = getDailySessionHours();
      var windowStart = getDailyStartMinHour();
      var windowEnd = getDailyEndMaxHour();
      var latestStart = windowEnd - sessionHours;

      for (var hour = windowStart; hour <= latestStart; hour += 1) {
        if (!doesRangeOverlap(dateValue, hour, hour + sessionHours)) {
          return true;
        }
      }

      return false;
    }

    function hasAvailableHourlyWindow(dateValue) {
      var minimumHours = getHourlyMinimumHours();

      for (var hour = openHour; hour <= closeHour - minimumHours; hour += 1) {
        if (!doesRangeOverlap(dateValue, hour, hour + minimumHours)) {
          return true;
        }
      }

      return false;
    }

    function setHourlyConflictState(message) {
      if (!hours) {
        return;
      }

      if (typeof hours.setCustomValidity === "function") {
        hours.setCustomValidity(message || "");
      }
    }

    function setStartTimeValidity(message) {
      if (!startTime) {
        return;
      }

      if (typeof startTime.setCustomValidity === "function") {
        startTime.setCustomValidity(message || "");
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

        if (!useAdvancedConfig) {
          var legacyDailyPlan = getCurrentPlanConfig() || {};
          if (doesRangeOverlap(startDate.value, Number(legacyDailyPlan.start_hour || openHour), Number(legacyDailyPlan.end_hour || closeHour))) {
            if (shouldShowBrowserMessage) {
              alert(data.dailyUnavailableMessage || "This date is no longer available for a daily booking.");
            }
            return false;
          }

          return true;
        }

        var dailyStartHour = Number(startTime.value || 0);
        var dailySessionHours = getDailySessionHours();
        if (!dailyStartHour) {
          setStartTimeValidity("Please select a time in for the daily plan.");
          if (shouldShowBrowserMessage && typeof startTime.reportValidity === "function") {
            startTime.reportValidity();
          }
          return false;
        }

        setStartTimeValidity("");

        if (doesRangeOverlap(startDate.value, dailyStartHour, dailyStartHour + dailySessionHours)) {
          if (shouldShowBrowserMessage) {
            alert(data.dailyUnavailableMessage || "This date is no longer available for a daily booking.");
          }
          return false;
        }

        return true;
      }

      if (isRecurringPlan(plan.value)) {
        return validateRecurringSlots(shouldShowBrowserMessage);
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

    function validateRecurringSlots(showIncompleteErrors) {
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
        syncRecurringSlotState(rows[i]);
      }

      for (var j = 0; j < rows.length; j += 1) {
        var row = rows[j];
        var rowDate = row.querySelector('input[name="eco_preferred_days[]"]');
        var rowStart = row.querySelector('select[name="eco_preferred_start_times[]"]');
        var rowEnd = row.querySelector('select[name="eco_preferred_end_times[]"]');

        if (showIncompleteErrors === true && (!rowDate || !rowStart || !rowEnd || !rowDate.value || !rowStart.value || !rowEnd.value)) {
          row.classList.add("eco-recurring-slot-error");
          setRecurringSlotCollapsed(row, false);
          hasError = true;
          if (!message) {
            message = "Please complete the office date, time in, and time out for every office day before booking.";
          }
          continue;
        }

        if (rowDate && rowDate.value) {
          if (seenDates[rowDate.value]) {
            row.classList.add("eco-recurring-slot-error");
            setRecurringSlotCollapsed(row, false);
            seenDates[rowDate.value].classList.add("eco-recurring-slot-error");
            setRecurringSlotCollapsed(seenDates[rowDate.value], false);
            hasError = true;
            if (!message) {
                message = data.duplicateRecurringDateMessage || "Office dates must be unique";
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
          setRecurringSlotCollapsed(row, false);
          seen[key].classList.add("eco-recurring-slot-error");
          setRecurringSlotCollapsed(seen[key], false);
          hasError = true;
          if (!message) {
              message = data.duplicateRecurringSlotMessage || "Duplicate office day selected";
          }
          continue;
        }

        if (doesRangeOverlap(rowDate.value, rowStart.value, rowEnd.value)) {
          row.classList.add("eco-recurring-slot-error");
          setRecurringSlotCollapsed(row, false);
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

    function getRecurringSlotSummary(row) {
      var rowDate = row.querySelector('input[name="eco_preferred_days[]"]');
      var rowStart = row.querySelector('select[name="eco_preferred_start_times[]"]');
      var rowEnd = row.querySelector('select[name="eco_preferred_end_times[]"]');
      var parts = [];

      if (rowDate && rowDate.value) {
        parts.push(rowDate.value);
      }

      if (rowStart && rowStart.value && rowEnd && rowEnd.value) {
        parts.push(formatHour(Number(rowStart.value || 0)) + " - " + formatHour(Number(rowEnd.value || 0)));
      } else if (rowStart && rowStart.value) {
        parts.push(formatHour(Number(rowStart.value || 0)));
      }

      return parts.length ? parts.join(" - ") : "Not set";
    }

    function isRecurringSlotComplete(row) {
      var rowDate = row.querySelector('input[name="eco_preferred_days[]"]');
      var rowStart = row.querySelector('select[name="eco_preferred_start_times[]"]');
      var rowEnd = row.querySelector('select[name="eco_preferred_end_times[]"]');

      return !!(rowDate && rowDate.value && rowStart && rowStart.value && rowEnd && rowEnd.value);
    }

    function syncRecurringSlotState(row) {
      var isComplete = isRecurringSlotComplete(row);

      row.classList.toggle("eco-recurring-slot-complete", isComplete);
      row.classList.toggle("eco-recurring-slot-incomplete", !isComplete);
    }

    function refreshRecurringSlotHeader(row) {
      var summary = row.querySelector(".eco-recurring-slot-summary");
      if (!summary) {
        return;
      }

      summary.textContent = getRecurringSlotSummary(row);
      syncRecurringSlotState(row);
    }

    function setRecurringSlotCollapsed(row, collapsed) {
      var isCollapsed = collapsed === true;
      var body = row.querySelector(".eco-recurring-slot-body");
      var toggle = row.querySelector(".eco-recurring-slot-toggle");

      row.classList.toggle("eco-recurring-slot-collapsed", isCollapsed);
      if (body) {
        body.style.display = isCollapsed ? "none" : "";
      }

      if (toggle) {
        toggle.setAttribute("aria-expanded", isCollapsed ? "false" : "true");
      }
    }

    function createRecurringSlotControls(defaultCollapsed) {
      var controls = document.createElement("div");
      controls.className = "eco-recurring-controls";

      var expandButton = document.createElement("button");
      expandButton.type = "button";
      expandButton.className = "eco-recurring-controls-button";
      expandButton.textContent = "Expand all";

      var collapseButton = document.createElement("button");
      collapseButton.type = "button";
      collapseButton.className = "eco-recurring-controls-button";
      collapseButton.textContent = "Collapse all";

      expandButton.addEventListener("click", function () {
        var rows = preferredDays.querySelectorAll(".eco-recurring-slot");
        for (var i = 0; i < rows.length; i += 1) {
          setRecurringSlotCollapsed(rows[i], false);
        }
      });

      collapseButton.addEventListener("click", function () {
        var rows = preferredDays.querySelectorAll(".eco-recurring-slot");
        for (var i = 0; i < rows.length; i += 1) {
          setRecurringSlotCollapsed(rows[i], true);
        }
      });

      controls.appendChild(expandButton);
      controls.appendChild(collapseButton);
      preferredDays.appendChild(controls);
    }

    function createRecurringSlotRow(slotLabel, start, end, defaultCollapsed) {
      var row = document.createElement("div");
      row.className = "eco-recurring-slot";
      slotIdCounter += 1;
      row.setAttribute("data-slot-id", "slot-" + slotIdCounter);

      var rowHeader = document.createElement("div");
      rowHeader.className = "eco-recurring-slot-header";

      var toggleButton = document.createElement("button");
      toggleButton.type = "button";
      toggleButton.className = "eco-recurring-slot-toggle";
      toggleButton.setAttribute("aria-expanded", "true");

      var rowTitle = document.createElement("span");
      rowTitle.className = "eco-recurring-slot-title";
      rowTitle.textContent = slotLabel || "Office Day";

      var rowSummary = document.createElement("span");
      rowSummary.className = "eco-recurring-slot-summary";
      rowSummary.textContent = "Not set";

      toggleButton.appendChild(rowTitle);
      toggleButton.appendChild(rowSummary);
      rowHeader.appendChild(toggleButton);
      row.appendChild(rowHeader);

      var rowBody = document.createElement("div");
      rowBody.className = "eco-recurring-slot-body";

      var dateField = document.createElement("p");
      var dateLabel = document.createElement("label");
        dateLabel.textContent = "Office Date";
      var dateInput = document.createElement("input");
      dateInput.type = "text";
      dateInput.className = "eco_calendar";
      dateInput.name = "eco_preferred_days[]";
      dateInput.autocomplete = "off";
      dateField.appendChild(dateLabel);
      dateField.appendChild(dateInput);
      rowBody.appendChild(dateField);

      var timeStartField = document.createElement("p");
      var timeStartLabel = document.createElement("label");
        timeStartLabel.textContent = "Time In";
      var timeStartSelect = document.createElement("select");
      timeStartSelect.name = "eco_preferred_start_times[]";
      timeStartSelect.appendChild(createSelectPlaceholder("Select"));
      for (var hour = recurringStartMin; hour <= recurringStartMax; hour += 1) {
        timeStartSelect.appendChild(createHourOption(hour));
      }
      timeStartField.appendChild(timeStartLabel);
      timeStartField.appendChild(timeStartSelect);
      rowBody.appendChild(timeStartField);

      var timeEndField = document.createElement("p");
      var timeEndLabel = document.createElement("label");
        timeEndLabel.textContent = "Time Out";
      var timeEndInput = document.createElement("select");
      timeEndInput.name = "eco_preferred_end_times[]";
      timeEndInput.className = "eco-recurring-end-time";
      timeEndInput.appendChild(createSelectPlaceholder("Select"));
      timeEndField.appendChild(timeEndLabel);
      timeEndField.appendChild(timeEndInput);
      rowBody.appendChild(timeEndField);
      row.appendChild(rowBody);

      function onSlotChange() {
        refreshAvailability(false);
        syncRecurringDateAvailability();
        populateRecurringEndOptions(row, true);
        refreshRecurringSlotHeader(row);
        validateRecurringSlots();
      }

      timeStartSelect.addEventListener("change", onSlotChange);
      timeEndInput.addEventListener("change", function () {
        refreshRecurringSlotHeader(row);
        validateRecurringSlots();
      });
      dateInput.addEventListener("change", onSlotChange);

      toggleButton.addEventListener("click", function () {
        setRecurringSlotCollapsed(row, !row.classList.contains("eco-recurring-slot-collapsed"));
      });

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
      refreshRecurringSlotHeader(row);
      setRecurringSlotCollapsed(row, defaultCollapsed === true);
    }

    function createPreferredInputs(count, labelPrefix) {
      clearPreferredInputs();
      var start = parseDate(startDate.value);
      var end = parseDate(endDate.value);

      if (count > 1) {
        createRecurringSlotControls(false);
      }

      for (var i = 0; i < count; i += 1) {
        var slotLabel = labelPrefix ? labelPrefix + " " + (i + 1) : "Office Day " + (i + 1);
        createRecurringSlotRow(slotLabel, start, end, false);
      }
    }

    function createMonthlyInputs(perWeek) {
      clearPreferredInputs();
      var start = parseDate(startDate.value);
      var end = parseDate(endDate.value);

      createRecurringSlotControls(true);

      for (var week = 1; week <= 4; week += 1) {
        for (var i = 0; i < perWeek; i += 1) {
          createRecurringSlotRow("Week " + week + " - Office Day " + (i + 1), start, end, true);
        }
      }
    }

    function createRecurringInputsForPlan(planKey) {
      var planConfig = getPlanConfig(planKey) || {};
      var sessionCount = getRecurringSessionsCount(planKey);

      if (!sessionCount) {
        clearPreferredInputs();
        return;
      }

      if (
        String(planKey).indexOf("monthly") === 0 &&
        Number(planConfig.window_value || 1) === 1 &&
        sessionCount % 4 === 0
      ) {
        createMonthlyInputs(sessionCount / 4);
        return;
      }

      createPreferredInputs(sessionCount, "Office Day");
    }

    function updateEndDateFromPlan() {
      var selectedStart = parseDate(startDate.value);
      var selectedPlanConfig = getCurrentPlanConfig();
      if (!selectedStart) {
        endDate.value = "";
        return;
      }

      if (!selectedPlanConfig || selectedPlanConfig.type !== "recurring") {
        endDate.value = "";
        return;
      }

      if (selectedPlanConfig.window_unit === "months") {
        endDatePicker.setDate(addMonths(selectedStart, Number(selectedPlanConfig.window_value || 1)), true, "Y-m-d");
      } else {
        endDatePicker.setDate(addDays(selectedStart, Number(selectedPlanConfig.window_value || 1)), true, "Y-m-d");
      }
    }

    function updateHourlyPrice() {
      var selectedStartHour = Number(startTime.value || 0);
      var selectedHours = Number(hours.value || 0);
      var hourlyRate = Number(data.hourlyRate || 0);
      var minimumHours = getHourlyMinimumHours();

      syncHourlyFieldAttributes();
      selectedHours = Number(hours.value || (useAdvancedConfig ? minimumHours : 0));

      setHourlyConflictState("");
      endTime.value = "";

      if (!selectedHours) {
        var defBase = useAdvancedConfig ? defaultPrice : 0;
        price.textContent = formatPrice(applyDiscount(defBase, "hourly"));
        showDiscountUI(defBase, applyDiscount(defBase, "hourly"), "hourly");
        return;
      }

      if (!selectedStartHour) {
        var noTimeBase = useAdvancedConfig ? hourlyRate * selectedHours : 0;
        price.textContent = formatPrice(applyDiscount(noTimeBase, "hourly"));
        showDiscountUI(noTimeBase, applyDiscount(noTimeBase, "hourly"), "hourly");
        return;
      }

      var endHour = selectedStartHour + selectedHours;
      if (endHour > closeHour) {
        alert(data.invalidHoursMessage || "Hours exceed closing time");
        hours.value = String(minimumHours);
        var excBase = hourlyRate * minimumHours;
        price.textContent = formatPrice(applyDiscount(excBase, "hourly"));
        showDiscountUI(excBase, applyDiscount(excBase, "hourly"), "hourly");
        return;
      }

      if (startDate.value && doesRangeOverlap(startDate.value, selectedStartHour, endHour)) {
        setHourlyConflictState(data.bookedTimeRangeMessage || "This time range is already booked.");
        endTime.value = "";
        var conflictBase = hourlyRate * selectedHours;
        price.textContent = formatPrice(applyDiscount(conflictBase, "hourly"));
        showDiscountUI(conflictBase, applyDiscount(conflictBase, "hourly"), "hourly");
        return;
      }

      endTime.value = formatHour(endHour);
      var finalBase = hourlyRate * selectedHours;
      price.textContent = formatPrice(applyDiscount(finalBase, "hourly"));
      showDiscountUI(finalBase, applyDiscount(finalBase, "hourly"), "hourly");
    }

    function syncStartDateAvailability() {
      if (!startDatePicker || typeof startDatePicker.set !== "function") {
        return;
      }

      startDatePicker.set("disable", [function (dateObject) {
        var year = dateObject.getFullYear();
        var month = String(dateObject.getMonth() + 1).padStart(2, "0");
        var day = String(dateObject.getDate()).padStart(2, "0");
        var dateKey = year + "-" + month + "-" + day;

        if (!useAdvancedConfig) {
          if (plan.value === "hourly") {
            return !hasAvailableHourlyWindow(dateKey);
          }
          return getBookedRangesForDate(dateKey).length > 0;
        }

        if (plan.value === "daily") {
          return !hasAvailableDailyWindow(dateKey);
        }

        if (plan.value === "hourly") {
          return !hasAvailableHourlyWindow(dateKey);
        }

        if (isRecurringPlan(plan.value)) {
          return false;
        }

        return false;
      }]);
    }

    function applyPlanUI() {
      var selectedPlan = plan.value;
      var selectedPlanConfig = getCurrentPlanConfig();
      clearPreferredInputs();
      endTime.value = "";

      if (!selectedPlanConfig) {
        if (preferredHeading) {
          preferredHeading.style.display = "none";
        }
        syncPlanFieldRequirements();
        price.textContent = formatPrice(0);
        return;
      }

      if (selectedPlan === "hourly") {
        endDateBlock.style.display = "none";
        byId("eco_end_time_block").style.display = "";
        byId("eco_hourly_fields").style.display = "block";
        if (preferredHeading) {
          preferredHeading.style.display = "none";
        }
        if (hoursField) {
          hoursField.style.display = "";
        }
        if (dailyHint) {
          dailyHint.style.display = "none";
        }
        syncPlanFieldRequirements();
        syncHourlyFieldAttributes();
        rebuildHourlyStartOptions();
        updateHourlyPrice();
        syncStartDateAvailability();
        return;
      }

      byId("eco_hourly_fields").style.display = "none";
      syncStartDateAvailability();

      if (selectedPlan === "daily") {
        endDateBlock.style.display = "none";
        byId("eco_end_time_block").style.display = "";
        byId("eco_hourly_fields").style.display = "block";
        if (preferredHeading) {
          preferredHeading.style.display = "none";
        }
        if (hoursField) {
          hoursField.style.display = "none";
        }
        if (dailyHint) {
          dailyHint.style.display = "";
        }
        syncPlanFieldRequirements();
        rebuildDailyStartOptions();
        updateDailyTimeWindow();
        setDisplayedPlanPrice(selectedPlan);
        return;
      }

      byId("eco_end_time_block").style.display = "none";
      if (dailyHint) {
        dailyHint.style.display = "none";
      }
      if (preferredHeading) {
        preferredHeading.style.display = "block";
      }
      syncPlanFieldRequirements();
      endDateBlock.style.display = "none";
      updateEndDateFromPlan();

      if (preferredHint) {
          preferredHint.textContent = "Select a time in and time out for each office day. Maximum duration per office day is " + recurringSessionHours + " hours.";
      }

      setDisplayedPlanPrice(selectedPlan);
      createRecurringInputsForPlan(selectedPlan);

      validateRecurringSlots();
    }

    plan.addEventListener("change", function () {
      applyPlanUI();
      refreshAvailability(true);
    });
    startDate.addEventListener("change", function () {
      rebuildHourlyStartOptions();
      rebuildDailyStartOptions();
      updateEndDateFromPlan();
      applyPlanUI();
      refreshAvailability(true);
    });
    startTime.addEventListener("change", function () {
      setStartTimeValidity("");
      if (plan.value === "daily") {
        updateDailyTimeWindow();
        validateCurrentPlanAvailability(false);
        return;
      }

      updateHourlyPrice();
    });
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

    syncHourlyFieldAttributes();
    syncPlanFieldRequirements();
    applyPlanUI();
    refreshAvailability(true);
  }

  document.addEventListener("DOMContentLoaded", init);
})();

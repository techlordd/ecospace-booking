
<?php
/**
 * Plugin Name: Ecospace Workspace Booking
 * Description: Coworking booking system with hourly, daily, weekly and monthly plans with calendar picker.
 * Version: 14.1
 * Author: Ecospace
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_product_options_general_product_data','eco_product_fields');
function eco_product_fields(){

echo '<div class="options_group">';

woocommerce_wp_checkbox(array(
'id' => '_eco_enable_booking',
'label' => 'Enable Workspace Booking'
));

woocommerce_wp_text_input(array(
'id' => '_eco_hourly_rate',
'label' => 'Hourly Rate',
'type' => 'number'
));

woocommerce_wp_text_input(array(
'id' => '_eco_seat_capacity',
'label' => 'Seat Capacity',
'type' => 'number'
));

echo '</div>';
}

add_action('woocommerce_process_product_meta','eco_save_fields');
function eco_save_fields($post_id){

update_post_meta($post_id,'_eco_enable_booking', isset($_POST['_eco_enable_booking']) ? 'yes' : 'no');

if(isset($_POST['_eco_hourly_rate'])){
update_post_meta($post_id,'_eco_hourly_rate', sanitize_text_field($_POST['_eco_hourly_rate']));
}

if(isset($_POST['_eco_seat_capacity'])){
update_post_meta($post_id,'_eco_seat_capacity', sanitize_text_field($_POST['_eco_seat_capacity']));
}

}

add_action('woocommerce_before_add_to_cart_button','eco_booking_ui');
function eco_booking_ui(){

global $product;

$enabled = get_post_meta($product->get_id(),'_eco_enable_booking',true);
if($enabled !== 'yes'){ return; }

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<div class="ecospace-booking-ui">

<p>
<label>Plan</label>
<select id="eco_plan" name="eco_plan">
<option value="hourly" selected>Hourly</option>
<option value="daily">Daily</option>
<option value="weekly3">Weekly (3x)</option>
<option value="weekly5">Weekly (5x)</option>
<option value="monthly3">Monthly (3x)</option>
<option value="monthly5">Monthly (5x)</option>
</select>
</p>

<p>
<label>Start Date</label>
<input type="text" id="eco_start_date" name="eco_start_date">
</p>

<p id="eco_end_date_block">
<label>End Date</label>
<input type="text" id="eco_end_date" name="eco_end_date" readonly>
</p>

<div id="eco_preferred_days"></div>

<p id="eco_hourly_fields">

<label>Start Time</label>
<select id="eco_start_time" name="eco_start_time">
<option value="">Select</option>
<option value="9">9:00 AM</option>
<option value="10">10:00 AM</option>
<option value="11">11:00 AM</option>
<option value="12">12:00 PM</option>
<option value="13">1:00 PM</option>
<option value="14">2:00 PM</option>
<option value="15">3:00 PM</option>
<option value="16">4:00 PM</option>
<option value="17">5:00 PM</option>
<option value="18">6:00 PM</option>
<option value="19">7:00 PM</option>
</select>

<label>Hours</label>
<input type="number" id="eco_hours" name="eco_hours" min="1" max="11">

</p>

<p>
<label>End Time</label>
<input type="text" id="eco_end_time" name="eco_end_time" readonly>
</p>

<p>
<strong>Price:</strong>
<span id="eco_price">0</span>
</p>

</div>

<script>

document.addEventListener("DOMContentLoaded",function(){

let plan=document.getElementById("eco_plan");
let startDate=document.getElementById("eco_start_date");
let endDate=document.getElementById("eco_end_date");
let preferredDays=document.getElementById("eco_preferred_days");
let startTime=document.getElementById("eco_start_time");
let hours=document.getElementById("eco_hours");
let endTime=document.getElementById("eco_end_time");
let price=document.getElementById("eco_price");

const hourlyRate = 750;
const dailyPrice = 4800;
const weekly5Price = 20000;
const weekly3Price = 12000;

flatpickr(startDate,{dateFormat:"Y-m-d"});
flatpickr(endDate,{dateFormat:"Y-m-d"});

function formatAMPM(hour){

let suffix = hour >= 12 ? "PM" : "AM";
let h = hour % 12;
if(h === 0) h = 12;
return h + ":00 " + suffix;

}

function calculateHourly(){

let s=parseInt(startTime.value);
let h=parseInt(hours.value);

if(!s || !h){return;}

let e=s+h;

if(e>20){
alert("Hours exceed closing time (8PM)");
hours.value="";
return;
}

endTime.value=formatAMPM(e);
price.innerText="₦"+(h*hourlyRate);

}

startTime.addEventListener("change",calculateHourly);
hours.addEventListener("change",calculateHourly);

function addDays(date,days){
let result=new Date(date);
result.setDate(result.getDate()+days);
return result;
}

function addMonths(date,months){
let result=new Date(date);
result.setMonth(result.getMonth()+months);
return result;
}

function createCalendar(count){

preferredDays.innerHTML="<p><strong>Select Preferred Dates</strong></p>";

for(let i=0;i<count;i++){

let input=document.createElement("input");
input.type="text";
input.className="eco_calendar";
input.name="eco_preferred_days[]";

preferredDays.appendChild(input);

flatpickr(input,{dateFormat:"Y-m-d"});

}

}

function createMonthlyGrouped(){

preferredDays.innerHTML="<p><strong>Select Preferred Dates (Grouped Weekly)</strong></p>";

for(let w=1; w<=4; w++){

let label=document.createElement("p");
label.innerHTML="<strong>Week "+w+"</strong>";
preferredDays.appendChild(label);

let count = plan.value==="monthly3" ? 3 : 5;

for(let i=0;i<count;i++){

let input=document.createElement("input");
input.type="text";
input.name="eco_preferred_days[]";

preferredDays.appendChild(input);

flatpickr(input,{dateFormat:"Y-m-d"});

}

}

}

function applyPlanUI(){

preferredDays.innerHTML="";

if(plan.value==="hourly"){

document.getElementById("eco_end_date_block").style.display="none";
document.getElementById("eco_hourly_fields").style.display="block";

}

else if(plan.value==="daily"){

document.getElementById("eco_end_date_block").style.display="none";
document.getElementById("eco_hourly_fields").style.display="none";

endTime.value="8:00 PM";
price.innerText="₦"+dailyPrice;

}

else if(plan.value==="weekly3"){

document.getElementById("eco_hourly_fields").style.display="none";
price.innerText="₦"+weekly3Price;
createCalendar(3);

}

else if(plan.value==="weekly5"){

document.getElementById("eco_hourly_fields").style.display="none";
price.innerText="₦"+weekly5Price;
createCalendar(5);

}

else if(plan.value==="monthly3" || plan.value==="monthly5"){

document.getElementById("eco_hourly_fields").style.display="none";
createMonthlyGrouped();

}

document.getElementById("eco_end_date_block").style.display="block";

}

plan.addEventListener("change",applyPlanUI);
applyPlanUI();

startDate.addEventListener("change",function(){

let start=new Date(startDate.value);

if(plan.value.includes("weekly")){
let end=addDays(start,7);
endDate._flatpickr.setDate(end);
}

if(plan.value.includes("monthly")){
let end=addMonths(start,1);
endDate._flatpickr.setDate(end);
}

});

});

</script>

<?php
}

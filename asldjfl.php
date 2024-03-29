<?php
include "pin/header.php";
include "pin/sidenav.php";

$user_name = $_SESSION['user_name'];
$emp_id = $_SESSION['emp_id'];
$user_role = $_SESSION['user_role'];


$month = isset($_GET['month']) ? $_GET['month'] : date("Y-m");
$eid = isset($_GET['emp_id']) ? $_GET['emp_id'] : $emp_id;

if ($user_role != 0) {
    if (isset($_GET['emp_id'])) {
        $ex = mysqli_num_rows(mysqli_query($db, "select id from users where employee_id='$_GET[emp_id]' and leader='$emp_id'"));
        if ($ex == 0 && $_GET['emp_id'] != $emp_id) {
            echo "<script>location.href='404'</script>";
        }
    }
}

?>

<!-- Plugin css -->
<link href="assets/libs/fullcalendar-core/main.min.css" rel="stylesheet" type="text/css" />
<link href="assets/libs/fullcalendar-daygrid/main.min.css" rel="stylesheet" type="text/css" />
<link href="assets/libs/fullcalendar-bootstrap/main.min.css" rel="stylesheet" type="text/css" />


<style>
    .fc-toolbar {
        flex-wrap: wrap;
    }
    .fc-toolbar > div{
        margin: 0 !important;
    }
   

    .fc-highlight {
        background: #bce8f1 !important;
        opacity: 1 !important;
    }
    .alert-info {
        color: #000 !important;
        background-color: #7cefd273 !important;
        border-color: #7cefd273 !important;
    }
    .fc-event,
    td {
        cursor: pointer;
    }

    .attendance-wrapper {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .attendance-status {
        width: 160px;
        height: 120px;
        background: #fff;
        padding: 1rem;
    }

    .attendance-status h6::before {
        content: '';
        width: 10px;
        height: 10px;
        background: #fff;
        display: inline-block;
        border-radius: 50%;
        position: relative;
        top: 50%;
        left: -5px;
    }

    .attendance-status:nth-of-type(1) h6::before {
        background: #198754;
    }

    .attendance-status:nth-of-type(2) h6::before {
        background: #ffc107;
    }

    .attendance-status:nth-of-type(3) h6::before {
        background: #0d6efd;
    }

    .attendance-status:nth-of-type(4) h6::before {
        background: #dc3545;
    }

    .attendance-status:nth-of-type(5) h6::before {
        background: #212529;
    }

    .attendance-status:nth-of-type(6) h6::before {
        background: #212529;
    }

    .attendance-change-bg {
        background-color: #285430;
    }

    .attendance-less-bg {
        background-color: #00c508;
    }
</style>


<div class="content-page" style="position:relative;">


    <div class="content">
        <div class="container-fluid">
            <div class="mt-5"></div>

            <div class="row align-items-start">
                <div class="col-md-4 mb-3">
                    <h4 class="m-0">Attendance <span class="text-primary" id="current_selected_emp" style="font-size:1rem;"></span></h4>
                </div>
                <div class="col-md-4 mb-3">
                    <p class="text-dark mb-1" id="office_timing"></p>
                    <p id="with_grace_timing" class="text-dark mb-0" style="display:none;"><strong><i class="uil uil-stopwatch text-primary"></i>&nbsp;With Grace Timing: </strong><span>10:00 AM To 7:00 PM</span></p>
                    <div id="leave_policy">
                        
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                <?php if ($user_role < 2) { ?>
                        <div class="form-group mb-1">
                            <select data-plugin="customselect" name="employee_id" class="form-control custom-select-picker" data-placeholder="Select Employee" id="employee_id">
                                <option></option>
                            </select>
                        </div>
                    <?php } ?>
                    <div class="form-group">
                        <input type="month" class="form-control" id="attendanceMonth" value="<?php echo $month; ?>">
                    </div>
                </div>

            </div>

            <?php if ($user_role < 2) { ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="w-100 h-100 d-flex align-items-center justify-content-end">
                            <a href="javascript:void(0);" class="btn btn-danger" id="addAttendance">Add Attendance</a>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="attendance-wrapper">
                <div class="attendance-status">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                        <h2 class="mb-0" id="present">0</h2>
                        <h6 class="mb-0">Present</h6>
                    </div>
                </div>
                <div class="attendance-status">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                        <h2 class="mb-0" id="halfDay">0</h2>
                        <h6 class="mb-0">Half Day</h6>
                    </div>
                </div>
                <div class="attendance-status">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                        <h2 class="mb-0" id="workFromHome">0</h2>
                        <h6 class="mb-0">Work From Home</h6>
                    </div>
                </div>
                <div class="attendance-status">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                        <h2 class="mb-0" id="absent">0</h2>
                        <h6 class="mb-0">Absent</h6>
                    </div>
                </div>
                <div class="attendance-status">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                        <h2 class="mb-0" id="weekOff">0</h2>
                        <h6 class="mb-0">Week Off</h6>
                    </div>
                </div>
                <div class="attendance-status">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                        <h2 class="mb-0" id="holiday">0</h2>
                        <h6 class="mb-0">Holiday</h6>
                    </div>
                </div>

            </div>



            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div id="calendar"></div>
                        </div>
                    </div> 
                </div>
            </div>



        </div>
    </div>
</div>





<?php
include "pin/footer.php";
?>
<!-- plugin js -->
<script src="assets/libs/fullcalendar-core/main.min.js"></script>
<script src="assets/libs/fullcalendar-bootstrap/main.min.js"></script>
<script src="assets/libs/fullcalendar-daygrid/main.min.js"></script>
<script src="assets/libs/fullcalendar-interaction/main.min.js"></script>


<script>
    fetch_employees('employee_id', '<?php echo isset($_GET['emp_id']) ? $_GET['emp_id'] : $emp_id; ?>', '<?php echo $currentPageName; ?>');
    
    var final_employee_id = `${($('#employee_id').val()=='' || $('#employee_id').val()==undefined || $('#employee_id').val()==null)?'<?php echo $eid; ?>':$('#employee_id').val()}`;
    function get_final_employee_id() {
        final_employee_id = `${($('#employee_id').val()=='' || $('#employee_id').val()==undefined || $('#employee_id').val()==null)?'<?php echo $eid; ?>':$('#employee_id').val()}`;
        return final_employee_id;
    }

    var final_month_date = `${($("#attendanceMonth").val()=='' || $("#attendanceMonth").val()==undefined || $("#attendanceMonth").val()==null)?'<?php echo $month; ?>':$("#attendanceMonth").val()}`;
    function get_final_month_date() {
        final_month_date = `${($("#attendanceMonth").val()=='' || $("#attendanceMonth").val()==undefined || $("#attendanceMonth").val()==null)?'<?php echo $month; ?>':$("#attendanceMonth").val()}`;
        return final_month_date;
    }
   

    function createDate(date) {
        return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, 0) + "-" + String(date.getDate()).padStart(2, 0);
    }

    function createMonth(date) {
        return date.getFullYear() + "-" + String(date.getMonth() + 1).padStart(2, 0);
    }

    const textColor = {
                Present: 'text-success',
                HalfDay: 'text-warning',
                Absent: 'text-danger',
                WeekOff: 'text-primary',
                Holiday: 'text-seconday',
                WorkFromHome: 'text-info'
            };
                        
    const status = [
        'Pending',
        'Approved',
        'Rejected'
    ];

    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    var events = [];
    let calendarObj = null;
    function loadCalender() {
        if(calendarObj!==null){
            calendarObj.destroy();
        }
        
        let calendar = $("#calendar");
        calendarObj = new FullCalendar.Calendar(calendar[0], {
            destroy:true,
            plugins: ["bootstrap", "interaction", "dayGrid"],
            defaultView: "dayGridMonth",
            themeSystem: "bootstrap",
            bootstrapFontAwesome: true,
            defaultDate: '<?php echo date("Y-m-01", strtotime($month)); ?>',
            header: {
                left: "title",
                center: "",
                right: "prev,next custom_today",
            },
            customButtons: {
                custom_today: {
                    text: 'Today'
                }
            },
            buttonText: {
                prev: "Prev",
                next: "Next",
                today: "Today"
            },
            weekNumbers: true,
            handleWindowResize: true,
            height: $(window).height() - 200,
            selectable: true,
            dayMaxEvents: true,
            dayMaxEventRows: 1,
            events: events,
        });

        calendarObj.render()


        calendarObj.on('eventClick', async function(e) {
            let event_info = e.event;
            // console.log(event_info);
            let ecDate = new Date(event_info.start);
            let fecDate = ecDate.toDateString();
           
            let response = await fetch('fun/getAttendance.php', {
                method: "POST",
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    employeeTimingId: get_final_employee_id(),
                    timeDate: fecDate,
                    eventClickId: event_info.id
                }),
            });
            let data = await response.json();
            // console.log(data);
            if (data.noData == undefined) {

                let leave_request ='';
                if (data.requestLeave != undefined) {
                    leave_request += `<h5 class="mb-0">Leave Request: </h5><ul>`
                    if (data.requestLeave.statusBy != null) {
                        leave_request += `<li><p class="mb-0 text-dark" id="requestStatus">Your <strong>${data.requestLeave.leaveType}</strong>, Request <strong>${status[data.requestLeave.leaveStatus]}</strong> By <strong>${data.requestLeave.statusBy}</strong>!</p></li>`;
                    } else {
                        leave_request += `<li><p class="mb-0 text-dark" id="requestStatus">Your <strong>${data.requestLeave.leaveType}</strong>, Request <strong>${status[data.requestLeave.leaveStatus]}</strong>!</p></li>`;
                    }
                    leave_request += `<li><p class="mb-0 text-dark" id="requestReason"><strong>Reason: </strong>${data.requestLeave.leaveReason}</p></li></ul>`;
                }
                let issue_request = '';
                if (data.issueWithDate != undefined) {
                    issue_request += `<h5 class="mb-0">Issue with date Request: </h5><ul>`
                    if (data.issueWithDate.statusBy != null) {
                        issue_request += `<li><p class="mb-0 text-dark" id="issueStatus">Your <strong>${data.issueWithDate.issueType}</strong>, Issue Request <strong>${status[data.issueWithDate.issueStatus]}</strong> By <strong>${data.issueWithDate.statusBy}</strong>!</p></li>`;
                    } else {
                        issue_request += `<li><p class="mb-0 text-dark" id="issueStatus">Your Issue Request <strong>${status[data.issueWithDate.issueStatus]}</strong>!</p></li>`;
                    }
                    issue_request += `<li><p class="mb-0 text-dark" id="issueReason"><strong>Reason: </strong>${data.issueWithDate.issueReason}</p></li></ul>`;
                    $("#issueWithDate").hide();
                }

                let previous_attendance = '';
                if (data.attendanceStatus != null) {
                    previous_attendance += `<h5 class="mb-0">Previous Attendance:</h5><ul><li>${data.attendanceStatus}</li></ul>`;
                    $("#issueWithDate").hide();
                }
                let hours=0,minutes=0;
                if(data.totalHours!=undefined){
                    let thour = (data.totalHours).split(':');
                    hours = thour[0]?thour[0]:0;
                    minutes = thour[1]?thour[1]:0;
                }

                let issue_html = `
                    <h4 class="mb-3"><i class="uil uil-stopwatch text-danger"></i>&nbsp;<span id="currentEventDate">${fecDate}</span></h4>
                    <div class="mb-3">${leave_request}</div>
                    <div class="mb-3">${issue_request}</div>
                    <div class="mb-3">${previous_attendance}</div>
                    <div class="mb-3">
                        <h5 class="mb-0 text-dark mb-2"><span id="attendanceStatus" class="${textColor[(data.attendance_status).replaceAll(" ", "")]}">${data.attendance_status}</span></h5>
                        <p class="mb-0 text-dark"><strong><i class="uil uil-clock-ten text-primary"></i>&nbsp;Punch In: <span id="punchInTime">${data.punch_in}</span></strong></p>
                        <p class="mb-0 text-dark"><strong><i class="uil uil-clock-ten text-primary"></i>&nbsp;Punch Out: <span id="punchOutTime">${data.punch_out}</span></strong></p>
                        <p class="mb-0 text-dark"><strong><i class="uil uil-clock-ten text-primary"></i>&nbsp;Total Hours: <span id="totalHours">${hours} Hour(s) ${minutes} Minute(s)</span></strong></p>
                        <p class="mb-0 text-dark"><strong><i class="uil uil-text-size text-primary"></i>&nbsp;Comment: <span id="attendance_comment">${!data.attendance_comment?'':data.attendance_comment}</span></strong></p>
                    </div>
                    <?php if ($user_role > 0) { ?>
                        <div>
                            <h6 class="${(issue_request=='' || previous_attendance=='')?'d-block':'d-none'}"><a href="add_issueDate?isDate=${fecDate}" id="issueWithDate" target="_blank"><i class="uil uil-calendar-alt mr-2"></i>Issue With that Date ! <i class="uil uil-arrow-right ml-2"></i></a></h6>
                        </div>
                    <?php } ?>
                    <?php if ($user_role == 0) { ?>
                        <div>
                            <h6><a href="javascript:void(0);" class="text-danger" id="changeAttendance">Update Attendance !</a></h6>
                        </div>
                    <?php } ?>
                `;

                if(data.total_leave.max_leave_available>0){

                    if(data.attendance_status=='Absent' || data.attendance_status=='Half Day'){
                        let leave_name = (data.total_leave.leave_name).split(',');
                        let leave_number = (data.total_leave.leave_number).split(',');
                        let available_leave = (data.total_leave.available_leave).split(',');
                        let leave_for_attendance = (data.attendance_status=='Absent')?1:0.5;
                        leave_for_attendance = (leave_for_attendance>data.total_leave.max_leave_available)?data.total_leave.max_leave_available:leave_for_attendance;
                        issue_html += `<div class="mt-4">
                            <input type="hidden" name="attendance_utilize_id" id="attendance_utilize_id" value="${event_info.id}">
                            <label for="utilize_to" class="text-dark fw-bold fs-4">Utilize Your Leave</label>
                            <select class="form-control form-select" id="utilize_to" name="utilize_to">
                                            <option>Utilize To</option>`;
                            for (const ld in leave_name) {
                                // {"leave_type":"${ld.leave_name}", "leave_utilize":"${(ld.available_leave>1)?1:ld.available_leave}"}
                                let fl = (available_leave[ld]>=leave_for_attendance)?leave_for_attendance:available_leave[ld];
                                if(fl>0){
                                    issue_html += `<option value='{"leave_name":"${leave_name[ld]}", "leave_for_attendance":"${fl}"}'>${leave_name[ld]}</option>`;
                                }
                            }
                        issue_html += `</select></div>`;
                    }
                }else{
                        issue_html += `<p class="mb-0 text-danger">You are Utilize your Monthly Leave Limit.</p>`;
                }

                $('#right_sidebar_details').html(issue_html);
                show_hide_right_bar();
                
            }
           
        });
        <?php if ($user_role > 0) { ?>
            calendarObj.on('dateClick', async function(e) {
                if(('<?php echo $user_role; ?>'==1 && '<?php echo $emp_id; ?>'==get_final_employee_id()) || '<?php echo $user_role; ?>'==2){

                    // console.log(e.date);
                    let fdcDate = e.dateStr;

                    let currentDate = createDate(new Date());
                    let under30Days = createDate(new Date(new Date(currentDate).setDate(new Date(currentDate).getDate() + 30)));

                    if (fdcDate >= currentDate && fdcDate <= under30Days) {

                    

                        let response = await fetch('fun/getAttendance.php', {
                            method: "POST",
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                employeeDateTimingId: get_final_employee_id(),
                                timeDate: fdcDate,
                            }),
                        });
                        let data = await response.json();
                        // console.log(data);
                        if (data == 2) {
                            let date = new Date(e.date);
                            let finDate = date.toDateString()
                            let leave_html = `
                                <h4 class="mb-3"><i class="uil uil-stopwatch text-danger"></i>&nbsp;<span id="currentDate">${finDate}</span></h4>
                                <div>
                                    <h6><a href="add_leaveRequest?requestDate=${finDate}" id="leaveRequest" target="_blank"><i class="uil uil-comments mr-2"></i>Request for Leave or Half Day ! <i class="uil uil-arrow-right ml-2"></i></a></h6>
                                </div>
                            `;
                            $('#right_sidebar_details').html(leave_html);
                            show_hide_right_bar();
                        }
                    }
                }
            });
        <?php } ?>
    }

    async function getData() {
        // $(window).scrollTop(0);
        let response = await fetch('fun/getAttendance.php', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                employee_id: get_final_employee_id(),
                selectedMonth: get_final_month_date()
            })
        });
        let data = await response.json();
        console.log(data);

        // set days count
        for (const [key, value] of Object.entries(data.statusCount)) {
            $("#" + key).text((value != null) ? value : 0);
        }

        $("#current_selected_emp").html(`(${data.employee_timing.name})`);
        $("#office_timing").html(`<strong><i class="uil uil-stopwatch text-primary"></i>&nbsp;Office Timing: </strong><span>${data.employee_timing.punch_in} To ${data.employee_timing.punch_out}</span>`);

        //############################################################# total leave
        try {
            let duration = data.total_leave.duration;
            let leave_number = eval((data.total_leave.leave_number).replaceAll(',','+'));
            let prev_available_leave = eval((data.total_leave.prev_available_leave).replaceAll(',','+'));
            let total_leave_used = eval((data.total_leave.total_leave_used).replaceAll(',','+'));

            let max_leave_take = data.total_leave.max_leave_take;
            let carry_forward = data.total_leave.carry_forward;

            let leave_name = (data.total_leave.leave_name).split(',');
            let available_leave = (data.total_leave.available_leave).split(',');
            let max_leave_available = data.total_leave.max_leave_available;
            let leave_policy_text = `<p class="mb-0 text-dark"><strong><i class="uil uil-schedule text-primary mr-2"></i>Leave Policy :</strong> </p>
                <ul>
                    <li class="text-dark mb-0"><strong>Total Leave: </strong><span>${leave_number + prev_available_leave}</span></li>`;
                // leave_policy_text += `<li class="text-dark mb-0"><strong>Max Leave Take: </strong><span>${max_leave_take}</span></li><li class="text-dark mb-0"><strong>Carry Forward: </strong><span>${carry_forward}</span></li>`;
                leave_policy_text += `<li class="text-dark mb-0"><strong>Total Used Leave: </strong><span>${total_leave_used}</span></li>`;
                
            leave_policy_text += `
                <li class="text-dark mb-0">
                    <strong>Available Leave (${duration}): </strong>`;
            for (let i=0; i<leave_name.length; i++) {
                leave_policy_text += `<span>${leave_name[i]}: <strong>${available_leave[i]}</strong></span> `;
            }
                    
            leave_policy_text += `</li>`;
                leave_policy_text += `<li class="text-dark mb-0"><strong>Max Leave Allow (In Month): </strong><span>${max_leave_available}</span></li></ul>`;
            $("#leave_policy").html(leave_policy_text);
        } catch (error) {
            // console.log(error);
        }
        //############################################################# total leave

        if(data.employee_timing.is_employee_time){
            $("#with_grace_timing").show();
        }else{
            $("#with_grace_timing").hide();
        }

        events = data.events;

        // console.log(calendarObj);
        if(calendarObj===null){
            loadCalender();
            $('button.fc-custom_today-button').attr('disabled',true);
        }
        $('.fc-header-toolbar .fc-center').html(`<h5>Till Now <strong>${(!data.total_hours?'00:00':data.total_hours)}</strong> Hours</h5>`);
    }

    $(document).ready(async function(){
        await getData();
    });

    async function reloadCalender(){
        calendarObj.gotoDate(`${get_final_month_date()}-01`);
        await getData();
        // Update the defaultDate option of the calendar
        // Update the events in the calendar without reinitializing it
        calendarObj.removeAllEvents();
        calendarObj.addEventSource(events);
    }

    $(document).on("change", "#employee_id, #attendanceMonth", function() {
        reloadCalender();
    });

    function calendar_buttons_events() {
        let cal_date = createMonth(calendarObj.getDate());
        $('#attendanceMonth').val(cal_date);
        reloadCalender();
    }

    $(document).on('click', 'button.fc-prev-button, button.fc-next-button', function() {
        $('button.fc-custom_today-button').attr('disabled',false);
        calendar_buttons_events()
    });

    $(document).on('click', 'button.fc-custom_today-button', function() {
        calendarObj.today();
        $(this).attr('disabled',true);
        calendar_buttons_events()
    });


    $("#addAttendance").click(async function() {

        let add_form = `
            <div>
                <form action="fun/function" method="post" id="insert_attendance_form">
                    <div class="row">
                        <div class="col-sm-12 col-lg-6">
                            <div class="form-group">
                                <!-- <label for="insert_emp_id" class="form-label mb-1">Employee Id</label> -->
                                <select data-plugin="customselect" name="insert_emp_id" class="form-control custom-select-picker" data-placeholder="Select Employee" id="insert_emp_id" required>
                                    <option></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-12 col-lg-6">
                            <div class="form-group">
                                <!-- <label for="insert_att_status" class="form-label mb-1">Attendance Status</label> -->
                                <select data-plugin="customselect" name="insert_att_status" class="form-control custom-select-picker" data-placeholder="Select Attendance Status" id="insert_att_status" required>
                                    <option></option>
                                    <option value="Absent">Absent</option>
                                    <option value="Half Day">Half Day</option>
                                    <option value="Holiday">Holiday</option>
                                    <option value="Present">Present</option>
                                    <option value="Week Off">Week Off</option>
                                    <option value="Work From Home">Work From Home</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-sm-12 col-lg-6">
                            <div class="form-group">
                                <!-- <label for="insert_punch_in" class="form-label mb-1">Punch In</label> -->
                                <input type="text" class="form-control" name="insert_punch_in" id="insert_punch_in" placeholder="Punch In (9:45 AM)">
                            </div>
                        </div>

                        <div class="col-sm-12 col-lg-6">
                            <div class="form-group">
                                <!-- <label for="insert_punch_out" class="form-label mb-1">Punch Out</label> -->
                                <input type="text" class="form-control" name="insert_punch_out" id="insert_punch_out" placeholder="Punch Out (6:30 PM)">
                            </div>
                        </div>

                        <div class="col-sm-12 col-lg-6">
                            <div class="form-group">
                                <!-- <label for="insert_which_month" class="form-label mb-1">Date</label> -->
                                <input type="date" class="form-control" name="insert_which_month" id="insert_which_month" placeholder="Date" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="insert_attendance_comment">Reason/Comment<span class="text-danger">*</span></label>
                                <textarea class="form-control" name="insert_attendance_comment" id="insert_attendance_comment" rows="5" required placeholder="Enter Comment..."></textarea>
                            </div>
                        </div>
                        <input type="hidden" name="insert_attendance">
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="w-100 d-flex align-items-center justify-content-center">
                                <button type="submit"  class="btn btn-primary" style="width:50%;">Insert</button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        `;

        $('#right_sidebar_title').html('Add Attendance');
        $('#right_sidebar_details').html(add_form);
        await fetch_employees('insert_emp_id', get_final_employee_id(), '<?php echo $currentPageName; ?>');
        $('.custom-select-picker').select2();
        show_hide_right_bar();
    });

    $(document).on('submit',"#insert_attendance_form",function(e){
        e.preventDefault();
        let form_data = $(this).serialize();
        // console.log(form_data);
        $.ajax({
            url:'fun/function',
            type:'post',
            data:form_data,
            success:function(res){
                // console.log(res);
                if(res==1){
                    successMessage('Attendance Added Successfully!','<?php echo $_SESSION['user_name']; ?>');
                }else if(res==2){
                    successMessage(`This employee attendance for ${$('#insert_which_month').val()} exist.`,'<?php echo $_SESSION['user_name']; ?>');
                }else{
                    failedMessage('Some Error Occurred, Please Try Again after some time.','<?php echo $_SESSION['user_name']; ?>');
                }
                show_hide_right_bar();
                $("#insert_attendance_form")[0].reset();
                calendar_buttons_events();
            }
        });
    });
   
    $(document).on('click','#changeAttendance', async function() {
        show_hide_right_bar();
        let d = createDate(new Date($("#currentEventDate").text()));

        let add_form = `
            <div>
                <form action="fun/function" method="post" id="update_attendance_form">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <!-- <label for="update_emp_id" class="form-label mb-1">Employee Id</label> -->
                                <select data-plugin="customselect" name="update_emp_id" class="form-control custom-select-picker" data-placeholder="Select Employee" id="update_emp_id" required>
                                    <option></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <!-- <label for="update_att_status" class="form-label mb-1">Attendance Status</label> -->
                                <select data-plugin="customselect" name="update_att_status" class="form-control custom-select-picker" data-placeholder="Select Attendance Status" id="update_att_status" required>
                                    <option></option>
                                    <option ${($("#attendanceStatus").text()=='Absent')?'selected':''} value="Absent">Absent</option>
                                    <option ${($("#attendanceStatus").text()=='Half Day')?'selected':''} value="Half Day">Half Day</option>
                                    <option ${($("#attendanceStatus").text()=='Holiday')?'selected':''} value="Holiday">Holiday</option>
                                    <option ${($("#attendanceStatus").text()=='Present')?'selected':''} value="Present">Present</option>
                                    <option ${($("#attendanceStatus").text()=='Week Off')?'selected':''} value="Week Off">Week Off</option>
                                    <option ${($("#attendanceStatus").text()=='Work From Home')?'selected':''} value="Work From Home">Work From Home</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="form-group">
                                <!-- <label for="update_punch_in" class="form-label mb-1">Punch In</label> -->
                                <input type="text" class="form-control" name="update_punch_in" id="update_punch_in" value="${$("#punchInTime").text()}" placeholder="Punch In (9:45 AM)">
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="form-group">
                                <!-- <label for="update_punch_out" class="form-label mb-1">Punch Out</label> -->
                                <input type="text" class="form-control" name="update_punch_out" id="update_punch_out" value="${$("#punchOutTime").text()}" placeholder="Punch Out (6:30 PM)">
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="form-group">
                                <!-- <label for="update_which_month" class="form-label mb-1">Date</label> -->
                                <input type="date" class="form-control" name="update_which_month" id="update_which_month" value="${d}" placeholder="Date" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="update_attendance_comment">Reason/Comment<span class="text-danger">*</span></label>
                                <textarea class="form-control" name="update_attendance_comment" id="update_attendance_comment" rows="5" required placeholder="Enter Comment...">${$('#attendance_comment').text()}</textarea>
                            </div>
                        </div>
                        <input type="hidden" name="update_attendance">

                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="w-100 d-flex align-items-center justify-content-center">
                                <button type="submit" name="" class="btn btn-primary" style="width:50%;">Update</button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        `;
        $('#right_sidebar_title').html('Update Attendance');
        $('#right_sidebar_details').html(add_form);

        await fetch_employees('update_emp_id', get_final_employee_id(), '<?php echo $currentPageName; ?>');
        $('.custom-select-picker').select2();
        show_hide_right_bar();
    });

    $(document).on('submit', "#update_attendance_form", function(e){
        e.preventDefault();
        let form_data = $(this).serialize();
        // console.log(form_data);
        $.ajax({
            url:'fun/function',
            type:'post',
            data:form_data,
            success:function(res){
                // console.log(res);
                if(res==1){
                    successMessage('Attendance updated Successfully!','<?php echo $_SESSION['user_name']; ?>');
                }else{
                    failedMessage('Some Error Occurred, Please Try Again after some time.','<?php echo $_SESSION['user_name']; ?>');
                }
                show_hide_right_bar();
                $("#update_attendance_form")[0].reset();
                calendar_buttons_events();
            }
        });
    });

    $(document).on('change',"#utilize_to",function(e) {

        if(confirm("Do you want to Utilize Leave. Yes/NO")){
            let utilize_to = $("#utilize_to").val();
            let attendance_utilize_id = $("#attendance_utilize_id").val();
            let currentEventDate = $("#currentEventDate").text();

            console.log(utilize_to, attendance_utilize_id, currentEventDate);
            $.ajax({
                url:'fun/function',
                type:'post',
                data:{
                    utilize_to,
                    attendance_utilize_id,
                    currentEventDate
                },
                success:function(res){
                    // console.log(res);
                    if(res==1){
                        successMessage('Leave Utilize Successfully!','<?php echo $_SESSION['user_name']; ?>');
                    }else{
                        failedMessage('Some Error Occurred, Please Try Again after some time.','<?php echo $_SESSION['user_name']; ?>');
                    }
                    show_hide_right_bar();
                    calendar_buttons_events();
                }
            });
        }else{
            $("#utilize_to option").first().prop('selected',true)
        }
        
    });


</script>
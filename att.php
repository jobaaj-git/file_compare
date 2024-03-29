<?php
require_once "../config.php";
header('Content-Type: text/plain');
header('Allow-Control-Allow-Origin: *');
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

function find_total_leave($financial_year_start, $financial_year_end, $getMonth, $joining_date, $eid, $firstDate, $lastDate){
    $total_leave_sql = "
        WITH RECURSIVE final_leave_type  AS (
            SELECT *
            FROM (
                SELECT 
                *,
                (CASE
                WHEN main_applicable_from<DATE_FORMAT(DATE_SUB(CONCAT(applicable_from,'-01'), INTERVAL 1 YEAR), '%Y-%m') THEN DATE_FORMAT(DATE_SUB(CONCAT(applicable_from,'-01'), INTERVAL 1 YEAR), '%Y-%m')
                ELSE main_applicable_from
                END) as prev_applicable_from,
                (CASE
                WHEN COALESCE(main_applicable_to,'')='' THEN DATE_FORMAT(DATE_SUB(CONCAT(applicable_to,'-01'), INTERVAL 1 YEAR), '%Y-%m')
                WHEN main_applicable_to<DATE_FORMAT(DATE_SUB(CONCAT(applicable_to,'-01'), INTERVAL 1 YEAR), '%Y-%m') THEN DATE_FORMAT(DATE_SUB(CONCAT(applicable_to,'-01'), INTERVAL 1 YEAR), '%Y-%m')
                ELSE DATE_FORMAT(CONCAT(applicable_to,'-01'), '%Y-03')
                END) as prev_applicable_to
                FROM (
                    SELECT
                        id,
                        duration,
                        leave_details,
                        max_leave_take,
                        carry_forward,
                        max_carry_forward,
                        applicable_from as main_applicable_from,
                        applicable_to as main_applicable_to,
                        (CASE
                            WHEN applicable_from>='$financial_year_start' THEN applicable_from
                            ELSE '$financial_year_start'
                        END) as applicable_from,
                        (CASE
                            WHEN NULLIF(applicable_to,'')<='$financial_year_end' THEN applicable_to
                            ELSE '$financial_year_end'
                        END) AS applicable_to
                    FROM leave_policy
                    ) sub
            ) sub
            WHERE ('$getMonth' BETWEEN applicable_from AND applicable_to)
        ),
        find_total_leave  AS (
            SELECT 
                id,
                duration,
                leave_details,
                max_leave_take,
                carry_forward,
                max_carry_forward,
                prev_applicable_from,
                prev_applicable_to,
                applicable_from,
                applicable_to,
                leave_details->>'$[0].leave_type' AS leave_name,
                leave_details->>'$[0].total_leave' AS leave_number,
                0 AS idx
            FROM final_leave_type
            UNION ALL
            SELECT
                id,
                duration,
                leave_details,
                max_leave_take,
                carry_forward,
                max_carry_forward,
                prev_applicable_from,
                prev_applicable_to,
                applicable_from,
                applicable_to,
                JSON_UNQUOTE(JSON_EXTRACT(leave_details, CONCAT('$[', idx + 1, '].leave_type'))) AS leave_name,
                JSON_UNQUOTE(JSON_EXTRACT(leave_details, CONCAT('$[', idx + 1, '].total_leave'))) AS leave_number,
                idx + 1
            FROM find_total_leave
            WHERE idx + 1 < JSON_LENGTH(leave_details)
        ),
        final_max_leave AS (
            SELECT 
                id,
                duration,
                leave_details,
                (CASE
                WHEN COALESCE(max_leave_take,'')='' THEN leave_number
                ELSE max_leave_take
                END) as max_leave_take,
                carry_forward,
                max_carry_forward,
                prev_applicable_from,
                prev_applicable_to,
                applicable_from,
                applicable_to,
                leave_name,
                leave_number
            FROM find_total_leave
        )
        SELECT 
            id,
            Max(duration) AS duration,
            Max(max_leave_take) AS max_leave_take,
            Max(carry_forward) AS carry_forward,
            Max(max_carry_forward) AS max_carry_forward,
            MAX(prev_applicable_from) AS prev_applicable_from,
            Max(prev_applicable_to) AS prev_applicable_to,
            Max(applicable_from) AS applicable_from,
            Max(applicable_to) AS applicable_to,
            GROUP_CONCAT(leave_name) as leave_name,
            GROUP_CONCAT(leave_number) as leave_number
        FROM final_max_leave
        GROUP BY id
    ";
    $total_leave = mysqli_fetch_assoc(mysqli_query($GLOBALS['db'],$total_leave_sql));
    // $total_leave['query'] = $total_leave_sql;

    if($total_leave['duration']=='Yearly'){
        $st = ($total_leave['applicable_from']>date('Y-m',strtotime($joining_date)))?"$total_leave[applicable_from]-01":$joining_date;

        $date_num = date('d',strtotime($st));
        if($date_num>'05' && $date_num<='16'){
            $based_month_leave = [0.5, 0.5];
        }else if($date_num>'17' && $date_num<='24'){
            $based_month_leave = [0, 0.5];
        }else if($date_num>'24' && $date_num<=date('t',strtotime($st))){
            $based_month_leave = [0, 0];
        }else{
            $based_month_leave = [1, 0.5];
        }


        $date1=date_create($st);
        $date2=date_create(date("$financial_year_end-t"));
        $diff=date_diff($date1,$date2);
        $remaining_months_from_applicable = $diff->m;
        $u_leave_number = explode(',', $total_leave['leave_number']);
        foreach($u_leave_number as $i=> $v){
            $u_leave_number[$i] = $u_leave_number[$i]/12*$remaining_months_from_applicable;
            $u_leave_number[$i] += $based_month_leave[$i];
        }
        $total_leave['leave_number'] = implode(',',$u_leave_number);
    }

    // get monthly leave
    $utilize_leave_max_sql = "
        SELECT 
            sum(leave_count) as utilize_leave_max_count
        FROM utilize_leave WHERE employee_id='$eid' AND (leave_date BETWEEN '$firstDate' and '$lastDate')
        GROUP BY employee_id
    ";
    $utilize_leave_max_count = mysqli_fetch_assoc(mysqli_query($GLOBALS['db'],$utilize_leave_max_sql))['utilize_leave_max_count'];
    $max_leave_available = ($total_leave['max_leave_take']-$utilize_leave_max_count);

    if($total_leave['duration']=='Monthly' && $total_leave['carry_forward']=='No'){

        $utilize_leave_total_sql = "
            SELECT 
                Max(employee_id) as employee_id, leave_status, COALESCE(sum(leave_count),0) as leave_count 
            FROM utilize_leave WHERE employee_id='$eid' AND (leave_date BETWEEN '$firstDate' and '$lastDate')
            GROUP BY leave_status
        ";
        $utilize_leave_total_count = mysqli_query($GLOBALS['db'],$utilize_leave_total_sql);
        $leave_name = explode(',',$total_leave['leave_name']);
        $leave_number = explode(',',$total_leave['leave_number']);
        while($ultc = mysqli_fetch_assoc($utilize_leave_total_count)){
            $ni = array_search($ultc['leave_status'],$leave_name);
            $total_leave_used[$ni] = $ultc['leave_count'];
            $leave_number[$ni] = $leave_number[$ni]- $ultc['leave_count'];
        }
    }else if($total_leave['duration']=='Monthly' && $total_leave['carry_forward']=='Yes'){

        $utilize_leave_total_sql = "
            SELECT 
                Max(employee_id) as employee_id, leave_status, COALESCE(sum(leave_count),0) as leave_count 
            FROM utilize_leave WHERE employee_id='$eid' AND (leave_date BETWEEN '$firstDate' and '$lastDate')
            GROUP BY leave_status
        ";
        $utilize_leave_total_count = mysqli_query($GLOBALS['db'],$utilize_leave_total_sql);
        $leave_name = explode(',',$total_leave['leave_name']);
        $leave_number = explode(',',$total_leave['leave_number']);
        while($ultc = mysqli_fetch_assoc($utilize_leave_total_count)){
            $ni = array_search($ultc['leave_status'],$leave_name);
            $leave_number[$ni] = $leave_number[$ni]- $ultc['leave_count'];
        }

        // ################################################### previous leave
        $prev_start_month = date('Y-m-01',strtotime($total_leave['applicable_from']));
        $prev_till_month = date('Y-m-t',strtotime('previous month'));
            $date1=date_create($prev_start_month);
            $date2=date_create($prev_till_month);
            $diff=date_diff($date1,$date2);
        $total_prev_months = $diff->m +1;
        // $total_leave['total_prev_months'] = $total_prev_months;

        $u_leave_number = explode(',', $total_leave['leave_number']);
        foreach($u_leave_number as $i=> $v){
            $u_leave_number[$i] = $u_leave_number[$i]*$total_prev_months;
        }
        $prev_year_leave = $u_leave_number;

        
        $prev_utilize_leave_sql = "
            SELECT 
                Max(employee_id) as employee_id, leave_status, COALESCE(sum(leave_count),0) as leave_count 
            FROM utilize_leave WHERE employee_id='$eid' AND (leave_date BETWEEN '$prev_start_month' and '$prev_till_month')
            GROUP BY leave_status
        ";
        $prev_utilize_leave_count = mysqli_query($GLOBALS['db'],$prev_utilize_leave_sql);
        while($ultc = mysqli_fetch_assoc($prev_utilize_leave_count)){
            $ni = array_search($ultc['leave_status'],$leave_name);
            $total_leave_used[$ni] = $ultc['leave_count'];
            $prev_year_leave[$ni] = $prev_year_leave[$ni]- $ultc['leave_count'];
        }

        $max_leave_available = array_sum($prev_year_leave) + array_sum($leave_number);
        // ################################################### previous leave

    }else if($total_leave['duration']=='Yearly' && $total_leave['carry_forward']=='No'){

        $leave_year_start = date('Y-m-01', strtotime($total_leave['applicable_from']));
        $leave_year_end = date('Y-m-t',strtotime($total_leave['applicable_to']));

        // get yearly leave
        $utilize_leave_total_sql = "
            SELECT 
                Max(employee_id) as employee_id, leave_status, COALESCE(sum(leave_count),0) as leave_count 
            FROM utilize_leave WHERE employee_id='$eid' AND (leave_date BETWEEN '$leave_year_start' and '$leave_year_end') 
            GROUP BY leave_status
        ";
        $utilize_leave_total_count = mysqli_query($GLOBALS['db'],$utilize_leave_total_sql);
        $leave_name = explode(',',$total_leave['leave_name']);
        $leave_number = explode(',',$total_leave['leave_number']);
        while($ultc = mysqli_fetch_assoc($utilize_leave_total_count)){
            $ni = array_search($ultc['leave_status'],$leave_name);
            $total_leave_used[$ni] = $ultc['leave_count'];
            $leave_number[$ni] = $leave_number[$ni]- $ultc['leave_count'];
        }

    }else if($total_leave['duration']=='Yearly' && $total_leave['carry_forward']=='Yes'){

        $leave_year_start = date('Y-m-01', strtotime($total_leave['applicable_from']));
        $leave_year_end = date('Y-m-t',strtotime($total_leave['applicable_to']));

        // get yearly leave
        $utilize_leave_total_sql = "
            SELECT 
                Max(employee_id) as employee_id, leave_status, COALESCE(sum(leave_count),0) as leave_count 
            FROM utilize_leave WHERE employee_id='$eid' AND (leave_date BETWEEN '$leave_year_start' and '$leave_year_end') 
            GROUP BY leave_status
        ";
        $utilize_leave_total_count = mysqli_query($GLOBALS['db'],$utilize_leave_total_sql);
        $leave_name = explode(',',$total_leave['leave_name']);
        $leave_number = explode(',',$total_leave['leave_number']);
        while($ultc = mysqli_fetch_assoc($utilize_leave_total_count)){
            $ni = array_search($ultc['leave_status'],$leave_name);
            $total_leave_used[$ni] = $ultc['leave_count'];
            $leave_number[$ni] = $leave_number[$ni]- $ultc['leave_count'];
        }

        // ################################################### previous leave
        if($total_leave['applicable_from']>$total_leave['prev_applicable_from']){
            
            // ##################
            $st = ($total_leave['prev_applicable_from']>date('Y-m',strtotime($joining_date)))?"$total_leave[prev_applicable_from]-01":$joining_date;

            $date_num = date('d',strtotime($st));
            if($date_num>'05' && $date_num<='16'){
                $based_month_leave = [0.5, 0.5];
            }else if($date_num>'17' && $date_num<='24'){
                $based_month_leave = [0, 0.5];
            }else if($date_num>'24' && $date_num<=date('t',strtotime($st))){
                $based_month_leave = [0, 0];
            }else{
                $based_month_leave = [1, 0.5];
            }

            $date1=date_create($st);
            $date2=date_create(date('Y-m-t',strtotime("$total_leave[prev_applicable_from] +1 year")));
            $diff=date_diff($date1,$date2);
            $remaining_months_from_applicable = $diff->m;
            $u_leave_number = explode(',', $total_leave['leave_number']);
            foreach($u_leave_number as $i=> $v){
                $u_leave_number[$i] = $u_leave_number[$i]/12*$remaining_months_from_applicable;
                $u_leave_number[$i] += $based_month_leave[$i];
            }
            $prev_year_leave = $u_leave_number;
            // ##################
            $total_leave['prev_year_leave'] = $prev_year_leave;
            $total_leave['joining_date'] = $joining_date;
            $carry_forward_limit = $total_leave['max_carry_forward']/sizeof($prev_year_leave);


            // find previous year utilize leave
            $prev_leave_year_start = date('Y-m-01',strtotime($total_leave['prev_applicable_from']));
            $prev_leave_year_end = date('Y-m-t',strtotime($total_leave['prev_applicable_to']));
            // ############################ get yearly leave
            $prev_utilize_leave_sql = "
                SELECT 
                    Max(employee_id) as employee_id, leave_status, COALESCE(sum(leave_count),0) as leave_count 
                FROM utilize_leave WHERE employee_id='$eid' AND (leave_date BETWEEN '$prev_leave_year_start' and '$prev_leave_year_end') 
                GROUP BY leave_status
            ";
            $prev_utilize_leave_count = mysqli_query($GLOBALS['db'],$prev_utilize_leave_sql);
            while($ultc = mysqli_fetch_assoc($prev_utilize_leave_count)){
                $ni = array_search($ultc['leave_status'],$leave_name);
                $prev_year_leave[$ni] = $prev_year_leave[$ni]- $ultc['leave_count'];
            }
            // ############################ get yearly leave

            // add in current year
            foreach($prev_year_leave as $i=>$v){
                $prev_year_leave[$i] = ($prev_year_leave[$i]<$carry_forward_limit)?$prev_year_leave[$i]:$carry_forward_limit;
                $leave_number[$i] += $prev_year_leave[$i];
            }

        }
        // ################################################### previous leave
    }
    
    $max_leave_available = ($max_leave_available>array_sum($leave_number))?array_sum($leave_number):$max_leave_available;
    return $total_leave += [
        'available_leave' => !empty($leave_number) ? implode(',', $leave_number) : '0',
        'prev_available_leave' => !empty($prev_year_leave) ? implode(',', $prev_year_leave) : '0',
        'max_leave_available' => !empty($max_leave_available) ? "$max_leave_available" : '0',
        'total_leave_used' => !empty($total_leave_used) ? implode(',', $total_leave_used) : '0',
    ];
}



if (isset($data['employee_id'])) {
    
    $eid = $data['employee_id'];

    if(date('m',strtotime($data['selectedMonth']))<='03'){
        $financial_year_start = date("Y-04", strtotime("$data[selectedMonth] -1 year"));
        $financial_year_end = date("Y-03", strtotime("$data[selectedMonth]"));
    }else{
        $financial_year_start = date("Y-04", strtotime($data['selectedMonth']));
        $financial_year_end = date("Y-03", strtotime("$data[selectedMonth] +1 year"));
    }

    $firstDate = date("Y-m-01", strtotime($data['selectedMonth']));
    $lastDate = date("Y-m-t", strtotime($data['selectedMonth']));
    $currMonth = date("Y-m");
    $getMonth = date("Y-m", strtotime($data['selectedMonth']));
    $getYear = date("Y", strtotime($data['selectedMonth']));
    $events = array();
    $attendance_sql = '';
    
    $joining_date = mysqli_fetch_assoc(mysqli_query($db,"SELECT joining_date FROM users WHERE employee_id='$eid' "))['joining_date'];

    $attendance_sql = "
      WITH RECURSIVE date_series AS (
        SELECT DATE('$firstDate') AS date, '$eid' as d_emp_id
        UNION ALL
        SELECT DATE_ADD(date, INTERVAL 1 DAY), d_emp_id
        FROM date_series
        WHERE DATE_ADD(date, INTERVAL 1 DAY) <= '$lastDate'
      ),
      
      with_week_offs AS (
        SELECT
            t2.id,
            t1.d_emp_id as employee_id,
            (CASE
                WHEN COALESCE(attendance_status,'')='' and DAYNAME(t1.date) = 'Sunday' THEN 'Week Off'
                ELSE attendance_status
            END) as attendance_status,
            t2.punch_in,
            t2.punch_out,
            date as which_month,
            t2.attendance_change,
            t2.attendance_comment,
            NULL as leave_status
            FROM date_series t1
        LEFT JOIN (
          SELECT * FROM attendance WHERE employee_id='$eid' and (str_to_date(which_month,'%M %d %Y') BETWEEN '$firstDate' and '$lastDate')
        ) t2 ON t1.date=str_to_date(t2.which_month,'%M %d %Y') AND t1.d_emp_id=t2.employee_id
      ),
      
      with_holidays AS (
        SELECT 
          NULL as id,
          '$eid' as employee_id,
          title as attendance_status,
          NULL as punch_in,
          NULL as punch_out,
          str_to_date(date, '%M %d %Y') as which_month,
          NULL as attendance_change,
          NULL as attendance_comment,
          NULL as leave_status
        FROM holidays WHERE (str_to_date(date,'%M %d %Y') BETWEEN '$firstDate' and '$lastDate')
      ),

      with_utilize_leave AS (
        SELECT 
          NULL as id,
          employee_id,
          leave_status as attendance_status,
          NULL as punch_in,
          NULL as punch_out,
          leave_date as which_month,
          NULL as attendance_change,
          NULL as attendance_comment,
          NULL as leave_status
        FROM utilize_leave WHERE employee_id='$eid' AND (leave_date BETWEEN '$firstDate' and '$lastDate')
      )
    ";
      
    // for add future leaves
    if (strtotime($currMonth) <= strtotime($getMonth)) {
        $attendance_sql .= "
            ,
            with_future_leaves as (
                SELECT
                employee_id,
                start_leave,
                (CASE
                    WHEN start_leave=end_leave THEN start_leave
                    ELSE end_leave
                END) as end_leave,
                leave_type,
                leave_status
                FROM (
                    SELECT 
                        employee_id,
                        str_to_date(SUBSTRING_INDEX(leave_date,' to ',1),'%d %M %Y') as start_leave,
                        str_to_date(SUBSTRING_INDEX(leave_date,' to ',-1),'%d %M %Y') as end_leave,
                        leave_type,
                        leave_status
                    FROM request_leave 
                    WHERE employee_id='$eid'
                ) break_leave
            ),
            
            future_leaves as (
                SELECT
                    NULL as id,
                    t2.employee_id,
                    CONCAT('Request For ',(CASE
                        WHEN t2.leave_type='Absent' THEN 'Leave'
                        ELSE t2.leave_type
                    END)) as attendance_status,
                    NULL as punch_in,
                    NULL as punch_out,
                    t1.date as which_month,
                    NULL as attendance_change,
                    NULL as attendance_comment,
                    t2.leave_status
                FROM date_series t1
                INNER JOIN with_future_leaves t2 ON t1.date BETWEEN t2.start_leave AND t2.end_leave
            )
            SELECT * FROM future_leaves
            UNION ALL 
        ";

    }
    $attendance_sql .= "
      SELECT * FROM with_week_offs WHERE attendance_status IS NOT NULL
      UNION ALL
      SELECT * FROM with_utilize_leave
      UNION ALL 
      SELECT * FROM with_holidays
    ";


    // ##########################################
    $attendance_status = mysqli_fetch_assoc(mysqli_query($db, "SELECT  
    COUNT(CASE WHEN `attendance_status` = 'Absent' THEN 1 ELSE NULL END) AS `absent`,
    COUNT(CASE WHEN `attendance_status` = 'Half Day' THEN 1 ELSE NULL END) AS `half_day`,
    COUNT(CASE WHEN `attendance_status` = 'Present' THEN 1 ELSE NULL END) AS `present`,
    COUNT(CASE WHEN `attendance_status` = 'Week Off' THEN 1 ELSE NULL END) AS `week_off`,
    COUNT(CASE WHEN `attendance_status` = 'Holiday' THEN 1 ELSE NULL END) AS `holiday`,
    COUNT(CASE WHEN `attendance_status` = 'Work From Home' THEN 1 ELSE NULL END) AS `work_from_home`
    FROM `attendance`
    WHERE `employee_id` = '$eid'
    AND str_to_date(`which_month`, '%M %d %Y') BETWEEN '$firstDate' AND '$lastDate' "));

    $statusCount = array('present' => $attendance_status['present'], 'halfDay' => $attendance_status['half_day'], 'workFromHome' => $attendance_status['work_from_home'], 'absent' => $attendance_status['absent'], 'weekOff' => $attendance_status['week_off'], 'holiday' => $attendance_status['holiday']);
    // ##########################################

    // Show attendance ##########################
    $bgcolor = array(
        'Present' => 'bg-success', 'Half Day' => 'bg-warning', 'Work From Home' => 'bg-primary', 'Absent' => 'bg-danger', 'Week Off' => 'bg-secondary', 'Holiday' => 'bg-secondary', 
        'Pending' => 'bg-warning', 'Approved' => 'bg-success', 'Rejected' => 'bg-danger');
    $status = array('Pending', 'Approved', 'Rejected');

    $attendance_query = mysqli_query($db,$attendance_sql);
    while ($row = mysqli_fetch_assoc($attendance_query)) {

        $title =  $row['attendance_status'];
        $start =  $row['which_month'];

        $leave_status = $status[$row['leave_status']];
        if ($row['attendance_change'] == 1) {
            $className = 'attendance-change-bg';
        }else if (!empty($leave_status)) {
            $className = $bgcolor[$leave_status];
            $title .= "$leave_status";
        }else if(!array_key_exists($title, $bgcolor)){
            $className = $bgcolor['Holiday'];
        }else {
            $className = $bgcolor[$title];
        }

        // check hours is lesh than 8:50 hours
        if($_SESSION['user_role']<2){
            $less_hours = date("H:i", (strtotime($row['punch_out']) - strtotime($row['punch_in'])));
            if($row['attendance_status']=='Present' && $less_hours>='08:50' && $less_hours<'09:00'){
                $className = 'attendance-less-bg';
            }
        }

        $event = array(
            'id' => $row['id'],
            'title' => $title,
            'start' => $start,
            'className' => $className
        );
        array_push($events, $event);
    }
    // ##############################################

    // ########################################### available leave
    $total_leave = find_total_leave($financial_year_start, $financial_year_end, $getMonth, $joining_date, $eid, $firstDate, $lastDate);
    // ########################################### available leave    


    // find total hours
    $total_hours = mysqli_fetch_assoc(
        mysqli_query($db,
            "SELECT 
                TIME_FORMAT(
                    SEC_TO_TIME(
                        SUM(
                            TIME_TO_SEC(
                                TIME_FORMAT(TIMEDIFF(
                                    IF(
                                        str_to_date(punch_out, '%h:%i %p') < str_to_date(punch_in, '%h:%i %p'), 
                                        DATE_ADD(str_to_date(punch_out, '%h:%i %p'), INTERVAL 12 HOUR), 
                                        str_to_date(punch_out, '%h:%i %p')
                                    ), 
                                str_to_date(punch_in, '%h:%i %p')
                            ), '%H:%i')
                        )
                    )
                ),'%H:%i') AS hours_worked
            FROM attendance 
            WHERE 
            employee_id='$eid' and 
            (str_to_date(which_month,'%M %d %Y') BETWEEN '$firstDate' and '$lastDate')  
            GROUP BY employee_id;")
        );


    $employee = mysqli_fetch_assoc(mysqli_query($db, "SELECT name, employee_time FROM `users` WHERE `employee_id`='$eid'  "));
    if (!empty($employee['employee_time'])) {
        $employee_time = json_decode($employee['employee_time'], true);
        $employee_timing = array('name'=>$employee['name'], 'punch_in'=>$employee_time['punch_in'], 'punch_out'=>$employee_time['punch_out'],'is_employee_time'=>true);
    } else {
        $employee_timing = array('name'=>$employee['name'], 'punch_in'=>'9:30 AM', 'punch_out'=>'6:30 PM','is_employee_time'=>false);
    }

    echo json_encode(array('events' => $events, 'statusCount' => $statusCount, 'total_hours'=>$total_hours['hours_worked'], 'employee_timing'=>$employee_timing, 'total_leave'=>$total_leave));
}


if (isset($data['employeeTimingId'])) {


    if(date('m',strtotime($data['timeDate']))<='03'){
        $financial_year_start = date("Y-04", strtotime("$data[timeDate] -1 year"));
        $financial_year_end = date("Y-03", strtotime("$data[timeDate]"));
    }else{
        $financial_year_start = date("Y-04", strtotime($data['timeDate']));
        $financial_year_end = date("Y-03", strtotime("$data[timeDate] +1 year"));
    }

    $firstDate = date("Y-m-01", strtotime($data['timeDate']));
    $lastDate = date("Y-m-t", strtotime($data['timeDate']));
    $currMonth = date("Y-m");
    $getMonth = date("Y-m", strtotime($data['timeDate']));
    $getYear = date("Y", strtotime($data['timeDate']));

    $id = $data['eventClickId'];
    $eid = $data['employeeTimingId'];
    $date = date("Y-m-d", strtotime($data['timeDate']));

    $joining_date = mysqli_fetch_assoc(mysqli_query($db,"SELECT joining_date FROM users WHERE id='$eid' "))['joining_date'];

    $q = mysqli_query($db, "SELECT 
            t1.punch_in,
            t1.punch_out,
            t1.attendance_status,
            t1.attendance_comment,
            t2.attendance_status as attendanceStatus
            FROM attendance t1
            LEFT JOIN attendance_change t2 on t1.id=t2.attendance_id 
            WHERE t1.id='$id' ");
            
    if (mysqli_num_rows($q) > 0) {

        $qf = mysqli_fetch_assoc($q);

        // ################################# total hours
        $time1 = strtotime($qf['punch_in']);
        $time2 = strtotime($qf['punch_out']);
        $hours = $time2 - $time1;
        if (empty($qf['punch_in']) || empty($qf['punch_out'])) {
            $qf += ['totalHours' => ''];
        } else {
            $qf += ['totalHours' => date("H:i", $hours)];
        }

        // $qf += ['attendanceChange' => $attChange['attendanceStatus']];
        // #################################

        // ################################# request leave
        $request_leave_sql = "
            WITH RECURSIVE date_series AS (
                SELECT DATE('$firstDate') AS date, '$eid' as d_emp_id
                UNION ALL
                SELECT DATE_ADD(date, INTERVAL 1 DAY), d_emp_id
                FROM date_series
                WHERE DATE_ADD(date, INTERVAL 1 DAY) <= '$lastDate'
            ),
            with_future_leaves as (
                SELECT
                id,
                employee_id,
                leave_date,
                leave_type,
                leave_reason,
                leave_status,
                remark,
                status_by,
                created_at,
                start_leave,
                (CASE
                    WHEN start_leave=end_leave THEN start_leave
                    ELSE end_leave
                END) as end_leave
                FROM (
                    SELECT 
                        *,
                        str_to_date(SUBSTRING_INDEX(leave_date,' to ',1),'%d %M %Y') as start_leave,
                        str_to_date(SUBSTRING_INDEX(leave_date,' to ',-1),'%d %M %Y') as end_leave
                    FROM request_leave 
                    WHERE employee_id='$eid'
                ) break_leave
            ),
            
            future_leaves as (
                SELECT
                    t1.date,
                    t2.*
                FROM date_series t1
                INNER JOIN with_future_leaves t2 ON t1.date BETWEEN t2.start_leave AND t2.end_leave
            )
            SELECT 
            t1.*,
            t2.name
            FROM future_leaves t1
            LEFT JOIN users t2 ON t1.status_by=t2.employee_id
            WHERE t1.date='$date'
        ";
        $requestLeave = mysqli_query($db,$request_leave_sql);
        while ($reqLeave = mysqli_fetch_assoc($requestLeave)) {
            
            $qf += ['requestLeave' => ['leaveStatus' => $reqLeave['leave_status'], 'leaveType' => $reqLeave['leave_type'], 'statusBy' => $reqLeave['name'], 'leaveReason' => $reqLeave['leave_reason']]];
        }

        // issue with date
        $issue_request_sql = "
            WITH RECURSIVE date_series AS (
                SELECT DATE('$firstDate') AS date, '$eid' as d_emp_id
                UNION ALL
                SELECT DATE_ADD(date, INTERVAL 1 DAY), d_emp_id
                FROM date_series
                WHERE DATE_ADD(date, INTERVAL 1 DAY) <= '$lastDate'
            ),
            with_future_leaves as (
                SELECT
                id,
                employee_id,
                issue_date,
                issue_type,
                issue_note,
                issue_status,
                remark,
                status_by,
                created_at,
                start_leave,
                (CASE
                    WHEN start_leave=end_leave THEN start_leave
                    ELSE end_leave
                END) as end_leave
                FROM (
                    SELECT 
                        *,
                        str_to_date(SUBSTRING_INDEX(issue_date,' to ',1),'%d %M %Y') as start_leave,
                        str_to_date(SUBSTRING_INDEX(issue_date,' to ',-1),'%d %M %Y') as end_leave
                    FROM issue_with_date 
                    WHERE employee_id='$eid'
                ) break_leave
            ),
            future_leaves as (
                SELECT
                    t1.date,
                    t2.*
                FROM date_series t1
                INNER JOIN with_future_leaves t2 ON t1.date BETWEEN t2.start_leave AND t2.end_leave
            )
            SELECT 
            t1.*,
            t2.name
            FROM future_leaves t1
            LEFT JOIN users t2 ON t1.status_by=t2.employee_id
            WHERE t1.date='$date'
        ";
        $requestLeave = mysqli_query($db, $issue_request_sql);
        while ($reqLeave = mysqli_fetch_assoc($requestLeave)) {

            $qf += ['issueWithDate' => ['issueStatus' => $reqLeave['issue_status'], 'issueType' => $reqLeave['issue_type'], 'statusBy' => $reqLeave['name'], 'issueReason' => $reqLeave['issue_note']]];
        }

        $lAD = mysqli_fetch_assoc(mysqli_query($db, "select max(str_to_date(which_month,'%M %d %Y')) as max_date from attendance "));
        $qf += ['lastAttendanceDate' => $lAD['max_date']];

    } else {
        $qf = array('noData' => 0);
    }


    // ########################################### available leave
    $total_leave = find_total_leave($financial_year_start, $financial_year_end, $getMonth, $joining_date, $eid, $firstDate, $lastDate);
    // ########################################### available leave   
    

    $qf += ['total_leave'=>$total_leave];
    echo json_encode($qf);
}


if (isset($data['employeeDateTimingId'])) {

    // $id = $data['eventClickId'];
    $eid = $data['employeeDateTimingId'];
    $date = date("d M Y", strtotime($data['timeDate']));

    // request leave
    $requestLeave = mysqli_query($db, "select * from request_leave where employee_id='$eid' ");
    while ($reqLeave = mysqli_fetch_assoc($requestLeave)) {

        if (strpos($reqLeave['leave_date'], 'to') == false) {

            if (strtotime($reqLeave['leave_date']) == strtotime($date)) {
                echo 1;
                exit();
            }
        } else {
            $exDate = explode("to", $reqLeave['leave_date']);

            if ((strtotime(trim($exDate[0])) <= strtotime($date)) && (strtotime(trim($exDate[1])) >= strtotime($date))) {
                echo 1;
                exit();
            }
        }
    }

    echo 2;
    exit();
}

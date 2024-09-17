<?php

require_once 'jdf.php';
function send_birthday_sms_from_gravity_forms_once() {
    global $wpdb;
    $today_gregorian = current_time('Y-m-d');
    list($year, $month, $day) = explode('-', $today_gregorian);
    $today_jalali = gregorian_to_jalali($year, $month, $day, '-');
    list($jalali_year, $jalali_month, $jalali_day) = explode('-', $today_jalali);
    $today_jalali_db_format = sprintf('%04d-%02d-%02d', $jalali_year, $jalali_month, $jalali_day);
    $meta_key_birthday = '6'; 
    $meta_key_phone = '5'; 
    $meta_key_name = '4.3';  
    $meta_key_gender = '1';
    $query = $wpdb->prepare("
        SELECT DISTINCT em_birthday.entry_id, em_birthday.meta_value AS birthday, em_phone.meta_value AS phone, em_name.meta_value AS name, em_gender.meta_value AS gender
        FROM pinki_gf_entry_meta AS em_birthday
        JOIN pinki_gf_entry_meta AS em_phone ON em_birthday.entry_id = em_phone.entry_id
        JOIN pinki_gf_entry_meta AS em_name ON em_birthday.entry_id = em_name.entry_id
        JOIN pinki_gf_entry_meta AS em_gender ON em_birthday.entry_id = em_gender.entry_id
        WHERE em_birthday.meta_key = %s
        AND em_phone.meta_key = %s
        AND em_name.meta_key = %s
        AND em_gender.meta_key = %s
        AND em_birthday.meta_value = %s
    ", $meta_key_birthday, $meta_key_phone, $meta_key_name, $meta_key_gender, $today_jalali_db_format);

    $results = $wpdb->get_results($query);

    foreach ($results as $result) {
        $unique_key = 'birthday_sms_sent_' . $result->entry_id . '_' . $today_jalali_db_format;
        $sent_status = get_option($unique_key);

        if (!$sent_status) {
            $api_url = 'API ADDRESS';
            $username = 'YOUR USERNAME';
            $password = 'YOURPASSWORD';
            $sender = 'SENDER NUMBER';
            
            $male_message = "YOUR MESSAGE HERE";
            $female_message = "YOUR MESSAGE HERE";

            if ($result->gender == 'male') {
                $message = $male_message;
            } elseif ($result->gender == 'female') {
                $message = $female_message;
            } else {
                continue;
            }

            $message = str_replace('%name%', $result->name, $message);
            $message = str_replace('%date%', $today_jalali, $message);

            $phone_numbers = array($result->phone); 

            $param = array(
                'uname' => $username,
                'pass' => $password,
                'from' => $sender,
                'message' => $message,
                'to' => json_encode($phone_numbers),
                'op' => 'send'
            );

            $handler = curl_init($api_url);
            curl_setopt($handler, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($handler, CURLOPT_POSTFIELDS, $param);
            curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($handler);

            if (curl_errno($handler)) {
                error_log('cURL error: ' . curl_error($handler));
            } else {
                $response_data = json_decode($response);
                $res_code = $response_data[0];
                $res_message = $response_data[1];
                
                if ($res_code == 1) {
                    error_log('SMS sent successfully to ' . $result->phone);
                    update_option($unique_key, true);
                } else {
                    error_log('Error sending SMS: ' . $res_message);
                }
            }

            curl_close($handler);
        } else {
            error_log('SMS already sent to ' . $result->phone);
        }
    }

}

if (!wp_next_scheduled('daily_birthday_sms_event')) {
    $timestamp = strtotime('today 01:00:00', current_time('timestamp'));  // 1 بامداد به وقت ایران
    wp_schedule_event($timestamp, 'daily', 'daily_birthday_sms_event');
}

add_action('daily_birthday_sms_event', 'send_birthday_sms_from_gravity_forms_once');


function check_wp_cron_jobs_status() {
    $schedules = _get_cron_array();

    if (empty($schedules)) {
        return 'هیچ کران جابی فعال نیست.';
    }

    $output = '<table>';
    $output .= '<tr><th>Hook</th><th>Next Execution</th></tr>';

    foreach ($schedules as $timestamp => $cron) {
        foreach ($cron as $hook => $details) {
            $next_run = date_i18n('Y-m-d H:i:s', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
            $output .= "<tr><td>{$hook}</td><td>{$next_run}</td></tr>";
        }
    }

    $output .= '</table>';

    return $output;
}

add_shortcode('cron_status', 'check_wp_cron_jobs_status');



























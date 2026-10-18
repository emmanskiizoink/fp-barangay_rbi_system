<?php
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("CSRF token validation failed. Possible cross-site request forgery detected.");
        }
    }
}

if (php_sapi_name() === 'cli') {
    $host_address_url = 'http://localhost:8000';
    $current_operating_system = PHP_OS_FAMILY;
    if ($current_operating_system === 'Windows') {
        pclose(popen("start $host_address_url", "r"));
    } elseif ($current_operating_system === 'Emman') {
        exec("open $host_address_url");
    } else {
        exec("xdg-open $host_address_url");
    }
    passthru("php -S localhost:8000 " . __FILE__);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $database_host_name = "localhost";
    $database_user_name = "root";
    $database_password_key = "";
    $database_schema_name = "barangay_rbi_system";
    
    $database_connection_object = new mysqli($database_host_name, $database_user_name, $database_password_key);
    
    $create_database_sql_query = "CREATE DATABASE IF NOT EXISTS `$database_schema_name`";
    $database_connection_object->query($create_database_sql_query);
    $database_connection_object->select_db($database_schema_name);
    
    $create_table_sql_query = "CREATE TABLE IF NOT EXISTS `masterlist` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `last_name` varchar(100) NOT NULL,
        `first_name` varchar(100) NOT NULL,
        `middle_name` varchar(100) DEFAULT NULL,
        `qualifier` varchar(20) DEFAULT NULL,
        `house_no` varchar(50) DEFAULT NULL,
        `st_name` varchar(100) DEFAULT NULL,
        `purok_no` varchar(50) NOT NULL,
        `date_of_birth` date NOT NULL,
        `place_of_birth` varchar(150) DEFAULT NULL,
        `sex` varchar(10) DEFAULT NULL,
        `civil_status` varchar(50) DEFAULT NULL,
        `citizenship` varchar(50) DEFAULT 'Filipino',
        `occupation` varchar(100) DEFAULT NULL,
        `relationship_to_head` varchar(100) DEFAULT NULL,
        `is_4ps` varchar(10) DEFAULT 'No',
        `is_ips` varchar(10) DEFAULT 'No',
        PRIMARY KEY (`id`)
    )";
    $database_connection_object->query($create_table_sql_query);

    $create_users_table_query = "CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        `full_name` varchar(100) DEFAULT NULL,
        `role` varchar(50) DEFAULT 'Encoder',
        PRIMARY KEY (`id`)
    )";
    $database_connection_object->query($create_users_table_query);
    

    $check_role_query = "SHOW COLUMNS FROM `users` LIKE 'role'";
    if ($database_connection_object->query($check_role_query)->num_rows == 0) {
        $database_connection_object->query("ALTER TABLE `users` ADD `role` varchar(50) DEFAULT 'Encoder'");
        $database_connection_object->query("UPDATE users SET role='Administrator' WHERE username='admin'");
    }



$create_logs_table_query = "CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `details` text,
    `timestamp` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
)";
$database_connection_object->query($create_logs_table_query);

function log_system_activity($db, $action, $details = "") {
    try {
        $u_id = $_SESSION['user_id'] ?? null;
        $l_stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
        $l_stmt->bind_param("iss", $u_id, $action, $details);
        $l_stmt->execute();
    } catch (Exception $e) {

    }
}

if (isset($_POST['change_password'])) {
    verify_csrf();

    try {
        $user_id = $_SESSION['user_id'];
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if ($new_pass !== $confirm_pass) {
            echo "<script>alert('New passwords do not match!'); window.history.back();</script>";
            exit;
        }

        $stmt = $database_connection_object->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();

        if (password_verify($old_pass, $user_data['password'])) {
            $hashed_new = password_hash($new_pass, PASSWORD_DEFAULT);
            $update_stmt = $database_connection_object->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_new, $user_id);
            $update_stmt->execute();
            log_system_activity($database_connection_object, "CHANGE_PASSWORD", "User updated their own password.");
            header("Location: index.php?mode=users&msg=Password_updated_successfully");
            exit;
        } else {
            header("Location: index.php?mode=users&msg=Incorrect_old_password&type=error");
            exit;
        }
    } catch (Exception $e) {
        header("Location: index.php?mode=users&msg=Error_updating_password&type=error");
        exit();
    }
}

    $user_check = $database_connection_object->query("SELECT id FROM users LIMIT 1");
    if ($user_check->num_rows == 0) {
        $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $database_connection_object->query("INSERT INTO users (username, password, full_name, role) VALUES ('admin', '$default_pass', 'System Administrator', 'Administrator')");
    }

    
    $check_column_query = "SHOW COLUMNS FROM `masterlist` LIKE 'is_4ps'";
    $result = $database_connection_object->query($check_column_query);
    if ($result->num_rows == 0) {
        $database_connection_object->query("ALTER TABLE `masterlist` ADD `is_4ps` varchar(10) DEFAULT 'No'");
        $database_connection_object->query("ALTER TABLE `masterlist` ADD `is_ips` varchar(10) DEFAULT 'No'");
    }

    
    $check_senior_query = "SHOW COLUMNS FROM `masterlist` LIKE 'is_senior'";
    if ($database_connection_object->query($check_senior_query)->num_rows == 0) {
        $database_connection_object->query("ALTER TABLE `masterlist` ADD `is_senior` varchar(10) DEFAULT 'No'");
        $database_connection_object->query("ALTER TABLE `masterlist` ADD `educational_background` varchar(150) DEFAULT 'N/A'");
    }

   
    $check_senior_id_query = "SHOW COLUMNS FROM `masterlist` LIKE 'senior_id'";
    if ($database_connection_object->query($check_senior_id_query)->num_rows == 0) {
        $database_connection_object->query("ALTER TABLE `masterlist` ADD `senior_id` varchar(50) DEFAULT 'N/A'");
    }


    $check_pwd_query = "SHOW COLUMNS FROM `masterlist` LIKE 'is_pwd'";
    if ($database_connection_object->query($check_pwd_query)->num_rows == 0) {
        $database_connection_object->query("ALTER TABLE `masterlist` ADD `is_pwd` varchar(10) DEFAULT 'No'");
        $database_connection_object->query("ALTER TABLE `masterlist` ADD `pwd_id` varchar(50) DEFAULT 'N/A'");
        $database_connection_object->query("ALTER TABLE `masterlist` ADD `disability_type` varchar(150) DEFAULT 'N/A'");
    }


    $check_religion_query = "SHOW COLUMNS FROM `masterlist` LIKE 'religion'";
    if ($database_connection_object->query($check_religion_query)->num_rows == 0) {
        $database_connection_object->query("ALTER TABLE `masterlist` ADD `religion` varchar(100) DEFAULT 'N/A'");
    }
} catch (Exception $database_connection_exception) {
    header("Location: index.php?mode=login&msg=Database_Error_Check_MySQL&type=error");
    exit();
}

$application_current_mode = $_GET['mode'] ?? 'search';

if ($application_current_mode == 'heartbeat') {
    file_put_contents('last_heartbeat.txt', time());
    echo "alive";
    exit;
}

if ($application_current_mode == 'logout') {
    log_system_activity($database_connection_object, "LOGOUT", "User logged out manually");
    $_SESSION = [];
    session_destroy();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    header("Location: index.php?mode=login");
    exit();
}

if ($application_current_mode == 'login' && isset($_POST['login_action'])) {
    verify_csrf();

    $auth_username = $_POST['username'];
    $auth_password = $_POST['password'];
    $auth_stmt = $database_connection_object->prepare("SELECT * FROM users WHERE username = ?");
    $auth_stmt->bind_param("s", $auth_username);
    $auth_stmt->execute();
    $auth_user_data = $auth_stmt->get_result()->fetch_assoc();
    if ($auth_user_data && password_verify($auth_password, $auth_user_data['password'])) {
        $_SESSION['user_id'] = $auth_user_data['id'];
        $_SESSION['username'] = $auth_user_data['username'];
        $_SESSION['full_name'] = $auth_user_data['full_name'];
        $_SESSION['role'] = $auth_user_data['role'];
        log_system_activity($database_connection_object, "LOGIN", "Successful login for user: $auth_username");
        session_regenerate_id(true);
        header("Location: index.php?mode=search");
        exit();
    } else {
        log_system_activity($database_connection_object, "LOGIN_FAILED", "Failed attempt for username: $auth_username");
        $auth_error_message = "Invalid username or password!";
    }
}

if (!isset($_SESSION['user_id']) && $application_current_mode != 'login') {
    header("Location: index.php?mode=login");
    exit();
}

if ($application_current_mode == 'users' && ($_SESSION['role'] ?? '') != 'Administrator') {
    header("Location: index.php?mode=search");
    exit();
}

if (isset($_POST['save_user'])) {
    verify_csrf();

    $new_user_name = htmlspecialchars($_POST['user_name']);
    $new_full_name = htmlspecialchars($_POST['user_full_name']);
    $new_role = htmlspecialchars($_POST['user_role']);
    $new_password_hash = password_hash($_POST['user_password'], PASSWORD_DEFAULT);
    $user_insert_stmt = $database_connection_object->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $user_insert_stmt->bind_param("ssss", $new_user_name, $new_password_hash, $new_full_name, $new_role);
    $user_insert_stmt->execute();
    log_system_activity($database_connection_object, "ADD_USER", "Created user account: $new_user_name");
    header("Location: index.php?mode=users");
    exit();
}

if (isset($_POST['delete_user_action'])) {
    verify_csrf();
    if (($_SESSION['role'] ?? '') != 'Administrator') die("Unauthorized");
    $delete_user_id = $_POST['delete_user_id'];
    if ($delete_user_id != $_SESSION['user_id']) {
        log_system_activity($database_connection_object, "DELETE_USER", "Deleted user ID: $delete_user_id");
        $user_delete_stmt = $database_connection_object->prepare("DELETE FROM users WHERE id = ?");
        $user_delete_stmt->bind_param("i", $delete_user_id);
        $user_delete_stmt->execute();
        header("Location: index.php?mode=users&msg=User_deleted_successfully");
    } else {
        header("Location: index.php?mode=users&msg=Cannot_delete_yourself&type=error");
    }
    exit();
}

if (isset($_POST['delete_resident_action'])) {
    verify_csrf();
    try {
        $record_delete_id = $_POST['delete_id'];
        log_system_activity($database_connection_object, "DELETE_RESIDENT", "Deleted record ID: $record_delete_id");
        $delete_record_query = "DELETE FROM masterlist WHERE id = ?";
        $prepared_delete_statement = $database_connection_object->prepare($delete_record_query);
        $prepared_delete_statement->bind_param("i", $record_delete_id);
        $prepared_delete_statement->execute();
        header("Location: index.php?mode=search&msg=Record_deleted_successfully");
        exit();
    } catch (Exception $record_deletion_exception) {
        header("Location: index.php?mode=search&msg=Error_deleting_record&type=error");
        exit();
    }
}

if (isset($_POST['save_resident'])) {
    verify_csrf();

    try {
        $input_last_name = htmlspecialchars($_POST['last_name']);
        $input_first_name = htmlspecialchars($_POST['first_name']);
        $input_middle_name = htmlspecialchars($_POST['middle_name']);
        $input_name_qualifier = htmlspecialchars($_POST['name_qualifier']);
        $input_house_number = htmlspecialchars($_POST['house_number']);
        $input_street_name = htmlspecialchars($_POST['street_name']);
        $input_purok_number = htmlspecialchars($_POST['purok_number']);
        $input_place_of_birth = htmlspecialchars($_POST['place_of_birth']);
        $input_gender_sex = htmlspecialchars($_POST['gender_sex']);
        $input_civil_status = htmlspecialchars($_POST['civil_status']);
        $input_citizenship_status = htmlspecialchars($_POST['citizenship_status']);
        $input_occupation_job = htmlspecialchars($_POST['occupation_job']);
        $input_relationship_status = htmlspecialchars($_POST['relationship_status']);
        $input_is_4ps = htmlspecialchars($_POST['is_4ps']);
        $input_is_ips = htmlspecialchars($_POST['is_ips']);
        $input_is_senior = htmlspecialchars($_POST['is_senior']);
        $input_senior_id = htmlspecialchars($_POST['senior_id'] ?? 'N/A');
        $input_is_pwd = htmlspecialchars($_POST['is_pwd']);
        $input_pwd_id = htmlspecialchars($_POST['pwd_id'] ?? 'N/A');
        $input_disability = htmlspecialchars($_POST['disability_type'] ?? 'N/A');
        $input_religion = htmlspecialchars($_POST['religion_status'] ?? 'N/A');
        

        if ($input_is_senior == 'Yes' && (!preg_match('/^\d{10,16}$/', $input_senior_id))) {
            header("Location: " . $_SERVER['HTTP_REFERER'] . "&msg=Invalid_Senior_ID&type=error");
            exit;
        }


        if ($input_is_pwd == 'Yes' && (!preg_match('/^\d{14,16}$/', $input_pwd_id))) {
            header("Location: " . $_SERVER['HTTP_REFERER'] . "&msg=Invalid_PWD_ID&type=error");
            exit;
        }

        $input_education = htmlspecialchars($_POST['educational_background']);
        
        $date_of_birth_raw_input = $_POST['date_of_birth'];
        $parsed_date_object = DateTime::createFromFormat('m/d/Y', $date_of_birth_raw_input);
        $date_of_birth_formatted_string = $parsed_date_object ? $parsed_date_object->format('Y-m-d') : date('Y-m-d');

        $insert_record_query = "INSERT INTO masterlist (last_name, first_name, middle_name, qualifier, house_no, st_name, purok_no, date_of_birth, place_of_birth, sex, civil_status, citizenship, religion, occupation, relationship_to_head, is_4ps, is_ips, is_senior, senior_id, is_pwd, pwd_id, disability_type, educational_background) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $prepared_insert_statement = $database_connection_object->prepare($insert_record_query);
        $prepared_insert_statement->bind_param("sssssssssssssssssssssss", $input_last_name, $input_first_name, $input_middle_name, $input_name_qualifier, $input_house_number, $input_street_name, $input_purok_number, $date_of_birth_formatted_string, $input_place_of_birth, $input_gender_sex, $input_civil_status, $input_citizenship_status, $input_religion, $input_occupation_job, $input_relationship_status, $input_is_4ps, $input_is_ips, $input_is_senior, $input_senior_id, $input_is_pwd, $input_pwd_id, $input_disability, $input_education);
        
        if ($prepared_insert_statement->execute()) {
            log_system_activity($database_connection_object, "ADD_RESIDENT", "Added: $input_first_name $input_last_name");
            header("Location: index.php?mode=search&msg=Resident_added_successfully");
            exit;
        }
    } catch (Exception $record_insertion_exception) {
        header("Location: index.php?mode=add&msg=Error_adding_resident&type=error");
        exit();
    }
}

if (isset($_POST['update_resident'])) {
    verify_csrf();

    try {
        $edit_target_id = $_POST['edit_target_id'];
        $input_last_name = htmlspecialchars($_POST['last_name']);
        $input_first_name = htmlspecialchars($_POST['first_name']);
        $input_middle_name = htmlspecialchars($_POST['middle_name']);
        $input_name_qualifier = htmlspecialchars($_POST['name_qualifier']);
        $input_house_number = htmlspecialchars($_POST['house_number']);
        $input_street_name = htmlspecialchars($_POST['street_name']);
        $input_purok_number = htmlspecialchars($_POST['purok_number']);
        $input_place_of_birth = htmlspecialchars($_POST['place_of_birth']);
        $input_gender_sex = htmlspecialchars($_POST['gender_sex']);
        $input_civil_status = htmlspecialchars($_POST['civil_status']);
        $input_citizenship_status = htmlspecialchars($_POST['citizenship_status']);
        $input_occupation_job = htmlspecialchars($_POST['occupation_job']);
        $input_relationship_status = htmlspecialchars($_POST['relationship_status']);
        $input_is_4ps = htmlspecialchars($_POST['is_4ps']);
        $input_is_ips = htmlspecialchars($_POST['is_ips']);
        $input_is_senior = htmlspecialchars($_POST['is_senior']);
        $input_senior_id = htmlspecialchars($_POST['senior_id'] ?? 'N/A');
        $input_is_pwd = htmlspecialchars($_POST['is_pwd']);
        $input_pwd_id = htmlspecialchars($_POST['pwd_id'] ?? 'N/A');
        $input_disability = htmlspecialchars($_POST['disability_type'] ?? 'N/A');
        $input_religion = htmlspecialchars($_POST['religion_status'] ?? 'N/A');


        if ($input_is_senior == 'Yes' && (!preg_match('/^\d{10,16}$/', $input_senior_id))) {
            echo "<script>alert('Invalid Senior ID. It must be 10-16 digits.'); window.history.back();</script>";
            exit;
        }


        if ($input_is_pwd == 'Yes' && (!preg_match('/^\d{14,16}$/', $input_pwd_id))) {
            echo "<script>alert('Invalid PWD ID. It must be 14-16 digits.'); window.history.back();</script>";
            exit;
        }

        $input_education = htmlspecialchars($_POST['educational_background']);
        
        $date_of_birth_raw_input = $_POST['date_of_birth'];
        $parsed_date_object = DateTime::createFromFormat('m/d/Y', $date_of_birth_raw_input);
        $date_of_birth_formatted_string = $parsed_date_object ? $parsed_date_object->format('Y-m-d') : date('Y-m-d');

        $update_record_query = "UPDATE masterlist SET last_name=?, first_name=?, middle_name=?, qualifier=?, house_no=?, st_name=?, purok_no=?, date_of_birth=?, place_of_birth=?, sex=?, civil_status=?, citizenship=?, religion=?, occupation=?, relationship_to_head=?, is_4ps=?, is_ips=?, is_senior=?, senior_id=?, is_pwd=?, pwd_id=?, disability_type=?, educational_background=? WHERE id=?";
        $prepared_update_statement = $database_connection_object->prepare($update_record_query);
        $prepared_update_statement->bind_param("sssssssssssssssssssssssi", $input_last_name, $input_first_name, $input_middle_name, $input_name_qualifier, $input_house_number, $input_street_name, $input_purok_number, $date_of_birth_formatted_string, $input_place_of_birth, $input_gender_sex, $input_civil_status, $input_citizenship_status, $input_religion, $input_occupation_job, $input_relationship_status, $input_is_4ps, $input_is_ips, $input_is_senior, $input_senior_id, $input_is_pwd, $input_pwd_id, $input_disability, $input_education, $edit_target_id);
        
        if ($prepared_update_statement->execute()) {
            log_system_activity($database_connection_object, "UPDATE_RESIDENT", "Updated record ID: $edit_target_id ($input_first_name $input_last_name)");
            header("Location: index.php?mode=search&msg=Record_updated_successfully");
            exit;
        }
    } catch (Exception $record_update_exception) {
        header("Location: index.php?mode=search&msg=Error_updating_record&type=error");
        exit();
    }
}

$search_results_data_array =[];
$search_query_string = trim($_GET['search_keyword'] ?? '');
$sort_option = $_GET['sort_by'] ?? 'last_name';

if (!empty($search_query_string)) {
    try {
        log_system_activity($database_connection_object, "SEARCH", "Keyword: $search_query_string (Sorted by: $sort_option)");
        $order_clause = "last_name ASC";
        if ($sort_option == 'first_name') $order_clause = "first_name ASC";
        if ($sort_option == 'purok') $order_clause = "purok_no ASC, last_name ASC";

        $search_records_query = "SELECT *, DATE_FORMAT(date_of_birth, '%m/%d/%Y') as formatted_birthday FROM masterlist WHERE last_name LIKE ? OR first_name LIKE ? OR purok_no LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? ORDER BY $order_clause";
        $prepared_search_statement = $database_connection_object->prepare($search_records_query);
        $wildcard_search_term_string = "%{$search_query_string}%";
        $prepared_search_statement->bind_param("ssss", $wildcard_search_term_string, $wildcard_search_term_string, $wildcard_search_term_string, $wildcard_search_term_string);
        $prepared_search_statement->execute();
        $database_search_result_set = $prepared_search_statement->get_result();
        while($fetched_row_data = $database_search_result_set->fetch_assoc()) { 
            $search_results_data_array[] = $fetched_row_data; 
        }
    } catch (Exception $search_execution_exception) {
        header("Location: index.php?mode=search&msg=Search_Failed&type=error");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>barangay_san_eugenio_rbi_system</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; margin: 0; padding: 20px; color: #333; }
        .glass_container_box { max-width: 98%; margin: auto; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); padding: 30px; border-radius: 20px; box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.5); }
        .system_header_title { text-align: center; border-bottom: 3px solid #667eea; margin-bottom: 25px; padding-bottom: 10px; }
        .navigation_menu_bar { text-align: center; margin-bottom: 25px; }
        .action_button_style { padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; color: white; display: inline-block; cursor: pointer; border: none; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .action_button_style:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0,0,0,0.2); }
        .button_color_green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .button_color_blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .button_color_orange { background: linear-gradient(135deg, #f12711 0%, #f5af19 100%); }
        .button_color_red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
        
        .data_table_layout { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; font-size: 11px; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); white-space: nowrap; }
        .resident_table { min-width: 1200px; }
        .table_header_cell { background: #667eea; color: white; padding: 10px 8px; text-align: left; text-transform: uppercase; font-size: 10px; }
        .table_data_cell { background: rgba(255,255,255,0.9); border-bottom: 1px solid #eee; padding: 8px 6px; color: #444; }
        .data_row_hover:hover .table_data_cell { background: #f8f9ff; }
        .table_scroll_wrapper { width: 100%; overflow-x: auto; border-radius: 10px; margin-top: 15px; scrollbar-width: thin; scrollbar-color: #667eea #f0f0f0; border: 1px solid #eee; }
        .sticky_action_cell { position: sticky; right: 0; background: rgba(255,255,255,0.95) !important; border-left: 2px solid #667eea; box-shadow: -6px 0 10px rgba(0,0,0,0.1); z-index: 10; }
        th.sticky_action_cell { background: #667eea !important; color: white; border-left: none; box-shadow: none; }
        .data_row_hover:hover .sticky_action_cell { background: #f0f4ff !important; }

        .modal_overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; animation: fadeIn 0.3s; }
        .modal_content { background: white; width: 90%; max-width: 700px; padding: 30px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); position: relative; max-height: 90vh; overflow-y: auto; }
        .close_modal { position: absolute; top: 20px; right: 20px; font-size: 24px; cursor: pointer; color: #888; font-weight: bold; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .profile_detail_grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .detail_item { border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .detail_label { font-size: 11px; color: #777; text-transform: uppercase; font-weight: bold; }
        .detail_value { font-size: 15px; color: #333; margin-top: 2px; }

        .form_grid_layout { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; text-align: left; }
        .input_group_container { display: flex; flex-direction: column; }
        .input_label_text { font-size: 12px; font-weight: bold; color: #555; margin-bottom: 5px; text-transform: uppercase; }
        .text_input_field, .dropdown_select_field { padding: 12px; border: 1px solid #ddd; border-radius: 8px; width: 100%; box-sizing: border-box; background: rgba(255,255,255,0.9); transition: border 0.3s, box-shadow 0.3s; }
        .text_input_field:focus, .dropdown_select_field:focus { border: 1px solid #667eea; outline: none; box-shadow: 0 0 8px rgba(102, 126, 234, 0.4); }
        .full_width_input_group { grid-column: 1 / -1; }
        .form_background_container { background: rgba(245, 247, 250, 0.8); padding: 25px; border-radius: 15px; border: 1px solid #e1e5ee; }
        .edit_form_background_container { background: rgba(255, 250, 240, 0.9); padding: 25px; border: 2px solid #f5af19; border-radius: 15px; }

       
        #toast_container { position: fixed; top: 20px; right: 20px; z-index: 9999; pointer-events: none; }
        .toast_message { 
            background: white; color: #333; padding: 16px 24px; border-radius: 12px; margin-bottom: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-left: 5px solid #667eea;
            display: flex; align-items: center; gap: 12px; font-weight: 500;
            transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: auto; min-width: 300px;
        }
        .toast_message.show { transform: translateX(0); }
        .toast_success { border-left-color: #11998e; }
        .toast_error { border-left-color: #eb3349; }
        .toast_icon { font-size: 20px; }
    </style>

</head>
<body onload="<?php if(isset($on_load_js)) echo $on_load_js; ?>">
<div id="toast_container"></div>


<div class="glass_container_box">
    <div class="system_header_title">
        <h1 style="margin:0; color:#2c3e50;">BARANGAY SAN EUGENIO</h1>
        <p style="color: #34495e; margin-bottom:10px; font-weight:bold;">Registry of Barangay Inhabitants (RBI) Monitoring System</p>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="navigation_menu_bar">
        <a href="index.php?mode=search" class="action_button_style button_color_green">SEARCH</a>
        <a href="index.php?mode=add" class="action_button_style button_color_blue">ADD RECORD</a>
        <?php if(($_SESSION['role'] ?? '') == 'Administrator'): ?>
            <a href="index.php?mode=users" class="action_button_style button_color_orange">ACCOUNTS</a>
            <a href="index.php?mode=history" class="action_button_style button_color_blue" style="background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);">HISTORY</a>
        <?php endif; ?>
        <a href="index.php?mode=logout" class="action_button_style button_color_red" onclick="return confirm('Confirm Logout?')">LOGOUT</a>
    </div>
    <div style="text-align:right; font-size:12px; color:#555; margin-bottom:10px;">
        Logged in as: <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?></strong> 
        (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?>)
    </div>
    <?php endif; ?>

    <?php if ($application_current_mode == 'search'): ?>
        <form method="GET" style="text-align:center; margin-bottom:20px;">
            <input type="hidden" name="mode" value="search">
            <input type="text" name="search_keyword" class="text_input_field" placeholder="Search name or purok..." style="width:300px; display:inline-block;" value="<?php echo htmlspecialchars($search_query_string); ?>">
            <select name="sort_by" class="dropdown_select_field" style="width:160px; display:inline-block; margin-left:10px;" onchange="this.form.submit()">
                <option value="last_name" <?php if($sort_option=='last_name')echo'selected';?>>Sort by Last Name</option>
                <option value="first_name" <?php if($sort_option=='first_name')echo'selected';?>>Sort by First Name</option>
                <option value="purok" <?php if($sort_option=='purok')echo'selected';?>>Sort by Purok</option>
            </select>
            <button type="submit" class="action_button_style button_color_green">FIND</button>
        </form>

        <?php if (!empty($search_query_string)): ?>
            <div class="table_scroll_wrapper">
                <table class="data_table_layout resident_table">
                    <thead>
                        <tr>
                            <th class="table_header_cell">LAST</th><th class="table_header_cell">FIRST</th><th class="table_header_cell">MIDDLE</th><th class="table_header_cell">QUAL</th><th class="table_header_cell">HOUSE #</th><th class="table_header_cell">ST. NAME</th>
                            <th class="table_header_cell">PRK</th><th class="table_header_cell">BIRTH DATE</th><th class="table_header_cell">PLACE OF BIRTH</th><th class="table_header_cell">SEX</th><th class="table_header_cell">CIVIL</th><th class="table_header_cell">CITIZEN</th><th class="table_header_cell">RELIGION</th><th class="table_header_cell">OCCUPATION</th><th class="table_header_cell">RELATION TO HEAD</th><th class="table_header_cell">4PS</th><th class="table_header_cell">IPs</th><th class="table_header_cell">SR</th><th class="table_header_cell">PWD</th><th class="table_header_cell">EDUCATION</th><th class="table_header_cell sticky_action_cell">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results_data_array as $fetched_record): ?>
                        <tr class="data_row_hover">
                            <td class="table_data_cell"><strong><?php echo htmlspecialchars($fetched_record['last_name']); ?></strong></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['first_name']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['middle_name']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['qualifier']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['house_no']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['st_name']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['purok_no']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['formatted_birthday']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['place_of_birth']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['sex']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['civil_status']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['citizenship']); ?></td>
                            <td class="table_data_cell" style="font-size:9px;"><?php echo htmlspecialchars($fetched_record['religion']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['occupation']); ?></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($fetched_record['relationship_to_head']); ?></td>
                            <td class="table_data_cell"><strong><?php echo htmlspecialchars($fetched_record['is_4ps']); ?></strong></td>
                            <td class="table_data_cell"><strong><?php echo htmlspecialchars($fetched_record['is_ips']); ?></strong></td>
                            <td class="table_data_cell">
                                <strong><?php echo htmlspecialchars($fetched_record['is_senior']); ?></strong>
                                <?php if($fetched_record['is_senior'] == 'Yes') echo "<br><span style='font-size:9px; color:#555;'>ID:" . htmlspecialchars($fetched_record['senior_id']) . "</span>"; ?>
                            </td>
                            <td class="table_data_cell">
                                <strong><?php echo htmlspecialchars($fetched_record['is_pwd']); ?></strong>
                                <?php if($fetched_record['is_pwd'] == 'Yes') echo "<br><span style='font-size:8px; color:#555;'>ID:" . htmlspecialchars($fetched_record['pwd_id']) . "<br>" . htmlspecialchars($fetched_record['disability_type']) . "</span>"; ?>
                            </td>
                            <td class="table_data_cell" style="font-size:9px;"><?php echo htmlspecialchars($fetched_record['educational_background']); ?></td>
                            <td class="table_data_cell sticky_action_cell">
                                <a href="javascript:void(0)" onclick="showProfile(<?php echo htmlspecialchars(json_encode($fetched_record)); ?>)" style="color:#667eea; font-weight:bold; text-decoration:none;">VIEW</a> | 
                                <a href="index.php?mode=edit&id=<?php echo $fetched_record['id']; ?>" style="color:#f5af19; font-weight:bold; text-decoration:none;">EDIT</a> | 
                                <a href="javascript:void(0)" onclick="secureDelete('resident', <?php echo $fetched_record['id']; ?>)" style="color:#f12711; font-weight:bold; text-decoration:none;">DEL</a>

                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($application_current_mode == 'add'): ?>
        <form method="POST" class="form_background_container">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <h3 style="margin-top:0; color:#4facfe;">Add New Inhabitant</h3>
            <div class="form_grid_layout">
                <div class="input_group_container"><label class="input_label_text">LAST</label><input type="text" name="last_name" class="text_input_field" required></div>
                <div class="input_group_container"><label class="input_label_text">FIRST</label><input type="text" name="first_name" class="text_input_field" required></div>
                <div class="input_group_container"><label class="input_label_text">MIDDLE</label><input type="text" name="middle_name" class="text_input_field"></div>
                <div class="input_group_container">
                    <label class="input_label_text">QUALIFIER</label>
                    <select name="name_qualifier" class="dropdown_select_field">
                        <option value="">N/A</option>
                        <option value="Jr.">Jr.</option>
                        <option value="Sr.">Sr.</option>
                        <option value="I">I</option>
                        <option value="II">II</option>
                        <option value="III">III</option>
                        <option value="IV">IV</option>
                        <option value="V">V</option>
                    </select>
                </div>
                <div class="input_group_container"><label class="input_label_text">HOUSE NO.</label><input type="text" name="house_number" class="text_input_field"></div>
                <div class="input_group_container"><label class="input_label_text">ST. NAME</label><input type="text" name="street_name" class="text_input_field"></div>
                <div class="input_group_container"><label class="input_label_text">PUROK NO.</label><input type="text" name="purok_number" class="text_input_field" required></div>
                <div class="input_group_container">
                    <label class="input_label_text">DATE OF BIRTH (MM/DD/YYYY)</label>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <input type="text" name="date_of_birth" id="date_of_birth_add_input" class="text_input_field" maxlength="10" placeholder="MM/DD/YYYY" required>
                        <input type="date" id="dob_picker_add" style="position:absolute; opacity:0; width:0; height:0; pointer-events:none;">
                        <button type="button" class="action_button_style button_color_blue" style="padding: 10px; min-width: 45px; font-size: 18px;" onclick="document.getElementById('dob_picker_add').showPicker()">📅</button>
                    </div>
                </div>
                <div class="input_group_container"><label class="input_label_text">PLACE OF BIRTH</label><input type="text" name="place_of_birth" class="text_input_field"></div>
                <div class="input_group_container"><label class="input_label_text">SEX</label><select name="gender_sex" class="dropdown_select_field"><option>M</option><option>F</option></select></div>
                <div class="input_group_container">
                    <label class="input_label_text">CIVIL STATUS</label>
                    <select name="civil_status" class="dropdown_select_field">
                        <option>Single</option>
                        <option>Married</option>
                        <option>Widowed</option>
                        <option>Separated</option>
                        <option>Annulled</option>
                    </select>
                </div>
                <div class="input_group_container"><label class="input_label_text">CITIZENSHIP</label><input type="text" name="citizenship_status" class="text_input_field" value="Filipino"></div>
                <div class="input_group_container"><label class="input_label_text">RELIGION</label><input type="text" name="religion_status" class="text_input_field" placeholder="e.g. Roman Catholic"></div>
                <div class="input_group_container"><label class="input_label_text">OCCUPATION</label><input type="text" name="occupation_job" class="text_input_field"></div>
                <div class="input_group_container"><label class="input_label_text">RELATIONSHIP TO HOUSEHOLD HEAD</label><input type="text" name="relationship_status" class="text_input_field"></div>
                <div class="input_group_container"><label class="input_label_text">EDUCATIONAL BACKGROUND</label><select name="educational_background" class="dropdown_select_field"><option>N/A</option><option>Elementary Undergraduate</option><option>Elementary Graduate</option><option>High School Undergraduate</option><option>High School Graduate</option><option>Vocational</option><option>College Undergraduate</option><option>College Graduate</option><option>Post Graduate</option></select></div>
                <div class="input_group_container"><label class="input_label_text">4PS MEMBER?</label><select name="is_4ps" class="dropdown_select_field"><option>No</option><option>Yes</option></select></div>
                <div class="input_group_container"><label class="input_label_text">IP MEMBER?</label><select name="is_ips" class="dropdown_select_field"><option>No</option><option>Yes</option></select></div>
                <div class="input_group_container"><label class="input_label_text">SENIOR CITIZEN?</label><select name="is_senior" id="is_senior_add" class="dropdown_select_field" onchange="toggleField('is_senior_add', 'senior_id_container_add')"><option>No</option><option>Yes</option></select></div>
                <div class="input_group_container" id="senior_id_container_add" style="display:none;"><label class="input_label_text">SENIOR ID NUMBER (10-16 digits)</label><input type="text" name="senior_id" class="text_input_field" placeholder="Enter 10-16 digits" pattern="\d{10,16}" title="Senior ID must be between 10 and 16 digits."></div>
                <div class="input_group_container"><label class="input_label_text">PWD?</label><select name="is_pwd" id="is_pwd_add" class="dropdown_select_field" onchange="toggleField('is_pwd_add', 'pwd_container_add')"><option>No</option><option>Yes</option></select></div>
                <div id="pwd_container_add" style="display:none; grid-column: span 2; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="input_group_container"><label class="input_label_text">PWD ID NUMBER (14-16 digits)</label><input type="text" name="pwd_id" class="text_input_field" placeholder="Enter 14-16 digits" pattern="\d{14,16}" title="PWD ID must be between 14 and 16 digits."></div>
                    <div class="input_group_container"><label class="input_label_text">DISABILITY TYPE / DETAILS</label><input type="text" name="disability_type" class="text_input_field" placeholder="e.g. Visual Impairment"></div>
                </div>
            </div>
            <button type="submit" name="save_resident" class="action_button_style button_color_blue" style="width:100%; margin-top:20px;">SAVE RESIDENT</button>
        </form>
    <?php endif; ?>

    <?php if ($application_current_mode == 'edit' && isset($_GET['id'])): 
        try {
            $record_edit_id = $_GET['id'];
            $fetch_edit_record_query = "SELECT *, DATE_FORMAT(date_of_birth, '%m/%d/%Y') as formatted_birthday FROM masterlist WHERE id=?";
            $prepared_fetch_statement = $database_connection_object->prepare($fetch_edit_record_query);
            $prepared_fetch_statement->bind_param("i", $record_edit_id);
            $prepared_fetch_statement->execute();
            $edit_record_data = $prepared_fetch_statement->get_result()->fetch_assoc();
        } catch (Exception $fetch_execution_exception) {
            header("Location: index.php?mode=search&msg=Record_Not_Found&type=error");
            exit();
        }
    ?>
        <form method="POST" class="edit_form_background_container">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <h3 style="color:#f5af19; margin-top:0;">Edit Resident Record</h3>
            <input type="hidden" name="edit_target_id" value="<?php echo htmlspecialchars($edit_record_data['id']); ?>">
            <div class="form_grid_layout">
                <div class="input_group_container"><label class="input_label_text">LAST</label><input type="text" name="last_name" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['last_name']); ?>" required></div>
                <div class="input_group_container"><label class="input_label_text">FIRST</label><input type="text" name="first_name" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['first_name']); ?>" required></div>
                <div class="input_group_container"><label class="input_label_text">MIDDLE</label><input type="text" name="middle_name" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['middle_name']); ?>"></div>
                <div class="input_group_container">
                    <label class="input_label_text">QUALIFIER</label>
                    <select name="name_qualifier" class="dropdown_select_field">
                        <?php 
                        $qualifier_options = ["", "Jr.", "Sr.", "I", "II", "III", "IV", "V"];
                        foreach ($qualifier_options as $option) {
                            $selected = ($edit_record_data['qualifier'] == $option) ? "selected" : "";
                            $label = ($option == "") ? "N/A" : $option;
                            echo "<option value=\"$option\" $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="input_group_container"><label class="input_label_text">HOUSE NO.</label><input type="text" name="house_number" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['house_no']); ?>"></div>
                <div class="input_group_container"><label class="input_label_text">ST. NAME</label><input type="text" name="street_name" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['st_name']); ?>"></div>
                <div class="input_group_container"><label class="input_label_text">PUROK NO.</label><input type="text" name="purok_number" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['purok_no']); ?>" required></div>
                <div class="input_group_container">
                    <label class="input_label_text">DATE OF BIRTH (MM/DD/YYYY)</label>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <input type="text" name="date_of_birth" id="date_of_birth_edit_input" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['formatted_birthday']); ?>" maxlength="10" required>
                        <input type="date" id="dob_picker_edit" style="position:absolute; opacity:0; width:0; height:0; pointer-events:none;">
                        <button type="button" class="action_button_style button_color_orange" style="padding: 10px; min-width: 45px; font-size: 18px;" onclick="document.getElementById('dob_picker_edit').showPicker()">📅</button>
                    </div>
                </div>
                <div class="input_group_container"><label class="input_label_text">PLACE OF BIRTH</label><input type="text" name="place_of_birth" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['place_of_birth']); ?>"></div>
                <div class="input_group_container"><label class="input_label_text">SEX</label><select name="gender_sex" class="dropdown_select_field"><option value="M" <?php if($edit_record_data['sex']=='M')echo'selected';?>>M</option><option value="F" <?php if($edit_record_data['sex']=='F')echo'selected';?>>F</option></select></div>
                <div class="input_group_container">
                    <label class="input_label_text">CIVIL STATUS</label>
                    <select name="civil_status" class="dropdown_select_field">
                        <?php 
                        $civil_status_options = ["Single", "Married", "Widowed", "Separated", "Annulled"];
                        foreach ($civil_status_options as $option) {
                            $selected = ($edit_record_data['civil_status'] == $option) ? "selected" : "";
                            echo "<option value=\"$option\" $selected>$option</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="input_group_container"><label class="input_label_text">CITIZENSHIP</label><input type="text" name="citizenship_status" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['citizenship']); ?>"></div>
                <div class="input_group_container"><label class="input_label_text">RELIGION</label><input type="text" name="religion_status" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['religion']); ?>"></div>
                <div class="input_group_container"><label class="input_label_text">OCCUPATION</label><input type="text" name="occupation_job" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['occupation']); ?>"></div>
                <div class="input_group_container"><label class="input_label_text">RELATIONSHIP TO HOUSEHOLD HEAD</label><input type="text" name="relationship_status" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['relationship_to_head']); ?>"></div>
                <div class="input_group_container"><label class="input_label_text">EDUCATIONAL BACKGROUND</label>
                    <select name="educational_background" class="dropdown_select_field">
                        <?php 
                        $edu_options = ["N/A", "Elementary Undergraduate", "Elementary Graduate", "High School Undergraduate", "High School Graduate", "Vocational", "College Undergraduate", "College Graduate", "Post Graduate"];
                        foreach ($edu_options as $option) {
                            $selected = ($edit_record_data['educational_background'] == $option) ? "selected" : "";
                            echo "<option value=\"$option\" $selected>$option</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="input_group_container"><label class="input_label_text">4PS MEMBER?</label><select name="is_4ps" class="dropdown_select_field"><option value="No" <?php if($edit_record_data['is_4ps']=='No')echo'selected';?>>No</option><option value="Yes" <?php if($edit_record_data['is_4ps']=='Yes')echo'selected';?>>Yes</option></select></div>
                <div class="input_group_container"><label class="input_label_text">IP MEMBER?</label><select name="is_ips" class="dropdown_select_field"><option value="No" <?php if($edit_record_data['is_ips']=='No')echo'selected';?>>No</option><option value="Yes" <?php if($edit_record_data['is_ips']=='Yes')echo'selected';?>>Yes</option></select></div>
                <div class="input_group_container">
                    <label class="input_label_text">SENIOR CITIZEN?</label>
                    <select name="is_senior" id="is_senior_edit" class="dropdown_select_field" onchange="toggleField('is_senior_edit', 'senior_id_container_edit')">
                        <option value="No" <?php if($edit_record_data['is_senior']=='No')echo'selected';?>>No</option>
                        <option value="Yes" <?php if($edit_record_data['is_senior']=='Yes')echo'selected';?>>Yes</option>
                    </select>
                </div>
                <div class="input_group_container" id="senior_id_container_edit" style="display:<?php echo ($edit_record_data['is_senior']=='Yes')?'block':'none'; ?>;">
                    <label class="input_label_text">SENIOR ID NUMBER (10-16 digits)</label>
                    <input type="text" name="senior_id" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['senior_id']); ?>" pattern="\d{10,16}" title="Senior ID must be between 10 and 16 digits.">
                </div>
                <div class="input_group_container">
                    <label class="input_label_text">PWD?</label>
                    <select name="is_pwd" id="is_pwd_edit" class="dropdown_select_field" onchange="toggleField('is_pwd_edit', 'pwd_container_edit')">
                        <option value="No" <?php if($edit_record_data['is_pwd']=='No')echo'selected';?>>No</option>
                        <option value="Yes" <?php if($edit_record_data['is_pwd']=='Yes')echo'selected';?>>Yes</option>
                    </select>
                </div>
                <div id="pwd_container_edit" style="display:<?php echo ($edit_record_data['is_pwd']=='Yes')?'grid':'none'; ?>; grid-column: span 2; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="input_group_container">
                        <label class="input_label_text">PWD ID NUMBER (14-16 digits)</label>
                        <input type="text" name="pwd_id" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['pwd_id']); ?>" pattern="\d{14,16}" title="PWD ID must be between 14 and 16 digits.">
                    </div>
                    <div class="input_group_container">
                        <label class="input_label_text">DISABILITY TYPE / DETAILS</label>
                        <input type="text" name="disability_type" class="text_input_field" value="<?php echo htmlspecialchars($edit_record_data['disability_type']); ?>">
                    </div>
                </div>
            </div>
            <button type="submit" name="update_resident" class="action_button_style button_color_orange" style="width:100%; margin-top:20px;">UPDATE RECORD</button>
            <p style="text-align:center; margin-top:15px;"><a href="index.php?mode=search" style="color:#f12711; text-decoration:none; font-weight:bold;">Cancel Update</a></p>
        </form>
    <?php endif; ?>

    <?php if ($application_current_mode == 'login'): ?>
        <div style="max-width: 400px; margin: 40px auto;">
            <form method="POST" class="form_background_container" style="text-align:center;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <h3 style="color:#667eea; margin-top:0;">System Authentication</h3>
                <?php if(isset($auth_error_message)): ?>
                    <p style="color:#f12711; font-weight:bold; font-size:14px;"><?php echo $auth_error_message; ?></p>
                <?php endif; ?>
                <div class="input_group_container" style="margin-bottom:15px;">
                    <label class="input_label_text">Username</label>
                    <input type="text" name="username" class="text_input_field" required autofocus>
                </div>
                <div class="input_group_container" style="margin-bottom:20px;">
                    <label class="input_label_text">Password</label>
                    <input type="password" name="password" class="text_input_field" required>
                </div>
                <button type="submit" name="login_action" class="action_button_style button_color_green" style="width:100%;">LOGIN</button>
                
            </form>
        </div>
    <?php endif; ?>

    <?php if ($application_current_mode == 'users'): 
        $user_list_res = $database_connection_object->query("SELECT * FROM users ORDER BY username ASC");
    ?>
        <div class="form_grid_layout" style="grid-template-columns: 350px 1fr; align-items: start;">
            <form method="POST" class="form_background_container">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <h3 style="margin-top:0; color:#4facfe;">Create New Account</h3>
                <div class="input_group_container" style="margin-bottom:12px;"><label class="input_label_text">Username</label><input type="text" name="user_name" class="text_input_field" required></div>
                <div class="input_group_container" style="margin-bottom:12px;"><label class="input_label_text">Full Name</label><input type="text" name="user_full_name" class="text_input_field" required></div>
                <div class="input_group_container" style="margin-bottom:12px;"><label class="input_label_text">Role / Position</label><select name="user_role" class="dropdown_select_field"><option>Encoder</option><option>Administrator</option></select></div>
                <div class="input_group_container" style="margin-bottom:18px;"><label class="input_label_text">Password</label><input type="password" name="user_password" class="text_input_field" required></div>
                <button type="submit" name="save_user" class="action_button_style button_color_blue" style="width:100%;">ADD USER</button>
            </form>

            <div class="form_background_container">
                <h3 style="margin-top:0; color:#764ba2;">Manage Users</h3>
                <table class="data_table_layout">
                    <thead><tr><th class="table_header_cell">USERNAME</th><th class="table_header_cell">FULL NAME</th><th class="table_header_cell">POSITION</th><th class="table_header_cell">ACTION</th></tr></thead>
                    <tbody>
                        <?php while($user_row = $user_list_res->fetch_assoc()): ?>
                        <tr>
                            <td class="table_data_cell"><strong><?php echo htmlspecialchars($user_row['username']); ?></strong></td>
                            <td class="table_data_cell"><?php echo htmlspecialchars($user_row['full_name']); ?></td>
                            <td class="table_data_cell"><em><?php echo htmlspecialchars($user_row['role']); ?></em></td>
                            <td class="table_data_cell">
                                <?php if($user_row['id'] != $_SESSION['user_id']): ?>
                                    <a href="javascript:void(0)" onclick="secureDelete('user', <?php echo $user_row['id']; ?>)" style="color:#f12711; font-weight:bold; text-decoration:none;">DELETE</a>

                                <?php else: ?>
                                    <span style="color:#888;">(Current)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" class="form_background_container" style="margin-top:20px; grid-column: 1 / -1;" onsubmit="return validatePasswordChange()">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <h3 style="margin-top:0; color:#eb3349;">Change My Password</h3>
                <div class="form_grid_layout" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="input_group_container"><label class="input_label_text">Current Password</label><input type="password" name="old_password" class="text_input_field" required></div>
                    <div class="input_group_container"><label class="input_label_text">New Password</label><input type="password" id="new_p" name="new_password" class="text_input_field" required minlength="6"></div>
                    <div class="input_group_container"><label class="input_label_text">Confirm New Password</label><input type="password" id="conf_p" name="confirm_password" class="text_input_field" required></div>
                </div>
                <button type="submit" name="change_password" class="action_button_style button_color_red" style="width:100%; margin-top:15px;">UPDATE MY PASSWORD</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($application_current_mode == 'history'): 
        $log_res = $database_connection_object->query("SELECT l.*, u.username FROM audit_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.timestamp DESC LIMIT 500");
    ?>
        <div class="form_background_container">
            <h3 style="margin-top:0; color:#764ba2;">System Activity Logs</h3>
            <div style="max-height: 500px; overflow-y: auto; border-radius: 8px;">
                <table class="data_table_layout">
                    <thead><tr><th class="table_header_cell">TIME</th><th class="table_header_cell">USER</th><th class="table_header_cell">ACTION</th><th class="table_header_cell">DETAILS</th></tr></thead>
                    <tbody>
                        <?php while($log_row = $log_res->fetch_assoc()): 
                            $tag_color = "#667eea";
                            if(strpos($log_row['action'], 'DELETE') !== false) $tag_color = "#f12711";
                            if(strpos($log_row['action'], 'UPDATE') !== false) $tag_color = "#f5af19";
                            if(strpos($log_row['action'], 'LOGIN') !== false) $tag_color = "#11998e";
                        ?>
                        <tr>
                            <td class="table_data_cell" style="white-space:nowrap; font-size:11px;"><?php echo date('M d, H:i:s', strtotime($log_row['timestamp'])); ?></td>
                            <td class="table_data_cell"><strong><?php echo htmlspecialchars($log_row['username'] ?? 'Guest'); ?></strong></td>
                            <td class="table_data_cell"><span style="background:<?php echo $tag_color; ?>; color:white; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;"><?php echo $log_row['action']; ?></span></td>
                            <td class="table_data_cell" style="font-size:11px;"><?php echo htmlspecialchars($log_row['details']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div style="text-align:center; margin-top:20px; font-size:12px; color:#555; font-weight:bold;">RBI System Version 2.2</div>
</div>


<div id="profileModal" class="modal_overlay" onclick="if(event.target == this) closeModal()">
    <div class="modal_content">
        <span class="close_modal" onclick="closeModal()">&times;</span>
        <h2 id="modal_name" style="margin-top:0; color:#667eea; border-bottom:2px solid #667eea; padding-bottom:10px;">Resident Profile</h2>
        <div class="profile_detail_grid" id="profile_details">

        </div>
        <div style="margin-top:30px; text-align:right;">
            <button onclick="closeModal()" class="action_button_style button_color_blue">Close Profile</button>
        </div>
    </div>
</div>

<script>
function apply_date_mask(input_id_string) {
    let input_element_node = document.getElementById(input_id_string);
    if(!input_element_node) return;
    input_element_node.addEventListener('input', function (event_object) {
        let sanitized_input_value = event_object.target.value.replace(/\D/g, '').substring(0, 8);
        let extracted_month_value = sanitized_input_value.substring(0, 2);
        let extracted_day_value = sanitized_input_value.substring(2, 4);
        let extracted_year_value = sanitized_input_value.substring(4, 8);
        if (sanitized_input_value.length > 4) {
            event_object.target.value = extracted_month_value + '/' + extracted_day_value + '/' + extracted_year_value;
        } else if (sanitized_input_value.length > 2) {
            event_object.target.value = extracted_month_value + '/' + extracted_day_value;
        } else {
            event_object.target.value = extracted_month_value;
        }
    });
}

function sync_date_picker(picker_id, text_id) {
    const picker = document.getElementById(picker_id);
    const text = document.getElementById(text_id);
    if (!picker || !text) return;
    picker.addEventListener('change', function() {
        if (this.value) {
            const date_parts = this.value.split('-'); // YYYY-MM-DD
            text.value = date_parts[1] + '/' + date_parts[2] + '/' + date_parts[0];
        }
    });
}

apply_date_mask('date_of_birth_add_input');
apply_date_mask('date_of_birth_edit_input');
sync_date_picker('dob_picker_add', 'date_of_birth_add_input');
sync_date_picker('dob_picker_edit', 'date_of_birth_edit_input');


function send_heartbeat() {
    fetch('index.php?mode=heartbeat').catch(err => console.log('offline'));
}
if (document.visibilityState === 'visible') {
    send_heartbeat();
    setInterval(send_heartbeat, 4000);
}

function toggleField(selectId, containerId) {
    const selector = document.getElementById(selectId);
    const container = document.getElementById(containerId);
    if (containerId.includes('pwd')) {
        container.style.display = (selector.value === 'Yes') ? 'grid' : 'none';
    } else {
        container.style.display = (selector.value === 'Yes') ? 'block' : 'none';
    }
}

function showProfile(data) {
    const modal = document.getElementById('profileModal');
    const details = document.getElementById('profile_details');
    document.getElementById('modal_name').innerText = data.first_name + " " + data.last_name;
    
    let html = "";
    const mapping = {
        "Full Name": `${data.first_name} ${data.middle_name} ${data.last_name} ${data.qualifier}`,
        "Household": `House #${data.house_no}, ${data.st_name}, Purok ${data.purok_no}`,
        "Birth Date": data.formatted_birthday,
        "Birthplace": data.place_of_birth,
        "Sex": data.sex == 'M' ? 'Male' : 'Female',
        "Civil Status": data.civil_status,
        "Religion": data.religion,
        "Citizenship": data.citizenship,
        "Occupation": data.occupation,
        "Relationship to Household Head": data.relationship_to_head,
        "Education": data.educational_background,
        "4Ps Status": data.is_4ps,
        "IP Member": data.is_ips,
        "Senior Status": data.is_senior + (data.senior_id !== 'N/A' ? ` (ID: ${data.senior_id})` : ''),
        "PWD Status": data.is_pwd + (data.is_pwd === 'Yes' ? `<br>ID: ${data.pwd_id}<br>Details: ${data.disability_type}` : '')
    };

    for (const [label, value] of Object.entries(mapping)) {
        html += `<div class="detail_item">
                    <div class="detail_label">${label}</div>
                    <div class="detail_value">${value || 'N/A'}</div>
                 </div>`;
    }
    
    details.innerHTML = html;
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('profileModal').style.display = 'none';
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast_container');
    const toast = document.createElement('div');
    toast.className = `toast_message toast_${type}`;
    const icon = type === 'success' ? '✅' : '❌';
    toast.innerHTML = `<span class="toast_icon">${icon}</span> <span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}


window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.has('msg')) {
        const msg = params.get('msg');
        const type = params.get('type') || 'success';
        showToast(msg.replace(/_/g, ' '), type);
    }
});

function validatePasswordChange() {
    const newP = document.getElementById('new_p').value;
    const confP = document.getElementById('conf_p').value;
    if (newP !== confP) {
        showToast("Passwords do not match!", "error");
        return false;
    }
    if (newP.length < 6) {
        showToast("Password too short!", "error");
        return false;
    }
    return true;
}

function secureDelete(type, id) {
    if (!confirm(`Are you sure you want to delete this ${type}?`)) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?php echo $_SESSION['csrf_token']; ?>';
    form.appendChild(csrfInput);
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = type === 'user' ? 'delete_user_action' : 'delete_resident_action';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = type === 'user' ? 'delete_user_id' : 'delete_id';
    idInput.value = id;
    form.appendChild(idInput);
    
    document.body.appendChild(form);
    form.submit();
}

</script>
</body>
</html>
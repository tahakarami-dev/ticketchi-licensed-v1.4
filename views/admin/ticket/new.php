<?php
/**
 * «ارسال تیکت»: staff opens tickets for one or more customers.
 */
defined('ABSPATH') || exit('NO Access');

$statuses           = tkm_get_status();
$department_manager = new TKM_Admin_Department_Manager();
$parent_departments = $department_manager->get_parent_department();
$ticket_manager     = new TKM_Ticket_Manager();

include TKM_VIEWS_PATH . 'admin/partials/header.php';
include TKM_VIEWS_PATH . 'admin/ticket/compose.php';
include TKM_VIEWS_PATH . 'admin/partials/footer.php';

<?php
/**
 * Single Membership Tab: Students Subtab
 * @since    [version]
 * @version  [version]
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! is_admin() ) { exit; }


$table = new LLMS_Table_Membership_Students();
$table->get_results();
echo $table->get_table_html();

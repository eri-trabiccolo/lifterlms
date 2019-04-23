<?php
/**
 * Query data about a membership
 *
 * @since   [version]
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query data about a membership
 *
 * @since [version]
 */
class LLMS_Membership_Data {

	/**
	 * @var LLMS_Membership
	 * @since [version]
	 */
	public $membership;

	/**
	 * @var int
	 * @since [version]
	 */
	public $membership_id;

	/**
	 * @var array
	 * @since [version]
	 */
	protected $dates = array();

	/**
	 * Constructor
	 * @param    int     $membership_id  WP Post ID of the membership
	 * @since    [version]
	 * @version  [version]
	 */
	public function __construct( $membership_id ) {

		$this->membership_id = $membership_id;
		$this->membership = llms_get_post( $this->membership_id );

	}

	/**
	 * Allow dates and timestamps to be passed into various data functions
	 * @param    mixed     $date  date string or timestamp
	 * @return   int
	 * @since    [version]
	 * @version  [version]
	 */
	protected function strtotime( $date ) {
		if ( ! is_numeric( $date ) ) {
			$date = date( 'U', strtotime( $date ) );
		}
		return $date;
	}


	/**
	 * Retrieve a start or end date based on the period
	 * @param    string     $period  period [current|previous]
	 * @param    string     $date    date type [start|end]
	 * @return   string
	 * @since    [version]
	 * @version  [version]
	 */
	protected function get_date( $period, $date ) {

		return date( 'Y-m-d H:i:s', $this->dates[ $period ][ $date ] );

	}

	/**
	 * Set the dates passed on a date range period
	 * @param    string     $period  date range period
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	public function set_period( $period = 'today' ) {

		$now = current_time( 'timestamp' );

		switch ( $period ) {

			case 'all_time':
				$curr_start = 0;
				$curr_end = $now;

				$prev_start = 0;
				$prev_end = $now;
			break;

			case 'last_year':
				$curr_start = strtotime( 'first day of january last year', $now );
				$curr_end = strtotime( 'last day of december last year', $now );

				$prev_start = strtotime( 'first day of january last year', $curr_start );
				$prev_end = strtotime( 'last day of december last year', $curr_start );
			break;

			case 'year':
				$curr_start = strtotime( 'first day of january this year', $now );
				$curr_end = strtotime( 'last day of december this year', $now );

				$prev_start = strtotime( 'first day of january last year', $now );
				$prev_end = strtotime( 'last day of december last year', $now );
			break;

			case 'last_month':
				$curr_start = strtotime( 'first day of previous month', $now );
				$curr_end = strtotime( 'last day of previous month', $now );

				$prev_start = strtotime( 'first day of previous month', $curr_start );
				$prev_end = strtotime( 'last day of previous month', $curr_start );
			break;

			case 'month':
				$curr_start = strtotime( 'first day of this month', $now );
				$curr_end = strtotime( 'last day of this month', $now );

				$prev_start = strtotime( 'first day of previous month', $now );
				$prev_end = strtotime( 'last day of previous month', $now );
			break;

			case 'last_week':
				$curr_start = strtotime( 'monday this week', $now - WEEK_IN_SECONDS );
				$curr_end = $now;

				$prev_start = strtotime( 'monday previous week', $curr_start - WEEK_IN_SECONDS );
				$prev_end = $curr_start - DAY_IN_SECONDS;
			break;

			case 'week':
				$curr_start = strtotime( 'monday this week', $now );
				$curr_end = $now;

				$prev_start = strtotime( 'monday previous week', $now );
				$prev_end = $curr_start - DAY_IN_SECONDS;
			break;

			case 'yesterday':
				$curr_start = $now - DAY_IN_SECONDS;
				$curr_end = $curr_start;

				$prev_start = $curr_start - DAY_IN_SECONDS;
				$prev_end = $prev_start;
			break;

			case 'today':
			default:

				$curr_start = $now;
				$curr_end = $now;

				$prev_start = $now - DAY_IN_SECONDS;
				$prev_end = $prev_start;

		}// End switch().

		$this->dates = array(
			'current' => array(
				'start' => strtotime( 'midnight', $curr_start ),
				'end' => strtotime( 'tomorrow', $curr_end ) - 1,
			),
			'previous' => array(
				'start' => strtotime( 'midnight', $prev_start ),
				'end' => strtotime( 'tomorrow', $prev_end ) - 1,
			),
		);

	}

	/**
	 * retrieve # of membership enrollments within the period
	 * @param    string     $period  date period [current|previous]
	 * @return   int
	 * @since    [version]
	 * @version  [version]
	 */
	public function get_enrollments( $period = 'current' ) {

		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "
			SELECT DISTINCT COUNT( user_id )
			FROM {$wpdb->prefix}lifterlms_user_postmeta
			WHERE meta_value = 'yes'
			  AND meta_key = '_start_date'
			  AND post_id = %d
			  AND updated_date BETWEEN %s AND %s
			",
			$this->membership_id,
			$this->get_date( $period, 'start' ),
			$this->get_date( $period, 'end' )
		) );

	}

	/**
	 * retrieve # of engagements related to the membership awarded within the period
	 * @param    string     $type    engagement type [email|certificate|achievement]
	 * @param    string     $period  date period [current|previous]
	 * @return   int
	 * @since    [version]
	 * @version  [version]
	 */
	public function get_engagements( $type, $period = 'current' ) {

		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "
			SELECT DISTINCT COUNT( user_id )
			FROM {$wpdb->prefix}lifterlms_user_postmeta
			WHERE meta_key = %s
			  AND post_id = %d
			  AND updated_date BETWEEN %s AND %s
			",
			'_' . $type,
			$this->membership_id,
			$this->get_date( $period, 'start' ),
			$this->get_date( $period, 'end' )
		) );

	}

	/**
	 * retrieve # of orders placed for the membership within the period
	 * @param    string     $period  date period [current|previous]
	 * @return   int
	 * @since    [version]
	 * @version  [version]
	 */
	public function get_orders( $period = 'current' ) {

		$query = $this->orders_query( array(
			array(
				'after'     => $this->get_date( $period, 'start' ),
				'before'    => $this->get_date( $period, 'end' ),
				'inclusive' => true,
			),
		), 1 );
		return $query->found_posts;

	}

	/**
	 * retrieve total amount of transactions related to orders for the membership completed within the period
	 * @param    string     $period  date period [current|previous]
	 * @return   float
	 * @since    [version]
	 * @version  [version]
	 */
	public function get_revenue( $period ) {

		$query = $this->orders_query( -1 );
		$order_ids = wp_list_pluck( $query->posts, 'ID' );

		$revenue = 0;

		if ( $order_ids ) {

			$order_ids = implode( ',', $order_ids );

			global $wpdb;
			$revenue = $wpdb->get_var( $wpdb->prepare(
				"SELECT SUM( m2.meta_value )
				 FROM $wpdb->posts AS p
				 LEFT JOIN $wpdb->postmeta AS m1 ON m1.post_id = p.ID AND m1.meta_key = '_llms_order_id' -- join for the ID
				 LEFT JOIN $wpdb->postmeta AS m2 ON m2.post_id = p.ID AND m2.meta_key = '_llms_amount'-- get the actual amounts
				 WHERE p.post_type = 'llms_transaction'
				   AND p.post_status = 'llms-txn-succeeded'
				   AND m1.meta_value IN ({$order_ids})
				   AND p.post_modified BETWEEN %s AND %s
				;",
				$this->get_date( $period, 'start' ),
				$this->get_date( $period, 'end' )
			) );

			if ( is_null( $revenue ) ) {
				$revenue = 0;
			}
		}

		return apply_filters( 'llms_membership_data_get_revenue', $revenue, $period, $this );

	}

	/**
	 * Retrieve the number of unenrollments on a given date
	 * @param    mixed     $start  date string or timestamp
	 * @param    mixed     $end    date string or timestamp
	 * @return   int
	 * @since    [version]
	 * @version  [version]
	 */
	public function get_unenrollments( $period = 'current' ) {

		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "
			SELECT DISTINCT COUNT( user_id )
			FROM {$wpdb->prefix}lifterlms_user_postmeta
			WHERE meta_value != 'enrolled'
			  AND meta_key = '_status'
			  AND post_id = %d
			  AND updated_date BETWEEN %s AND %s
			",
			$this->membership_id,
			$this->get_date( $period, 'start' ),
			$this->get_date( $period, 'end' )
		) );

	}

	/**
	 * Execute a WP Query to retrieve orders within the given date range
	 * @param    int        $num_orders  number of orders to retrieve
	 * @param    array      $dates       date range (passed to WP_Query['date_query'])
	 * @return   obj
	 * @since    [version]
	 * @version  [version]
	 */
	private function orders_query( $num_orders = 1, $dates = array() ) {

		$args = array(
			'post_type' => 'llms_order',
			'post_status' => array( 'llms-active', 'llms-complete' ),
			'posts_per_page' => $num_orders,
			'meta_key' => '_llms_product_id',
			'meta_value' => $this->membership_id,
		);

		if ( $dates ) {
			$args['date_query'] = $dates;
		}

		$query = new WP_Query( $args );

		return $query;

	}

	/**
	 * Retrieve recent LLMS_User_Postmeta for the membership
	 * @return   array
	 * @since    [version]
	 * @version  [version]
	 */
	public function recent_events() {

		$query = new LLMS_Query_User_Postmeta( array(
			'per_page' => 10,
			'post_id' => $this->membership_id,
			'types' => 'all',
		) );

		return $query->get_metas();

	}

}

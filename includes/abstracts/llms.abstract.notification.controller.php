<?php
/**
 * Notification Controller Abstract
 *
 * @package LifterLMS/Abstracts/Classes
 *
 * @since 3.8.0
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notification Controller abstract class
 *
 * @since 3.8.0
 * @since 3.30.3 Explicitly define undefined properties & fixed typo in output string.
 */
abstract class LLMS_Abstract_Notification_Controller extends LLMS_Abstract_Options_Data implements LLMS_Interface_Notification_Controller {

	/**
	 * Trigger Identifier
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Number of accepted arguments passed to the callback function
	 *
	 * @var integer
	 */
	protected $action_accepted_args = 1;

	/**
	 * Action hooks used to trigger sending of the notification
	 *
	 * @var string[]
	 */
	protected $action_hooks = array();

	/**
	 * Priority used when adding action hook
	 *
	 * @var integer
	 */
	protected $action_priority = 15;

	/**
	 * If true, will automatically dupcheck before sending
	 *
	 * @var boolean
	 */
	protected $auto_dupcheck = false;

	/**
	 * Related course object
	 *
	 * @since 3.8.0
	 * @var LLMS_Course
	 */
	public $course;

	/**
	 * WP Post ID associated with the triggering action
	 *
	 * @var int|null
	 */
	protected $post_id = null;

	/**
	 * WP Post ID of the post which triggered the achievement to be awarded
	 *
	 * @var int
	 * @since 3.8.0
	 */
	public $related_post_id;

	/**
	 * Array of subscriptions for the notification
	 *
	 * @var array
	 */
	protected $subscriptions = array();

	/**
	 * Array of supported notification types
	 *
	 * @var array
	 */
	protected $supported_types = array();

	/**
	 * Determines if test notifications can be sent
	 *
	 * @var array
	 */
	protected $testable = array(
		'basic' => false,
		'email' => false,
	);

	/**
	 * WP User ID associated with the triggering action
	 *
	 * @var int|null
	 */
	protected $user_id = null;

	/**
	 * Holds singletons for extending classes
	 *
	 * @var LLMS_Abstract_Notification_Controller[]
	 */
	private static $_instances = array();

	/**
	 * Takes a subscriber type (student, author, etc) and retrieves a User ID
	 *
	 * @since 3.8.0
	 *
	 * @param string $subscriber Subscriber type string
	 * @return int|false
	 */
	abstract protected function get_subscriber( $subscriber );

	/**
	 * Get the translatable title for the notification
	 *
	 * Used on settings screens.
	 *
	 * @since 3.8.0
	 *
	 * @return string
	 */
	abstract public function get_title();

	/**
	 * Setup the subscriber options for the notification
	 *
	 * @since 3.8.0
	 *
	 * @param string $type Notification type id
	 * @return array
	 */
	abstract protected function set_subscriber_options( $type );

	/**
	 * Get the singleton instance for the extending class
	 *
	 * @since 3.8.0
	 *
	 * @return LLMS_Abstract_Notification_Controller
	 */
	public static function instance() {

		$class = get_called_class();

		if ( ! isset( self::$_instances[ $class ] ) ) {
			self::$_instances[ $class ] = new $class();
		}

		return self::$_instances[ $class ];

	}

	/**
	 * Constructor
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	private function __construct() {

		$this->add_actions();

	}

	/**
	 * Add an action to trigger the notification to send
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	protected function add_actions() {

		foreach ( $this->action_hooks as $hook ) {
			add_action( $hook, array( $this, 'action_callback' ), $this->action_accepted_args, $this->action_priority );
		}

	}

	/**
	 * Add custom subscriptions
	 *
	 * @since unknown
	 *
	 * @param string $type Notification type.
	 * @return void
	 */
	private function add_custom_subscriptions( $type ) {
		$option      = $this->get_option( $type . '_custom_subscribers' );
		$subscribers = explode( ',', $option );
		foreach ( $subscribers as $subscriber ) {
			$subscriber = trim( $subscriber );
			if ( $subscriber ) {
				$this->subscribe( $subscriber, $type );
			}
		}
	}

	/**
	 * Adds subscribers before sending a notifications
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	private function add_subscriptions() {

		foreach ( array_keys( $this->get_supported_types() ) as $type ) {

			foreach ( $this->get_subscribers_settings( $type ) as $subscriber_key => $enabled ) {

				if ( 'no' === $enabled ) {
					continue;
				} elseif ( 'custom' === $subscriber_key ) {
					$this->add_custom_subscriptions( $type );
				}

				$subscriber = $this->get_subscriber( $subscriber_key );

				if ( $subscriber ) {

					$this->subscribe( $subscriber, $type );

				}
			}
		}

	}

	/**
	 * Get a fake instance of a view, used for managing options & customization on the admin panel
	 *
	 * @since 3.8.0
	 *
	 * @param string $type       Optional. Notification type. Default is 'basic'.
	 * @param int    $subscriber Optional. WP_User ID of the subscriber's. Default is `null`.
	 *                           When not provided the current user id will be used.
	 * @param int    $user_id    Optional. WP_User ID of the user who triggered the notification to be generated. Default is `null`.
	 *                           When not provided the current user id will be used.
	 * @param int    $post_id    Optional. WP_Post ID of the post which triggered the notification to be generated. Deafult is `null`.
	 * @return LLMS_Abstract_Notification_View|false
	 */
	public function get_mock_view( $type = 'basic', $subscriber = null, $user_id = null, $post_id = null ) {

		$notification = new LLMS_Notification();
		$notification->set( 'type', $type );
		$notification->set( 'subscriber', $subscriber ? $subscriber : get_current_user_id() );
		$notification->set( 'user_id', $user_id ? $user_id : get_current_user_id() );
		$notification->set( 'post_id', $post_id );
		$notification->set( 'trigger_id', $this->id );

		return LLMS()->notifications()->get_view( $notification );

	}

	/**
	 * Retrieve a prefix for options related to the notification
	 *
	 * This overrides the LLMS_Abstract_Options_Data method.
	 *
	 * @since 3.8.0
	 *
	 * @return string
	 */
	protected function get_option_prefix() {
		return sprintf( '%1$snotification_%2$s_', $this->option_prefix, $this->id );
	}

	/**
	 * Retrieve get an array of subscriber options for the current notification by type
	 *
	 * @since 3.8.0
	 *
	 * @param string $type Notification type 'basic|email'.
	 * @return array
	 */
	public function get_subscriber_options( $type ) {
		/**
		 * Filters the notifications supported types.
		 *
		 * The dynamic part of the filter `$this->id` identifies the extending controller.
		 *
		 * @since 3.8.0
		 *
		 * @param array                        $subscriber_options      Array of subscriber options.
		 * @param string                       $type                    The notification type.
		 * @param LLMS_Notification_Controller $notification_controller The notification controller instance.
		 */
		return apply_filters( "llms_notification_{$this->id}_subscriber_options", $this->set_subscriber_options( $type ), $type, $this );
	}

	/**
	 * Get an array of saved subscriber settings prefilled with defaults for the current notification
	 *
	 * @since 3.8.0
	 *
	 * @param string $type Notification type 'basic|email'.
	 * @return array
	 */
	public function get_subscribers_settings( $type ) {
		$defaults = wp_list_pluck( $this->get_subscriber_options( $type ), 'enabled', 'id' );
		return $this->get_option( $type . '_subscribers', $defaults );
	}

	/**
	 * Get an array of prebuilt subscriber option settings for common subscriptions
	 *
	 * @since 3.8.0
	 * @since 3.30.3 Fixed typo in default description string.
	 *
	 * @param string $id      Id of the subscriber type.
	 * @param string $enabled Optional. Whether or not the subscription should be enabled by default [yes|no]. Defaul is `'yes'`.
	 * @return array
	 */
	public function get_subscriber_option_array( $id, $enabled = 'yes' ) {

		$defaults = array(
			'author'        => array(
				'title' => __( 'Author', 'lifterlms' ),
			),
			'student'       => array(
				'title' => __( 'Student', 'lifterlms' ),
			),
			'lesson_author' => array(
				'title' => __( 'Lesson Author', 'lifterlms' ),
			),
			'course_author' => array(
				'title' => __( 'Course Author', 'lifterlms' ),
			),
			'custom'        => array(
				'description' => __( 'Enter additional email addresses which will receive this notification. Separate multiple addresses with commas.', 'lifterlms' ),
				'title'       => __( 'Additional Recipients', 'lifterlms' ),
			),
		);

		if ( isset( $defaults[ $id ] ) ) {
			$arr            = $defaults[ $id ];
			$arr['id']      = $id;
			$arr['enabled'] = $enabled;
			return $arr;
		}

	}

	/**
	 * Get a subscriptions array for a specific subscriber
	 *
	 * @since 3.8.0
	 *
	 * @param mixed $subscriber WP User ID, email address, etc...
	 * @return array
	 */
	public function get_subscriber_subscriptions( $subscriber ) {
		$subscriptions = $this->get_subscriptions();
		return isset( $subscriptions[ $subscriber ] ) ? $subscriptions[ $subscriber ] : array();
	}

	/**
	 * Retrieve subscribers
	 *
	 * @since 3.8.0
	 *
	 * @return array
	 */
	public function get_subscriptions() {
		return $this->subscriptions;
	}

	/**
	 * Get an array of supported notification types
	 *
	 * @since 3.8.0
	 *
	 * @return array
	 */
	public function get_supported_types() {
		/**
		 * Filters the notifications supported types.
		 *
		 * The dynamic part of the filter `$this->id` identifies the extending controller.
		 *
		 * @since 3.8.0
		 *
		 * @param array                        $supported_types         Array of supported notification types.
		 * @param LLMS_Notification_Controller $notification_controller The notification controller instance.
		 */
		return apply_filters( "llms_notification_{$this->id}_supported_types", $this->set_supported_types(), $this );
	}

	/**
	 * Get an array of LifterLMS Admin Page settings to send test notifications
	 *
	 * @since 3.24.0
	 *
	 * @param string $type Notification type [basic|email].
	 * @return array
	 */
	public function get_test_settings( $type ) {
		return array();
	}

	/**
	 * Determine if the notification is a potential duplicate
	 *
	 * @since 3.11.0
	 * @since [version] Instantiate the notification query passing `no_found_rows` arg as `true`.
	 *               Also use query method `has_results()` in place of a check on the query's property `found_results`.
	 *
	 * @param string $type       Notification type id.
	 * @param mixed  $subscriber WP User ID for the subscriber, email address, phone number, etc...
	 * @return boolean
	 */
	public function has_subscriber_received( $type, $subscriber ) {

		$query = new LLMS_Notifications_Query(
			array(
				'post_id'       => $this->post_id,
				'subscriber'    => $subscriber,
				'types'         => $type,
				'trigger_id'    => $this->id,
				'user_id'       => $this->user_id,
				'no_found_rows' => true,
			)
		);

		return $query->has_results();

	}

	/**
	 * Determine if the notification type support tests
	 *
	 * @since 3.24.0
	 *
	 * @param string $type Notification type [email|basic].
	 * @return bool
	 */
	public function is_testable( $type ) {

		if ( empty( $this->testable[ $type ] ) ) {
			return false;
		}

		return true;

	}

	/**
	 * Send all the subscriptions
	 *
	 * @since 3.8.0
	 * @since 3.11.0 Unknown.
	 *
	 * @param bool $force Optional. If true, will force a send even if duplicate's only applies to controllers that flag $this->auto_dupcheck to true.
	 *                    Default is `false`.
	 * @return void
	 */
	public function send( $force = false ) {

		$this->add_subscriptions();

		foreach ( $this->get_subscriptions() as $subscriber => $types ) {

			foreach ( $types as $type ) {

				$this->send_one( $type, $subscriber, $force );

			}
		}

		/**
		 * Cleanup subscriptions so if the notification is triggered again we don't have incorrect subscribers
		 * on the next trigger this happens when receipts are triggered in bulk by action scheduler.
		 */
		$this->unset_subscriptions();

	}

	/**
	 * Send a notification for a subscriber
	 *
	 * @since 3.8.0
	 * @since 3.24.0 Unknown.
	 *
	 * @param string $type       Notification type.
	 * @param mixed  $subscriber WP_User ID for the subscriber, email address, phone number, etc...
	 * @param bool   $force      Optional. If true, will force a send even if duplicate's only applies to controllers that flag $this->auto_dupcheck to true.
	 *                           Default is `false`.
	 * @return int|false
	 */
	protected function send_one( $type, $subscriber, $force = false ) {

		/**
		 * If autodupcheck is set and the send function doesn't override the dupcheck
		 * and the subscriber has already received the notification skip it.
		 */
		if ( $this->auto_dupcheck && ! $force && $this->has_subscriber_received( $type, $subscriber ) ) {
			// phpcs:ignore -- commented out code
			// llms_log( sprintf( 'Skipped %1$s to subscriber "%2$s" bc of dupcheck', $type, $subscriber ), 'notifications' );
			return false;
		}

		$notification = new LLMS_Notification();
		$id           = $notification->create(
			array(
				'post_id'    => $this->post_id,
				'subscriber' => $subscriber,
				'type'       => $type,
				'trigger_id' => $this->id,
				'user_id'    => $this->user_id,
			)
		);

		// If successful, push to the processor where processing is supported.
		if ( $id ) {

			$processor = LLMS()->notifications()->get_processor( $type );
			if ( $processor ) {

				$processor->log( sprintf( 'Queuing %1$s notification ID #%2$d', $type, $id ) );
				$processor->push_to_queue( $id );
				LLMS()->notifications()->schedule_processing( $type );

			}
		}

		return $id;

	}

	/**
	 * Send a test notification to the currently logged in users
	 *
	 * Extending classes should redefine this in order to properly setup the controller with post_id and user_id data.
	 *
	 * @since 3.24.0
	 *
	 * @param string $type Notification type [basic|email].
	 * @param array  $data Array of test notification data as specified by `$this->get_test_data()`.
	 * @return int|false
	 */
	public function send_test( $type, $data = array() ) {
		return $this->send_one( $type, get_current_user_id(), true );
	}

	/**
	 * Determine what types are supported
	 *
	 * Extending classes can override this function in order to add or remove support
	 * 3rd parties should add support via filter on `$this->get_supported_types()`.
	 *
	 * @since 3.8.0
	 *
	 * @return array Associative array, keys are the ID/db type, values should be translated display types.
	 */
	protected function set_supported_types() {
		return array(
			'basic' => __( 'Basic', 'lifterlms' ),
			'email' => __( 'Email', 'lifterlms' ),
		);
	}

	/**
	 * Subscribe a user to a notification type
	 *
	 * @since 3.8.0
	 *
	 * @param mixed  $subscriber WP User ID, email address, etc...
	 * @param string $type       Identifier for a subscription type eg: basic.
	 * @return void
	 */
	public function subscribe( $subscriber, $type ) {

		// Prevent unsupported types from being subscribed.
		if ( ! $this->supports( $type ) ) {
			return;
		}

		$subscriptions = $this->get_subscriber_subscriptions( $subscriber );

		if ( ! in_array( $type, $subscriptions ) ) {
			array_push( $subscriptions, $type );
		}

		$this->subscriptions[ $subscriber ] = $subscriptions;

	}

	/**
	 * Determine if a given notification type is supported
	 *
	 * @since 3.8.0
	 *
	 * @param string $type Notification type id.
	 * @return boolean
	 */
	public function supports( $type ) {
		return in_array( $type, array_keys( $this->get_supported_types() ) );
	}

	/**
	 * Reset the subscriptions array
	 *
	 * @since 3.8.0
	 *
	 * @return void
	 */
	public function unset_subscriptions() {
		$this->subscriptions = array();
	}

}

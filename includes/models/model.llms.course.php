<?php
/**
 * LifterLMS Course Model
 *
 * @package LifterLMS/Models/Classes
 *
 * @since 1.0.0
 * @version [version]
 */

defined( 'ABSPATH' ) || exit;

/**
 * LLMS_Course model class
 *
 * @since 1.0.0
 * @since 3.30.3 Explicitly define class properties.
 * @since 4.0.0 Remove previously deprecated class methods.
 *
 * @property string $audio_embed                URL to an oEmbed enable audio URL
 * @property float  $average_grade              Calculated value of the overall average grade of all *enrolled* students in the course.
 * @property float  $average_progress           Calculated value of the overall average progress of all *enrolled* students in the course.
 * @property int    $capacity                   Number of students who can be enrolled in the course before enrollment closes
 * @property string $capacity_message           Message displayed when capacity has been reached
 * @property string $content_restricted_message Message displayed when non-enrolled visitors try to access lessons/quizzes directly
 * @property string $course_closed_message      Message displayed to visitors when the course is accessed after the Course End Date has passed. Only applicable when $time_period is 'yes'
 * @property string $course_opens_message       Message displayed to visitors when the course is accessed before the Course Start Date has passed. Only applicable when $time_period is 'yes'
 * @property string $enable_capacity            Whether capacity restrictions are enabled [yes|no]
 * @property string $enrollment_closed_message  Message displayed to non-enrolled visitors when the course is accessed after the Enrollment End Date has passed. Only applicable when $enrollment_period is 'yes'
 * @property string $enrollment_end_date        After this date, registration closes
 * @property string $enrollment_opens_message   Message displayed to non-enrolled visitors when the course is accessed before the Enrollment Start Date has passed. Only applicable when $enrollment_period is 'yes'
 * @property string $enrollment_period          Whether or not a course time period restriction is enabled [yes|no] (all checks should check for 'yes' as an empty string might be returned)
 * @property string $enrollment_start_date      Before this date, registration is closed
 * @property string $end_date                   Date when a course closes. Students may no longer view content or complete lessons / quizzes after this date.
 * @property string $has_prerequisite           Determine if prerequisites are enabled [yes|no]
 * @property array  $instructors                Course instructor user information
 * @property int    $prerequisite               WP Post ID of a the prerequisite course
 * @property int    $prerequisite_track         WP Tax ID of a the prerequisite track
 * @property int    $sales_page_content_page_id WP Post ID of the WP page to redirect to when $sales_page_content_type is 'page'
 * @property string $sales_page_content_type    Sales page behavior [none,content,page,url]
 * @property string $sales_page_content_url     Redirect URL for a sales page, when $sales_page_content_type is 'url'
 * @property string $start_date                 Date when a course is opens. Students may register before this date but can only view content and complete lessons or quizzes after this date.
 * @property string $length                     User defined course length
 * @property string $tile_featured_video        Displays the featured video instead of the featured image on course tiles [yes|no]
 * @property string $time_period                Whether or not a course time period restriction is enabled [yes|no] (all checks should check for 'yes' as an empty string might be returned)
 * @property string $video_embed                URL to an oEmbed enable video URL
 */
class LLMS_Course
extends LLMS_Post_Model
implements LLMS_Interface_Post_Audio
		 , LLMS_Interface_Post_Instructors
		 , LLMS_Interface_Post_Sales_Page
		 , LLMS_Interface_Post_Video {

	/**
	 * Meta properties
	 *
	 * @var array
	 */
	protected $properties = array(

		// Public.
		'audio_embed'                => 'text',
		'average_grade'              => 'float',
		'average_progress'           => 'float',
		'capacity'                   => 'absint',
		'capacity_message'           => 'text',
		'course_closed_message'      => 'text',
		'course_opens_message'       => 'text',
		'content_restricted_message' => 'text',
		'enable_capacity'            => 'yesno',
		'end_date'                   => 'text',
		'enrolled_students'          => 'absint',
		'enrollment_closed_message'  => 'text',
		'enrollment_end_date'        => 'text',
		'enrollment_opens_message'   => 'text',
		'enrollment_period'          => 'yesno',
		'enrollment_start_date'      => 'text',
		'has_prerequisite'           => 'yesno',
		'instructors'                => 'array',
		'length'                     => 'text',
		'prerequisite'               => 'absint',
		'prerequisite_track'         => 'absint',
		'sales_page_content_page_id' => 'absint',
		'sales_page_content_type'    => 'string',
		'sales_page_content_url'     => 'string',
		'tile_featured_video'        => 'yesno',
		'time_period'                => 'yesno',
		'start_date'                 => 'text',
		'video_embed'                => 'text',

		// Private.
		'temp_calc_data'             => 'array',

	);

	/**
	 * Default property values
	 *
	 * @var array
	 */
	protected $property_defaults = array(
		'enrolled_students' => 0,
	);

	/**
	 * DB post type name.
	 *
	 * @var string
	 */
	protected $db_post_type = 'course';

	/**
	 * Model post type name.
	 *
	 * @var string
	 */
	protected $model_post_type = 'course';

	/**
	 * @since 1.0.0
	 * @deprecated [version] Unused property `LLMS_Course::$sections` is replaced by `LLMS_Course::get_sections()`.
	 *
	 * @var array
	 */
	public $sections;

	/**
	 * @since 1.0.0
	 * @deprecated [version] Unused property `LLMS_Course::$sku` is deprecated with no replacement.
	 *
	 * @var string
	 */
	public $sku;

	/**
	 * Retrieve an instance of the Post Instructors model
	 *
	 * @since 3.13.0
	 *
	 * @return LLMS_Post_Instructors
	 */
	public function instructors() {
		return new LLMS_Post_Instructors( $this );
	}

	/**
	 * Retrieve the total points available for the course
	 *
	 * @since 3.24.0
	 *
	 * @return int
	 */
	public function get_available_points() {
		$points = 0;
		foreach ( $this->get_lessons() as $lesson ) {
			$points += $lesson->get( 'points' );
		}

		/**
		 * Filters the total available points for the course.
		 *
		 * Hook description.
		 *
		 * @since 3.24.0
		 *
		 * @param int         $points Number of available points.
		 * @param LLMS_Course $course Course object.
		 */
		return apply_filters( 'llms_course_get_available_points', $points, $this );
	}

	/**
	 * Get course's prerequisite id based on the type of prerequisite
	 *
	 * @since 3.0.0
	 * @since 3.7.3 Unknown.
	 *
	 * @param string $type Optional. Type of prereq to retrieve id for [course|track]. Default is `'course'`.
	 * @return int|false Post ID of a course, taxonomy ID of a track, or false if none found.
.	 */
	public function get_prerequisite_id( $type = 'course' ) {

		if ( $this->has_prerequisite( $type ) ) {

			switch ( $type ) {

				case 'course':
					$key = 'prerequisite';
					break;

				case 'course_track':
					$key = 'prerequisite_track';
					break;

			}

			if ( isset( $key ) ) {
				return $this->get( $key );
			}
		}

		return false;

	}

	/**
	 * Attempt to get oEmbed for an audio provider
	 *
	 * Falls back to the [audio] shortcode if the oEmbed fails.
	 *
	 * @since 1.0.0
	 * @since 3.17.0 Unknown
	 *
	 * @return string
	 */
	public function get_audio() {
		return $this->get_embed( 'audio' );
	}

	/**
	 * Retrieve course categories
	 *
	 * @since 3.3.0
	 *
	 * @param array $args Array of args passed to wp_get_post_terms.
	 * @return array
	 */
	public function get_categories( $args = array() ) {
		return wp_get_post_terms( $this->get( 'id' ), 'course_cat', $args );
	}

	/**
	 * Get Difficulty
	 *
	 * @since 1.0.0
	 * @since 3.24.0 Unknown.
	 *
	 * @param string $field Optional. Which field to return from the available term fields.
	 *                      Any public variables from a WP_Term object are acceptable: term_id, name, slug, and more.
	 *                      Default is `'name'`.
	 * @return string
	 */
	public function get_difficulty( $field = 'name' ) {

		$terms = get_the_terms( $this->get( 'id' ), 'course_difficulty' );

		if ( false === $terms ) {

			return '';

		} else {

			foreach ( $terms as $term ) {

				return $term->$field;

			}
		}

	}

	/**
	 * Retrieve course instructor information
	 *
	 * @since 3.13.0
	 *
	 * @param boolean $exclude_hidden Optional. If true, excludes hidden instructors from the return array. Default is `false`.
	 * @return array
	 */
	public function get_instructors( $exclude_hidden = false ) {

		/**
		 * Filters the course's instructors list
		 *
		 * @since 3.13.0
		 *
		 * @param array       $instructors    Instructor data array.
		 * @param LLMS_Course $course         Course object.
		 * @param boolearn    $exclude_hidden If true, excludes hidden instructors from the return array.
		 */
		return apply_filters(
			'llms_course_get_instructors',
			$this->instructors()->get_instructors( $exclude_hidden ),
			$this,
			$exclude_hidden
		);

	}

	/**
	 * Get course lessons
	 *
	 * @since 3.0.0
	 * @since 3.24.0 Unknown.
	 *
	 * @param string $return Optional. Type of return [ids|posts|lessons]. Default is `'lessons'`.
	 * @return int[]|WP_Post[]|LLMS_Lesson[] The type depends on value of `$return`.
	 */
	public function get_lessons( $return = 'lessons' ) {

		$lessons = array();
		foreach ( $this->get_sections( 'sections' ) as $section ) {
			$lessons = array_merge( $lessons, $section->get_lessons( 'posts' ) );
		}

		if ( 'ids' === $return ) {
			$ret = wp_list_pluck( $lessons, 'ID' );
		} elseif ( 'posts' === $return ) {
			$ret = $lessons;
		} else {
			$ret = array_map( 'llms_get_post', $lessons );
		}
		return $ret;

	}

	/**
	 * Retrieve an array of quizzes within a course
	 *
	 * @since 3.12.0
	 * @since 3.16.0 Unknown.
	 *
	 * @return int[] Array of WP_Post IDs of the quizzes.
	 */
	public function get_quizzes() {

		$quizzes = array();
		foreach ( $this->get_lessons( 'lessons' ) as $lesson ) {
			if ( $lesson->has_quiz() ) {
				$quizzes[] = $lesson->get( 'quiz' );
			}
		}
		return $quizzes;

	}

	/**
	 * Get the URL to a WP Page or Custom URL when sales page redirection is enabled
	 *
	 * @since 3.20.0
	 * @since 3.23.0 Unknown.
	 *
	 * @return string
	 */
	public function get_sales_page_url() {

		$type = $this->get( 'sales_page_content_type' );
		switch ( $type ) {

			case 'page':
				$url = get_permalink( $this->get( 'sales_page_content_page_id' ) );
				break;

			case 'url':
				$url = $this->get( 'sales_page_content_url' );
				break;

			default:
				$url = get_permalink( $this->get( 'id' ) );

		}

		/**
		 * Filters the course's sales page URL
		 *
		 * @since Unknown
		 *
		 * @param string      $url    Sales page URL.
		 * @param LLMS_Course $course The course object.
		 * @param string      $type   The sales page content type.
		 */
		return apply_filters( 'llms_course_get_sales_page_url', $url, $this, $type );
	}

	/**
	 * Get course sections
	 *
	 * @since 3.0.0
	 * @since 3.24.0 Unknown.
	 *
	 * @param string $return Optional. Type of return [ids|posts|sections]. Default is `'sections'`.
	 * @return int[]|WP_Post[]|LLMS_Section[] The type depends on value of `$return`.
	 */
	public function get_sections( $return = 'sections' ) {

		$q = new WP_Query(
			array(
				'meta_key'       => '_llms_order',
				'meta_query'     => array(
					array(
						'key'   => '_llms_parent_course',
						'value' => $this->id,
					),
				),
				'order'          => 'ASC',
				'orderby'        => 'meta_value_num',
				'post_type'      => 'section',
				'posts_per_page' => 500,
			)
		);

		if ( 'ids' === $return ) {
			$r = wp_list_pluck( $q->posts, 'ID' );
		} elseif ( 'posts' === $return ) {
			$r = $q->posts;
		} else {
			$r = array();
			foreach ( $q->posts as $p ) {
				$r[] = new LLMS_Section( $p );
			}
		}

		return $r;

	}

	/**
	 * Retrieve the number of enrolled students in the course
	 *
	 * The cached value is calculated in the `LLMS_Processor_Course_Data` background processor.
	 *
	 * If, for whatever reason, it's not found, it will be calculated on demand and saved for later use.
	 *
	 * @since 3.15.0
	 * @since version] Use cached value where possible.
	 *
	 * @param boolean $skip_cache Default: `false`. Whether or not to bypass the cache. If `true`, bypasses the cache.
	 * @return int
	 */
	public function get_student_count( $skip_cache = false ) {

		$count = ! $skip_cache ? $this->get( 'enrolled_students' ) : false;

		/**
		 * Query enrolled students when `$skip_cache=true` or when there's no stored meta data.
		 *
		 * The second condition is necessary to disambiguate between a cached `0` and a `0` that's
		 * returned as the default value when the metadata doesn't exist.
		 */
		if ( false === $count || ! isset( $this->enrolled_students ) ) {

			$query = new LLMS_Student_Query(
				array(
					'post_id'  => $this->get( 'id' ),
					'statuses' => array( 'enrolled' ),
					'per_page' => 1,
					'sort'     => array(
						'id' => 'ASC',
					),
				)
			);

			$count = $query->found_results;

			// Cache result for later use.
			$this->set( 'enrolled_students', $count );

		}

		/**
		 * Filter the number of actively enrolled students in the course
		 *
		 * @since [version]
		 *
		 * @param int         $count  Number of students enrolled in the course.
		 * @param LLMS_Course $course Instance of the course object.
		 */
		$count = apply_filters( 'llms_course_get_student_count', $count, $this );

		return absint( $count );

	}

	/**
	 * Get an array of student IDs based on enrollment status in the course
	 *
	 * @since 3.0.0
	 *
	 * @param string|string[] $statuses Optional. List of enrollment statuses to query by. Students matching at least one of the provided statuses will be returned. Default is `'enrolled'`.
	 * @param integer         $limit    Optional. Number of results. Default is `50`.
	 * @param integer         $skip     Optional. Number of results to skip (for pagination). Default is `0`.
	 * @return array
	 */
	public function get_students( $statuses = 'enrolled', $limit = 50, $skip = 0 ) {
		return llms_get_enrolled_students( $this->get( 'id' ), $statuses, $limit, $skip );
	}

	/**
	 * Retrieve course tags
	 *
	 * @since 3.3.0
	 *
	 * @param array $args Array of args passed to wp_get_post_terms.
	 * @return array
	 */
	public function get_tags( $args = array() ) {
		return wp_get_post_terms( $this->get( 'id' ), 'course_tag', $args );
	}

	/**
	 * Retrieve course tracks
	 *
	 * @since 3.3.0
	 *
	 * @param array $args Array of args passed to wp_get_post_terms.
	 * @return array
	 */
	public function get_tracks( $args = array() ) {
		return wp_get_post_terms( $this->get( 'id' ), 'course_track', $args );
	}

	/**
	 * Retrieve an array of students currently enrolled in the course
	 *
	 * @since 1.0.0
	 * @since 3.0.0 Use `LLMS_Course::get_students()`.
	 *
	 * @param integer $limit Number of results.
	 * @param integer $skip Number of results to skip (for pagination).
	 * @return array
	 */
	public function get_enrolled_students( $limit, $skip ) {
		return $this->get_students( 'enrolled', $limit, $skip );
	}

	/**
	 * Get a user's percentage completion through the course
	 *
	 * @since 1.0.0
	 * @since 3.17.2 Unknown.
	 *
	 * @return float
	 */
	public function get_percent_complete( $user_id = '' ) {

		$student = llms_get_student( $user_id );
		if ( ! $student ) {
			return 0;
		}
		return $student->get_progress( $this->get( 'id' ), 'course' );

	}

	/**
	 * Retrieve an instance of the LLMS_Product for this course
	 *
	 * @since 3.3.0
	 *
	 * @return LLMS_Product
	 */
	public function get_product() {
		return new LLMS_Product( $this->get( 'id' ) );
	}

	/**
	 * Attempt to get oEmbed for a video provider
	 *
	 * Falls back to the [video] shortcode if the oEmbed fails.
	 *
	 * @since 1.0.0
	 * @since 3.17.0 Unknown.
	 *
	 * @return string
	 */
	public function get_video() {
		return $this->get_embed( 'video' );
	}

	/**
	 * Compare a course meta info date to the current date and get a bool
	 *
	 * @since 3.0.0
	 *
	 * @param string $date_key Property key, eg "start_date" or "enrollment_end_date".
	 * @return boolean Returns `true` when the date is in the past and `false` when the date is in the future.
	 */
	public function has_date_passed( $date_key ) {

		$now  = current_time( 'timestamp' );
		$date = $this->get_date( $date_key, 'U' );

		/**
		 * If there's no date, we can't make a comparison
		 * so assume it's unset and unnecessary
		 * so return 'false'.
		 */
		if ( ! $date ) {
			return false;

		}

		return $now > $date;

	}

	/**
	 * Determine if the course is at capacity based on course capacity settings
	 *
	 * @since 3.0.0
	 * @since 3.15.0 Unknown.
	 *
	 * @return boolean Returns `true` if not at capacity & `false` if at or over capacity.
	 */
	public function has_capacity() {

		// Capacity disabled, so there is capacity.
		if ( 'yes' !== $this->get( 'enable_capacity' ) ) {
			return true;
		}

		$capacity = $this->get( 'capacity' );
		// No capacity restriction set, so it has capacity.
		if ( ! $capacity ) {
			return true;
		}

		// Compare results.
		return ( $this->get_student_count() < $capacity );

	}

	/**
	 * Determine if prerequisites are enabled and there are prereqs configured
	 *
	 * @since 3.0.0
	 * @since 3.7.5 Unknown.
	 *
	 * @param string $type Determine if a specific type of prereq exists [any|course|track].
	 * @return boolean Returns true if prereq is enabled and there is a prerequisite course or track.
	 */
	public function has_prerequisite( $type = 'any' ) {

		if ( 'yes' === $this->get( 'has_prerequisite' ) ) {

			if ( 'any' === $type ) {

				return ( $this->get( 'prerequisite' ) || $this->get( 'prerequisite_track' ) );

			} elseif ( 'course' === $type ) {

				return ( $this->get( 'prerequisite' ) ) ? true : false;

			} elseif ( 'course_track' === $type ) {

				return ( $this->get( 'prerequisite_track' ) ) ? true : false;

			}
		}

		return false;

	}

	/**
	 * Determine if sales page redirection is enabled
	 *
	 * @since 3.20.0
	 * @since 3.23.0 Unknown.
	 * @since [version] Use strict `in_array()` comparison.
	 *
	 * @return boolean
	 */
	public function has_sales_page_redirect() {

		$type = $this->get( 'sales_page_content_type' );

		/**
		 * Filters whether or not the course has a sales page redirect.
		 *
		 * @since Unknown.
		 *
		 * @param boolean     $has_redirect Whether or not the course has a sales page redirect.
		 * @param LLMS_Course $course       Course object.
		 * @param string      $type         The course's sales page content type property value.
		 */
		return apply_filters( 'llms_course_has_sales_page_redirect', in_array( $type, array( 'page', 'url' ), true ), $this, $type );

	}

	/**
	 * Determine if students can access course content based on the current date
	 *
	 * @since 3.0.0
	 * @since 3.7.0 Unknown.
	 *
	 * @return boolean
	 */
	public function is_enrollment_open() {

		// If no period is set, enrollment is automatically open.
		if ( 'yes' !== $this->get( 'enrollment_period' ) ) {

			$is_open = true;

		} else {

			$is_open = ( $this->has_date_passed( 'enrollment_start_date' ) && ! $this->has_date_passed( 'enrollment_end_date' ) );

		}

		/**
		 * Filters whether or not course enrollment is open.
		 *
		 * @since Unknown
		 *
		 * @param boolean     $is_open Whether or not enrollment is open.
		 * @param LLMS_Course $course  Course object.
		 */
		return apply_filters( 'llms_is_course_enrollment_open', $is_open, $this );

	}

	/**
	 * Determine if students can access course content based on the current date
	 *
	 * Note that enrollment does not affect the outcome of this check as regardless
	 * of enrollment, once a course closes content is locked.
	 *
	 * @since 3.0.0
	 * @since 3.7.0 Unknown.
	 *
	 * @return boolean
	 */
	public function is_open() {

		// If a course time period is not enabled, just return true (content is accessible).
		if ( 'yes' !== $this->get( 'time_period' ) ) {

			$is_open = true;

		} else {

			$is_open = ( $this->has_date_passed( 'start_date' ) && ! $this->has_date_passed( 'end_date' ) );

		}

		/**
		 * Filters whether or not the course is considered open based on the current date.
		 *
		 * @since Unknown
		 *
		 * @param boolean     $is_open Whether or not enrollment is open.
		 * @param LLMS_Course $course  Course object.
		 */
		return apply_filters( 'llms_is_course_open', $is_open, $this );

	}

	/**
	 * Determine if a prerequisite is completed for a student
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Type of prereq [course|track].
	 * @return boolean
	 */
	public function is_prerequisite_complete( $type = 'course', $student_id = null ) {

		if ( ! $student_id ) {
			$student_id = get_current_user_id();
		}

		// No user or no prereqs so no reason to proceed.
		if ( ! $student_id || ! $this->has_prerequisite( $type ) ) {
			return false;
		}

		$prereq_id = $this->get_prerequisite_id( $type );

		// No prereq id of this type, no need to proceed.
		if ( ! $prereq_id ) {
			return false;
		}

		// Setup student.
		$student = new LLMS_Student( $student_id );

		return $student->is_complete( $prereq_id, $type );

	}

	/**
	 * Save instructor information
	 *
	 * @since 3.13.0
	 *
	 * @param array $instructors Array of course instructor information.
	 */
	public function set_instructors( $instructors = array() ) {

		return $this->instructors()->set_instructors( $instructors );

	}

	/**
	 * Add data to the course model when converted to array
	 *
	 * Called before data is sorted and returned by $this->jsonSerialize().
	 *
	 * @since 3.3.0
	 * @since 3.8.0 Unknown.
	 *
	 * @param array $arr Data to be serialized.
	 * @return array
	 */
	public function toArrayAfter( $arr ) {

		$product             = $this->get_product();
		$arr['access_plans'] = array();
		foreach ( $product->get_access_plans( false, false ) as $p ) {
			$arr['access_plans'][] = $p->toArray();
		}

		$arr['sections'] = array();
		foreach ( $this->get_sections() as $s ) {
			$arr['sections'][] = $s->toArray();
		}

		$arr['categories'] = $this->get_categories(
			array(
				'fields' => 'names',
			)
		);
		$arr['tags']       = $this->get_tags(
			array(
				'fields' => 'names',
			)
		);
		$arr['tracks']     = $this->get_tracks(
			array(
				'fields' => 'names',
			)
		);

		$arr['difficulty'] = $this->get_difficulty();

		return $arr;

	}

}

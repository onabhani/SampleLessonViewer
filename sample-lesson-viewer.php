<?php
/**
 * Plugin Name: Sample Lesson Viewer for LearnDash
 * Plugin URI: https://github.com/onabhani/SampleLessonViewer
 * Description: Display all sample lessons from all LearnDash courses using a simple shortcode [learndash_sample_lessons]
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://github.com/onabhani
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sample-lesson-viewer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if LearnDash is active
 */
function slv_check_learndash_active() {
    if ( ! class_exists( 'SFWD_LMS' ) ) {
        add_action( 'admin_notices', 'slv_learndash_missing_notice' );
        return false;
    }
    return true;
}

/**
 * Admin notice when LearnDash is not active
 */
function slv_learndash_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'Sample Lesson Viewer requires LearnDash LMS to be installed and activated.', 'sample-lesson-viewer' ); ?></p>
    </div>
    <?php
}

/**
 * Register the shortcode
 */
function slv_register_shortcode() {
    add_shortcode( 'learndash_sample_lessons', 'slv_display_sample_lessons' );
}
add_action( 'init', 'slv_register_shortcode' );

/**
 * Enqueue plugin styles
 */
function slv_enqueue_styles() {
    wp_register_style(
        'sample-lesson-viewer',
        plugin_dir_url( __FILE__ ) . 'assets/css/sample-lesson-viewer.css',
        array(),
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'slv_enqueue_styles' );

/**
 * Get all sample lessons grouped by course
 *
 * @return array Array of courses with their sample lessons
 */
function slv_get_sample_lessons() {
    // Check if LearnDash is active
    if ( ! class_exists( 'SFWD_LMS' ) ) {
        return array();
    }

    $sample_lessons = array();

    // Query all lessons
    $lessons_args = array(
        'post_type'      => 'sfwd-lessons',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    );

    $lessons_query = new WP_Query( $lessons_args );

    if ( $lessons_query->have_posts() ) {
        while ( $lessons_query->have_posts() ) {
            $lessons_query->the_post();
            $lesson_id = get_the_ID();

            // Check if this is a sample lesson
            $is_sample = slv_is_sample_lesson( $lesson_id );

            if ( $is_sample ) {
                // Get the course ID for this lesson
                $course_id = slv_get_lesson_course_id( $lesson_id );

                if ( $course_id ) {
                    if ( ! isset( $sample_lessons[ $course_id ] ) ) {
                        $sample_lessons[ $course_id ] = array(
                            'course_title' => get_the_title( $course_id ),
                            'course_url'   => get_permalink( $course_id ),
                            'lessons'      => array(),
                        );
                    }

                    $sample_lessons[ $course_id ]['lessons'][] = array(
                        'id'        => $lesson_id,
                        'title'     => get_the_title( $lesson_id ),
                        'url'       => get_permalink( $lesson_id ),
                        'excerpt'   => get_the_excerpt( $lesson_id ),
                        'thumbnail' => get_the_post_thumbnail_url( $lesson_id, 'medium' ),
                    );
                }
            }
        }
        wp_reset_postdata();
    }

    // Sort courses by title
    uasort( $sample_lessons, function( $a, $b ) {
        return strcasecmp( $a['course_title'], $b['course_title'] );
    });

    return $sample_lessons;
}

/**
 * Check if a lesson is a sample lesson
 *
 * @param int $lesson_id The lesson ID
 * @return bool True if sample lesson, false otherwise
 */
function slv_is_sample_lesson( $lesson_id ) {
    // Method 1: Check using LearnDash function if available
    if ( function_exists( 'learndash_is_sample' ) ) {
        return learndash_is_sample( $lesson_id );
    }

    // Method 2: Check the lesson meta directly
    $lesson_settings = get_post_meta( $lesson_id, '_sfwd-lessons', true );

    if ( is_array( $lesson_settings ) ) {
        // Check for sample lesson setting
        if ( isset( $lesson_settings['sfwd-lessons_sample_lesson'] ) ) {
            return $lesson_settings['sfwd-lessons_sample_lesson'] === 'on';
        }
    }

    // Method 3: Check individual meta key (newer LearnDash versions)
    $sample_meta = get_post_meta( $lesson_id, 'sample_lesson', true );
    if ( $sample_meta === 'on' || $sample_meta === '1' || $sample_meta === true ) {
        return true;
    }

    return false;
}

/**
 * Get the course ID for a lesson
 *
 * @param int $lesson_id The lesson ID
 * @return int|false The course ID or false if not found
 */
function slv_get_lesson_course_id( $lesson_id ) {
    // Method 1: Use LearnDash function if available
    if ( function_exists( 'learndash_get_course_id' ) ) {
        return learndash_get_course_id( $lesson_id );
    }

    // Method 2: Check lesson meta
    $lesson_settings = get_post_meta( $lesson_id, '_sfwd-lessons', true );

    if ( is_array( $lesson_settings ) && isset( $lesson_settings['sfwd-lessons_course'] ) ) {
        return intval( $lesson_settings['sfwd-lessons_course'] );
    }

    // Method 3: Check course meta key directly
    $course_id = get_post_meta( $lesson_id, 'course_id', true );
    if ( $course_id ) {
        return intval( $course_id );
    }

    return false;
}

/**
 * Display sample lessons shortcode callback
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function slv_display_sample_lessons( $atts ) {
    // Check if LearnDash is active
    if ( ! slv_check_learndash_active() ) {
        return '<p class="slv-error">' . esc_html__( 'LearnDash LMS is required to display sample lessons.', 'sample-lesson-viewer' ) . '</p>';
    }

    // Parse shortcode attributes
    $atts = shortcode_atts( array(
        'columns'        => 3,
        'show_excerpt'   => 'yes',
        'show_thumbnail' => 'yes',
        'show_course'    => 'yes',
        'course_id'      => '',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ), $atts, 'learndash_sample_lessons' );

    // Enqueue styles
    wp_enqueue_style( 'sample-lesson-viewer' );

    // Get sample lessons
    $sample_lessons = slv_get_sample_lessons();

    // Filter by course if specified
    if ( ! empty( $atts['course_id'] ) ) {
        $course_ids = array_map( 'intval', explode( ',', $atts['course_id'] ) );
        $sample_lessons = array_intersect_key( $sample_lessons, array_flip( $course_ids ) );
    }

    if ( empty( $sample_lessons ) ) {
        return '<p class="slv-no-lessons">' . esc_html__( 'No sample lessons found.', 'sample-lesson-viewer' ) . '</p>';
    }

    // Build output
    ob_start();
    ?>
    <div class="slv-sample-lessons-wrapper">
        <?php foreach ( $sample_lessons as $course_id => $course_data ) : ?>
            <?php if ( $atts['show_course'] === 'yes' ) : ?>
                <div class="slv-course-section">
                    <h2 class="slv-course-title">
                        <a href="<?php echo esc_url( $course_data['course_url'] ); ?>">
                            <?php echo esc_html( $course_data['course_title'] ); ?>
                        </a>
                    </h2>
            <?php endif; ?>

            <div class="slv-lessons-grid slv-columns-<?php echo intval( $atts['columns'] ); ?>">
                <?php foreach ( $course_data['lessons'] as $lesson ) : ?>
                    <div class="slv-lesson-card">
                        <?php if ( $atts['show_thumbnail'] === 'yes' && ! empty( $lesson['thumbnail'] ) ) : ?>
                            <div class="slv-lesson-thumbnail">
                                <a href="<?php echo esc_url( $lesson['url'] ); ?>">
                                    <img src="<?php echo esc_url( $lesson['thumbnail'] ); ?>" alt="<?php echo esc_attr( $lesson['title'] ); ?>">
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="slv-lesson-content">
                            <h3 class="slv-lesson-title">
                                <a href="<?php echo esc_url( $lesson['url'] ); ?>">
                                    <?php echo esc_html( $lesson['title'] ); ?>
                                </a>
                            </h3>

                            <?php if ( $atts['show_course'] !== 'yes' ) : ?>
                                <span class="slv-lesson-course">
                                    <?php echo esc_html( $course_data['course_title'] ); ?>
                                </span>
                            <?php endif; ?>

                            <?php if ( $atts['show_excerpt'] === 'yes' && ! empty( $lesson['excerpt'] ) ) : ?>
                                <div class="slv-lesson-excerpt">
                                    <?php echo wp_kses_post( $lesson['excerpt'] ); ?>
                                </div>
                            <?php endif; ?>

                            <a href="<?php echo esc_url( $lesson['url'] ); ?>" class="slv-lesson-link">
                                <?php esc_html_e( 'View Lesson', 'sample-lesson-viewer' ); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ( $atts['show_course'] === 'yes' ) : ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Add inline styles as fallback if CSS file doesn't load
 */
function slv_add_inline_styles() {
    $inline_css = '
        .slv-sample-lessons-wrapper { margin: 20px 0; }
        .slv-course-section { margin-bottom: 40px; }
        .slv-course-title { font-size: 1.5em; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #0073aa; }
        .slv-course-title a { color: inherit; text-decoration: none; }
        .slv-course-title a:hover { color: #0073aa; }
        .slv-lessons-grid { display: grid; gap: 20px; }
        .slv-columns-1 { grid-template-columns: 1fr; }
        .slv-columns-2 { grid-template-columns: repeat(2, 1fr); }
        .slv-columns-3 { grid-template-columns: repeat(3, 1fr); }
        .slv-columns-4 { grid-template-columns: repeat(4, 1fr); }
        .slv-lesson-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; transition: box-shadow 0.3s ease; }
        .slv-lesson-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .slv-lesson-thumbnail img { width: 100%; height: 180px; object-fit: cover; display: block; }
        .slv-lesson-content { padding: 20px; }
        .slv-lesson-title { font-size: 1.1em; margin: 0 0 10px 0; }
        .slv-lesson-title a { color: #333; text-decoration: none; }
        .slv-lesson-title a:hover { color: #0073aa; }
        .slv-lesson-course { display: block; font-size: 0.85em; color: #666; margin-bottom: 10px; }
        .slv-lesson-excerpt { font-size: 0.9em; color: #555; margin-bottom: 15px; line-height: 1.5; }
        .slv-lesson-link { display: inline-block; padding: 8px 16px; background: #0073aa; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.9em; transition: background 0.3s ease; }
        .slv-lesson-link:hover { background: #005177; color: #fff; }
        .slv-no-lessons, .slv-error { padding: 20px; background: #f7f7f7; border-left: 4px solid #0073aa; margin: 20px 0; }
        .slv-error { border-left-color: #dc3232; }
        @media (max-width: 768px) {
            .slv-columns-2, .slv-columns-3, .slv-columns-4 { grid-template-columns: 1fr; }
        }
        @media (min-width: 769px) and (max-width: 1024px) {
            .slv-columns-3, .slv-columns-4 { grid-template-columns: repeat(2, 1fr); }
        }
    ';
    wp_add_inline_style( 'sample-lesson-viewer', $inline_css );
}
add_action( 'wp_enqueue_scripts', 'slv_add_inline_styles', 20 );

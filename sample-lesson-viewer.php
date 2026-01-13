<?php
/**
 * Plugin Name: Sample Lesson Viewer for LearnDash
 * Plugin URI: https://github.com/onabhani/SampleLessonViewer
 * Description: Display all sample lessons from all LearnDash courses using a simple shortcode [learndash_sample_lessons]
 * Version: 1.4.0
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
 * Enqueue plugin styles and scripts
 */
function slv_enqueue_styles() {
    wp_register_style(
        'sample-lesson-viewer',
        plugin_dir_url( __FILE__ ) . 'assets/css/sample-lesson-viewer.css',
        array(),
        '1.4.0'
    );

    wp_register_script(
        'sample-lesson-viewer',
        plugin_dir_url( __FILE__ ) . 'assets/js/sample-lesson-viewer.js',
        array(),
        '1.4.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'slv_enqueue_styles' );

/**
 * Extract video URL from lesson - Enhanced version
 *
 * @param int $lesson_id The lesson ID
 * @return array|false Video data array or false if no video found
 */
function slv_get_lesson_video( $lesson_id ) {
    $video_url = '';

    // Method 1: Check LearnDash video progression settings (main array)
    $lesson_settings = get_post_meta( $lesson_id, '_sfwd-lessons', true );
    if ( is_array( $lesson_settings ) ) {
        $possible_keys = array(
            'sfwd-lessons_lesson_video_url',
            'sfwd-lessons_video_url',
            'lesson_video_url',
            'video_url',
        );
        foreach ( $possible_keys as $key ) {
            if ( ! empty( $lesson_settings[ $key ] ) ) {
                $video_url = $lesson_settings[ $key ];
                break;
            }
        }
    }

    // Method 2: Check individual meta keys (newer LearnDash versions)
    if ( empty( $video_url ) ) {
        $meta_keys = array(
            'lesson_video_url',
            '_lesson_video_url',
            'video_url',
            '_video_url',
            'sfwd-lessons_lesson_video_url',
            '_ld_video_url',
        );
        foreach ( $meta_keys as $key ) {
            $value = get_post_meta( $lesson_id, $key, true );
            if ( ! empty( $value ) ) {
                $video_url = $value;
                break;
            }
        }
    }

    // Method 3: Use LearnDash function if available
    if ( empty( $video_url ) && function_exists( 'learndash_get_setting' ) ) {
        $video_url = learndash_get_setting( $lesson_id, 'lesson_video_url' );
    }

    // Method 4: Extract video from lesson content
    if ( empty( $video_url ) ) {
        $content = get_post_field( 'post_content', $lesson_id );
        $extracted = slv_extract_video_from_content( $content );
        if ( $extracted && ! empty( $extracted['url'] ) ) {
            $video_url = $extracted['url'];
        }
    }

    // If we have a URL, process and generate embed
    if ( ! empty( $video_url ) ) {
        return slv_process_video_url( $video_url );
    }

    return false;
}

/**
 * Extract video URL from content - Enhanced version
 *
 * @param string $content The post content
 * @return array|false Video data or false
 */
function slv_extract_video_from_content( $content ) {
    if ( empty( $content ) ) {
        return false;
    }

    // Check for iframe embeds first (most common)
    if ( preg_match( '/<iframe[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
        return array( 'url' => $matches[1], 'provider' => 'iframe' );
    }

    // Check for video tags
    if ( preg_match( '/<video[^>]*>.*?<source[^>]+src=["\']([^"\']+)["\'][^>]*>.*?<\/video>/is', $content, $matches ) ) {
        return array( 'url' => $matches[1], 'provider' => 'self-hosted' );
    }

    // YouTube patterns
    $youtube_patterns = array(
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        '/(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
    );
    foreach ( $youtube_patterns as $pattern ) {
        if ( preg_match( $pattern, $content, $matches ) ) {
            return array( 'url' => 'https://www.youtube.com/watch?v=' . $matches[1], 'video_id' => $matches[1], 'provider' => 'youtube' );
        }
    }

    // Vimeo patterns
    if ( preg_match( '/(?:https?:\/\/)?(?:www\.)?(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/', $content, $matches ) ) {
        return array( 'url' => 'https://vimeo.com/' . $matches[1], 'video_id' => $matches[1], 'provider' => 'vimeo' );
    }

    // Bunny.net / BunnyCDN
    if ( preg_match( '/(https?:\/\/[^"\'<>\s]+\.b-cdn\.net\/[^"\'<>\s]+)/i', $content, $matches ) ) {
        return array( 'url' => $matches[1], 'provider' => 'bunny' );
    }
    if ( preg_match( '/(https?:\/\/iframe\.mediadelivery\.net\/[^"\'<>\s]+)/i', $content, $matches ) ) {
        return array( 'url' => $matches[1], 'provider' => 'bunny' );
    }

    // Cloudflare Stream
    if ( preg_match( '/(https?:\/\/[^"\'<>\s]*cloudflarestream\.com[^"\'<>\s]*)/i', $content, $matches ) ) {
        return array( 'url' => $matches[1], 'provider' => 'cloudflare' );
    }

    // Wistia
    if ( preg_match( '/(?:https?:\/\/)?(?:.*\.)?wistia\.(?:com|net)\/(?:medias|embed)\/([a-zA-Z0-9]+)/', $content, $matches ) ) {
        return array( 'url' => 'https://fast.wistia.net/embed/iframe/' . $matches[1], 'video_id' => $matches[1], 'provider' => 'wistia' );
    }

    // Direct video file URLs (mp4, webm, etc.)
    if ( preg_match( '/(https?:\/\/[^\s<>"\']+\.(?:mp4|webm|ogg|mov|m4v))/i', $content, $matches ) ) {
        return array( 'url' => $matches[1], 'provider' => 'self-hosted' );
    }

    // Generic video URL in shortcode
    if ( preg_match( '/\[video[^\]]*src=["\']([^"\']+)["\'][^\]]*\]/', $content, $matches ) ) {
        return array( 'url' => $matches[1], 'provider' => 'self-hosted' );
    }

    return false;
}

/**
 * Process video URL and generate embed code - Enhanced version with lazy loading
 *
 * @param string $url The video URL
 * @return array Video data with embed code
 */
function slv_process_video_url( $url ) {
    $video_data = array(
        'url'       => $url,
        'embed'     => '',
        'provider'  => '',
        'video_id'  => '',
        'thumbnail' => '',
    );

    // YouTube - with thumbnail for lazy loading
    if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches ) ) {
        $video_data['provider'] = 'youtube';
        $video_data['video_id'] = $matches[1];
        $video_data['thumbnail'] = 'https://img.youtube.com/vi/' . $matches[1] . '/hqdefault.jpg';
        $video_data['embed'] = '<iframe data-src="https://www.youtube.com/embed/' . esc_attr( $matches[1] ) . '?rel=0&autoplay=1" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        return $video_data;
    }

    // Vimeo
    if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches ) ) {
        $video_data['provider'] = 'vimeo';
        $video_data['video_id'] = $matches[1];
        $video_data['embed'] = '<iframe src="https://player.vimeo.com/video/' . esc_attr( $matches[1] ) . '?badge=0&autopause=0" frameborder="0" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        return $video_data;
    }

    // Wistia
    if ( preg_match( '/wistia\.(?:com|net)\/(?:medias|embed\/iframe)\/([a-zA-Z0-9]+)/', $url, $matches ) ) {
        $video_data['provider'] = 'wistia';
        $video_data['video_id'] = $matches[1];
        $video_data['embed'] = '<iframe src="https://fast.wistia.net/embed/iframe/' . esc_attr( $matches[1] ) . '?videoFoam=true" frameborder="0" loading="lazy" allow="autoplay; fullscreen" allowfullscreen></iframe>';
        return $video_data;
    }

    // Bunny.net Stream (iframe.mediadelivery.net)
    if ( strpos( $url, 'iframe.mediadelivery.net' ) !== false || strpos( $url, 'b-cdn.net' ) !== false ) {
        $video_data['provider'] = 'bunny';
        if ( strpos( $url, 'iframe.mediadelivery.net' ) !== false ) {
            $video_data['embed'] = '<iframe src="' . esc_url( $url ) . '" loading="lazy" frameborder="0" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>';
        } else {
            $video_data['embed'] = '<video controls preload="none" loading="lazy"><source src="' . esc_url( $url ) . '" type="video/mp4">Your browser does not support the video tag.</video>';
        }
        return $video_data;
    }

    // Cloudflare Stream
    if ( strpos( $url, 'cloudflarestream.com' ) !== false || strpos( $url, 'videodelivery.net' ) !== false ) {
        $video_data['provider'] = 'cloudflare';
        $video_data['embed'] = '<iframe src="' . esc_url( $url ) . '" frameborder="0" loading="lazy" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        return $video_data;
    }

    // Self-hosted video (mp4, webm, ogg, mov)
    if ( preg_match( '/\.(mp4|webm|ogg|mov|m4v)(\?|$)/i', $url ) ) {
        $video_data['provider'] = 'self-hosted';
        $video_data['embed'] = '<video controls preload="none"><source src="' . esc_url( $url ) . '" type="video/mp4">Your browser does not support the video tag.</video>';
        return $video_data;
    }

    // Generic iframe URL (for unknown providers)
    if ( strpos( $url, 'iframe' ) !== false || strpos( $url, 'embed' ) !== false || strpos( $url, 'player' ) !== false ) {
        $video_data['provider'] = 'iframe';
        $video_data['embed'] = '<iframe src="' . esc_url( $url ) . '" frameborder="0" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        return $video_data;
    }

    // Try WordPress oEmbed as last fallback
    $oembed = wp_oembed_get( $url );
    if ( $oembed ) {
        $video_data['provider'] = 'oembed';
        $video_data['embed'] = $oembed;
        return $video_data;
    }

    return $video_data;
}

/**
 * Get all sample lessons grouped by course
 *
 * @param bool $include_video Whether to include video data
 * @return array Array of courses with their sample lessons
 */
function slv_get_sample_lessons( $include_video = false ) {
    if ( ! class_exists( 'SFWD_LMS' ) ) {
        return array();
    }

    $sample_lessons = array();

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

            if ( slv_is_sample_lesson( $lesson_id ) ) {
                $course_id = slv_get_lesson_course_id( $lesson_id );

                if ( $course_id ) {
                    if ( ! isset( $sample_lessons[ $course_id ] ) ) {
                        $sample_lessons[ $course_id ] = array(
                            'course_title' => get_the_title( $course_id ),
                            'course_url'   => get_permalink( $course_id ),
                            'lessons'      => array(),
                        );
                    }

                    $lesson_data = array(
                        'id'        => $lesson_id,
                        'title'     => get_the_title( $lesson_id ),
                        'url'       => get_permalink( $lesson_id ),
                        'excerpt'   => get_the_excerpt( $lesson_id ),
                        'thumbnail' => get_the_post_thumbnail_url( $lesson_id, 'medium' ),
                    );

                    if ( $include_video ) {
                        $lesson_data['video'] = slv_get_lesson_video( $lesson_id );
                    }

                    $sample_lessons[ $course_id ]['lessons'][] = $lesson_data;
                }
            }
        }
        wp_reset_postdata();
    }

    uasort( $sample_lessons, function( $a, $b ) {
        return strcasecmp( $a['course_title'], $b['course_title'] );
    });

    return $sample_lessons;
}

/**
 * Check if a lesson is a sample lesson
 */
function slv_is_sample_lesson( $lesson_id ) {
    if ( function_exists( 'learndash_is_sample' ) ) {
        return learndash_is_sample( $lesson_id );
    }

    $lesson_settings = get_post_meta( $lesson_id, '_sfwd-lessons', true );
    if ( is_array( $lesson_settings ) && isset( $lesson_settings['sfwd-lessons_sample_lesson'] ) ) {
        return $lesson_settings['sfwd-lessons_sample_lesson'] === 'on';
    }

    $sample_meta = get_post_meta( $lesson_id, 'sample_lesson', true );
    if ( $sample_meta === 'on' || $sample_meta === '1' || $sample_meta === true ) {
        return true;
    }

    return false;
}

/**
 * Get the course ID for a lesson
 */
function slv_get_lesson_course_id( $lesson_id ) {
    if ( function_exists( 'learndash_get_course_id' ) ) {
        return learndash_get_course_id( $lesson_id );
    }

    $lesson_settings = get_post_meta( $lesson_id, '_sfwd-lessons', true );
    if ( is_array( $lesson_settings ) && isset( $lesson_settings['sfwd-lessons_course'] ) ) {
        return intval( $lesson_settings['sfwd-lessons_course'] );
    }

    $course_id = get_post_meta( $lesson_id, 'course_id', true );
    if ( $course_id ) {
        return intval( $course_id );
    }

    return false;
}

/**
 * Display sample lessons shortcode callback
 */
function slv_display_sample_lessons( $atts ) {
    if ( ! slv_check_learndash_active() ) {
        return '<p class="slv-error">' . esc_html__( 'LearnDash LMS is required to display sample lessons.', 'sample-lesson-viewer' ) . '</p>';
    }

    $atts = shortcode_atts( array(
        'columns'        => 2,
        'show_excerpt'   => 'no',
        'show_thumbnail' => 'yes',
        'show_video'     => 'yes',
        'show_course'    => 'yes',
        'course_id'      => '',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ), $atts, 'learndash_sample_lessons' );

    // Enqueue styles
    wp_enqueue_style( 'sample-lesson-viewer' );
    wp_enqueue_script( 'sample-lesson-viewer' );

    $columns = intval( $atts['columns'] );
    if ( $columns < 1 ) $columns = 1;
    if ( $columns > 4 ) $columns = 4;

    $include_video = ( $atts['show_video'] === 'yes' );
    $sample_lessons = slv_get_sample_lessons( $include_video );

    if ( ! empty( $atts['course_id'] ) ) {
        $course_ids = array_map( 'intval', explode( ',', $atts['course_id'] ) );
        $sample_lessons = array_intersect_key( $sample_lessons, array_flip( $course_ids ) );
    }

    if ( empty( $sample_lessons ) ) {
        return '<p class="slv-no-lessons">' . esc_html__( 'No sample lessons found.', 'sample-lesson-viewer' ) . '</p>';
    }

    // Detect RTL
    $is_rtl = is_rtl();
    $dir_attr = $is_rtl ? 'dir="rtl"' : '';

    ob_start();
    ?>
    <style>
    .slv-sample-lessons-wrapper {
        margin: 20px 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    /* Courses Grid - responsive grid for course sections */
    .slv-courses-grid {
        display: grid !important;
        gap: 24px !important;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)) !important;
    }
    @media (max-width: 768px) {
        .slv-courses-grid {
            grid-template-columns: 1fr !important;
        }
    }
    .slv-course-section {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .slv-course-title {
        font-size: 1.1em;
        font-weight: 600;
        margin: 0;
        padding: 16px;
        background: linear-gradient(135deg, #0073aa 0%, #005177 100%);
        color: #fff;
    }
    .slv-course-title a {
        color: #fff;
        text-decoration: none;
    }
    .slv-course-title a:hover {
        text-decoration: underline;
    }
    .slv-lessons-container {
        padding: 16px;
    }
    .slv-lessons-grid {
        display: flex !important;
        flex-direction: column !important;
        gap: 16px !important;
    }
    .slv-lesson-card {
        background: #f9f9f9;
        border: 1px solid #eee;
        border-radius: 8px;
        overflow: hidden;
        transition: box-shadow 0.3s ease;
    }
    .slv-lesson-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .slv-video-container {
        position: relative;
        width: 100%;
        background: #000;
    }
    .slv-video-wrapper {
        position: relative;
        padding-bottom: 56.25%;
        height: 0;
        overflow: hidden;
    }
    .slv-video-wrapper iframe,
    .slv-video-wrapper video {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: none;
    }
    /* Lazy load video thumbnail with play button */
    .slv-video-lazy {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
        background-size: cover;
        background-position: center;
        background-color: #000;
    }
    .slv-video-lazy img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .slv-play-btn {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 68px;
        height: 48px;
        background: rgba(255,0,0,0.9);
        border-radius: 14px;
        transition: background 0.2s ease, transform 0.2s ease;
    }
    .slv-video-lazy:hover .slv-play-btn {
        background: #f00;
        transform: translate(-50%, -50%) scale(1.1);
    }
    .slv-play-btn::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-40%, -50%);
        border-style: solid;
        border-width: 10px 0 10px 18px;
        border-color: transparent transparent transparent #fff;
    }
    .slv-lesson-thumbnail {
        overflow: hidden;
        background: #f5f5f5;
    }
    .slv-lesson-thumbnail img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        display: block;
    }
    .slv-lesson-content {
        padding: 12px;
    }
    .slv-lesson-title {
        font-size: 0.95em;
        font-weight: 600;
        margin: 0 0 10px 0;
        line-height: 1.4;
    }
    .slv-lesson-title a {
        color: #333;
        text-decoration: none;
    }
    .slv-lesson-title a:hover {
        color: #0073aa;
    }
    .slv-lesson-excerpt {
        font-size: 0.85em;
        color: #666;
        margin-bottom: 10px;
        line-height: 1.5;
    }
    .slv-lesson-link {
        display: inline-block;
        padding: 8px 16px;
        background: #0073aa;
        color: #fff !important;
        text-decoration: none !important;
        border-radius: 4px;
        font-size: 0.85em;
        font-weight: 500;
        transition: background 0.3s ease;
    }
    .slv-lesson-link:hover {
        background: #005177;
        color: #fff !important;
    }
    .slv-no-lessons, .slv-error {
        padding: 20px;
        background: #f7f7f7;
        border-left: 4px solid #0073aa;
        margin: 20px 0;
    }
    .slv-error {
        border-left-color: #dc3232;
    }
    /* RTL Support */
    .slv-rtl {
        direction: rtl;
        text-align: right;
    }
    .slv-rtl .slv-course-title {
        background: linear-gradient(135deg, #005177 0%, #0073aa 100%);
    }
    .slv-rtl .slv-no-lessons,
    .slv-rtl .slv-error {
        border-left: none;
        border-right: 4px solid #0073aa;
    }
    .slv-rtl .slv-error {
        border-right-color: #dc3232;
    }
    </style>

    <div class="slv-sample-lessons-wrapper <?php echo $is_rtl ? 'slv-rtl' : ''; ?>" <?php echo $dir_attr; ?>>
        <div class="slv-courses-grid">
            <?php foreach ( $sample_lessons as $course_id => $course_data ) : ?>
                <div class="slv-course-section">
                    <?php if ( $atts['show_course'] === 'yes' ) : ?>
                        <h2 class="slv-course-title">
                            <a href="<?php echo esc_url( $course_data['course_url'] ); ?>">
                                <?php echo esc_html( $course_data['course_title'] ); ?>
                            </a>
                        </h2>
                    <?php endif; ?>

                    <div class="slv-lessons-container">
                        <div class="slv-lessons-grid">
                            <?php foreach ( $course_data['lessons'] as $lesson ) : ?>
                                <div class="slv-lesson-card">
                                    <?php
                                    $has_video = $include_video && ! empty( $lesson['video'] ) && ! empty( $lesson['video']['embed'] );
                                    $is_youtube = $has_video && $lesson['video']['provider'] === 'youtube' && ! empty( $lesson['video']['thumbnail'] );
                                    ?>

                                    <?php if ( $has_video ) : ?>
                                        <div class="slv-video-container">
                                            <div class="slv-video-wrapper">
                                                <?php if ( $is_youtube ) : ?>
                                                    <!-- Lazy load YouTube: show thumbnail, load iframe on click -->
                                                    <div class="slv-video-lazy" onclick="slvLoadVideo(this)" data-embed="<?php echo esc_attr( $lesson['video']['embed'] ); ?>">
                                                        <img src="<?php echo esc_url( $lesson['video']['thumbnail'] ); ?>" alt="<?php echo esc_attr( $lesson['title'] ); ?>" loading="lazy">
                                                        <div class="slv-play-btn"></div>
                                                    </div>
                                                <?php else : ?>
                                                    <?php echo $lesson['video']['embed']; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php elseif ( $atts['show_thumbnail'] === 'yes' && ! empty( $lesson['thumbnail'] ) ) : ?>
                                        <div class="slv-lesson-thumbnail">
                                            <a href="<?php echo esc_url( $lesson['url'] ); ?>">
                                                <img src="<?php echo esc_url( $lesson['thumbnail'] ); ?>" alt="<?php echo esc_attr( $lesson['title'] ); ?>" loading="lazy">
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <div class="slv-lesson-content">
                                        <h3 class="slv-lesson-title">
                                            <a href="<?php echo esc_url( $lesson['url'] ); ?>">
                                                <?php echo esc_html( $lesson['title'] ); ?>
                                            </a>
                                        </h3>

                                        <?php if ( $atts['show_excerpt'] === 'yes' && ! empty( $lesson['excerpt'] ) ) : ?>
                                            <div class="slv-lesson-excerpt">
                                                <?php echo wp_kses_post( $lesson['excerpt'] ); ?>
                                            </div>
                                        <?php endif; ?>

                                        <a href="<?php echo esc_url( $lesson['url'] ); ?>" class="slv-lesson-link">
                                            <?php echo $is_rtl ? 'مشاهدة الدرس' : 'View Lesson'; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    function slvLoadVideo(el) {
        var embed = el.getAttribute('data-embed');
        // Replace data-src with src in the iframe
        embed = embed.replace('data-src=', 'src=');
        el.parentNode.innerHTML = embed;
    }
    </script>
    <?php

    return ob_get_clean();
}

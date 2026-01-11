# Sample Lesson Viewer for LearnDash

A WordPress plugin that displays all sample lessons from LearnDash LMS courses using a simple shortcode.

## Description

This plugin creates a `[learndash_sample_lessons]` shortcode that queries and displays all sample lessons from your LearnDash courses in one place. Sample lessons are grouped by course and displayed in a responsive grid layout.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- LearnDash LMS plugin (installed and activated)

## Installation

1. Upload the `sample-lesson-viewer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the `[learndash_sample_lessons]` shortcode on any page or post

## Usage

### Basic Usage

Simply add the shortcode to any page or post:

```
[learndash_sample_lessons]
```

### Shortcode Attributes

| Attribute | Default | Description |
|-----------|---------|-------------|
| `columns` | `3` | Number of columns in the grid (1-4) |
| `show_excerpt` | `yes` | Show lesson excerpt (`yes` or `no`) |
| `show_thumbnail` | `yes` | Show lesson featured image (`yes` or `no`) |
| `show_course` | `yes` | Group lessons by course with course title (`yes` or `no`) |
| `course_id` | `''` | Filter by specific course ID(s), comma-separated |
| `orderby` | `title` | Order lessons by field |
| `order` | `ASC` | Sort order (`ASC` or `DESC`) |

### Examples

**Display in 2 columns:**
```
[learndash_sample_lessons columns="2"]
```

**Hide excerpts and thumbnails:**
```
[learndash_sample_lessons show_excerpt="no" show_thumbnail="no"]
```

**Show lessons from specific courses:**
```
[learndash_sample_lessons course_id="123,456"]
```

**Display as a simple list (1 column, no images):**
```
[learndash_sample_lessons columns="1" show_thumbnail="no"]
```

**Don't group by course:**
```
[learndash_sample_lessons show_course="no"]
```

## Styling

The plugin includes responsive CSS styling. You can override styles in your theme's stylesheet using the following classes:

- `.slv-sample-lessons-wrapper` - Main container
- `.slv-course-section` - Course section wrapper
- `.slv-course-title` - Course title heading
- `.slv-lessons-grid` - Grid container for lessons
- `.slv-lesson-card` - Individual lesson card
- `.slv-lesson-thumbnail` - Thumbnail container
- `.slv-lesson-content` - Content area
- `.slv-lesson-title` - Lesson title
- `.slv-lesson-excerpt` - Lesson excerpt
- `.slv-lesson-link` - "View Lesson" button

## License

GPL-2.0+

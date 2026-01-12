# Sample Lesson Viewer for LearnDash

A WordPress plugin that displays all sample lessons from LearnDash LMS courses using a simple shortcode, with inline video playback support.

## Description

This plugin creates a `[learndash_sample_lessons]` shortcode that queries and displays all sample lessons from your LearnDash courses in one place. Sample lessons are grouped by course and displayed in a responsive grid layout with embedded video players.

## Features

- Display all sample lessons from all courses in one page
- **Inline video playback** - Videos play directly on the page without navigation
- **Multiple videos per row** on large screens
- Supports YouTube, Vimeo, Wistia, and self-hosted videos
- Responsive grid layout (1-4 columns)
- Group lessons by course
- Dark mode support
- Print-friendly styles

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
| `columns` | `2` | Number of columns in the grid (1-4) |
| `show_video` | `yes` | Show inline video player (`yes` or `no`) |
| `show_excerpt` | `yes` | Show lesson excerpt (`yes` or `no`) |
| `show_thumbnail` | `yes` | Show lesson featured image if no video (`yes` or `no`) |
| `show_course` | `yes` | Group lessons by course with course title (`yes` or `no`) |
| `course_id` | `''` | Filter by specific course ID(s), comma-separated |
| `orderby` | `title` | Order lessons by field |
| `order` | `ASC` | Sort order (`ASC` or `DESC`) |

### Examples

**Display with inline videos (default):**
```
[learndash_sample_lessons]
```

**Display 3 videos per row on large screens:**
```
[learndash_sample_lessons columns="3"]
```

**Display without videos (thumbnail only):**
```
[learndash_sample_lessons show_video="no"]
```

**Show lessons from specific courses:**
```
[learndash_sample_lessons course_id="123,456"]
```

**Single column layout with videos:**
```
[learndash_sample_lessons columns="1"]
```

**Don't group by course:**
```
[learndash_sample_lessons show_course="no"]
```

## Video Support

The plugin automatically detects and embeds videos from:

- **YouTube** - Standard and embed URLs
- **Vimeo** - Standard and player URLs
- **Wistia** - Media and embed URLs
- **Self-hosted** - MP4, WebM, OGG, MOV files

Videos are extracted from:
1. LearnDash video progression settings (`lesson_video_url`)
2. Lesson content (embedded iframes or direct URLs)

## Responsive Behavior

| Screen Size | Columns Displayed |
|-------------|-------------------|
| Mobile (< 768px) | 1 column |
| Tablet (768px - 1024px) | 2 columns max |
| Desktop (1025px - 1399px) | 2-3 columns |
| Large (1400px+) | Full column count (up to 4) |

## Styling

The plugin includes responsive CSS styling. You can override styles in your theme's stylesheet using the following classes:

### Main Container
- `.slv-sample-lessons-wrapper` - Main wrapper
- `.slv-with-video` - Added when videos are enabled

### Course & Grid
- `.slv-course-section` - Course section wrapper
- `.slv-course-title` - Course title heading
- `.slv-lessons-grid` - Grid container
- `.slv-columns-1` through `.slv-columns-4` - Column modifiers

### Lesson Cards
- `.slv-lesson-card` - Individual lesson card
- `.slv-has-video` - Card contains a video
- `.slv-video-container` - Video container
- `.slv-video-wrapper` - Video wrapper (16:9 ratio)
- `.slv-lesson-thumbnail` - Thumbnail container
- `.slv-lesson-content` - Content area
- `.slv-lesson-title` - Lesson title
- `.slv-lesson-excerpt` - Lesson excerpt
- `.slv-lesson-link` - "View Full Lesson" button

## Changelog

### 1.1.0
- Added inline video playback support
- Added support for YouTube, Vimeo, Wistia, and self-hosted videos
- Improved responsive grid for multiple videos per row
- Added `show_video` shortcode attribute
- Enhanced CSS with video-specific styles
- Added dark mode and print styles

### 1.0.0
- Initial release
- Basic sample lesson display with thumbnails
- Course grouping
- Responsive grid layout

## License

GPL-2.0+

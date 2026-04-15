<?php
/**
 * FramePress Builder — bare HTML shell.
 *
 * This is a full-viewport admin page that mounts the React builder application.
 * It intentionally bypasses WP admin chrome to give the builder maximum screen space.
 * wp_head() / wp_footer() are called to load enqueued assets (React bundle + wp.media).
 */

defined( 'ABSPATH' ) || exit;

// Suppress the normal WP admin page wrapper.
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e( 'FramePress Builder', 'framepress' ); ?> — <?php bloginfo( 'name' ); ?></title>
    <?php wp_enqueue_media(); wp_head(); ?>
    <style>
        /* Reset admin body styles so the builder fills the viewport cleanly */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            height: 100% !important;
            overflow: hidden !important;
            background: #f1f2f4 !important;
        }
        #wpadminbar,
        #adminmenumain,
        #wpfooter {
            display: none !important;
        }
        #wpcontent, #wpfooter {
            margin-left: 0 !important;
        }
        #framepress-builder-root {
            
            width: 100vw;
            height: 100vh;
            overflow: hidden;
        }
    </style>
</head>
<body class="framepress-builder-body">
    <div id="framepress-builder-root"></div>
    <?php wp_footer(); ?>
</body>
</html>
<?php
// Prevent WordPress from appending anything after this template.
exit;

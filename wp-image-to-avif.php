<?php

/**
 * Copyright (c) Chris Andersson
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://github.com/puredazzle/image-to-avif
 */

/**
 * Plugin Name: Image to AVIF
 * Description: Converts uploaded images to AVIF format for optimal performance.
 * Version: 0.4.0
 * Author: Chris Andersson
 * Author URI: https://github.com/puredazzle
 * Plugin URI: https://github.com/puredazzle/image-to-avif
 * GitHub Plugin URI: puredazzle/image-to-avif
 * Requires PHP: 8.2
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit();
}

final class ImageToAvif
{
    private const QUALITY = 85;
    private const MAX_ORIGINAL_SIZE = 10 * 1024 * 1024;
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const EXTENSION_PATTERN = '/\.(jpe?g|png|webp|gif)$/i';

    public function __construct()
    {
        add_filter('wp_generate_attachment_metadata', [$this, 'convert_on_upload'], 10, 2);
        add_filter('wp_get_attachment_image_src', [$this, 'filter_image_src'], 10, 4);
        add_filter('wp_calculate_image_srcset', [$this, 'filter_srcset'], 10, 5);
        add_filter('attachment_fields_to_edit', [$this, 'add_convert_button'], 10, 2);
        add_action('wp_ajax_image_to_avif_convert', [$this, 'ajax_convert']);
        add_action('admin_footer', [$this, 'admin_script']);
    }

    public function convert_on_upload(array $metadata, int $attachmentId): array
    {
        if (empty($metadata['file']) || empty($metadata['sizes'])) {
            return $metadata;
        }

        $mimeType = get_post_mime_type($attachmentId);

        if (! in_array($mimeType, self::ALLOWED_TYPES, true)) {
            return $metadata;
        }

        $uploadDir = wp_upload_dir();
        $baseDir = $uploadDir['basedir'] . '/' . dirname($metadata['file']);

        foreach ($metadata['sizes'] as $size => $data) {
            $sourcePath = $baseDir . '/' . $data['file'];

            if (! file_exists($sourcePath)) {
                continue;
            }

            $avifFilename = $this->to_avif_filename($data['file']);
            $avifPath = $baseDir . '/' . $avifFilename;

            if ($this->convert_to_avif($sourcePath, $avifPath)) {
                $metadata['sizes'][$size]['file'] = $avifFilename;
                $metadata['sizes'][$size]['mime-type'] = 'image/avif';
            }
        }

        $metadata = $this->convert_original_image($metadata, $attachmentId, $uploadDir);

        return $metadata;
    }

    private function convert_original_image(array $metadata, int $attachmentId, array $uploadDir): array
    {
        $originalPath = $uploadDir['basedir'] . '/' . $metadata['file'];

        if (! file_exists($originalPath) || filesize($originalPath) >= self::MAX_ORIGINAL_SIZE) {
            return $metadata;
        }

        $avifFilename = $this->to_avif_filename($metadata['file']);
        $avifPath = $uploadDir['basedir'] . '/' . $avifFilename;

        if ($this->convert_to_avif($originalPath, $avifPath)) {
            $metadata['file'] = $avifFilename;

            wp_update_post([
                'ID' => $attachmentId,
                'post_mime_type' => 'image/avif',
            ]);

            update_attached_file($attachmentId, $avifFilename);
        }

        return $metadata;
    }

    private function convert_to_avif(string $sourcePath, string $destinationPath): bool
    {
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            return $this->convert_with_imagick($sourcePath, $destinationPath);
        }

        return false;
    }

    private function convert_with_imagick(string $sourcePath, string $destinationPath): bool
    {
        $imagick = new Imagick($sourcePath);
        $imagick->setImageFormat('avif');
        $imagick->setImageCompressionQuality(self::QUALITY);
        $imagick->writeImage($destinationPath);
        $imagick->destroy();

        return file_exists($destinationPath);
    }

    private function to_avif_filename(string $filename): string
    {
        return preg_replace(self::EXTENSION_PATTERN, '.avif', $filename);
    }

    public function filter_image_src(
        array|false $image,
        ?int $attachmentId,
        string|int|array|null $size,
        ?bool $icon,
    ): array|false {
        if (! $image) {
            return $image;
        }

        $avifUrl = preg_replace(self::EXTENSION_PATTERN, '.avif', $image[0]);

        if ($this->avif_exists($avifUrl)) {
            $image[0] = $avifUrl;
        }

        return $image;
    }

    public function filter_srcset(
        array $sources,
        ?array $sizeArray,
        ?string $imageSrc,
        ?array $imageMeta,
        ?int $attachmentId,
    ): array {
        foreach ($sources as $width => $source) {
            $avifUrl = preg_replace(self::EXTENSION_PATTERN, '.avif', $source['url']);

            if ($this->avif_exists($avifUrl)) {
                $sources[$width]['url'] = $avifUrl;
            }
        }

        return $sources;
    }

    private function avif_exists(string $url): bool
    {
        $uploadDir = wp_upload_dir();
        $path = str_replace($uploadDir['baseurl'], $uploadDir['basedir'], $url);

        return file_exists($path);
    }

    public function add_convert_button(array $fields, WP_Post $post): array
    {
        $mimeType = $post->post_mime_type;

        if (! in_array($mimeType, self::ALLOWED_TYPES, true)) {
            return $fields;
        }

        $fields['image_to_avif_convert'] = [
            'label' => 'AVIF',
            'input' => 'html',
            'html' => sprintf(
                '<button type="button" class="button image-to-avif-convert" data-id="%d">Convert to AVIF</button>',
                $post->ID,
            ),
        ];

        return $fields;
    }

    public function ajax_convert(): void
    {
        check_ajax_referer('image_to_avif_nonce', 'nonce');

        if (! current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }

        $attachmentId = (int) ($_POST['attachment_id'] ?? 0);

        if (! $attachmentId) {
            wp_send_json_error('Invalid attachment ID');
        }

        $metadata = wp_get_attachment_metadata($attachmentId);

        if (! $metadata) {
            wp_send_json_error('Could not get attachment metadata');
        }

        $metadata = $this->convert_on_upload($metadata, $attachmentId);
        wp_update_attachment_metadata($attachmentId, $metadata);

        wp_send_json_success('Converted to AVIF');
    }

    public function admin_script(): void
    {
        $screen = get_current_screen();

        if (! $screen || ! in_array($screen->base, ['upload', 'post'], true)) {
            return;
        }

        wp_enqueue_script(
            'image-to-avif',
            plugin_dir_url(__FILE__) . 'image-to-avif.js',
            [],
            '1.0.0',
            true,
        );

        wp_localize_script('image-to-avif', 'imageToAvif', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('image_to_avif_nonce'),
        ]);
    }
}

new ImageToAvif();

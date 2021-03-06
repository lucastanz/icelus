<?php

namespace Beryllium\Icelus;

use Imanee\Imanee;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Service for Image Manipulation.
 */
class ImageService
{
    public $imanee;
    public $source_dir;
    public $output_dir;
    public $watermark_image;
    public $filesystem;
    public $prefix    = '/_thumbs';
    public $completed = array();

    /**
     * Constructor
     *
     * @param Imanee        $imanee             Performs the required image manipulations
     * @param string        $source_dir         Where to find the images
     * @param string        $output_dir         Where to save the images
     * @param string        $watermark_image    The watermark
     * @param Filesystem    $filesystem         Filesystem class for doing filesystem things
     */
    public function __construct(Imanee $imanee, $source_dir, $output_dir, $watermark_image = null, Filesystem $filesystem)
    {
        $this->imanee          = $imanee;
        $this->source_dir      = rtrim($source_dir, '/');
        $this->output_dir      = rtrim($output_dir, '/');
        $this->watermark_image = rtrim($watermark_image, '/');
        $this->filesystem      = $filesystem;
    }

    /**
     * Prepare the output directory.
     *
     * This makes sure we have somewhere to put the thumbnails once we've generated them.
     */
    protected function prepOutputDir()
    {
        if (!is_dir($this->output_dir . $this->prefix)) {
            $this->filesystem->mkdir($this->output_dir . $this->prefix);
        }
    }

    /**
     * Generate a thumbnail
     *
     * @param string    $image      Path to image file (relative to source_dir)
     * @param int       $width      Width, in pixels (default: 150)
     * @param int       $height     Height, in pixels (default: 150)
     * @param bool      $crop       When set to true, the thumbnail will be cropped
     *                              from the center to match the given size
     * @param bool      $watermark  When set to true, the thumbnail will be watermarked
     *
     * @return string               Location of the thumbnail, for use in <img> tags
     */
    public function thumbnail($image, $width = 150, $height = 150, $crop = false, $watermark = false, $compression = 80)
    {
        $path_parts = pathinfo($image);
        $img_name_prefix = $path_parts['filename'] . '_';

        $thumb_name = $img_name_prefix . vsprintf(
            '%s-%sx%s%s.%s',
            array(
                md5($image),
                $width,
                $height,
                ($crop ? '-cropped' : ''),
                'jpeg'
            )
        );

        if ( ! file_exists($this->output_dir . $this->prefix . '/' . $thumb_name )) {

            // no sense duplicating work - only process image if thumbnail doesn't already exist
            if (!isset($this->completed[$image][$width][$height][$crop]['filename'])) {
                $this->prepOutputDir();
                $this->imanee->load($this->source_dir . '/' . $image);

		        $this->imanee->getResource()->getResource()->setImageCompression(true);
		        $this->imanee->getResource()->getResource()->setImageCompression(\imagick::COMPRESSION_JPEG);
		        $this->imanee->getResource()->getResource()->setImageCompressionQuality($compression); 
                // rotates image if portrait
                if (($this->imanee->getResource()->getResource()->getImageOrientation() == \imagick::ORIENTATION_RIGHTTOP)) {
                    $this->imanee->rotate(90, '#000000');
                }

                // adds watermark
                if ($watermark && false === empty($this->watermark_image)) {
                    $this->imanee->watermark($this->source_dir . $this->watermark_image, Imanee::IM_POS_BOTTOM_RIGHT, 0);
                }

                $this->imanee->thumbnail($width, $height, $crop);

                // write the thumbnail to disk
                file_put_contents(
                    $this->output_dir . $this->prefix . '/' . $thumb_name,
                    $this->imanee->output()
                );
            }
        }

        $this->completed[$image][$width][$height][$crop]['filename'] = $thumb_name;

        return $this->prefix . '/' . $this->completed[$image][$width][$height][$crop]['filename'];
    }
}

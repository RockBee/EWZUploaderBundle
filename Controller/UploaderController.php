<?php

namespace EWZ\Bundle\UploaderBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * Uploader Controller.
 */
class UploaderController extends Controller {

    /**
     * Uploads a file.
     *
     * @param UploadedFile $file      Item uploaded via the HTTP POST method
     * @param string       $folder    The target folder
     * @param string       $maxSize   File max size
     * @param string|array $mimeTypes Mime types of the file
     *
     * @return Response A Response instance
     *
     * @Route("/file_upload", name="ewz_uploader_file_upload")
     * @Method("POST")
     */
    public function uploadAction() {
        $file = $this->get('request')->files->get('file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new Response(json_encode(array(
                                'event' => 'uploader:error',
                                'data' => array(
                                    'message' => $this->get("translator")->trans('uploader.error.missing.file'),
                                ),
                            )));
        }

        // validate file size and mimetype
        if (!$maxSize = $this->get('request')->request->get('maxSize')) {
            $maxSize = $this->container->getParameter('ewz_uploader.media.max_size');
        }
        if (!$mimeTypes = $this->get('request')->request->get('mimeTypes')) {
            $mimeTypes = $this->container->getParameter('ewz_uploader.media.mime_types');
        }
        $mimeTypes = is_array($mimeTypes) ? $mimeTypes : json_decode($mimeTypes, true);

        $fileConst = new \Symfony\Component\Validator\Constraints\File(array(
                    'maxSize' => $maxSize,
                    'mimeTypes' => $mimeTypes,
                ));

        $errors = $this->get('validator')->validateValue($file, $fileConst);
        if (count($errors) > 0) {
            return new Response(json_encode(array(
                                'event' => 'uploader:error',
                                'data' => array(
                                    'message' => $this->get("translator")->trans('uploader.error.invalid.file'),
                                ),
                            )));
        }
        
        // check if exists
        if (!is_file($file->__toString())) {
            return new Response(json_encode(array(
                'event' => 'uploader:error',
                'data' => array(
                    'message' => $this->get("translator")->trans('uploader.error.file.not.uploaded'),
                ),
            )));
        }

        // Image min width
        $imageSize = getimagesize($file);
        if (!$imageMinWidth = $this->get('request')->request->get('imageMinWidth')) {
            $imageMinWidth = $this->container->getParameter('ewz_uploader.media.image_min_width');
        }
        
        // Image min height
        if (!$imageMinHeight = $this->get('request')->request->get('imageMinHeight')) {
            $imageMinHeight = $this->container->getParameter('ewz_uploader.media.image_min_height');
        }
        
        // Image max width
        if (!$imageMaxWidth = $this->get('request')->request->get('imageMaxWidth')) {
            $imageMaxWidth = $this->container->getParameter('ewz_uploader.media.image_max_width');
        }
        
        // Image max height
        if (!$imageMaxHeight = $this->get('request')->request->get('imageMaxHeight')) {
            $imageMaxHeight = $this->container->getParameter('ewz_uploader.media.image_max_height');
        }
        
        // Image show width
        if (!$imageShowMaxWidth = $this->get('request')->request->get('imageShowMaxWidth')) {
            $imageShowMaxWidth = $this->container->getParameter('ewz_uploader.media.image_show_max_width');
        }
        
        // Image show height
        if (!$imageShowMinWidth = $this->get('request')->request->get('imageShowMinWidth')) {
            $imageShowMinWidth = $this->container->getParameter('ewz_uploader.media.image_show_min_width');
        }
        // Image resize to show
        if (!$imageResizeToShow = $this->get('request')->request->get('imageResizeToShow')) {
            $imageResizeToShow = $this->container->getParameter('ewz_uploader.media.image_resize_to_show');
        }
        $imageResizeToShow = filter_var($imageResizeToShow, FILTER_VALIDATE_BOOLEAN);
        
        // set drop directory
        if (!$folder = $this->get('request')->request->get('folder')) {
            $folder = $this->container->getParameter('ewz_uploader.media.folder');
        }
        $directory = sprintf('%s/%s', $this->container->getParameter('ewz_uploader.media.dir'), $folder);

        // Check if is necessaary generate a unique name
        if (!$generateUniqueName = $this->get('request')->request->get('generateUniqueName')) {
            $generateUniqueName = $this->container->getParameter('ewz_uploader.generate_unique_name');
        }
        $generateUniqueName = filter_var($generateUniqueName, FILTER_VALIDATE_BOOLEAN);

        // Check if will keep original name
        if (!$keepOriginalName = $this->get('request')->request->get('keepOriginalName')) {
            $keepOriginalName = $this->container->getParameter('ewz_uploader.keep_original_name');
        }
        $keepOriginalName = filter_var($keepOriginalName, FILTER_VALIDATE_BOOLEAN);

        // Get the default filename
        if (!$defaultFilename = $this->get('request')->request->get('defaultFilename')) {
            $defaultFilename = $this->container->getParameter('ewz_uploader.default_filename');
        }

        // Defines the filename
        if ($generateUniqueName) {
            $filename = sha1(uniqid(mt_rand(), true)) . '.' . $file->guessExtension();
        } elseif ($keepOriginalName) {
            $filename = $file->getClientOriginalName();
        } else {
            $filename = $defaultFilename . '.' . $file->guessExtension();
        }
        
        // Validate file min width and height
        if ((($imageMinHeight != "null") && $imageSize[0] < $imageMinWidth) || (($imageMinHeight != null) && $imageSize[1] < $imageMinHeight)) {
            return new Response(json_encode(array(
                'event' => 'uploader:error',
                'data' => array(
                    'message' =>  $this->get("translator")->trans('uploader.error.minsize', array("%imageMinWidth%" => $imageMinWidth, "%imageMinHeight%" => $imageMinHeight)),
                ),
            )));
        }
        
        // Validate file max width and height
        if ((($imageMaxWidth != "null") && $imageSize[0] > $imageMaxWidth) || (($imageMaxHeight != "null") && $imageSize[1] > $imageMaxHeight)) {
            return new Response(json_encode(array(
                'event' => 'uploader:error',
                'data' => array(
                    'message' =>  $this->get("translator")->trans('uploader.error.maxsize', array("%imageMaxWidth%" => $imageMaxWidth, "%imageMaxHeight%" => $imageMaxHeight)),
                ),
            )));
        }
        
        // Verifica se a imagem precisa ser redimensionada
        if ($imageResizeToShow) {
            $newWidth = $imageSize[0];
            $newHeight = $imageSize[1];
            
            // Verifica se a imagem é maior que o max show
            if (($imageShowMaxWidth != "null") && ($imageSize[0] > $imageShowMaxWidth)) {
                $newWidth = $imageShowMaxWidth;
                $proportion = $imageSize[0] / $imageShowMaxWidth;
                $newHeight = $imageSize[1] / $proportion;
            }
            
            // Verifica se a imagem é menor que o min show
            if (($imageShowMinWidth != "null") && ($imageSize[0] < $imageShowMinWidth)) {
                $newWidth = $imageShowMinWidth;
                $proportion = $imageSize[0] / $imageShowMinWidth;
                $newHeight = $imageSize[1] / $proportion;
            }
            
            $filepath = sprintf('%s/%s', $directory, $filename);

            // Redimensiona a imagem e salva.
            $imageResized = imagecreatetruecolor($newWidth, $newHeight);
            $imageTmp = $this->imageCreateFromAny($file);
            imagecopyresampled($imageResized, $imageTmp, 0, 0, 0, 0, $newWidth, $newHeight, $imageSize[0], $imageSize[1]);
            imagejpeg($imageResized, $filepath, 90);
            $imageSize = getimagesize($filepath);
            
        } else {
            // Apenas move a imagem.
            $file->move($directory, $filename);
        }
        
        return new Response(json_encode(array(
                'event' => 'uploader:success',
                'data' => array(
                    'filename' => $filename,
                    'imagesize' => $imageSize,
                ),
            )));
    }

    /**
     * Removes a file.
     *
     * @param string $filename The file name
     * @param string $folder   The target folder
     *
     * @return Response A Response instance
     *
     * @Route("/file_remove", name="ewz_uploader_file_remove")
     * @Method("POST")
     */
    public function removeAction() {
        $response = new Response(null, 200, array(
                    'Content-Type' => 'application/json',
                ));

        if (!$filename = $this->get('request')->request->get('filename')) {
            $response->setStatusCode(500);
            $response->setContent(json_encode(array(
                        'event' => 'uploader:error',
                        'data' => array(
                            'message' => $this->get("translator")->trans('uploader.error.invalid.file'),
                        ),
                    )));

            return $response;
        }

        if (!$folder = $this->get('request')->request->get('folder')) {
            $folder = $this->container->getParameter('ewz_uploader.media.folder');
        }
        $filepath = sprintf('%s/%s/%s', $this->container->getParameter('ewz_uploader.media.dir'), $folder, $filename);

        // check if exists
        if (!is_file($filepath)) {
            $response->setStatusCode(500);
            $response->setContent(json_encode(array(
                        'event' => 'uploader:error',
                        'data' => array(
                            'message' => $this->get("translator")->trans('uploader.error.file.not.uploaded'),
                        ),
                    )));

            return $response;
        }

        // remove file
        $filesystem = new Filesystem();
        $filesystem->remove($filepath);

        $response->setContent(json_encode(array(
                    'event' => 'uploader:fileremoved',
                    'data' => array(),
                )));

        return $response;
    }

    /**
     * Downloads a file.
     *
     * @param string $filename The file name
     * @param string $folder   The target folder
     *
     * @return Response A Response instance
     *
     * @Route("/file_download", name="ewz_uploader_file_download")
     * @Method("GET")
     *
     * @throws FileException         If the file invalid
     * @throws FileNotFoundException If the file does not exist
     */
    public function downloadAction() {
        if (!$filename = $this->get('request')->query->get('filename')) {
            throw new FileException('Invalid file.');
        }

        if (!$folder = $this->get('request')->query->get('folder')) {
            $folder = $this->container->getParameter('ewz_uploader.media.folder');
        }

        $filepath = sprintf('%s/%s/%s', $this->container->getParameter('ewz_uploader.media.dir'), $folder, $filename);

        // load file
        $file = new File($filepath);

        // read file
        $content = file_get_contents($filepath);

        return new Response($content, 200, array(
                    'Content-Type' => $file->getMimeType(),
                    'Content-Disposition' => sprintf('attachment;filename=%s', $file->getFilename()),
                ));
    }

    /**
     * Crops a file.
     *
     * @param string $filename The file name
     * @param string $folder   The target folder
     * @param string $uploadProportion The proportion used in preview image
     * @param string $x X coordinate 
     * @param string $y Y coordinate 
     * @param string $w Width of cropped are
     * @param string $h Height of cropped are
     *
     * @return Response A Response instance
     *
     * @Route("/file_crop", name="ewz_uploader_file_crop")
     * @Method("POST")
     */
    public function cropAction() {
        $response = new Response(null, 200, array(
                    'Content-Type' => 'application/json',
                ));


        if (!$filename = $this->get('request')->request->get('filename')) {
            $response->setStatusCode(500);
            $response->setContent(json_encode(array(
                        'event' => 'uploader:error',
                        'data' => array(
                            'message' => $this->get("translator")->trans('uploader.error.invalid.file'),
                        ),
                    )));

            return $response;
        }

        $uploadProportion = $this->get('request')->request->get('uploadProportion');
        $x = $this->get('request')->request->get('x') / $uploadProportion;
        $y = $this->get('request')->request->get('y') / $uploadProportion;
        $w = $this->get('request')->request->get('w') / $uploadProportion;
        $h = $this->get('request')->request->get('h') / $uploadProportion;

        if (!$folder = $this->get('request')->request->get('folder')) {
            $folder = $this->container->getParameter('ewz_uploader.media.folder');
        }
        
        $filepath = sprintf('%s/%s/%s', $this->container->getParameter('ewz_uploader.media.dir'), $folder, $filename);

        // check if exists
        if (!is_file($filepath)) {
            $response->setStatusCode(500);
            $response->setContent(json_encode(array(
                        'event' => 'uploader:error',
                        'data' => array(
                            'message' => $this->get("translator")->trans('uploader.error.invalid.file'),
                        ),
                    )));

            return $response;
        }

        // crops file
        $jpeg_quality = 90;

        // creates the new image
        $img_r = $this->imageCreateFromAny($filepath);
        $dst_r = ImageCreateTrueColor($w, $h);

        // deletes the old image
        $filesystem = new Filesystem();
        $filesystem->remove($filepath);

        // saves the new one
        imagecopyresampled($dst_r, $img_r, 0, 0, $x, $y, $w, $h, $w, $h);

        $success = imagejpeg($dst_r, $filepath, $jpeg_quality);
        $imageSize = getimagesize($filepath);
        
        if (!$imageShowMinWidth = $this->get('request')->request->get('imageShowMinWidth')) {
            $imageShowMinWidth = $this->container->getParameter('ewz_uploader.media.image_show_min_width');
        }
        
        // Verifica se a imagem precisa ser redimensionada para o tamanho mínimo
        $cropResizeToShow = filter_var($this->get('request')->request->get('cropResizeToShow'), FILTER_VALIDATE_BOOLEAN);
        if ($cropResizeToShow) {
            // Verifica se a imagem será redimensionada para a largura mínima
            if ($imageSize[0] > $imageShowMinWidth) {
                $proportion = $imageSize[0] / $imageShowMinWidth;
                $newHeight = $imageSize[1] / $proportion;

                // Redimensiona a imagem e salva.
                $imageResized = imagecreatetruecolor($imageShowMinWidth, $newHeight);
                $imageTmp = $this->imageCreateFromAny($filepath);
                imagecopyresampled($imageResized, $imageTmp, 0, 0, 0, 0, $imageShowMinWidth, $newHeight, $imageSize[0], $imageSize[1]);
                imagejpeg($imageResized, $filepath, 90);
                $imageSize = getimagesize($filepath);
            }
        }
        

        if ($success) {
            $response->setContent(json_encode(array(
                        'event' => 'uploader:filecropped',
                        'data' => array(
                            'filename' => $filename,
                            'imagesize' => $imageSize
                        ),
                    )));
        } else {
            $response->setStatusCode(500);
            $response->setContent(json_encode(array(
                        'event' => 'uploader:error',
                        'data' => array(
                            'message' => $this->get("translator")->trans('uploader.error.crop'),
                        ),
                    )));
        }
        return $response;
    }

    /**
     * 
     * @param type $originalImage
     * @param type $toWidth
     * @param type $toHeight
     * @return type
     */
    private function resizeImage($originalImage, $toWidth, $toHeight) {

        list($width, $height) = getimagesize($originalImage);
        $xscale = $width / $toWidth;
        $yscale = $height / $toHeight;

        if ($yscale > $xscale) {
            $new_width = round($width * (1 / $yscale));
            $new_height = round($height * (1 / $yscale));
        } else {
            $new_width = round($width * (1 / $xscale));
            $new_height = round($height * (1 / $xscale));
        }


        $imageResized = imagecreatetruecolor($new_width, $new_height);
        $imageTmp = $this->imageCreateFromAny($originalImage);
        imagecopyresampled($imageResized, $imageTmp, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        return $imageResized;
    }

    /**
     * 
     * @param type $filepath
     * @return boolean
     */
    private function imageCreateFromAny($filepath) {
        $imagesize = getimagesize($filepath); 
        $type = $imagesize[2]; 
        $allowedTypes = array(
            1, // [] gif
            2, // [] jpg
            3, // [] png
            6   // [] bmp
        );
        if (!in_array($type, $allowedTypes)) {
            return false;
        }
        switch ($type) {
            case 1 :
                $im = imageCreateFromGif($filepath);
                break;
            case 2 :
                $im = imageCreateFromJpeg($filepath);
                break;
            case 3 :
                $im = imageCreateFromPng($filepath);
                break;
            case 6 :
                $im = imageCreateFromBmp($filepath);
                break;
        }
        return $im;
    }

}

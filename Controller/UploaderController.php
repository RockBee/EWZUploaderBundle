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
class UploaderController extends Controller
{
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
    public function uploadAction()
    {
        $file = $this->get('request')->files->get('file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new Response(json_encode(array(
                'event' => 'uploader:error',
                'data'  => array(
                    'message' => 'Missing file.',
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
            'maxSize'   => $maxSize,
            'mimeTypes' => $mimeTypes,
        ));
        
        $errors = $this->get('validator')->validateValue($file, $fileConst);
        if (count($errors) > 0) {
            return new Response(json_encode(array(
                'event' => 'uploader:error',
                'data'  => array(
                    'message' => 'Invalid file.',
                ),
            )));
        }
        
        // Validate file min width and height
        $imageSize = getimagesize($file);
        if (!$imageMinWidth = $this->get('request')->request->get('imageMinWidth')) {
            $imageMinWidth = $this->container->getParameter('ewz_uploader.media.image_min_width');
        }
        
        if (!$imageMinHeight = $this->get('request')->request->get('imageMinHeight')) {
            $imageMinHeight = $this->container->getParameter('ewz_uploader.media.image_min_height');
        }
        
        if ($imageSize[0] < $imageMinWidth || $imageSize[1] < $imageMinHeight) {
            return new Response(json_encode(array(
                'event' => 'uploader:error',
                'data'  => array(
                    'message' => 'The image must be at least '. $imageMinWidth .' pixels wide and ' . $imageMinHeight . ' pixels tall.',
                ),
            )));
        }

        // check if exists
        if (!is_file($file->__toString())) {
            return new Response(json_encode(array(
                'event' => 'uploader:error',
                'data'  => array(
                    'message' => 'File was not uploaded.',
                ),
            )));
        }

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
        
        if ($generateUniqueName) {
            $filename = sha1(uniqid(mt_rand(), true)) . '.' . $file->guessExtension();
        } elseif ($keepOriginalName) {
            $filename = $file->getClientOriginalName();
        } else {
            $filename = $defaultFilename . '.' . $file->guessExtension();
        }
        
        $file->move($directory, $filename);

        return new Response(json_encode(array(
            'event' => 'uploader:success',
            'data'  => array(
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
    public function removeAction()
    {
        $response = new Response(null, 200, array(
            'Content-Type' => 'application/json',
        ));

        if (!$filename = $this->get('request')->request->get('filename')) {
            $response->setStatusCode(500);
            $response->setContent(json_encode(array(
                'event' => 'uploader:error',
                'data'  => array(
                    'message' => 'Invalid file.',
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
                'data'  => array(
                    'message' => 'File was not uploaded.',
                ),
            )));

            return $response;
        }

        // remove file
        $filesystem = new Filesystem();
        $filesystem->remove($filepath);

        $response->setContent(json_encode(array(
            'event' => 'uploader:fileremoved',
            'data'  => array(),
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
    public function downloadAction()
    {
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
            'Content-Type'        => $file->getMimeType(),
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
                'data'  => array(
                    'message' => 'Invalid file.',
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
                'data'  => array(
                    'message' => 'File does not exists.',
                ),
            )));

            return $response;
        }

        // crops file
        $targ_w = $targ_h = 180;
	$jpeg_quality = 90;
        
        // creates the new image
	$img_r = imagecreatefromjpeg($filepath);
	$dst_r = ImageCreateTrueColor($targ_w, $targ_h);
        
        // deletes the old image
        $filesystem = new Filesystem();
        $filesystem->remove($filepath);
        
        // saves the new one
	imagecopyresampled($dst_r, $img_r, 0, 0, $x, $y,
	$targ_w, $targ_h, $w, $h);

        $success = imagejpeg($dst_r, $filepath, $jpeg_quality);
        
        if ($success) {
            $response->setContent(json_encode(array(
                'event' => 'uploader:filecropped',
                'data'  => array(
                    'filename' => $filename,
               ),
            )));

        } else {
            $response->setStatusCode(500);
            $response->setContent(json_encode(array(
                'event' => 'uploader:error',
                'data'  => array(
                    'message' => 'Error on file crop.',
                ),
            )));

        }
        return $response;
        
    }
}

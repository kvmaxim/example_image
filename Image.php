<?php

namespace app\models\common;

use app\models\common\DataBase;
use app\models\common\Trace;
use const app\models\common\ARRAY_HEIGHTS_IMAGE;
use const app\models\common\PATH_TMP_FOLDER;

class Image {

//    private $tmp_file = '/var/www/svet_v_okne/svetvokne/web/files/tmp_file';
    private $tmp_file = '/tmp/tmp_file';

    public function getCountImagesForCurrentBuildingAndAnchor($id_building, $anchor)
    {
        return DataBase::getInstance()->tbImagesGetCountImagesForCurrentBuildingAndAnchor($id_building, $anchor);
    }

    public function getHashImage($buffer){
        return md5($buffer);
    }

    public function deleteImageFromBase($label, $url,  $hash, $id_building, $anchor) {
        return DataBase::getInstance()->tbImagesDeleteInfo($label, $url,  $hash, $id_building, $anchor);
    }

    public function addInfoImageInBase($id_building, $label, $ext, $url, $hash, $status, $anchor) {
        return DataBase::getInstance()->tbImagesInfoAddImage($id_building, $label, $ext, $url, $hash, $status, $anchor);
    }

    public function compareFullDataImageAndGetStatus($url_image) {

        try{
            $buffer = file_get_contents($url_image);
        } catch (\Throwable $e) {
            $r=0;
            $r++;
        }

        $hash = $this->getHashImage($buffer);

        $info = DataBase::getInstance()->tbImagesInfoGetStatus(null, $url_image, null);

        if ($info === null || !isset($info['url'])) return null;
        if ($info['url'] !== $url_image || $info['hash'] !== $hash) return null; else return intval($info['status']);
    }

    public function compareFullDataImageUseUrlAndGetInfo($url_image) {
        $buffer = file_get_contents($url_image);
        $hash = $this->getHashImage($buffer);

        $info = DataBase::getInstance()->tbImagesInfoGetStatus(null, $url_image, null);

        if ($info === null || !isset($info['url'])) return null;
        if ($info['url'] !== $url_image || $info['hash'] !== $hash) return null; else return $info;
    }

    public function compareFullDataImageUseBufferAndGetInfo($buffer) {
        $hash = $this->getHashImage($buffer);

        $info = DataBase::getInstance()->tbImagesInfoGetStatus(null, null, $hash);

        if ($info === null || count($info) === 0) return null;
        return $info;
    }

    public function checkExistImageInBaseAndGetStatus($label, $url, $hash) {
        return DataBase::getInstance()->tbImagesInfoGetStatus($label, $url, $hash);
    }

    public function saveImageInFolder($url_image, $path_folder, $label_image, $array_heights, $buffer = null)
    {

        $this->createDirIfNotExist($path_folder);

        if($url_image !== null) file_put_contents($this->tmp_file, file_get_contents($url_image));
        if ($url_image === null && $buffer !== null) file_put_contents($this->tmp_file, $buffer);

        $ext = mime_content_type($this->tmp_file);

        if ($ext === "image/png") $ext = "png";
        if ($ext === "image/jpeg") $ext = "jpg";
        if ($ext === "image/svg+xml") $ext = "svg";
//        if (strpos($buffer, '<svg viewBox') !== false) $ext= "svg";
//        if (strpos($buffer, '<svg version="1.1" xmlns') !== false && strpos($buffer, 'viewBox') !== false) $ext= "svg";
        if (substr($buffer, 0 ,4) === '<svg' && strpos($buffer, 'viewBox') !== false) $ext ='svg';
        if (substr($buffer, 0 ,8) === '<ns0:svg' && strpos($buffer, 'viewBox') !== false) $ext ='svg';

        if ($ext !== "jpg" && $ext !== "png" && $ext !== 'svg') {
            Trace::write("Error!!! Check File. path_folder === ".$path_folder.' url_image === '.$url_image);
            return [
                'result' => STATUS_ERROR,
                ];
        }

        if ($ext === 'jpg' || $ext === 'png') {

            foreach ($array_heights as $height) {
                if ($height !== 'main') {
                    $out_file = $path_folder . $label_image . '_' . $height . '.'.$ext;
                    $this->resizeImage($this->tmp_file, $out_file, $height, $ext);

                    $tmp_result['array_width'][] = $height;
                }
            }

        }

        $out_file_main = $path_folder.$label_image.'_main.'.$ext;
        $buffer = file_get_contents($this->tmp_file);
        $result_save = file_put_contents($out_file_main, $buffer);
        $tmp_result['array_width'][] = 'main';

        return [
            'result' => $result_save,
            'ext' => $ext,
            'array_width' => $tmp_result['array_width'],
            'hash' => $this->getHashImage($buffer),
        ];
    }

    public function deleteAllFilesFromFolder($path_folder)
    {
        $files = scandir($path_folder);
        foreach($files as $file) {
            if (is_file($path_folder.$file)) {
                $result = unlink($path_folder.$file);
            }
        }
    }

    public function saveImageAndUploadOnCloud($path_file, $path_folder_on_cloud, $label_image,  $array_heights = null)
    {
        if ($array_heights === null) $array_heights = ARRAY_HEIGHTS_IMAGE;

        $buffer = file_get_contents($path_file);

        $ext = mime_content_type($path_file);

        if ($ext === "image/png") $ext = "png";
        if ($ext === "image/jpeg") $ext = "jpg";
        if ($ext === "image/svg+xml") $ext = "svg";

        if ($ext !== "jpg" && $ext !== "png" && $ext !== 'svg') {
            Trace::write("Error!!! Check File. path_folder_on_cloud === ".$path_folder_on_cloud.' path_file === '.$path_file);
            return [
                'result' => \app\models\STATUS_ERROR,
            ];
        }

        $array_files_for_upload_on_cloud = [];

        if ($ext === 'jpg' || $ext === 'png') {

            foreach ($array_heights as $height) {
                if ($height !== 'main') {
                    $out_file = PATH_TMP_FOLDER . $label_image . '_' . $height . '.'.$ext;
                    $out_file_on_cloud = $path_folder_on_cloud.'/'.$label_image.'_'.$height.'.'.$ext;
                    $this->resizeImage($path_file, $out_file, $height, $ext);
                    $tmp_result['array_width'][] = $height;

                    $array_files_for_upload_on_cloud[] = [
                        'path_file_on_cloud' => $out_file_on_cloud,
                        'full_path_file_local' => $out_file,
                    ];
                }
            }
        }

        $out_file_main = PATH_TMP_FOLDER.$label_image.'_main.'.$ext;
        $out_file_on_cloud = $path_folder_on_cloud.'/'.$label_image.'_main.'.$ext;
        $result_save = file_put_contents($out_file_main, $buffer);
        $tmp_result['array_width'][] = 'main';

        $array_files_for_upload_on_cloud[] = [
            'path_file_on_cloud' => $out_file_on_cloud,
            'full_path_file_local' => $out_file_main,
        ];

        foreach($array_files_for_upload_on_cloud as $file) {
            CloudStorage::getInstance()->uploadFile($file['full_path_file_local'], $file['path_file_on_cloud']);
        }

        $this->deleteAllFilesFromFolder(PATH_TMP_FOLDER);

        return [
            'result' => $result_save,
            'ext' => $ext,
            'array_width' => $tmp_result['array_width'],
            'hash' => $this->getHashImage($buffer),
        ];
    }


    private function createDirIfNotExist($path_dir)
    {
        if (!file_exists($path_dir)) {
            return mkdir($path_dir, 0777, true);
        };
    }

    private function deleteDir($path_dir)
    {
        if (file_exists($path_dir)) {
            return rmdir($path_dir);
        };
    }

    private function resizeImage($file, $out_file, $new_width, $ext) {
        list($width, $height) = getimagesize($file);

        $new_height = (int)($height/($width / $new_width));

        switch($ext){
            case "png":
                // Для Png добавляем задний фон в случае если он прозрачный.
                $backgroundImg = @imagecreatetruecolor($width, $height);
                $colorRgb = array('red' => 255, 'green' => 255, 'blue' => 255);
                $color = imagecolorallocate($backgroundImg, $colorRgb['red'], $colorRgb['green'], $colorRgb['blue']);
                imagefill($backgroundImg, 0, 0, $color);

                $src = imagecreatefrompng($file);
                imagecopy($backgroundImg, $src, 0, 0, 0, 0, $width, $height);

                $dst = imagecreatetruecolor($new_width, $new_height);
                imagecopyresampled($dst, $backgroundImg, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagepng($dst, $out_file);

                break;
            case "jpeg":
            case "jpg":
                $src = imagecreatefromjpeg($file);

                $dst = imagecreatetruecolor($new_width, $new_height);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagejpeg($dst, $out_file);

                break;
            default:
                break;
        }

        return true;
    }
}

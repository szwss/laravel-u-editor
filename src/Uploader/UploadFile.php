<?php namespace Szwss\UEditor\Uploader;

use Szwss\UEditor\Uploader\Upload;
use App\Repositories\PublicRepository;//20170221引入PublicRepository，controller有new这个类，固此不能用__construct，单独new这个包来调用

use Intervention\Image\ImageManager;//图片管理类，属于Image

/**
 *
 *
 * Class UploadFile
 *
 * 文件/图像普通上传
 *
 * @package Stevenyangecho\UEditor\Uploader
 */
class UploadFile  extends Upload{
    use UploadQiniu;

    public function doUpload()
    {


        $file = $this->request->file($this->fileField);
        if (empty($file)) {
            $this->stateInfo = $this->getStateInfo("ERROR_FILE_NOT_FOUND");
            return false;
        }
        if (!$file->isValid()) {
            $this->stateInfo = $this->getStateInfo($file->getError());
            return false;

        }

        $this->file = $file;

        $this->oriName = $this->file->getClientOriginalName();

        $this->fileSize = $this->file->getSize();
        $this->fileType = $this->getFileExt();

        $this->fullName = $this->getFullName();


        $this->filePath = $this->getFilePath();

        $this->fileName = basename($this->filePath);


        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return false;
        }
        //检查是否不允许的文件格式
        if (!$this->checkType()) {
            $this->stateInfo = $this->getStateInfo("ERROR_TYPE_NOT_ALLOWED");
            return false;
        }

        if(config('UEditorUpload.core.mode')=='local'){
            try {
                $this->file->move(dirname($this->filePath), $this->fileName);

                $this->stateInfo = $this->stateMap[0];

            } catch (FileException $exception) {
                $this->stateInfo = $this->getStateInfo("ERROR_WRITE_CONTENT");
                return false;
            }

        }else if(config('UEditorUpload.core.mode')=='qiniu'){

            /*
             * 20170221添加内容
             * */
            $_path = public_path().'/temp/uploads/ueditor/image';
//            new PublicRepository(createDirectory($_path));//判断并创建对应目录//20170217支持递归创建
            (new PublicRepository)->createDirectory($_path);//判断并创建对应目录//20170217支持递归创建

            $file_name = date('YmdHis').'-'.mt_rand(10000,99999).'.jpg'; //重命名文件

            $save_path = $_path.'/'.$file_name;

            //开始创建图像
            $manager = new ImageManager();
            $image = $manager->make( $this->file );

            //20170222处理图片过大或过小的的问题，如果宽度或高度超过或小于500，改为500，并约束纵横比
            if($image->width() > 500 || $image->width() < 500){
                $image->resize(500,null, function ($constraint) {
                    $constraint->aspectRatio();
                });
            }

            //统一编码为jpg格式
            $image->encode('jpg')
                ->save($save_path);

            /*
             * end
             * */
            $content=file_get_contents($save_path);//file_get_contents($this->file->getPathname())
            $return = $this->uploadQiniu($this->filePath,$content);
//            new PublicRepository(deleteFile($save_path));//删除图片
            (new PublicRepository)->deleteFile($save_path);//删除图片
            return $return;

        }else{
            $this->stateInfo = $this->getStateInfo("ERROR_UNKNOWN_MODE");
            return false;
        }


        return true;

    }

}

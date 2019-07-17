<?php namespace Szwss\UEditor\Uploader;

use Szwss\UEditor\Uploader\Upload;
use App\Repositories\PublicRepository;//20170221引入PublicRepository，controller有new这个类，固此不能用__construct，单独new这个包来调用

use Intervention\Image\ImageManager;//图片管理类，属于Image
/**
 * Class UploadCatch
 * 图片远程抓取
 *
 * @package Stevenyangecho\UEditor\Uploader
 */
class UploadCatch  extends Upload{
    use UploadQiniu;

    public function doUpload()
    {

//        $imgUrl = strtolower(str_replace("&amp;", "&", $this->config['imgUrl']));//20170222去掉转换为小写函数，百度搜索里的图片，url转成小写，将访问空白
        $imgUrl = str_replace("&amp;", "&", $this->config['imgUrl']);

        //http开头验证
        if (strpos($imgUrl, "http") !== 0) {
            $this->stateInfo = $this->getStateInfo("ERROR_HTTP_LINK");
            return false;
        }
        //获取请求头并检测死链
        $heads = get_headers($imgUrl);

        if (!(stristr($heads[0], "200") && stristr($heads[0], "OK"))) {
            $this->stateInfo = $this->getStateInfo("ERROR_DEAD_LINK");
            return false;
        }

        //格式验证(扩展名验证和Content-Type验证)
        $fileType = strtolower(strrchr($imgUrl, '.'));
        if (!in_array($fileType, $this->config['allowFiles']) ) {
            $this->stateInfo = $this->getStateInfo("ERROR_HTTP_CONTENTTYPE");
            return false;
        }

        //打开输出缓冲区并获取远程图片
        ob_start();
        $context = stream_context_create(
            array('http' => array(
                'follow_location' => false // don't follow redirects
            ))
        );
        readfile($imgUrl, false, $context);
        $img = ob_get_contents();

        ob_end_clean();

        preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/", $imgUrl, $m);


        $this->oriName = $m ? $m[1]:"";
        $this->fileSize = strlen($img);
        $this->fileType = $this->getFileExt();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->fileName =  basename($this->filePath);
        $dirname = dirname($this->filePath);





        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return false;
        }


        if(config('UEditorUpload.core.mode')=='local'){
            //创建目录失败
            if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
                $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
                return false;
            } else if (!is_writeable($dirname)) {
                $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
                return false;
            }

            //移动文件
            if (!(file_put_contents($this->filePath, $img) && file_exists($this->filePath))) { //移动失败
                $this->stateInfo = $this->getStateInfo("ERROR_WRITE_CONTENT");
                return false;
            } else { //移动成功
                $this->stateInfo = $this->stateMap[0];
                return true;
            }
        }else if(config('UEditorUpload.core.mode')=='qiniu'){
            /*
            * 20170221添加内容
            * */
            $_path = public_path().'/temp/uploads/ueditor/distance/image/';
//            new PublicRepository(createDirectory($_path));//判断并创建对应目录//20170217支持递归创建
            (new PublicRepository)->createDirectory($_path);//判断并创建对应目录//20170217支持递归创建
            $file_name = date('YmdHis').'-'.mt_rand(10000,99999).'.jpg'; //重命名文件

            $save_path = $_path.'/'.$file_name;

            //开始创建图像
            $manager = new ImageManager();
            $image = $manager->make( $imgUrl );

            //20170222处理图片过大或过小的的问题，如果宽度或高度超过或小于500，改为500，并约束纵横比
            if($image->width() > 500 || $image->width() < 500){
                $image->resize(500,null, function ($constraint) {
                    $constraint->aspectRatio();
                });
            }

            //统一编码为jpg格式
            $image->encode('jpg')
                ->save($save_path);

            $content=file_get_contents($save_path);
            /*
             * end
             * */

            $return = $this->uploadQiniu($this->filePath,$content);//20170222缓冲区的$img换成调整过的$content
//            new PublicRepository(deleteFile($save_path));//删除图片
            (new PublicRepository)->deleteFile($save_path);//删除图片

            return $return;
        }else{
            $this->stateInfo = $this->getStateInfo("ERROR_UNKNOWN_MODE");
            return false;
        }

    }

    /**
     * 获取文件扩展名
     * @return string
     */
    protected function getFileExt()
    {
//        return strtolower(strrchr($this->oriName, '.'));//20170222去掉转换为小写函数，百度搜索里的图片，url转成小写，将访问空白
        return 'jpg';
//        return strrchr($this->oriName, '.');
    }
}
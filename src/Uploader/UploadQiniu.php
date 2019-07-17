<?php namespace Szwss\UEditor\Uploader;

use \Qiniu\Storage\UploadManager;
use \Qiniu\Auth;

/**
 *
 *
 * trait UploadQiniu
 *
 * 七牛 上传 类
 *
 * @package Stevenyangecho\UEditor\Uploader
 */
trait UploadQiniu
{
    /**
     * 获取文件路径
     * @return string
     */
    protected function getFilePath()
    {
        $fullName = $this->fullName;


        $fullName = ltrim($fullName, '/');


        return $fullName;
    }

    public function uploadQiniu($key, $content)
    {
        $upManager = new UploadManager();
        $auth = new Auth(config('UEditorUpload.core.qiniu.accessKey'), config('UEditorUpload.core.qiniu.secretKey'));
        $token = $auth->uploadToken(config('UEditorUpload.core.qiniu.bucket'));

        list($ret, $error) = $upManager->put($token, $key, $content);
        if ($error) {
            $this->stateInfo= $error->message();
        } else {
            //change $this->fullName ,return the url
            $url=rtrim(strtolower(config('UEditorUpload.core.qiniu.url')),'/');
            $fullName = ltrim($this->fullName, '/');
            $this->fullName=$url.'/'.$fullName.config('UEditorUpload.core.qiniu.image_watermark');//20170205本插件针对七牛云储存返回水印图片修改
            $this->stateInfo = $this->stateMap[0];
        }
        return true;
    }
}